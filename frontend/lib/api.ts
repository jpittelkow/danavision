import axios, { type AxiosError } from "axios";
import { setCorrelationId } from "@/lib/error-logger";
import {
  addToRequestQueue,
  registerBackgroundSync,
  setupOfflineRetry,
} from "@/lib/request-queue";

export const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL
    ? `${process.env.NEXT_PUBLIC_API_URL}/api`
    : "/api",
  withCredentials: true,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// Set up online listener to retry queued requests (fallback when Background Sync not supported)
if (typeof window !== "undefined") {
  const cleanupOfflineRetry = setupOfflineRetry(api);
  window.addEventListener("beforeunload", cleanupOfflineRetry);
}

// Flag to prevent multiple redirects
let isRedirecting = false;

// Response interceptor: capture correlation ID and handle errors
api.interceptors.response.use(
  (response) => {
    const correlationId = response.headers["x-correlation-id"];
    if (correlationId) {
      setCorrelationId(correlationId);
    }
    return response;
  },
  (error) => {
    if (error.response?.headers?.["x-correlation-id"]) {
      setCorrelationId(error.response.headers["x-correlation-id"]);
    }
    if (error.response) {
      // Server responded with error
      const message =
        error.response.data?.message ||
        error.response.data?.error ||
        "An error occurred";

      // Handle 401 (Unauthorized) - redirect to login
      // Skip redirect for auth-check endpoint (/auth/user) — a 401 there just
      // means "not logged in" and is expected on public pages like the homepage.
      const requestUrl = error.config?.url || "";
      const isAuthCheck = requestUrl === "/auth/user" || requestUrl.endsWith("/auth/user");
      if (error.response.status === 401 && !isAuthCheck) {
        if (
          typeof window !== "undefined" &&
          !isRedirecting &&
          !window.location.pathname.includes("/login") &&
          !window.location.pathname.includes("/register") &&
          !window.location.pathname.includes("/forgot-password") &&
          !window.location.pathname.includes("/auth/callback")
        ) {
          isRedirecting = true;
          window.location.replace("/login");
          // Reset flag after a delay in case navigation is blocked
          setTimeout(() => { isRedirecting = false; }, 3000);
        }
      }

      // Handle 403 with requires_2fa_setup - redirect to user security page to set up 2FA
      if (
        error.response.status === 403 &&
        error.response.data?.requires_2fa_setup === true
      ) {
        if (
          typeof window !== "undefined" &&
          !isRedirecting &&
          !window.location.pathname.includes("/user/security")
        ) {
          isRedirecting = true;
          window.location.replace("/user/security");
          setTimeout(() => { isRedirecting = false; }, 3000);
        }
      }

      const err = new Error(message) as Error & { response?: typeof error.response };
      err.response = error.response;
      throw err;
    } else if (error.request) {
      // Network error: queue mutations for retry when back online
      const config = error.config;
      const method = config?.method?.toUpperCase();
      const isFormData = config?.data instanceof FormData;
      if (
        typeof window !== "undefined" &&
        config &&
        method &&
        ["POST", "PUT", "PATCH", "DELETE"].includes(method) &&
        !isFormData
      ) {
        const base = config.baseURL ?? "";
        const path = config.url ?? "";
        const url = path.startsWith("http") ? path : base + (path.startsWith("/") ? path : `/${path}`);
        addToRequestQueue(
          method as "POST" | "PUT" | "PATCH" | "DELETE",
          url,
          config.data
        )
          .then(() => registerBackgroundSync())
          .catch(() => {});
      }
      throw new Error("Network error - please check your connection");
    } else {
      // Error in setting up request
      throw new Error(error.message || "An error occurred");
    }
  }
);

// File Manager API (admin only)
export const fileManagerApi = {
  listFiles: (path?: string, page = 1, perPage = 50) =>
    api.get<{ items: FileManagerItem[]; total: number }>("/storage/files", {
      params: { path: path || "", page, per_page: perPage },
    }),

  getFile: (path: string) =>
    api.get<FileManagerItem & { previewUrl?: string | null }>(
      `/storage/files/${encodeURIComponent(path)}`
    ),

  uploadFiles: (path: string, files: File[]) => {
    const formData = new FormData();
    formData.append("path", path);
    files.forEach((f) => formData.append("files[]", f));
    return api.post<{
      message: string;
      uploaded: { path: string; name: string; size: number }[];
      errors?: string[];
    }>("/storage/files", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
  },

  downloadFile: (path: string) =>
    api.get(`/storage/files/${encodeURIComponent(path)}/download`, {
      responseType: "blob",
    }),

  deleteFile: (path: string) =>
    api.delete(`/storage/files/${encodeURIComponent(path)}`),

  renameFile: (path: string, name: string) =>
    api.put<{ message: string; path: string }>(
      `/storage/files/${encodeURIComponent(path)}/rename`,
      { name }
    ),

  moveFile: (path: string, destination: string) =>
    api.put<{ message: string; path: string }>(
      `/storage/files/${encodeURIComponent(path)}/move`,
      { destination }
    ),
};

export type FileManagerItem = {
  name: string;
  path: string;
  size: number | null;
  mimeType: string | null;
  lastModified: number | null;
  isDirectory: boolean;
};
