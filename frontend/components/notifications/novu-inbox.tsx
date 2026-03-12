"use client";

import { useAuth } from "@/lib/auth";
import { useAppConfig } from "@/lib/app-config";
import { api } from "@/lib/api";
import { errorLogger } from "@/lib/error-logger";
import { getErrorMessage } from "@/lib/utils";
import { useState, useEffect } from "react";
import { Inbox } from "@novu/react";

/**
 * Renders @novu/react Inbox when Novu is enabled.
 * Subscriber ID must match backend: user_{id}.
 * Fetches subscriberHash from backend when HMAC is enabled in Novu dashboard.
 *
 * IMPORTANT: This component must be loaded with next/dynamic({ ssr: false })
 * because @novu/react uses solid-js internally, which Turbopack cannot
 * process during SSR.
 */
export function NovuInboxBell() {
  const { user } = useAuth();
  const { novu } = useAppConfig();
  const [subscriberHash, setSubscriberHash] = useState<string | null>(null);

  useEffect(() => {
    if (!user?.id || !novu?.enabled) return;
    api
      .get<{ subscriber_hash?: string }>("/novu/subscriber-hash")
      .then((res) => {
        const hash = res.data?.subscriber_hash;
        if (hash) setSubscriberHash(hash);
      })
      .catch((error: unknown) => {
        // Non-fatal: subscriberHash is only required when HMAC is enabled in Novu.
        errorLogger.captureMessage("Failed to fetch Novu subscriber hash", "warning", {
          error: getErrorMessage(error, "Unknown error"),
        });
      });
  }, [user?.id, novu?.enabled]);

  if (!user || !novu?.enabled || !novu?.app_identifier) {
    return null;
  }

  const subscriberId = `user_${user.id}`;

  return (
    <Inbox
      applicationIdentifier={novu.app_identifier}
      subscriberId={subscriberId}
      {...(subscriberHash ? { subscriberHash } : {})}
      backendUrl={novu.api_url}
      socketUrl={novu.socket_url}
    />
  );
}
