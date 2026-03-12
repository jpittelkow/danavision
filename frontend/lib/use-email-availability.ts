"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { api } from "@/lib/api";

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function isValidEmail(email: string): boolean {
  return EMAIL_REGEX.test(email.trim());
}

const DEBOUNCE_MS = 500;

export interface UseEmailAvailabilityResult {
  /** True while the API request is in flight. */
  isChecking: boolean;
  /** True if the last check found the email available (not registered). Undefined until first check completes. */
  isAvailable: boolean | undefined;
  /** Error message if the last check failed. */
  error: string | null;
  /** Call to check availability for the given email. Debounced; only runs when email format is valid. */
  checkEmail: (email: string) => void;
}

/**
 * Hook for real-time email availability check on registration.
 * Debounces API calls (500ms) and only checks when email format is valid.
 */
export function useEmailAvailability(): UseEmailAvailabilityResult {
  const [isChecking, setIsChecking] = useState(false);
  const [isAvailable, setIsAvailable] = useState<boolean | undefined>(undefined);
  const [error, setError] = useState<string | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const checkEmail = useCallback((email: string) => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }

    const trimmed = email.trim();
    if (!trimmed) {
      setIsAvailable(undefined);
      setError(null);
      setIsChecking(false);
      return;
    }
    if (!isValidEmail(trimmed)) {
      setIsAvailable(undefined);
      setError(null);
      setIsChecking(false);
      return;
    }

    timeoutRef.current = setTimeout(async () => {
      timeoutRef.current = null;
      const controller = new AbortController();
      abortRef.current = controller;
      setIsChecking(true);
      setError(null);

      try {
        const response = await api.post<{ available: boolean }>(
          "/auth/check-email",
          { email: trimmed },
          { signal: controller.signal }
        );
        if (!controller.signal.aborted) {
          setIsAvailable(response.data.available);
          setError(null);
        }
      } catch (err: unknown) {
        if (controller.signal.aborted) return;
        const message =
          typeof err === "object" && err !== null && "response" in err
            ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
            : null;
        setError(message || "Could not check email availability");
        setIsAvailable(undefined);
      } finally {
        if (!controller.signal.aborted) {
          setIsChecking(false);
        }
        abortRef.current = null;
      }
    }, DEBOUNCE_MS);
  }, []);

  useEffect(() => {
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
      if (abortRef.current) abortRef.current.abort();
    };
  }, []);

  return { isChecking, isAvailable, error, checkEmail };
}
