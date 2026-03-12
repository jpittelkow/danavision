"use client";

import { cn } from "@/lib/utils";
import { Logo } from "@/components/logo";
import { usePageTitle } from "@/lib/use-page-title";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

interface AuthPageLayoutProps {
  title: string;
  description?: string;
  children: React.ReactNode;
  className?: string;
}

export function AuthPageLayout({
  title,
  description,
  children,
  className,
}: AuthPageLayoutProps) {
  // Set page title with app name from config
  usePageTitle(title);

  return (
    <div className="flex min-h-svh bg-muted">
      {/* Decorative left panel — desktop only */}
      <div className="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-primary/20 via-primary/10 to-background items-center justify-center border-r">
        <div className="max-w-md text-center space-y-4 px-8">
          <Logo variant="full" size="lg" />
        </div>
      </div>

      {/* Form panel */}
      <div className="flex flex-1 flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div className="lg:hidden flex items-center gap-2 self-center">
          <Logo variant="full" size="md" />
        </div>
        <div className={cn("flex w-full max-w-sm flex-col gap-6", className)}>
          <Card className="animate-in fade-in slide-in-from-bottom-4 duration-500">
            <CardHeader className="text-center">
              <CardTitle className="text-xl">{title}</CardTitle>
              {description && (
                <CardDescription>{description}</CardDescription>
              )}
            </CardHeader>
            <CardContent className="space-y-6">
              {children}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
