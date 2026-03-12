"use client";

import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { SettingsPageSkeleton } from "@/components/ui/settings-page-skeleton";
import { HelpLink } from "@/components/help/help-link";
import { ChannelsTab } from "@/components/notifications/channels-tab";
import { EmailTab } from "@/components/notifications/email-tab";
import { TemplatesTab } from "@/components/notifications/templates-tab";
import { DeliveryLogTab } from "@/components/notifications/delivery-log-tab";
import { NovuTab } from "@/components/notifications/novu-tab";

function CommunicationsPageContent() {
  const searchParams = useSearchParams();
  const defaultTab = searchParams.get("tab") || "channels";

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Communications</h1>
        <p className="text-muted-foreground mt-1">
          Configure notification channels, email delivery, templates, and monitoring.{" "}
          <HelpLink articleId="notification-channels" />
        </p>
      </div>

      <Tabs defaultValue={defaultTab}>
        <TabsList className="w-full justify-start overflow-x-auto">
          <TabsTrigger value="channels">Channels</TabsTrigger>
          <TabsTrigger value="email">Email</TabsTrigger>
          <TabsTrigger value="templates">Templates</TabsTrigger>
          <TabsTrigger value="delivery-log">Delivery Log</TabsTrigger>
          <TabsTrigger value="novu">Novu</TabsTrigger>
        </TabsList>

        <TabsContent value="channels" className="mt-4">
          <ChannelsTab />
        </TabsContent>
        <TabsContent value="email" className="mt-4">
          <EmailTab />
        </TabsContent>
        <TabsContent value="templates" className="mt-4">
          <TemplatesTab />
        </TabsContent>
        <TabsContent value="delivery-log" className="mt-4">
          <DeliveryLogTab />
        </TabsContent>
        <TabsContent value="novu" className="mt-4">
          <NovuTab />
        </TabsContent>
      </Tabs>
    </div>
  );
}

export default function CommunicationsPage() {
  return (
    <Suspense fallback={<SettingsPageSkeleton />}>
      <CommunicationsPageContent />
    </Suspense>
  );
}
