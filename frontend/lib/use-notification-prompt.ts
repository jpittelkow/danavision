"use client";

import { useCallback, useRef } from "react";
import { isWebPushSupported, getPermissionStatus, subscribe } from "./web-push";
import { useAppConfig } from "./app-config";
import { api } from "./api";
import { toast } from "sonner";
import { Bell } from "lucide-react";
import { createElement } from "react";

const STORAGE_KEY = "notification-prompt-shown";
const STORAGE_BANNER_KEY = "notification-banner-dismissed-at";
const STORAGE_PWA_KEY = "push-prompt-dismissed-at";

/**
 * Returns true if the user has already been prompted via any notification flow
 * (onboarding, preferences banner, PWA post-install, or this contextual prompt).
 */
function hasBeenPromptedRecently(): boolean {
  if (typeof window === "undefined") return true;

  // Check if this contextual prompt was shown this session
  if (sessionStorage.getItem(STORAGE_KEY)) return true;

  // Check if the preferences banner was dismissed recently (shares 30-day cooldown)
  const bannerDismissed = localStorage.getItem(STORAGE_BANNER_KEY);
  if (bannerDismissed) {
    const days = (Date.now() - parseInt(bannerDismissed, 10)) / (1000 * 60 * 60 * 24);
    if (days < 30) return true;
  }

  // Check if the PWA post-install prompt was dismissed recently
  const pwaDismissed = localStorage.getItem(STORAGE_PWA_KEY);
  if (pwaDismissed) {
    const days = (Date.now() - parseInt(pwaDismissed, 10)) / (1000 * 60 * 60 * 24);
    if (days < 30) return true;
  }

  return false;
}

/**
 * Hook for showing a contextual notification prompt after a user performs
 * an action that generates notifications (e.g., starting a backup, changing
 * security settings).
 *
 * Shows a one-time toast per session. Coordinates with the post-install
 * prompt and preferences banner to avoid double-prompting.
 *
 * Usage:
 * ```tsx
 * const { promptIfNeeded } = useNotificationPrompt();
 *
 * const handleStartBackup = async () => {
 *   await api.post("/backups");
 *   promptIfNeeded("Want to know when your backup completes?");
 * };
 * ```
 */
export function useNotificationPrompt() {
  const { features } = useAppConfig();
  const shownRef = useRef(false);

  const promptIfNeeded = useCallback(
    (message?: string) => {
      // Only prompt once per session
      if (shownRef.current) return;

      // Guards: webpush must be enabled, supported, and permission not yet decided
      if (!features?.webpushEnabled || !features?.webpushVapidPublicKey) return;
      if (!isWebPushSupported()) return;

      const status = getPermissionStatus();
      if (status !== "default") return;

      // Don't double-prompt
      if (hasBeenPromptedRecently()) return;

      shownRef.current = true;
      sessionStorage.setItem(STORAGE_KEY, "1");

      const vapidKey = features.webpushVapidPublicKey;

      toast(message || "Want to be notified when this completes?", {
        icon: createElement(Bell, { className: "h-4 w-4" }),
        duration: 10000,
        action: {
          label: "Enable notifications",
          onClick: async () => {
            try {
              const payload = await subscribe(vapidKey);
              if (!payload) return;
              await api.post("/user/webpush-subscription", payload);
              await api.put("/user/notification-settings", {
                channel: "webpush",
                enabled: true,
                usage_accepted: true,
              });
              toast.success("Browser notifications enabled!");
            } catch {
              toast.error("Failed to enable notifications.");
            }
          },
        },
      });
    },
    [features?.webpushEnabled, features?.webpushVapidPublicKey]
  );

  return { promptIfNeeded };
}
