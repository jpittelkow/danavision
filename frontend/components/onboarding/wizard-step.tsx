import { cn } from "@/lib/utils";
import { type ReactNode } from "react";

interface WizardStepProps {
  children: ReactNode;
  className?: string;
}

export function WizardStep({ children, className }: WizardStepProps) {
  return (
    <div
      className={cn(
        "flex flex-col items-center text-center space-y-4 px-2",
        className
      )}
    >
      {children}
    </div>
  );
}

interface WizardStepTitleProps {
  children: ReactNode;
  className?: string;
}

export function WizardStepTitle({ children, className }: WizardStepTitleProps) {
  return (
    <h2 className={cn("text-xl font-semibold", className)}>{children}</h2>
  );
}

interface WizardStepDescriptionProps {
  children: ReactNode;
  className?: string;
}

export function WizardStepDescription({
  children,
  className,
}: WizardStepDescriptionProps) {
  return (
    <p className={cn("text-sm text-muted-foreground max-w-md", className)}>
      {children}
    </p>
  );
}

interface WizardStepContentProps {
  children: ReactNode;
  className?: string;
}

export function WizardStepContent({
  children,
  className,
}: WizardStepContentProps) {
  return (
    <div className={cn("w-full max-w-sm space-y-4 pt-4", className)}>
      {children}
    </div>
  );
}
