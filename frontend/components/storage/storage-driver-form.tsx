"use client";

import { useState, useEffect, useCallback } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { getErrorMessage } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Loader2, Bell, CheckCircle2, XCircle } from "lucide-react";
import { ProviderIcon } from "@/components/provider-icons";
import { SaveButton } from "@/components/ui/save-button";

const DRIVERS = ["local", "s3", "gcs", "azure", "do_spaces", "minio", "b2"] as const;

const storageSchema = z.object({
  driver: z.enum(DRIVERS),
  max_upload_size: z.number().min(1),
  allowed_file_types: z.array(z.string()),
  storage_alert_enabled: z.boolean().optional(),
  storage_alert_threshold: z.number().min(1).max(100).optional(),
  storage_alert_critical: z.number().min(1).max(100).optional(),
  storage_alert_email: z.boolean().optional(),
  s3_bucket: z.string().optional(),
  s3_region: z.string().optional(),
  s3_key: z.string().optional(),
  s3_secret: z.string().optional(),
  gcs_bucket: z.string().optional(),
  gcs_project_id: z.string().optional(),
  gcs_credentials_json: z.string().optional(),
  azure_container: z.string().optional(),
  azure_connection_string: z.string().optional(),
  do_spaces_bucket: z.string().optional(),
  do_spaces_region: z.string().optional(),
  do_spaces_key: z.string().optional(),
  do_spaces_secret: z.string().optional(),
  do_spaces_endpoint: z.string().optional(),
  minio_bucket: z.string().optional(),
  minio_endpoint: z.string().optional(),
  minio_key: z.string().optional(),
  minio_secret: z.string().optional(),
  b2_bucket: z.string().optional(),
  b2_region: z.string().optional(),
  b2_key_id: z.string().optional(),
  b2_application_key: z.string().optional(),
});

type StorageForm = z.infer<typeof storageSchema>;

const STORAGE_PROVIDERS: { id: (typeof DRIVERS)[number]; label: string }[] = [
  { id: "local", label: "Local Filesystem" },
  { id: "s3", label: "Amazon S3" },
  { id: "gcs", label: "Google Cloud Storage" },
  { id: "azure", label: "Azure Blob Storage" },
  { id: "do_spaces", label: "DigitalOcean Spaces" },
  { id: "minio", label: "MinIO" },
  { id: "b2", label: "Backblaze B2" },
];

interface StorageHealth {
  status: "healthy" | "warning";
  writable: boolean;
  disk_used_percent: number;
  disk_free_formatted: string;
  disk_total_formatted: string;
}

interface StorageDriverFormProps {
  health: StorageHealth | null;
  onSaved: () => void;
}

