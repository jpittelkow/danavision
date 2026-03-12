"use client";

import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";

interface VersionState {
  version: string;
  buildSha: string;
  buildTime: string | null;
  phpVersion: string;
  laravelVersion: string;
  isLoading: boolean;
  error: Error | null;
}

const VersionContext = React.createContext<VersionState | undefined>(undefined);

/**
 * Fetches version information from the API.
 * Uses React Query for caching (24 hour stale time since version rarely changes).
 */
function useVersionQuery() {
  return useQuery({
    queryKey: ["version"],
    queryFn: async () => {
      try {
        const response = await api.get("/version");
        return response.data;
      } catch (error: unknown) {
        // If API fails, return empty values - components should handle loading/error states
        return {
          version: "",
          build_sha: "development",
          build_time: null,
          php_version: "",
          laravel_version: "",
        };
      }
    },
    staleTime: 24 * 60 * 60 * 1000, // 24 hours cache
    gcTime: 24 * 60 * 60 * 1000, // 24 hours garbage collection
    retry: 1,
    refetchOnWindowFocus: false,
  });
}

/**
 * Provider component that fetches and provides version information.
 */
export function VersionProvider({ children }: { children: React.ReactNode }) {
  const query = useVersionQuery();

  const value: VersionState = {
    version: query.data?.version || "",
    buildSha: query.data?.build_sha || "development",
    buildTime: query.data?.build_time || null,
    phpVersion: query.data?.php_version || "",
    laravelVersion: query.data?.laravel_version || "",
    isLoading: query.isLoading,
    error: query.error as Error | null,
  };

  return React.createElement(
    VersionContext.Provider,
    { value },
    children
  );
}

/**
 * Hook to access version information (version, build SHA, build time, etc.)
 * Falls back to defaults if version info is not available.
 */
export function useVersion(): VersionState {
  const context = React.useContext(VersionContext);

  if (context === undefined) {
    // Fallback if used outside provider (shouldn't happen, but safe fallback)
    return {
      version: "",
      buildSha: "development",
      buildTime: null,
      phpVersion: "",
      laravelVersion: "",
      isLoading: false,
      error: null,
    };
  }

  return context;
}
