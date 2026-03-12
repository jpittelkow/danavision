"use client";

import { useState, useEffect, useCallback } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { getErrorMessage } from "@/lib/utils";
import {
  useStripeSettings,
  resetStripe,
  type StripeSettings,
} from "@/lib/stripe";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { CollapsibleCard } from "@/components/ui/collapsible-card";
import { FormField } from "@/components/ui/form-field";
import { SettingsPageSkeleton } from "@/components/ui/settings-page-skeleton";
import { SaveButton } from "@/components/ui/save-button";
import { HelpLink } from "@/components/help/help-link";
import {
  Settings2,
  Loader2,
  CheckCircle2,
  XCircle,
} from "lucide-react";

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

const stripeSchema = z.object({
  enabled: z.boolean(),
  mode: z.enum(["test", "live"]),
  secret_key: z.string().optional(),
  publishable_key: z.string().optional(),
  webhook_secret: z.string().optional(),
  currency: z
    .string()
    .optional()
    .refine((v) => !v || v.length === 3, {
      message: "Currency must be a 3-letter code (e.g. usd)",
    }),
});

type StripeForm = z.infer<typeof stripeSchema>;

// ---------------------------------------------------------------------------
// Settings View
// ---------------------------------------------------------------------------

function StripeSettingsView({
  settings,
  refetch,
}: {
  settings: StripeSettings;
  refetch: () => void;
}) {
  const [isSaving, setIsSaving] = useState(false);
  const [testStatus, setTestStatus] = useState<
    "idle" | "loading" | "success" | "error"
  >("idle");
  const [testError, setTestError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isDirty },
    setValue,
    watch,
    reset,
  } = useForm<StripeForm>({
    resolver: zodResolver(stripeSchema),
    mode: "onBlur",
    defaultValues: {
      enabled: false,
      mode: "test",
      secret_key: "",
      publishable_key: "",
      webhook_secret: "",
      currency: "usd",
    },
  });

  // Populate form when settings load
  useEffect(() => {
    if (settings) {
      reset({
        enabled: !!settings.enabled,
        mode: (settings.mode as "test" | "live") || "test",
        secret_key: settings.secret_key || "",
        publishable_key: settings.publishable_key || "",
        webhook_secret: settings.webhook_secret || "",
        currency: settings.currency || "usd",
      });
    }
  }, [settings, reset]);

  // Save settings
  const onSave = useCallback(
    async (data: StripeForm) => {
      setIsSaving(true);
      try {
        await api.put("/stripe/settings", data);
        resetStripe();
        toast.success("Stripe settings saved");
        refetch();
      } catch (err: unknown) {
        toast.error(getErrorMessage(err, "Failed to save Stripe settings"));
      } finally {
        setIsSaving(false);
      }
    },
    [refetch]
  );

  // Test connection
  const onTestConnection = async () => {
    setTestStatus("loading");
    setTestError(null);
    try {
      const res = await api.post("/stripe/settings/test");
      if (res.data?.account_id) {
        setTestStatus("success");
        toast.success("Connection successful");
      } else {
        setTestStatus("error");
        setTestError("Unexpected response from Stripe");
      }
    } catch (err: unknown) {
      setTestStatus("error");
      const msg = getErrorMessage(err, "Connection test failed");
      setTestError(msg);
      toast.error(msg);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
          Stripe
        </h1>
        <p className="text-muted-foreground mt-1">
          Configure Stripe payment processing.{" "}
          <HelpLink articleId="stripe-configuration" />
        </p>
      </div>

      <form onSubmit={handleSubmit(onSave)} className="space-y-6">
        <CollapsibleCard
          title="API Keys"
          description="Stripe API keys and payment configuration"
          icon={<Settings2 className="h-4 w-4" />}
          defaultOpen
        >
          <div className="space-y-6">
            {/* Mode */}
            <FormField
              id="mode"
              label="Mode"
              description="Use test mode for development, live mode for production"
              error={errors.mode?.message}
            >
              <Select
                value={watch("mode")}
                onValueChange={(v) =>
                  setValue("mode", v as "test" | "live", { shouldDirty: true })
                }
              >
                <SelectTrigger className="min-h-[44px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="test">Test</SelectItem>
                  <SelectItem value="live">Live</SelectItem>
                </SelectContent>
              </Select>
            </FormField>

            <div className="grid gap-4 md:grid-cols-2">
              <FormField
                id="secret_key"
                label="Secret Key"
                error={errors.secret_key?.message}
              >
                <Input
                  id="secret_key"
                  type="password"
                  placeholder="sk_test_..."
                  {...register("secret_key")}
                  className="min-h-[44px]"
                />
              </FormField>
              <FormField
                id="publishable_key"
                label="Publishable Key"
                error={errors.publishable_key?.message}
              >
                <Input
                  id="publishable_key"
                  placeholder="pk_test_..."
                  {...register("publishable_key")}
                  className="min-h-[44px]"
                />
              </FormField>
            </div>

            <FormField
              id="webhook_secret"
              label="Webhook Secret"
              description="Used to verify incoming Stripe webhook events"
              error={errors.webhook_secret?.message}
            >
              <Input
                id="webhook_secret"
                type="password"
                placeholder="whsec_..."
                {...register("webhook_secret")}
                className="min-h-[44px]"
              />
            </FormField>

            <FormField
              id="currency"
              label="Currency"
              description="3-letter ISO currency code"
              error={errors.currency?.message}
            >
              <Input
                id="currency"
                placeholder="usd"
                maxLength={3}
                {...register("currency")}
                className="min-h-[44px]"
              />
            </FormField>

            {/* Test Connection result */}
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

            {/* Actions */}
            <div className="flex flex-wrap items-center justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={onTestConnection}
                disabled={testStatus === "loading"}
              >
                {testStatus === "loading" && (
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                )}
                Test Connection
              </Button>
              <SaveButton isDirty={isDirty} isSaving={isSaving} />
            </div>
          </div>
        </CollapsibleCard>
      </form>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function StripeSettingsPage() {
  const { settings, isLoading, refetch } = useStripeSettings();

  if (isLoading) {
    return <SettingsPageSkeleton />;
  }

  return <StripeSettingsView settings={settings} refetch={refetch} />;
}
