"use client";

import { Sparkles } from "lucide-react";
import Image from "next/image";
import { useAppConfig } from "@/lib/app-config";
import {
  WizardStep,
  WizardStepTitle,
  WizardStepDescription,
} from "@/components/onboarding/wizard-step";

export function WelcomeStep() {
  const { appName, logoUrl } = useAppConfig();

  return (
    <WizardStep>
      {logoUrl ? (
        <div className="relative h-16 w-32">
          <Image
            src={logoUrl}
            alt={appName || "App Logo"}
            fill
            className="object-contain"
            unoptimized
          />
        </div>
      ) : (
        <div className="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
          <Sparkles className="h-8 w-8 text-primary" />
        </div>
      )}

      <WizardStepTitle>
        Welcome to {appName || "your new app"}!
      </WizardStepTitle>

      <WizardStepDescription>
        Let&apos;s take a quick tour to help you get started. This will only
        take a minute, and you can skip at any time.
      </WizardStepDescription>
    </WizardStep>
  );
}
