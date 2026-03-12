"use client";

import { useState, useEffect, useCallback } from "react";
import { Bell, Check, AlertTriangle, Smartphone, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  WizardStep,
  WizardStepTitle,
  WizardStepDescription,
  WizardStepContent,
} from "@/components/onboarding/wizard-step";
import { useAppConfig } from "@/lib/app-config";
import {
  isWebPushSupported,
  getPermissionStatus,
  subscribe,
} from "@/lib/web-push";
import { api } from "@/lib/api";
import { toast } from "sonner";

type PermissionState = "loading" | "unsupported" | "prompt" | "granted" | "denied";

export function NotificationsStep() {
  const { features } = useAppConfig();
  const webpushEnabled = features?.webpushEnabled;
  const vapidKey = features?.webpushVapidPublicKey;

  const [permissionState, setPermissionState] = useState<PermissionState>("loading");
  const [subscribing, setSubscribing] = useState(false);

  useEffect(() => {
    if (!webpushEnabled || !vapidKey) {
      setPermissionState("unsupported");
      return;
    }
    if (!isWebPushSupported()) {
      setPermissionState("unsupported");
      return;
    }
    const status = getPermissionStatus();
    if (status === "granted") {
      setPermissionState("granted");
    } else if (status === "denied") {
      setPermissionState("denied");
    } else {
      setPermissionState("prompt");
    }
  }, [webpushEnabled, vapidKey]);

  const handleEnable = useCallback(async () => {
    if (!vapidKey) return;
    setSubscribing(true);
    try {
      const payload = await subscribe(vapidKey);
      if (!payload) {
        // User denied or subscription failed
        const status = getPermissionStatus();
        if (status === "denied") {
          setPermissionState("denied");
        }
        return;
      }
      // Register with backend
      await api.post("/user/webpush-subscription", payload);
      await api.put("/user/notification-settings", {
        channel: "webpush",
        enabled: true,
        usage_accepted: true,
      });
      setPermissionState("granted");
      toast.success("Notifications enabled! You'll receive alerts here.");
    } catch {
      toast.error("Failed to enable notifications. You can try again in preferences.");
    } finally {
      setSubscribing(false);
    }
  }, [vapidKey]);

  return (
    <WizardStep>
      <div className="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
        {permissionState === "granted" ? (
          <Check className="h-8 w-8 text-green-600" />
        ) : (
          <Bell className="h-8 w-8 text-primary" />
        )}
      </div>

      <WizardStepTitle>Stay informed</WizardStepTitle>

      <WizardStepContent>
        {permissionState === "granted" ? (
          <GrantedContent />
        ) : permissionState === "denied" ? (
          <DeniedContent />
        ) : permissionState === "unsupported" ? (
          <UnsupportedContent webpushEnabled={!!webpushEnabled} />
        ) : permissionState === "prompt" ? (
          <PromptContent subscribing={subscribing} onEnable={handleEnable} />
        ) : (
          <div className="flex justify-center py-4">
            <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
          </div>
        )}
      </WizardStepContent>
    </WizardStep>
  );
}

function PromptContent({
  subscribing,
  onEnable,
}: {
  subscribing: boolean;
  onEnable: () => void;
}) {
  return (
    <div className="space-y-3">
      <div className="p-4 rounded-lg border bg-card text-left space-y-2">
        <div className="flex items-start gap-3">
          <div className="h-8 w-8 shrink-0 rounded-full bg-muted flex items-center justify-center">
            <Check className="h-4 w-4 text-green-600" />
          </div>
          <div>
            <p className="text-sm font-medium">In-app notifications</p>
            <p className="text-xs text-muted-foreground">Always on</p>
          </div>
        </div>
        <div className="flex items-start gap-3">
          <div className="h-8 w-8 shrink-0 rounded-full bg-primary/10 flex items-center justify-center">
            <Smartphone className="h-4 w-4 text-primary" />
          </div>
          <div>
            <p className="text-sm font-medium">Browser push notifications</p>
            <p className="text-xs text-muted-foreground">
              Get alerts even when the app isn&apos;t open
            </p>
          </div>
        </div>
      </div>

      <Button
        className="w-full"
        onClick={onEnable}
        disabled={subscribing}
      >
        {subscribing ? (
          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
        ) : (
          <Bell className="mr-2 h-4 w-4" />
        )}
        Enable Browser Notifications
      </Button>
    </div>
  );
}

function GrantedContent() {
  return (
    <div className="space-y-3">
      <div className="p-4 rounded-lg border border-green-500/20 bg-green-500/5 text-left">
        <p className="text-sm font-medium text-green-700 dark:text-green-400">
          Browser notifications enabled
        </p>
        <p className="text-xs text-muted-foreground mt-1">
          You&apos;ll receive alerts for important updates. Customize which
          notifications you receive in your preferences.
        </p>
      </div>
    </div>
  );
}

function DeniedContent() {
  return (
    <div className="space-y-3">
      <div className="p-4 rounded-lg border border-amber-500/20 bg-amber-500/5 text-left">
        <div className="flex items-start gap-2">
          <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-amber-700 dark:text-amber-400">
              Browser notifications were blocked
            </p>
            <p className="text-xs text-muted-foreground mt-1">
              You can enable them later in your browser&apos;s site settings.
              You&apos;ll still receive in-app and email notifications.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

function UnsupportedContent({ webpushEnabled }: { webpushEnabled: boolean }) {
  return (
    <div className="space-y-3">
      <div className="p-4 rounded-lg border bg-card text-left">
        <p className="text-sm font-medium mb-2">Available channels:</p>
        <ul className="text-sm text-muted-foreground space-y-1">
          <li>In-app notifications (always on)</li>
          <li>Email notifications</li>
        </ul>
        {webpushEnabled && (
          <p className="text-xs text-muted-foreground mt-3">
            Your browser doesn&apos;t support push notifications — you&apos;ll
            still receive in-app and email notifications.
          </p>
        )}
      </div>
    </div>
  );
}
