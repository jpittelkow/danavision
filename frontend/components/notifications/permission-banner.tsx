"use client";

import { useState, useEffect, useCallback } from "react";
import { Bell, X, Loader2, AlertTriangle, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAppConfig } from "@/lib/app-config";
import {
  isWebPushSupported,
  getPermissionStatus,
  subscribe,
} from "@/lib/web-push";
import { api } from "@/lib/api";
import { toast } from "sonner";

const STORAGE_DISMISSED_AT = "notification-banner-dismissed-at";
const RE_PROMPT_DAYS = 30;

function isDismissedRecently(): boolean {
  if (typeof window === "undefined") return true;
  const raw = localStorage.getItem(STORAGE_DISMISSED_AT);
  if (!raw) return false;
  const dismissedAt = parseInt(raw, 10);
  if (isNaN(dismissedAt)) return false;
  const daysSince = (Date.now() - dismissedAt) / (1000 * 60 * 60 * 24);
  return daysSince < RE_PROMPT_DAYS;
}

type BannerState = "hidden" | "prompt" | "denied" | "enabling" | "granted";

/**
 * Banner shown at the top of the notifications preferences tab
 * when the user hasn't yet granted/denied push notification permission.
 * Also shows unblock instructions when permission is denied.
 */
export function NotificationPermissionBanner() {
  const { features } = useAppConfig();
  const webpushEnabled = features?.webpushEnabled;
  const vapidKey = features?.webpushVapidPublicKey;

  const [state, setState] = useState<BannerState>("hidden");

  useEffect(() => {
    if (!webpushEnabled || !vapidKey || !isWebPushSupported()) {
      setState("hidden");
      return;
    }
    const status = getPermissionStatus();
    if (status === "granted") {
      setState("hidden");
      return;
    }
    if (status === "denied") {
      setState("denied");
      return;
    }
    // status === 'default' — never asked
    if (isDismissedRecently()) {
      setState("hidden");
      return;
    }
    setState("prompt");
  }, [webpushEnabled, vapidKey]);

  const handleEnable = useCallback(async () => {
    if (!vapidKey) return;
    setState("enabling");
    try {
      const payload = await subscribe(vapidKey);
      if (!payload) {
        const status = getPermissionStatus();
        if (status === "denied") {
          setState("denied");
        } else {
          setState("prompt");
        }
        return;
      }
      await api.post("/user/webpush-subscription", payload);
      await api.put("/user/notification-settings", {
        channel: "webpush",
        enabled: true,
        usage_accepted: true,
      });
      setState("granted");
      toast.success("Browser notifications enabled!");
    } catch {
      toast.error("Failed to enable notifications.");
      setState("prompt");
    }
  }, [vapidKey]);

  const handleDismiss = useCallback(() => {
    localStorage.setItem(STORAGE_DISMISSED_AT, String(Date.now()));
    setState("hidden");
  }, []);

  if (state === "hidden") return null;

  if (state === "granted") {
    return (
      <div className="rounded-lg border border-green-500/20 bg-green-500/5 p-4 flex items-start gap-3">
        <Check className="h-5 w-5 text-green-600 shrink-0 mt-0.5" />
        <div>
          <p className="text-sm font-medium text-green-700 dark:text-green-400">
            Browser notifications enabled
          </p>
          <p className="text-xs text-muted-foreground mt-1">
            You&apos;ll receive real-time alerts for important updates.
          </p>
        </div>
      </div>
    );
  }

  if (state === "denied") {
    return (
      <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-4">
        <div className="flex items-start gap-3">
          <AlertTriangle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-amber-700 dark:text-amber-400">
              Browser notifications are blocked
            </p>
            <p className="text-xs text-muted-foreground mt-1">
              To enable notifications, update your browser settings:
            </p>
            <BrowserUnblockInstructions />
          </div>
        </div>
      </div>
    );
  }

  // prompt or enabling
  return (
    <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
      <div className="flex items-start gap-3">
        <Bell className="h-5 w-5 text-primary shrink-0 mt-0.5" />
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium">Enable browser notifications</p>
          <p className="text-xs text-muted-foreground mt-1">
            Get real-time alerts for security events, backup completions, and
            system updates — even when you&apos;re not using the app.
          </p>
          <div className="flex items-center gap-2 mt-3">
            <Button
              size="sm"
              onClick={handleEnable}
              disabled={state === "enabling"}
            >
              {state === "enabling" ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Bell className="mr-2 h-4 w-4" />
              )}
              Enable Notifications
            </Button>
            <Button size="sm" variant="ghost" onClick={handleDismiss}>
              Not now
            </Button>
          </div>
        </div>
        <Button
          variant="ghost"
          size="icon"
          className="h-7 w-7 shrink-0 -mt-1 -mr-1"
          onClick={handleDismiss}
        >
          <X className="h-4 w-4" />
          <span className="sr-only">Dismiss</span>
        </Button>
      </div>
    </div>
  );
}

function BrowserUnblockInstructions() {
  const browser = detectBrowser();

  return (
    <ol className="text-xs text-muted-foreground mt-2 space-y-1 list-decimal list-inside">
      {browser === "chrome" && (
        <>
          <li>Click the lock/tune icon in the address bar</li>
          <li>Select &quot;Site settings&quot;</li>
          <li>Set Notifications to &quot;Allow&quot;</li>
        </>
      )}
      {browser === "firefox" && (
        <>
          <li>Click the shield icon in the address bar</li>
          <li>Click &quot;Protection settings&quot;</li>
          <li>Find Notifications and remove the block</li>
        </>
      )}
      {browser === "safari" && (
        <>
          <li>Open Safari Preferences &gt; Websites &gt; Notifications</li>
          <li>Find this site and change to &quot;Allow&quot;</li>
        </>
      )}
      {browser === "other" && (
        <>
          <li>Open your browser&apos;s site settings for this page</li>
          <li>Find Notifications and change to &quot;Allow&quot;</li>
          <li>Reload the page</li>
        </>
      )}
    </ol>
  );
}

function detectBrowser(): "chrome" | "firefox" | "safari" | "other" {
  if (typeof navigator === "undefined") return "other";
  const ua = navigator.userAgent;
  if (/Chrome/.test(ua) && !/Edg/.test(ua)) return "chrome";
  if (/Firefox/.test(ua)) return "firefox";
  if (/Safari/.test(ua) && !/Chrome/.test(ua)) return "safari";
  return "other";
}
