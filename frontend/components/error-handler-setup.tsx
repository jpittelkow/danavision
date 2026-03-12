"use client";

import { useEffect } from "react";
import { errorLogger } from "@/lib/error-logger";

/**
 * Sets up global window error handlers to report uncaught errors and
 * unhandled promise rejections to the backend. Mount once in the app root.
 */
export function ErrorHandlerSetup() {
  useEffect(() => {
    const handleError = (event: ErrorEvent) => {
      errorLogger.report(event.error ?? new Error(event.message), {
        type: "window.onerror",
      });
    };

    const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
      const error =
        event.reason instanceof Error
          ? event.reason
          : new Error(String(event.reason));
      errorLogger.report(error, {
        type: "unhandledrejection",
      });
    };

    window.addEventListener("error", handleError);
    window.addEventListener("unhandledrejection", handleUnhandledRejection);

    return () => {
      window.removeEventListener("error", handleError);
      window.removeEventListener("unhandledrejection", handleUnhandledRejection);
    };
  }, []);

  return null;
}
