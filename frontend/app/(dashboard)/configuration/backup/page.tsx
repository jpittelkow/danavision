"use client";

import { useState, useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { errorLogger } from "@/lib/error-logger";
import { formatBytes, formatDateTime, getErrorMessage } from "@/lib/utils";
import { useNotificationPrompt } from "@/lib/use-notification-prompt";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { CollapsibleCard } from "@/components/ui/collapsible-card";
import { ProviderIcon } from "@/components/provider-icons";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { HelpLink } from "@/components/help/help-link";
import { FormField } from "@/components/ui/form-field";
import { SettingsSwitchRow } from "@/components/ui/settings-switch-row";
import { SaveButton } from "@/components/ui/save-button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Loader2,
  Database,
  Download,
  Upload,
  Trash2,
  Plus,
  AlertTriangle,
  CheckCircle,
  Clock,
  HardDrive,
  FileArchive,
  Settings,
  Cloud,
  Server,
  Lock,
  Bell,
} from "lucide-react";

const backupSettingsSchema = z.object({
  disk: z.string().max(64),
  retention_enabled: z.boolean(),
  retention_days: z.coerce.number().min(1).max(365),
  retention_count: z.coerce.number().min(1).max(100),
  min_backups: z.coerce.number().min(0).max(100),
  schedule_enabled: z.boolean(),
  schedule_frequency: z.enum(["daily", "weekly", "monthly"]),
  schedule_time: z.string().regex(/^\d{2}:\d{2}$/),
  schedule_day: z.coerce.number().min(0).max(6),
  schedule_date: z.coerce.number().min(1).max(31),
  scheduled_destinations: z.string().max(255),
  s3_enabled: z.boolean(),
  s3_bucket: z.string().max(255).optional(),
  s3_path: z.string().max(255).optional(),
  s3_access_key_id: z.string().max(255).optional(),
  s3_secret_access_key: z.string().optional(),
  s3_region: z.string().max(64).optional(),
  s3_endpoint: z.string().max(512).optional(),
  sftp_enabled: z.boolean(),
  sftp_host: z.string().max(255).optional(),
  sftp_port: z.coerce.number().min(1).max(65535),
  sftp_username: z.string().max(255).optional(),
  sftp_password: z.string().optional(),
  sftp_private_key: z.string().optional(),
  sftp_passphrase: z.string().optional(),
  sftp_path: z.string().max(512).optional(),
  gdrive_enabled: z.boolean(),
  gdrive_client_id: z.string().max(255).optional(),
  gdrive_client_secret: z.string().optional(),
  gdrive_refresh_token: z.string().optional(),
  gdrive_folder_id: z.string().max(255).optional(),
  encryption_enabled: z.boolean(),
  encryption_password: z.string().optional(),
  notify_success: z.boolean(),
  notify_failure: z.boolean(),
});
type BackupSettingsForm = z.infer<typeof backupSettingsSchema>;

const defaultBackupSettings: BackupSettingsForm = {
  disk: "backups",
  retention_enabled: true,
  retention_days: 30,
  retention_count: 10,
  min_backups: 5,
  schedule_enabled: false,
  schedule_frequency: "daily",
  schedule_time: "02:00",
  schedule_day: 0,
  schedule_date: 1,
  scheduled_destinations: "local",
  s3_enabled: false,
  s3_bucket: "",
  s3_path: "backups",
  s3_access_key_id: "",
  s3_secret_access_key: "",
  s3_region: "us-east-1",
  s3_endpoint: "",
  sftp_enabled: false,
  sftp_host: "",
  sftp_port: 22,
  sftp_username: "",
  sftp_password: "",
  sftp_private_key: "",
  sftp_passphrase: "",
  sftp_path: "/backups",
  gdrive_enabled: false,
  gdrive_client_id: "",
  gdrive_client_secret: "",
  gdrive_refresh_token: "",
  gdrive_folder_id: "",
  encryption_enabled: false,
  encryption_password: "",
  notify_success: false,
  notify_failure: true,
};

