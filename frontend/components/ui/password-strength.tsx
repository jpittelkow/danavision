"use client";

import * as React from "react";
import { Check } from "lucide-react";
import { cn } from "@/lib/utils";

export type PasswordStrengthLevel = "none" | "weak" | "medium" | "strong" | "very-strong";

const REQUIREMENTS = [
  { key: "length", label: "At least 8 characters", test: (p: string) => p.length >= 8 },
  { key: "lowercase", label: "One lowercase letter", test: (p: string) => /[a-z]/.test(p) },
  { key: "uppercase", label: "One uppercase letter", test: (p: string) => /[A-Z]/.test(p) },
  { key: "number", label: "One number", test: (p: string) => /\d/.test(p) },
  { key: "special", label: "One special character", test: (p: string) => /[^A-Za-z0-9]/.test(p) },
] as const;

function getStrengthLevel(password: string): PasswordStrengthLevel {
  if (!password) return "none";
  const met = REQUIREMENTS.filter((r) => r.test(password)).length;
  if (met < 2) return "weak";
  if (met < 4) return "medium";
  if (met < 5) return "strong";
  return "very-strong";
}

function getStrengthWidth(level: PasswordStrengthLevel): number {
  switch (level) {
    case "none":
      return 0;
    case "weak":
      return 25;
    case "medium":
      return 50;
    case "strong":
      return 75;
    case "very-strong":
      return 100;
    default:
      return 0;
  }
}

function getStrengthBarClasses(level: PasswordStrengthLevel): string {
  switch (level) {
    case "weak":
      return "bg-red-500 dark:bg-red-600";
    case "medium":
      return "bg-yellow-500 dark:bg-yellow-600";
    case "strong":
    case "very-strong":
      return "bg-green-500 dark:bg-green-600";
    default:
      return "bg-muted";
  }
}

export interface PasswordStrengthProps {
  /** Current password value to evaluate. */
  password: string;
  /** Whether to show the requirements checklist. Defaults to true. */
  showRequirements?: boolean;
  className?: string;
}

/**
 * Displays password strength as a progress bar and optional requirements checklist.
 */
function PasswordStrength({
  password,
  showRequirements = true,
  className,
}: PasswordStrengthProps) {
  const level = React.useMemo(() => getStrengthLevel(password), [password]);
  const width = getStrengthWidth(level);
  const barClasses = getStrengthBarClasses(level);

  const requirementsMet = React.useMemo(
    () => REQUIREMENTS.map((r) => ({ ...r, met: r.test(password) })),
    [password]
  );

  if (!password) return null;

  return (
    <div className={cn("space-y-2", className)} role="status" aria-live="polite">
      <div className="flex gap-2 items-center">
        <div
          className="h-1.5 flex-1 rounded-full bg-muted overflow-hidden"
          aria-hidden
        >
          <div
            className={cn("h-full rounded-full transition-all duration-300", barClasses)}
            style={{ width: `${width}%` }}
          />
        </div>
        <span className="text-xs text-muted-foreground capitalize sr-only">
          Strength: {level === "none" ? "none" : level.replace("-", " ")}
        </span>
      </div>
      {showRequirements && (
        <ul className="space-y-1 text-xs text-muted-foreground">
          {requirementsMet.map(({ key, label, met }) => (
            <li key={key} className="flex items-center gap-2">
              {met ? (
                <Check className="h-3.5 w-3.5 text-green-600 dark:text-green-500 shrink-0" aria-hidden />
              ) : (
                <span className="h-3.5 w-3.5 rounded-full border border-current shrink-0" aria-hidden />
              )}
              <span className={met ? "text-green-700 dark:text-green-400" : ""}>{label}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export { PasswordStrength };
