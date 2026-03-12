"use client";

import { useState } from "react";
import { Bell } from "lucide-react";
import { toast } from "sonner";
import { usePostInstallPushPrompt } from "@/lib/use-post-install-push-prompt";
import { useAppConfig } from "@/lib/app-config";
import { isWebPushSupported, subscribe } from "@/lib/web-push";
import { api } from "@/lib/api";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

/**
 * Prompt shown after PWA install to enable push notifications on this device.
 * Uses AlertDialog for consistent theming. 30-day cooldown on dismiss.
 */
export function PostInstallPushPrompt() {
  const { shouldShowPushPrompt, dismissPushPrompt } =
    usePostInstallPushPrompt();
  const { features } = useAppConfig();
  const [isEnabling, setIsEnabling] = useState(false);

  if (!shouldShowPushPrompt) return null;

  const vapidKey = features?.webpushVapidPublicKey;
  if (!vapidKey || !isWebPushSupported()) return null;

  const handleEnable = async (e: React.MouseEvent) => {
    e.preventDefault();
    setIsEnabling(true);
    try {
      const payload = await subscribe(vapidKey);
      if (payload) {
        await api.post("/user/webpush-subscription", payload);
        await api.put("/user/notification-settings", {
          channel: "webpush",
          enabled: true,
          usage_accepted: true,
        });
        toast.success("Push notifications enabled!");
      }
      // payload is null when user denied permission — no error needed
    } catch {
      // Only show error for API failures, not permission denial
      if (Notification.permission !== "denied") {
        toast.error("Failed to enable push notifications");
      }
    }
    setIsEnabling(false);
    dismissPushPrompt();
  };

  return (
    <AlertDialog
      open={shouldShowPushPrompt}
      onOpenChange={(open) => {
        if (!open) dismissPushPrompt();
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2">
            <Bell className="h-5 w-5" aria-hidden />
            Enable Push Notifications
          </AlertDialogTitle>
          <AlertDialogDescription>
            Get notified about important updates on this device, even when the
            app is closed.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isEnabling}>Not now</AlertDialogCancel>
          <AlertDialogAction onClick={handleEnable} disabled={isEnabling}>
            {isEnabling ? "Enabling…" : "Enable"}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
