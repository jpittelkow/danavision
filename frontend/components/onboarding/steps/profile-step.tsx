"use client";

import { User } from "lucide-react";
import Image from "next/image";
import { useAuth } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import {
  WizardStep,
  WizardStepTitle,
  WizardStepDescription,
  WizardStepContent,
} from "@/components/onboarding/wizard-step";
import Link from "next/link";

export function ProfileStep() {
  const { user } = useAuth();

  return (
    <WizardStep>
      <div className="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
        {user?.avatar ? (
          <Image
            src={user.avatar}
            alt={user.name || "User avatar"}
            width={64}
            height={64}
            className="rounded-full object-cover"
            unoptimized
          />
        ) : (
          <User className="h-8 w-8 text-primary" />
        )}
      </div>

      <WizardStepTitle>Set up your profile</WizardStepTitle>

      <WizardStepDescription>
        Add a profile picture and update your display name to personalize your
        account.
      </WizardStepDescription>

      <WizardStepContent>
        <div className="space-y-3">
          <div className="p-4 rounded-lg bg-muted/50">
            <p className="text-sm font-medium">{user?.name || "Your Name"}</p>
            <p className="text-xs text-muted-foreground">{user?.email}</p>
          </div>

          <Button asChild variant="outline" className="w-full">
            <Link href="/user/profile">Edit Profile</Link>
          </Button>
        </div>
      </WizardStepContent>
    </WizardStep>
  );
}
