/**
 * Structured client error reporting. Sends errors to the backend for logging.
 * Use this instead of console.error for production error tracking.
 * Uses fetch() (not the api client) to avoid circular dependency with api.ts.
 */

export interface ErrorReport {
  message: string;
  stack?: string;
  component_stack?: string;
  url: string;
  user_agent: string;
  timestamp: string;
  level: "info" | "warning" | "error";
  context?: Record<string, unknown>;
}

let lastCorrelationId: string | null = null;

/**
 * Store correlation ID from API response headers for inclusion in error reports.
 * Called by the API client when responses include X-Correlation-ID.
 */
export function setCorrelationId(id: string | null): void {
  lastCorrelationId = id;
}

function getReportPayload(
  message: string,
  level: "info" | "warning" | "error",
  options?: { stack?: string; componentStack?: string; context?: Record<string, unknown> }
): ErrorReport {
  return {
    message,
    stack: options?.stack,
    component_stack: options?.componentStack,
    url: typeof window !== "undefined" ? window.location.href : "",
    user_agent: typeof navigator !== "undefined" ? navigator.userAgent : "",
    timestamp: new Date().toISOString(),
    level,
    context: options?.context,
  };
}

function sendReport(payload: ErrorReport): void {
  if (typeof window === "undefined") return;

  const headers: Record<string, string> = {
    "Content-Type": "application/json",
  };
  if (lastCorrelationId) {
    headers["X-Correlation-ID"] = lastCorrelationId;
  }

  fetch("/api/client-errors", {
    method: "POST",
    credentials: "include",
    headers,
    body: JSON.stringify(payload),
  }).catch(() => {
    // Silently ignore network errors when reporting errors
  });
}

export const errorLogger = {
  /**
   * Report an error (e.g. from catch block or error boundary).
   * Pass context.componentStack from React error boundaries so it is sent as component_stack.
   */
  report(error: Error, context?: Record<string, unknown>): void {
    const componentStack =
      context && typeof context.componentStack === "string"
        ? context.componentStack
        : undefined;
    const payload = getReportPayload(error.message, "error", {
      stack: error.stack,
      componentStack,
      context,
    });
    sendReport(payload);
  },

  /**
   * Capture a message at the given level (info, warning, error).
   */
  captureMessage(
    message: string,
    level: "info" | "warning" | "error" = "error",
    context?: Record<string, unknown>
  ): void {
    const payload = getReportPayload(message, level, { context });
    sendReport(payload);
  },
};
