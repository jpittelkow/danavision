"use client";

import { Shield, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  WizardStep,
  WizardStepTitle,
  WizardStepDescription,
  WizardStepContent,
} from "@/components/onboarding/wizard-step";
import { useAppConfig } from "@/lib/app-config";
import Link from "next/link";

export function SecurityStep() {
  const { features } = useAppConfig();
  const twoFactorMode = features?.twoFactorMode || "optional";
  const passkeyMode = features?.passkeyMode || "disabled";

  const show2FA = twoFactorMode !== "disabled";
  const showPasskeys = passkeyMode !== "disabled";

  return (
    <WizardStep>
      <div className="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
        <Shield className="h-8 w-8 text-primary" />
      </div>

      <WizardStepTitle>Secure your account</WizardStepTitle>

      <WizardStepDescription>
        Add an extra layer of protection to keep your account safe.
      </WizardStepDescription>

      <WizardStepContent>
        <div className="space-y-3">
          {show2FA && (
            <div className="p-4 rounded-lg border bg-card">
              <div className="flex items-start gap-3">
                <Check className="h-5 w-5 text-muted-foreground mt-0.5" />
                <div className="text-left">
                  <p className="text-sm font-medium">
                    Two-Factor Authentication
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Use an authenticator app for extra security
                  </p>
                </div>
              </div>
            </div>
          )}

          {showPasskeys && (
            <div className="p-4 rounded-lg border bg-card">
              <div className="flex items-start gap-3">
                <Check className="h-5 w-5 text-muted-foreground mt-0.5" />
                <div className="text-left">
                  <p className="text-sm font-medium">Passkeys</p>
                  <p className="text-xs text-muted-foreground">
                    Sign in with fingerprint or face recognition
                  </p>
                </div>
              </div>
            </div>
          )}

          <Button asChild variant="outline" className="w-full">
            <Link href="/user/security">Set Up Security</Link>
          </Button>

          <p className="text-xs text-muted-foreground">
            You can always set this up later in your security settings.
          </p>
        </div>
      </WizardStepContent>
    </WizardStep>
  );
}
