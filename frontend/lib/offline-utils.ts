/**
 * Offline detection and fallback utilities for graceful degradation.
 */

/**
 * Returns true if the error is likely due to being offline (network error).
 */
export function isOfflineError(error: unknown): boolean {
  if (error instanceof Error) {
    const msg = error.message.toLowerCase();
    return (
      msg.includes("network error") ||
      msg.includes("failed to fetch") ||
      msg.includes("network request failed") ||
      msg.includes("load failed")
    );
  }
  return false;
}

/**
 * Check if the app is currently offline (no network).
 */
export function isOffline(): boolean {
  if (typeof navigator === "undefined") return false;
  return !navigator.onLine;
}

/**
 * Wraps a fetcher so that when it fails with an offline error,
 * the fallback value is returned instead of throwing.
 * Useful with React Query's placeholderData or initialData.
 */
export async function withOfflineFallback<T>(
  fetcher: () => Promise<T>,
  fallback: T
): Promise<T> {
  try {
    return await fetcher();
  } catch (error: unknown) {
    if (isOfflineError(error)) {
      return fallback;
    }
    throw error;
  }
}