export function StorageDriverForm({ health, onSaved }: StorageDriverFormProps) {
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [fileTypesInput, setFileTypesInput] = useState("");
  const [testStatus, setTestStatus] = useState<"idle" | "loading" | "success" | "error">("idle");
  const [testError, setTestError] = useState<string | null>(null);

  const { register, handleSubmit, formState: { errors, isDirty }, setValue, watch } = useForm<StorageForm>({
    resolver: zodResolver(storageSchema),
    defaultValues: {
      driver: "local",
      max_upload_size: 10485760,
      allowed_file_types: [],
      storage_alert_enabled: false,
      storage_alert_threshold: 80,
      storage_alert_critical: 95,
      storage_alert_email: true,
    },
  });

  const driver = watch("driver");

  const fetchSettings = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await api.get("/storage-settings");
      const settings = response.data.settings || {};

      if (settings.allowed_file_types) {
        setFileTypesInput(Array.isArray(settings.allowed_file_types)
          ? settings.allowed_file_types.join(", ")
          : settings.allowed_file_types);
      }

      Object.keys(settings).forEach((key) => {
        if (key === "max_upload_size") {
          setValue(key as keyof StorageForm, settings[key] ? parseInt(settings[key]) : 10485760);
        } else if (key === "allowed_file_types") {
          setValue(key as keyof StorageForm, Array.isArray(settings[key]) ? settings[key] : []);
        } else if (key === "storage_alert_enabled") {
          setValue(key as keyof StorageForm, settings[key] === true || settings[key] === "true");
        } else if (key === "storage_alert_threshold") {
          setValue(key as keyof StorageForm, settings[key] != null ? parseInt(settings[key]) : 80);
        } else if (key === "storage_alert_critical") {
          setValue(key as keyof StorageForm, settings[key] != null ? parseInt(settings[key]) : 95);
        } else if (key === "storage_alert_email") {
          setValue(key as keyof StorageForm, settings[key] !== false && settings[key] !== "false");
        } else {
          setValue(key as keyof StorageForm, settings[key] || "");
        }
      });
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to load storage settings"));
    } finally {
      setIsLoading(false);
    }
  }, [setValue]);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const onTestConnection = async () => {
    setTestStatus("loading");
    setTestError(null);
    try {
      const fileTypes = fileTypesInput
        .split(",")
        .map((t) => t.trim())
        .filter((t) => t.length > 0);
      const payload = {
        driver,
        max_upload_size: 0,
        allowed_file_types: fileTypes,
        ...(driver === "s3" && {
          s3_bucket: watch("s3_bucket"),
          s3_region: watch("s3_region"),
          s3_key: watch("s3_key"),
          s3_secret: watch("s3_secret"),
        }),
        ...(driver === "gcs" && {
          gcs_bucket: watch("gcs_bucket"),
          gcs_project_id: watch("gcs_project_id"),
          gcs_credentials_json: watch("gcs_credentials_json"),
        }),
        ...(driver === "azure" && {
          azure_container: watch("azure_container"),
          azure_connection_string: watch("azure_connection_string"),
        }),
        ...(driver === "do_spaces" && {
          do_spaces_bucket: watch("do_spaces_bucket"),
          do_spaces_region: watch("do_spaces_region"),
          do_spaces_key: watch("do_spaces_key"),
          do_spaces_secret: watch("do_spaces_secret"),
          do_spaces_endpoint: watch("do_spaces_endpoint"),
        }),
        ...(driver === "minio" && {
          minio_bucket: watch("minio_bucket"),
          minio_endpoint: watch("minio_endpoint"),
          minio_key: watch("minio_key"),
          minio_secret: watch("minio_secret"),
        }),
        ...(driver === "b2" && {
          b2_bucket: watch("b2_bucket"),
          b2_region: watch("b2_region"),
          b2_key_id: watch("b2_key_id"),
          b2_application_key: watch("b2_application_key"),
        }),
      };
      const response = await api.post("/storage-settings/test", payload);
      if (response.data?.success) {
        setTestStatus("success");
        toast.success("Connection successful");
      } else {
        setTestStatus("error");
        setTestError(response.data?.error ?? "Connection failed");
      }
    } catch (error: unknown) {
      setTestStatus("error");
      const msg = getErrorMessage(error, "Connection test failed");
      setTestError(msg);
      toast.error(msg);
    }
  };

  const onSubmit = async (data: StorageForm) => {
    setIsSaving(true);
    try {
      const fileTypes = fileTypesInput
        .split(",")
        .map((t) => t.trim())
        .filter((t) => t.length > 0);

      const submitData = {
        ...data,
        allowed_file_types: fileTypes,
        storage_alert_enabled: data.storage_alert_enabled ?? false,
        storage_alert_threshold: data.storage_alert_threshold ?? 80,
        storage_alert_critical: data.storage_alert_critical ?? 95,
        storage_alert_email: data.storage_alert_email ?? true,
      };

      await api.put("/storage-settings", submitData);
      toast.success("Storage settings updated successfully");
      await fetchSettings();
      onSaved();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to update storage settings"));
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) return null;

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Bell className="h-5 w-5" />
            Storage Alerts
          </CardTitle>
          <CardDescription>
            Get notified when storage usage exceeds configured thresholds
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between rounded-lg border p-4">
            <div>
              <Label htmlFor="storage_alert_enabled" className="text-base font-medium">Enable alerts</Label>
              <p className="text-sm text-muted-foreground">Send notifications when storage usage exceeds thresholds</p>
            </div>
            <Switch
              id="storage_alert_enabled"
              checked={watch("storage_alert_enabled") ?? false}
              onCheckedChange={(checked) => setValue("storage_alert_enabled", checked, { shouldDirty: true })}
            />
          </div>
          {(watch("storage_alert_enabled") ?? false) && (
            <>
              {health && (
                <div className="rounded-lg border p-3 bg-muted/30">
                  <div className="text-sm font-medium text-muted-foreground mb-1">Current usage</div>
                  <div className="flex items-center gap-2">
                    <div className="flex-1 h-2 bg-muted rounded-full overflow-hidden">
                      <div
                        className={`h-full transition-all ${
                          (health.disk_used_percent ?? 0) >= (watch("storage_alert_critical") ?? 95)
                            ? "bg-destructive"
                            : (health.disk_used_percent ?? 0) >= (watch("storage_alert_threshold") ?? 80)
                              ? "bg-amber-500"
                              : "bg-primary"
                        }`}
                        style={{ width: `${Math.min(100, health.disk_used_percent ?? 0)}%` }}
                      />
                    </div>
                    <span className="text-sm font-medium">{health.disk_used_percent ?? 0}%</span>
                  </div>
                </div>
              )}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="storage_alert_threshold">Warning threshold (%)</Label>
                  <Input
                    id="storage_alert_threshold"
                    type="number"
                    min={1}
                    max={100}
                    {...register("storage_alert_threshold", { valueAsNumber: true })}
                  />
                  <p className="text-xs text-muted-foreground">Alert when usage exceeds this percentage</p>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="storage_alert_critical">Critical threshold (%)</Label>
                  <Input
                    id="storage_alert_critical"
                    type="number"
                    min={1}
                    max={100}
                    {...register("storage_alert_critical", { valueAsNumber: true })}
                  />
                  <p className="text-xs text-muted-foreground">Critical alert when usage exceeds this</p>
                </div>
              </div>
              <div className="flex items-center justify-between rounded-lg border p-4">
                <div>
                  <Label htmlFor="storage_alert_email" className="text-base font-medium">Email notifications</Label>
                  <p className="text-sm text-muted-foreground">Send storage alerts via email in addition to in-app</p>
                </div>
                <Switch
                  id="storage_alert_email"
                  checked={watch("storage_alert_email") ?? true}
                  onCheckedChange={(checked) => setValue("storage_alert_email", checked, { shouldDirty: true })}
                />
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Storage Configuration</CardTitle>
          <CardDescription>
            Configure storage driver and upload policies
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="driver">Storage Driver</Label>
            <Select
              value={driver}
              onValueChange={(value) => {
                setValue("driver", value as StorageForm["driver"], { shouldDirty: true });
                setTestStatus("idle");
                setTestError(null);
              }}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select driver" />
              </SelectTrigger>
              <SelectContent>
                {STORAGE_PROVIDERS.map((p) => (
                  <SelectItem key={p.id} value={p.id}>
                    <span className="flex items-center gap-2">
                      <ProviderIcon provider={p.id === "local" ? "key" : p.id === "gcs" ? "gdrive" : p.id === "s3" ? "s3" : p.id} size="sm" />
                      {p.label}
                    </span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {driver === "s3" && (
            <>
              <Separator />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="s3_bucket">S3 Bucket</Label>
                  <Input id="s3_bucket" {...register("s3_bucket")} placeholder="my-bucket" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="s3_region">Region</Label>
                  <Input id="s3_region" {...register("s3_region")} placeholder="us-east-1" />
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="s3_key">Access Key ID</Label>
                  <Input id="s3_key" {...register("s3_key")} placeholder="Your AWS access key" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="s3_secret">Secret Access Key</Label>
                  <Input id="s3_secret" type="password" {...register("s3_secret")} placeholder="Your AWS secret key" />
                </div>
              </div>
            </>
          )}

          {driver === "gcs" && (
            <>
              <Separator />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="gcs_bucket">GCS Bucket</Label>
                  <Input id="gcs_bucket" {...register("gcs_bucket")} placeholder="my-bucket" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="gcs_project_id">Project ID</Label>
                  <Input id="gcs_project_id" {...register("gcs_project_id")} placeholder="my-project" />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="gcs_credentials_json">Service Account JSON</Label>
                <Input
                  id="gcs_credentials_json"
                  type="password"
                  {...register("gcs_credentials_json")}
                  placeholder="Paste service account JSON (stored securely)"
                />
                <p className="text-sm text-muted-foreground">
                  Paste the contents of your GCS service account key JSON file.
                </p>
              </div>
            </>
          )}

          {driver === "azure" && (
            <>
              <Separator />
              <div className="space-y-2">
                <Label htmlFor="azure_connection_string">Connection String</Label>
                <Input
                  id="azure_connection_string"
                  type="password"
                  {...register("azure_connection_string")}
                  placeholder="DefaultEndpointsProtocol=https;AccountName=...;AccountKey=..."
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="azure_container">Container Name</Label>
                <Input id="azure_container" {...register("azure_container")} placeholder="my-container" />
              </div>
            </>
          )}

          {driver === "do_spaces" && (
            <>
              <Separator />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="do_spaces_bucket">Spaces Bucket</Label>
                  <Input id="do_spaces_bucket" {...register("do_spaces_bucket")} placeholder="my-space" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="do_spaces_region">Region</Label>
                  <Input id="do_spaces_region" {...register("do_spaces_region")} placeholder="nyc3" />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="do_spaces_endpoint">Endpoint (optional)</Label>
                <Input
                  id="do_spaces_endpoint"
                  {...register("do_spaces_endpoint")}
                  placeholder="https://nyc3.digitaloceanspaces.com"
                />
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="do_spaces_key">Spaces Access Key</Label>
                  <Input id="do_spaces_key" {...register("do_spaces_key")} placeholder="Access Key" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="do_spaces_secret">Spaces Secret Key</Label>
                  <Input id="do_spaces_secret" type="password" {...register("do_spaces_secret")} placeholder="Secret Key" />
                </div>
              </div>
            </>
          )}

          {driver === "minio" && (
            <>
              <Separator />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="minio_bucket">MinIO Bucket</Label>
                  <Input id="minio_bucket" {...register("minio_bucket")} placeholder="my-bucket" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="minio_endpoint">Endpoint URL</Label>
                  <Input id="minio_endpoint" {...register("minio_endpoint")} placeholder="http://localhost:9000" />
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="minio_key">Access Key</Label>
                  <Input id="minio_key" {...register("minio_key")} placeholder="Access Key" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="minio_secret">Secret Key</Label>
                  <Input id="minio_secret" type="password" {...register("minio_secret")} placeholder="Secret Key" />
                </div>
              </div>
            </>
          )}

          {driver === "b2" && (
            <>
              <Separator />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="b2_bucket">B2 Bucket Name</Label>
                  <Input id="b2_bucket" {...register("b2_bucket")} placeholder="my-bucket" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="b2_region">Region</Label>
                  <Input id="b2_region" {...register("b2_region")} placeholder="us-west-002" />
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="b2_key_id">Application Key ID</Label>
                  <Input id="b2_key_id" {...register("b2_key_id")} placeholder="Key ID" />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="b2_application_key">Application Key</Label>
                  <Input id="b2_application_key" type="password" {...register("b2_application_key")} placeholder="Application Key" />
                </div>
              </div>
            </>
          )}

          <Separator />

          <div className="space-y-2">
            <Label htmlFor="max_upload_size">Max Upload Size (bytes)</Label>
            <Input
              id="max_upload_size"
              type="number"
              {...register("max_upload_size", { valueAsNumber: true })}
              placeholder="10485760"
            />
            <p className="text-sm text-muted-foreground">
              Maximum file size in bytes (e.g., 10485760 = 10MB)
            </p>
            {errors.max_upload_size && (
              <p className="text-sm text-destructive">
                {errors.max_upload_size.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="allowed_file_types">Allowed File Types</Label>
            <Input
              id="allowed_file_types"
              value={fileTypesInput}
              onChange={(e) => setFileTypesInput(e.target.value)}
              placeholder="jpg, png, pdf, doc, docx (comma-separated)"
            />
            <p className="text-sm text-muted-foreground">
              Comma-separated list of allowed file extensions
            </p>
          </div>
          {testStatus === "error" && testError && (
            <div className="flex items-center gap-2 text-sm text-destructive">
              <XCircle className="h-4 w-4 shrink-0" />
              <span>{testError}</span>
            </div>
          )}
          {testStatus === "success" && (
            <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-500">
              <CheckCircle2 className="h-4 w-4 shrink-0" />
              <span>Connection successful</span>
            </div>
          )}
        </CardContent>
        <CardFooter className="flex flex-wrap items-center justify-end gap-2">
          {driver !== "local" && (
            <Button
              type="button"
              variant="outline"
              onClick={onTestConnection}
              disabled={testStatus === "loading"}
            >
              {testStatus === "loading" && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Test Connection
            </Button>
          )}
          <SaveButton isDirty={isDirty} isSaving={isSaving} />
        </CardFooter>
      </Card>
    </form>
  );
}
