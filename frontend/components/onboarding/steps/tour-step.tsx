"use client";

import { Map, LayoutDashboard, Settings, User, Bell } from "lucide-react";
import {
  WizardStep,
  WizardStepTitle,
  WizardStepDescription,
  WizardStepContent,
} from "@/components/onboarding/wizard-step";
import { isAdminUser, useAuth } from "@/lib/auth";

export function TourStep() {
  const { user } = useAuth();
  const isAdmin = user ? isAdminUser(user) : false;

  const items = [
    {
      icon: LayoutDashboard,
      title: "Dashboard",
      description: "Your home base with quick access to everything",
    },
    {
      icon: User,
      title: "Profile",
      description: "Manage your account and personal settings",
    },
    {
      icon: Bell,
      title: "Notifications",
      description: "Stay updated with important alerts",
    },
    ...(isAdmin
      ? [
          {
            icon: Settings,
            title: "Configuration",
            description: "System settings and administration",
          },
        ]
      : []),
  ];

  return (
    <WizardStep>
      <div className="h-16 w-16 rounded-full bg-primary/10 flex items-center justify-center">
        <Map className="h-8 w-8 text-primary" />
      </div>

      <WizardStepTitle>Quick tour</WizardStepTitle>

      <WizardStepDescription>
        Here&apos;s where to find the key features of the app.
      </WizardStepDescription>

      <WizardStepContent>
        <div className="space-y-2">
          {items.map(({ icon: Icon, title, description }) => (
            <div
              key={title}
              className="flex items-start gap-3 p-3 rounded-lg border bg-card text-left"
            >
              <div className="h-8 w-8 rounded-md bg-muted flex items-center justify-center shrink-0">
                <Icon className="h-4 w-4 text-muted-foreground" />
              </div>
              <div>
                <p className="text-sm font-medium">{title}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
              </div>
            </div>
          ))}
        </div>
      </WizardStepContent>
    </WizardStep>
  );
}