interface Backup {
  filename: string;
  size: number;
  created_at: string;
  type: "full" | "database" | "files";
}

export default function BackupPage() {
  const [backups, setBackups] = useState<Backup[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isCreating, setIsCreating] = useState(false);
  const [isRestoring, setIsRestoring] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<string | null>(null);
  const [restoreTarget, setRestoreTarget] = useState<string | null>(null);
  const [restoreConfirmation, setRestoreConfirmation] = useState("");
  const [settingsLoading, setSettingsLoading] = useState(false);
  const [settingsSaving, setSettingsSaving] = useState(false);
  const [testingDestination, setTestingDestination] = useState<string | null>(null);
  const { promptIfNeeded } = useNotificationPrompt();

  const {
    register,
    handleSubmit,
    formState: { errors, isDirty },
    reset,
    setValue,
    watch,
  } = useForm<BackupSettingsForm>({
    resolver: zodResolver(backupSettingsSchema),
    mode: "onBlur",
    defaultValues: defaultBackupSettings,
  });

  useEffect(() => {
    fetchBackups();
  }, []);

  const fetchBackups = async () => {
    try {
      const response = await api.get("/backup");
      setBackups(response.data.backups || []);
    } catch (error: unknown) {
      errorLogger.report(
        error instanceof Error ? error : new Error("Failed to fetch backups"),
        { source: "backup-page" }
      );
    } finally {
      setIsLoading(false);
    }
  };

  const handleCreateBackup = async () => {
    setIsCreating(true);

    try {
      await api.post("/backup/create");
      toast.success("Backup created successfully");
      fetchBackups();
      promptIfNeeded("Want to know when your backup completes?");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to create backup"));
    } finally {
      setIsCreating(false);
    }
  };

  const handleDownloadBackup = async (filename: string) => {
    try {
      const response = await api.get(`/backup/download/${filename}`, {
        responseType: "blob",
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", filename);
      document.body.appendChild(link);
      link.click();
      link.parentNode?.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to download backup"));
    }
  };

  const handleRestoreBackup = async () => {
    if (!restoreTarget || restoreConfirmation !== "RESTORE") {
      toast.error('Please type "RESTORE" to confirm');
      return;
    }

    setIsRestoring(true);

    try {
      await api.post("/backup/restore", { filename: restoreTarget });
      toast.success("Backup restored successfully. The application will restart.");
      setRestoreTarget(null);
      setRestoreConfirmation("");
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to restore backup"));
    } finally {
      setIsRestoring(false);
    }
  };

  const handleDeleteBackup = async (filename: string) => {
    try {
      await api.delete(`/backup/${filename}`);
      toast.success("Backup deleted");
      setBackups((prev) => prev.filter((b) => b.filename !== filename));
      setDeleteTarget(null);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to delete backup"));
    }
  };

  const fetchBackupSettings = async () => {
    setSettingsLoading(true);
    try {
      const response = await api.get("/backup-settings");
      const settings = response.data?.settings ?? {};
      const formValues: BackupSettingsForm = {
        disk: settings.disk ?? defaultBackupSettings.disk,
        retention_enabled: settings.retention_enabled ?? defaultBackupSettings.retention_enabled,
        retention_days: settings.retention_days != null ? Number(settings.retention_days) : defaultBackupSettings.retention_days,
        retention_count: settings.retention_count != null ? Number(settings.retention_count) : defaultBackupSettings.retention_count,
        min_backups: settings.min_backups != null ? Number(settings.min_backups) : defaultBackupSettings.min_backups,
        schedule_enabled: settings.schedule_enabled ?? defaultBackupSettings.schedule_enabled,
        schedule_frequency: (settings.schedule_frequency as BackupSettingsForm["schedule_frequency"]) ?? defaultBackupSettings.schedule_frequency,
        schedule_time: settings.schedule_time ?? defaultBackupSettings.schedule_time,
        schedule_day: settings.schedule_day != null ? Number(settings.schedule_day) : defaultBackupSettings.schedule_day,
        schedule_date: settings.schedule_date != null ? Number(settings.schedule_date) : defaultBackupSettings.schedule_date,
        scheduled_destinations: settings.scheduled_destinations ?? defaultBackupSettings.scheduled_destinations,
        s3_enabled: settings.s3_enabled ?? defaultBackupSettings.s3_enabled,
        s3_bucket: settings.s3_bucket ?? defaultBackupSettings.s3_bucket,
        s3_path: settings.s3_path ?? defaultBackupSettings.s3_path,
        s3_access_key_id: settings.s3_access_key_id ?? defaultBackupSettings.s3_access_key_id,
        s3_secret_access_key: settings.s3_secret_access_key ?? defaultBackupSettings.s3_secret_access_key,
        s3_region: settings.s3_region ?? defaultBackupSettings.s3_region,
        s3_endpoint: settings.s3_endpoint ?? defaultBackupSettings.s3_endpoint,
        sftp_enabled: settings.sftp_enabled ?? defaultBackupSettings.sftp_enabled,
        sftp_host: settings.sftp_host ?? defaultBackupSettings.sftp_host,
        sftp_port: settings.sftp_port != null ? Number(settings.sftp_port) : defaultBackupSettings.sftp_port,
        sftp_username: settings.sftp_username ?? defaultBackupSettings.sftp_username,
        sftp_password: settings.sftp_password ?? defaultBackupSettings.sftp_password,
        sftp_private_key: settings.sftp_private_key ?? defaultBackupSettings.sftp_private_key,
        sftp_passphrase: settings.sftp_passphrase ?? defaultBackupSettings.sftp_passphrase,
        sftp_path: settings.sftp_path ?? defaultBackupSettings.sftp_path,
        gdrive_enabled: settings.gdrive_enabled ?? defaultBackupSettings.gdrive_enabled,
        gdrive_client_id: settings.gdrive_client_id ?? defaultBackupSettings.gdrive_client_id,
        gdrive_client_secret: settings.gdrive_client_secret ?? defaultBackupSettings.gdrive_client_secret,
        gdrive_refresh_token: settings.gdrive_refresh_token ?? defaultBackupSettings.gdrive_refresh_token,
        gdrive_folder_id: settings.gdrive_folder_id ?? defaultBackupSettings.gdrive_folder_id,
        encryption_enabled: settings.encryption_enabled ?? defaultBackupSettings.encryption_enabled,
        encryption_password: settings.encryption_password ?? defaultBackupSettings.encryption_password,
        notify_success: settings.notify_success ?? defaultBackupSettings.notify_success,
        notify_failure: settings.notify_failure ?? defaultBackupSettings.notify_failure,
      };
      reset(formValues);
    } catch (e: unknown) {
      toast.error("Failed to load backup settings");
    } finally {
      setSettingsLoading(false);
    }
  };

  const onSubmitSettings = async (data: BackupSettingsForm) => {
    setSettingsSaving(true);
    try {
      await api.put("/backup-settings", data);
      toast.success("Backup settings saved");
      await fetchBackupSettings();
    } catch (err: unknown) {
      const msg = err && typeof err === "object" && "response" in err
        ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
        : null;
      toast.error(msg ?? "Failed to save backup settings");
    } finally {
      setSettingsSaving(false);
    }
  };

  const handleTestDestination = async (destination: "s3" | "sftp" | "google_drive") => {
    setTestingDestination(destination);
    try {
      await api.post(`/backup-settings/test/${destination}`);
      toast.success("Connection successful");
    } catch (err: unknown) {
      const msg = err && typeof err === "object" && "response" in err
        ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
        : null;
      toast.error(msg ?? "Connection failed");
    } finally {
      setTestingDestination(null);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Backup & Restore</h1>
        <p className="text-muted-foreground">
          Manage system backups and restore points.{" "}
          <HelpLink articleId="backup-settings" />
        </p>
      </div>

      <Tabs defaultValue="backups" className="space-y-6" onValueChange={(v) => v === "settings" && fetchBackupSettings()}>
        <TabsList>
          <TabsTrigger value="backups" className="gap-2">
            <FileArchive className="h-4 w-4" />
            Backups
          </TabsTrigger>
          <TabsTrigger value="settings" className="gap-2">
            <Settings className="h-4 w-4" />
            Settings
          </TabsTrigger>
        </TabsList>

        <TabsContent value="backups" className="space-y-6">
      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Database className="h-5 w-5" />
            Quick Actions
          </CardTitle>
          <CardDescription>
            Create a new backup or restore from an existing one.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap gap-4">
            <Button onClick={handleCreateBackup} disabled={isCreating}>
              {isCreating ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Plus className="mr-2 h-4 w-4" />
              )}
              Create Backup
            </Button>
            <Button variant="outline" asChild>
              <label className="cursor-pointer">
                <Upload className="mr-2 h-4 w-4" />
                Upload Backup
                <input
                  type="file"
                  accept=".zip"
                  className="hidden"
                  onChange={async (e) => {
                    const file = e.target.files?.[0];
                    if (!file) return;

                    const formData = new FormData();
                    formData.append("backup", file);

                    try {
                      await api.post("/backup/upload", formData, {
                        headers: { "Content-Type": "multipart/form-data" },
                      });
                      toast.success("Backup uploaded successfully");
                      fetchBackups();
                    } catch (error: unknown) {
                      toast.error(getErrorMessage(error, "Failed to upload backup"));
                    }
                  }}
                />
              </label>
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Backups List */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <FileArchive className="h-5 w-5" />
            Available Backups
          </CardTitle>
          <CardDescription>
            {backups.length} backup{backups.length !== 1 ? "s" : ""} available
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <div key={i} className="flex items-center justify-between p-4 border rounded-lg">
                  <div className="space-y-2">
                    <Skeleton className="h-4 w-48" />
                    <Skeleton className="h-3 w-32" />
                  </div>
                  <Skeleton className="h-9 w-24" />
                </div>
              ))}
            </div>
          ) : backups.length === 0 ? (
            <div className="text-center py-8">
              <Database className="mx-auto h-12 w-12 text-muted-foreground/50" />
              <h3 className="mt-4 text-lg font-medium">No backups yet</h3>
              <p className="text-sm text-muted-foreground mt-1">
                Create your first backup to protect your data.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {backups.map((backup) => (
                <div
                  key={backup.filename}
                  className="flex items-center justify-between p-4 border rounded-lg"
                >
                  <div className="flex items-center gap-4">
                    <div className="p-2 rounded-full bg-muted">
                      <FileArchive className="h-5 w-5 text-muted-foreground" />
                    </div>
                    <div>
                      <p className="font-medium">{backup.filename}</p>
                      <div className="flex items-center gap-4 text-sm text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <HardDrive className="h-3 w-3" />
                          {formatBytes(backup.size)}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {formatDateTime(backup.created_at)}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleDownloadBackup(backup.filename)}
                    >
                      <Download className="mr-2 h-4 w-4" />
                      Download
                    </Button>
                    <Dialog>
                      <DialogTrigger asChild>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => setRestoreTarget(backup.filename)}
                        >
                          <Upload className="mr-2 h-4 w-4" />
                          Restore
                        </Button>
                      </DialogTrigger>
                      <DialogContent>
                        <DialogHeader>
                          <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-destructive" />
                            Restore Backup
                          </DialogTitle>
                          <DialogDescription>
                            This will replace all current data with the backup data.
                            This action cannot be undone.
                          </DialogDescription>
                        </DialogHeader>
                        <Alert variant="destructive" className="my-4">
                          <AlertTriangle className="h-4 w-4" />
                          <AlertTitle>Warning</AlertTitle>
                          <AlertDescription>
                            Restoring a backup will overwrite all current data
                            including users, settings, and files. The application
                            will restart after restoration.
                          </AlertDescription>
                        </Alert>
                        <div className="space-y-4">
                          <p className="text-sm text-muted-foreground">
                            Type <strong>RESTORE</strong> to confirm:
                          </p>
                          <Input
                            value={restoreConfirmation}
                            onChange={(e) =>
                              setRestoreConfirmation(e.target.value)
                            }
                            placeholder="Type RESTORE"
                          />
                        </div>
                        <DialogFooter>
                          <Button
                            variant="outline"
                            onClick={() => {
                              setRestoreTarget(null);
                              setRestoreConfirmation("");
                            }}
                          >
                            Cancel
                          </Button>
                          <Button
                            variant="destructive"
                            onClick={handleRestoreBackup}
                            disabled={
                              isRestoring || restoreConfirmation !== "RESTORE"
                            }
                          >
                            {isRestoring && (
                              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Restore Backup
                          </Button>
                        </DialogFooter>
                      </DialogContent>
                    </Dialog>
                    <Dialog>
                      <DialogTrigger asChild>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-destructive hover:text-destructive"
                          onClick={() => setDeleteTarget(backup.filename)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </DialogTrigger>
                      <DialogContent>
                        <DialogHeader>
                          <DialogTitle>Delete Backup</DialogTitle>
                          <DialogDescription>
                            Are you sure you want to delete this backup? This
                            action cannot be undone.
                          </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                          <p className="text-sm">
                            <strong>{backup.filename}</strong>
                          </p>
                          <p className="text-sm text-muted-foreground">
                            {formatBytes(backup.size)} • {formatDateTime(backup.created_at)}
                          </p>
                        </div>
                        <DialogFooter>
                          <Button
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                          >
                            Cancel
                          </Button>
                          <Button
                            variant="destructive"
                            onClick={() => handleDeleteBackup(backup.filename)}
                          >
                            Delete
                          </Button>
                        </DialogFooter>
                      </DialogContent>
                    </Dialog>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Info */}
      <Alert>
        <CheckCircle className="h-4 w-4" />
        <AlertTitle>Backup Information</AlertTitle>
        <AlertDescription>
          Backups include the database, uploaded files, and application settings.
          We recommend creating regular backups and storing them in a secure
          location. Consider setting up automatic backups via scheduled tasks.
        </AlertDescription>
      </Alert>
        </TabsContent>

        <TabsContent value="settings" className="space-y-6">
          {settingsLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-32 w-full" />
              <Skeleton className="h-48 w-full" />
            </div>
          ) : (
            <form onSubmit={handleSubmit(onSubmitSettings)} className="space-y-6">
              {/* Retention & storage */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Database className="h-5 w-5" />
                    Retention & storage
                  </CardTitle>
                  <CardDescription>Storage disk and how long to keep backups.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <FormField id="disk" label="Storage disk" error={errors.disk?.message}>
                    <Input {...register("disk")} placeholder="backups" />
                  </FormField>
                  <SettingsSwitchRow
                    label="Enable retention policy"
                    checked={watch("retention_enabled")}
                    onCheckedChange={(v) => setValue("retention_enabled", v, { shouldDirty: true })}
                  />
                  <FormField id="retention_days" label="Retention (days)" error={errors.retention_days?.message}>
                    <Input type="number" {...register("retention_days")} min={1} max={365} />
                  </FormField>
                  <FormField id="retention_count" label="Keep count" error={errors.retention_count?.message}>
                    <Input type="number" {...register("retention_count")} min={1} max={100} />
                  </FormField>
                  <FormField id="min_backups" label="Minimum backups" error={errors.min_backups?.message}>
                    <Input type="number" {...register("min_backups")} min={0} max={100} />
                  </FormField>
                </CardContent>
              </Card>

              {/* Schedule */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Clock className="h-5 w-5" />
                    Schedule
                  </CardTitle>
                  <CardDescription>Scheduled backup frequency and time.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <SettingsSwitchRow
                    label="Enable scheduled backups"
                    checked={watch("schedule_enabled")}
                    onCheckedChange={(v) => setValue("schedule_enabled", v, { shouldDirty: true })}
                  />
                  <FormField id="schedule_frequency" label="Frequency" error={errors.schedule_frequency?.message}>
                    <Select value={watch("schedule_frequency")} onValueChange={(v) => setValue("schedule_frequency", v as BackupSettingsForm["schedule_frequency"], { shouldDirty: true })}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent>
                        <SelectItem value="daily">Daily</SelectItem>
                        <SelectItem value="weekly">Weekly</SelectItem>
                        <SelectItem value="monthly">Monthly</SelectItem>
                      </SelectContent>
                    </Select>
                  </FormField>
                  <FormField id="schedule_time" label="Time (24h)" error={errors.schedule_time?.message}>
                    <Input type="time" {...register("schedule_time")} />
                  </FormField>
                  <FormField id="schedule_day" label="Day of week (0=Sun)" error={errors.schedule_day?.message}>
                    <Input type="number" {...register("schedule_day")} min={0} max={6} />
                  </FormField>
                  <FormField id="schedule_date" label="Day of month" error={errors.schedule_date?.message}>
                    <Input type="number" {...register("schedule_date")} min={1} max={31} />
                  </FormField>
                  <FormField id="scheduled_destinations" label="Destinations (comma-separated)" error={errors.scheduled_destinations?.message}>
                    <Input {...register("scheduled_destinations")} placeholder="local, s3" />
                  </FormField>
                </CardContent>
              </Card>

              {/* S3 */}
              <CollapsibleCard
                title="Amazon S3"
                description="Store backups in an S3 bucket."
                icon={<ProviderIcon provider="s3" size="sm" style="mono" />}
                status={{
                  label: watch("s3_enabled") ? "Enabled" : "Disabled",
                  variant: watch("s3_enabled") ? "success" : "default",
                }}
                defaultOpen={false}
              >
                <div className="space-y-4">
                  <SettingsSwitchRow
                    label="Enable S3 destination"
                    checked={watch("s3_enabled")}
                    onCheckedChange={(v) => setValue("s3_enabled", v, { shouldDirty: true })}
                  />
                  <FormField id="s3_bucket" label="Bucket">
                    <Input {...register("s3_bucket")} placeholder="my-bucket" />
                  </FormField>
                  <FormField id="s3_path" label="Path prefix">
                    <Input {...register("s3_path")} placeholder="backups" />
                  </FormField>
                  <FormField id="s3_access_key_id" label="Access Key ID">
                    <Input type="password" autoComplete="off" {...register("s3_access_key_id")} />
                  </FormField>
                  <FormField id="s3_secret_access_key" label="Secret Access Key">
                    <Input type="password" autoComplete="off" {...register("s3_secret_access_key")} />
                  </FormField>
                  <FormField id="s3_region" label="Region">
                    <Input {...register("s3_region")} placeholder="us-east-1" />
                  </FormField>
                  <FormField id="s3_endpoint" label="Custom endpoint (optional)">
                    <Input {...register("s3_endpoint")} placeholder="https://..." />
                  </FormField>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => handleTestDestination("s3")}
                    disabled={!!testingDestination}
                  >
                    {testingDestination === "s3" ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                    Test Connection
                  </Button>
                </div>
              </CollapsibleCard>

              {/* SFTP */}
              <CollapsibleCard
                title="SFTP"
                description="Store backups on an SFTP server."
                icon={<Server className="h-4 w-4" />}
                status={{
                  label: watch("sftp_enabled") ? "Enabled" : "Disabled",
                  variant: watch("sftp_enabled") ? "success" : "default",
                }}
                defaultOpen={false}
              >
                <div className="space-y-4">
                  <SettingsSwitchRow
                    label="Enable SFTP destination"
                    checked={watch("sftp_enabled")}
                    onCheckedChange={(v) => setValue("sftp_enabled", v, { shouldDirty: true })}
                  />
                  <FormField id="sftp_host" label="Host">
                    <Input {...register("sftp_host")} placeholder="sftp.example.com" />
                  </FormField>
                  <FormField id="sftp_port" label="Port" error={errors.sftp_port?.message}>
                    <Input type="number" {...register("sftp_port")} min={1} max={65535} />
                  </FormField>
                  <FormField id="sftp_username" label="Username">
                    <Input {...register("sftp_username")} />
                  </FormField>
                  <FormField id="sftp_password" label="Password">
                    <Input type="password" autoComplete="off" {...register("sftp_password")} />
                  </FormField>
                  <FormField id="sftp_path" label="Remote path">
                    <Input {...register("sftp_path")} placeholder="/backups" />
                  </FormField>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => handleTestDestination("sftp")}
                    disabled={!!testingDestination}
                  >
                    {testingDestination === "sftp" ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                    Test Connection
                  </Button>
                </div>
              </CollapsibleCard>

              {/* Google Drive */}
              <CollapsibleCard
                title="Google Drive"
                description="Store backups in Google Drive."
                icon={<ProviderIcon provider="gdrive" size="sm" style="mono" />}
                status={{
                  label: watch("gdrive_enabled") ? "Enabled" : "Disabled",
                  variant: watch("gdrive_enabled") ? "success" : "default",
                }}
                defaultOpen={false}
              >
                <div className="space-y-4">
                  <SettingsSwitchRow
                    label="Enable Google Drive destination"
                    checked={watch("gdrive_enabled")}
                    onCheckedChange={(v) => setValue("gdrive_enabled", v, { shouldDirty: true })}
                  />
                  <FormField id="gdrive_client_id" label="Client ID">
                    <Input {...register("gdrive_client_id")} />
                  </FormField>
                  <FormField id="gdrive_client_secret" label="Client Secret">
                    <Input type="password" autoComplete="off" {...register("gdrive_client_secret")} />
                  </FormField>
                  <FormField id="gdrive_refresh_token" label="Refresh Token">
                    <Input type="password" autoComplete="off" {...register("gdrive_refresh_token")} />
                  </FormField>
                  <FormField id="gdrive_folder_id" label="Folder ID (optional)">
                    <Input {...register("gdrive_folder_id")} />
                  </FormField>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => handleTestDestination("google_drive")}
                    disabled={!!testingDestination}
                  >
                    {testingDestination === "google_drive" ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                    Test Connection
                  </Button>
                </div>
              </CollapsibleCard>

              {/* Encryption */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Lock className="h-5 w-5" />
                    Encryption
                  </CardTitle>
                  <CardDescription>Encrypt backup files with a password.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <SettingsSwitchRow
                    label="Enable encryption"
                    checked={watch("encryption_enabled")}
                    onCheckedChange={(v) => setValue("encryption_enabled", v, { shouldDirty: true })}
                  />
                  <FormField id="encryption_password" label="Encryption password">
                    <Input type="password" autoComplete="off" {...register("encryption_password")} />
                  </FormField>
                </CardContent>
              </Card>

              {/* Notifications */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Bell className="h-5 w-5" />
                    Notifications
                  </CardTitle>
                  <CardDescription>Notify on backup success or failure.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <SettingsSwitchRow
                    label="Notify on success"
                    checked={watch("notify_success")}
                    onCheckedChange={(v) => setValue("notify_success", v, { shouldDirty: true })}
                  />
                  <SettingsSwitchRow
                    label="Notify on failure"
                    checked={watch("notify_failure")}
                    onCheckedChange={(v) => setValue("notify_failure", v, { shouldDirty: true })}
                  />
                </CardContent>
              </Card>

              <Card>
                <CardFooter className="flex justify-end">
                  <SaveButton isDirty={isDirty} isSaving={settingsSaving} />
                </CardFooter>
              </Card>
            </form>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
