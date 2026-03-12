"use client";

import { useState, useEffect, useMemo } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { formatDate } from "@/lib/utils";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { SettingsPageSkeleton } from "@/components/ui/settings-page-skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { AlertTriangle, ChevronRight } from "lucide-react";
import {
  getNotificationType,
  getNotificationCategory,
  getCategoryLabel,
  getAllCategories,
  CHANNEL_GROUP_LABELS,
  type NotificationCategory,
} from "@/lib/notification-types";

interface EmailTemplateSummary {
  key: string;
  name: string;
  description: string;
  is_system: boolean;
  is_active: boolean;
  updated_at: string;
}

interface NotificationTemplateSummary {
  id: number;
  type: string;
  channel_group: string;
  title: string;
  is_system: boolean;
  is_active: boolean;
  updated_at: string;
}

const channelGroupLabel = CHANNEL_GROUP_LABELS;

export function TemplatesTab() {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(true);
  const [emailTemplates, setEmailTemplates] = useState<EmailTemplateSummary[]>([]);
  const [notificationTemplates, setNotificationTemplates] = useState<NotificationTemplateSummary[]>([]);
  const [novuEnabled, setNovuEnabled] = useState(false);

  const groupedNotificationTemplates = useMemo(() => {
    return notificationTemplates.reduce<
      Record<string, NotificationTemplateSummary[]>
    >((acc, t) => {
      const cat = getNotificationCategory(t.type);
      if (!acc[cat]) acc[cat] = [];
      acc[cat].push(t);
      return acc;
    }, {});
  }, [notificationTemplates]);

  useEffect(() => {
    fetchAll();
  }, []);

  const fetchAll = async () => {
    setIsLoading(true);
    try {
      const [emailRes, notifRes] = await Promise.all([
        api.get("/email-templates"),
        api.get("/notification-templates"),
      ]);
      const emailData = emailRes.data?.data ?? emailRes.data;
      setEmailTemplates(Array.isArray(emailData) ? emailData : []);
      const notifData = notifRes.data?.data ?? notifRes.data;
      setNotificationTemplates(Array.isArray(notifData) ? notifData : []);
    } catch {
      toast.error("Failed to load templates");
    } finally {
      setIsLoading(false);
    }

    // Check Novu status separately (non-blocking)
    try {
      const res = await api.get("/novu-settings");
      const settings = res.data?.settings ?? {};
      setNovuEnabled(settings.enabled === true && !!settings.api_key);
    } catch {
      // Silently ignore
    }
  };

  if (isLoading) {
    return <SettingsPageSkeleton />;
  }

  return (
    <div className="space-y-6">
      {novuEnabled && (
        <Alert variant="warning">
          <AlertTriangle className="h-4 w-4" />
          <AlertTitle>Novu is active</AlertTitle>
          <AlertDescription>
            Notification templates only apply when Novu is disabled. While Novu is active, notification content is managed through your Novu dashboard.
          </AlertDescription>
        </Alert>
      )}

      <Tabs defaultValue="notification">
        <TabsList>
          <TabsTrigger value="notification">
            Notification Templates
            {notificationTemplates.length > 0 && (
              <Badge variant="secondary" className="ml-2 text-xs">{notificationTemplates.length}</Badge>
            )}
          </TabsTrigger>
          <TabsTrigger value="email">
            Email Templates
            {emailTemplates.length > 0 && (
              <Badge variant="secondary" className="ml-2 text-xs">{emailTemplates.length}</Badge>
            )}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="notification" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle>Notification Templates</CardTitle>
              <CardDescription>
                Customize per-type notification messages for push, in-app, chat, and email channels. Click a template to edit.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>Channel</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Last Updated</TableHead>
                    <TableHead className="w-10" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {notificationTemplates.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-muted-foreground py-8">
                        No notification templates found
                      </TableCell>
                    </TableRow>
                  ) : (
                    getAllCategories()
                      .filter(({ value }) => groupedNotificationTemplates[value]?.length)
                      .flatMap(({ value: category }) => {
                        const categoryTemplates = groupedNotificationTemplates[category];
                        return [
                          <TableRow
                            key={`cat-${category}`}
                            className="bg-muted/30 hover:bg-muted/30"
                          >
                            <TableCell
                              colSpan={6}
                              className="py-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                            >
                              {getCategoryLabel(category as NotificationCategory)}
                            </TableCell>
                          </TableRow>,
                          ...categoryTemplates.map((template) => {
                            const typeMeta = getNotificationType(template.type);
                            const TypeIcon = typeMeta.icon;
                            return (
                              <TableRow
                                key={`${template.type}-${template.channel_group}`}
                                className="cursor-pointer"
                                onClick={() =>
                                  router.push(`/configuration/notification-templates/${template.id}`)
                                }
                              >
                                <TableCell className="font-medium">
                                  <div className="flex items-center gap-2">
                                    <TypeIcon className="h-4 w-4 text-muted-foreground shrink-0" />
                                    {typeMeta.label}
                                    {template.is_system && (
                                      <Badge variant="secondary" className="text-xs">System</Badge>
                                    )}
                                  </div>
                                </TableCell>
                                <TableCell>
                                  <Badge variant="outline">
                                    {channelGroupLabel[template.channel_group] ?? template.channel_group}
                                  </Badge>
                                </TableCell>
                                <TableCell className="text-muted-foreground max-w-md truncate">
                                  {template.title || "\u2014"}
                                </TableCell>
                                <TableCell>
                                  <Badge variant={template.is_active ? "default" : "secondary"}>
                                    {template.is_active ? "Active" : "Inactive"}
                                  </Badge>
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                  {formatDate(template.updated_at)}
                                </TableCell>
                                <TableCell>
                                  <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                </TableCell>
                              </TableRow>
                            );
                          }),
                        ];
                      })
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="email" className="mt-4">
          <Card>
            <CardHeader>
              <CardTitle>Email Templates</CardTitle>
              <CardDescription>
                Customize system-generated emails such as password reset and verification. Click a template to edit.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Last Updated</TableHead>
                    <TableHead className="w-10" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {emailTemplates.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                        No email templates found
                      </TableCell>
                    </TableRow>
                  ) : (
                    emailTemplates.map((template) => (
                      <TableRow
                        key={template.key}
                        className="cursor-pointer"
                        onClick={() =>
                          router.push(`/configuration/email-templates/${encodeURIComponent(template.key)}`)
                        }
                      >
                        <TableCell className="font-medium">
                          <div className="flex items-center gap-2">
                            {template.name}
                            {template.is_system && (
                              <Badge variant="secondary" className="text-xs">System</Badge>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground max-w-md truncate">
                          {template.description || "\u2014"}
                        </TableCell>
                        <TableCell>
                          <Badge variant={template.is_active ? "default" : "secondary"}>
                            {template.is_active ? "Active" : "Inactive"}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {formatDate(template.updated_at)}
                        </TableCell>
                        <TableCell>
                          <ChevronRight className="h-4 w-4 text-muted-foreground" />
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
