"use client";

import { useState, useEffect, useCallback } from "react";

const STORAGE_VISIT_COUNT = "pwa-visit-count";
const STORAGE_DISMISSED_AT = "pwa-install-dismissed-at";
const MIN_VISITS_BEFORE_PROMPT = 2;
const RE_PROMPT_DAYS = 30;

/**
 * BeforeInstallPromptEvent is not in TypeScript DOM lib.
 * See https://developer.mozilla.org/en-US/docs/Web/API/BeforeInstallPromptEvent
 */
export interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>;
  userChoice: Promise<{ outcome: "accepted" | "dismissed"; platform: string }>;
}

export interface UseInstallPromptResult {
  /** The deferred install prompt event (null if not available or already used). */
  deferredPrompt: BeforeInstallPromptEvent | null;
  /** True if the app can show an install prompt (event fired, not installed, criteria met). */
  canPrompt: boolean;
  /** True when running as installed PWA (standalone display mode). */
  isInstalled: boolean;
  /** Call to trigger the native install prompt. Resolves to user choice. */
  promptInstall: () => Promise<{ outcome: "accepted" | "dismissed" } | null>;
  /** Call when user dismisses the banner without installing. Pass dontShowAgain to suppress for 30 days. */
  dismissBanner: (dontShowAgain?: boolean) => void;
  /** Whether the install banner should be shown (visit count >= 2, not dismissed, etc.). */
  shouldShowBanner: boolean;
}

function getVisitCount(): number {
  if (typeof window === "undefined") return 0;
  try {
    const raw = localStorage.getItem(STORAGE_VISIT_COUNT);
    return raw ? Math.max(0, parseInt(raw, 10)) : 0;
  } catch {
    return 0;
  }
}

function incrementVisitCount(): number {
  if (typeof window === "undefined") return 0;
  try {
    const next = getVisitCount() + 1;
    localStorage.setItem(STORAGE_VISIT_COUNT, String(next));
    return next;
  } catch {
    return 0;
  }
}

function getDismissedAt(): number | null {
  if (typeof window === "undefined") return null;
  try {
    const raw = localStorage.getItem(STORAGE_DISMISSED_AT);
    return raw ? parseInt(raw, 10) : null;
  } catch {
    return null;
  }
}

function setDismissedAt(dontShowAgain: boolean): void {
  if (typeof window === "undefined") return;
  try {
    if (dontShowAgain) {
      localStorage.setItem(STORAGE_DISMISSED_AT, String(Date.now()));
    } else {
      localStorage.removeItem(STORAGE_DISMISSED_AT);
    }
  } catch {
    // ignore
  }
}

function isDismissedWithinDays(days: number): boolean {
  const at = getDismissedAt();
  if (!at) return false;
  return Date.now() - at < days * 24 * 60 * 60 * 1000;
}

function getIsStandalone(): boolean {
  if (typeof window === "undefined") return false;
  return (
    window.matchMedia("(display-mode: standalone)").matches ||
    (window.navigator as { standalone?: boolean }).standalone === true
  );
}

/**
 * Hook for PWA install prompt: captures beforeinstallprompt, tracks visits and dismissal,
 * and exposes promptInstall() and whether to show the custom banner.
 */
export function useInstallPrompt(): UseInstallPromptResult {
  const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
  const [isInstalled, setIsInstalled] = useState(false);
  const [visitCount, setVisitCount] = useState(0);
  const [dismissedRecently, setDismissedRecently] = useState(true);

  useEffect(() => {
    if (typeof window === "undefined") return;

    const installed = getIsStandalone();
    setIsInstalled(installed);

    const count = incrementVisitCount();
    setVisitCount(count);

    setDismissedRecently(isDismissedWithinDays(RE_PROMPT_DAYS));
  }, []);

  useEffect(() => {
    if (typeof window === "undefined") return;

    const handler = (e: Event) => {
      e.preventDefault();
      setDeferredPrompt(e as BeforeInstallPromptEvent);
    };
    const onInstalled = () => {
      setDeferredPrompt(null);
      setIsInstalled(true);
    };

    window.addEventListener("beforeinstallprompt", handler);
    window.addEventListener("appinstalled", onInstalled);
    return () => {
      window.removeEventListener("beforeinstallprompt", handler);
      window.removeEventListener("appinstalled", onInstalled);
    };
  }, []);

  useEffect(() => {
    if (typeof window === "undefined") return;
    const media = window.matchMedia("(display-mode: standalone)");
    const handler = () => setIsInstalled(getIsStandalone());
    media.addEventListener("change", handler);
    return () => media.removeEventListener("change", handler);
  }, []);

  const promptInstall = useCallback(async (): Promise<{ outcome: "accepted" | "dismissed" } | null> => {
    if (!deferredPrompt) return null;
    try {
      await deferredPrompt.prompt();
      const choice = await deferredPrompt.userChoice;
      if (choice.outcome === "accepted") {
        setDeferredPrompt(null);
      }
      return { outcome: choice.outcome };
    } catch {
      return null;
    }
  }, [deferredPrompt]);

  const dismissBanner = useCallback((dontShowAgain = false) => {
    setDismissedRecently(true);
    setDismissedAt(dontShowAgain);
  }, []);

  const canPrompt = Boolean(deferredPrompt) && !isInstalled;
  const shouldShowBanner =
    canPrompt &&
    visitCount >= MIN_VISITS_BEFORE_PROMPT &&
    !dismissedRecently;

  return {
    deferredPrompt,
    canPrompt,
    isInstalled,
    promptInstall,
    dismissBanner,
    shouldShowBanner,
  };
}
