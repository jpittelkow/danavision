import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { cn } from "@/lib/utils";
import type { LucideIcon } from "lucide-react";

export type AuditStatsCardVariant = "default" | "warning" | "error" | "success" | "info";

const variantStyles: Record<
  AuditStatsCardVariant,
  { card: string; icon: string }
> = {
  default: {
    card: "border-border bg-card",
    icon: "bg-primary/10 text-primary",
  },
  warning: {
    card: "border-amber-500/30 bg-amber-500/5",
    icon: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
  },
  error: {
    card: "border-red-500/30 bg-red-500/5",
    icon: "bg-red-500/10 text-red-600 dark:text-red-400",
  },
  success: {
    card: "border-green-500/30 bg-green-500/5",
    icon: "bg-green-500/10 text-green-600 dark:text-green-400",
  },
  info: {
    card: "border-blue-500/30 bg-blue-500/5",
    icon: "bg-blue-500/10 text-blue-600 dark:text-blue-400",
  },
};

interface AuditStatsCardProps {
  title: string;
  value: string | number;
  description?: string;
  icon: LucideIcon;
  variant?: AuditStatsCardVariant;
  className?: string;
}

export function AuditStatsCard({
  title,
  value,
  description,
  icon: Icon,
  variant = "default",
  className,
}: AuditStatsCardProps) {
  const styles = variantStyles[variant];
  return (
    <Card
      className={cn(
        "transition-colors",
        styles.card,
        className
      )}
    >
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2 p-4 md:p-6 md:pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <div className={cn("p-2 rounded-lg", styles.icon)}>
          <Icon className="h-4 w-4" />
        </div>
      </CardHeader>
      <CardContent className="p-4 pt-0 md:p-6 md:pt-0">
        <div className="text-2xl font-bold tabular-nums">{value}</div>
        {description && (
          <CardDescription className="mt-1">{description}</CardDescription>
        )}
      </CardContent>
    </Card>
  );
}
