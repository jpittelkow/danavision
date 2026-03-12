import Link from "next/link";
import { Button } from "@/components/ui/button";
import { ExternalLink } from "lucide-react";
import { cn } from "@/lib/utils";

interface DashboardSectionProps {
  /** Section heading */
  title: string;
  /** Optional description below the heading */
  description?: string;
  /** Optional "View all" or action link */
  actionHref?: string;
  /** Label for the action link (defaults to "View all") */
  actionLabel?: string;
  /** Grid columns: "1" | "2" | "3" | "4" — controls responsive grid layout */
  columns?: "1" | "2" | "3" | "4";
  /** Widget content */
  children: React.ReactNode;
  /** Additional className for the grid container */
  className?: string;
}

const columnClasses: Record<string, string> = {
  "1": "grid grid-cols-1 gap-4",
  "2": "grid grid-cols-1 md:grid-cols-2 gap-4",
  "3": "grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4",
  "4": "grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4",
};

export function DashboardSection({
  title,
  description,
  actionHref,
  actionLabel = "View all",
  columns = "3",
  children,
  className,
}: DashboardSectionProps) {
  return (
    <section>
      <div className="flex items-center justify-between mb-3">
        <div>
          <h2 className="font-heading text-lg font-semibold">{title}</h2>
          {description && (
            <p className="text-sm text-muted-foreground mt-0.5">
              {description}
            </p>
          )}
        </div>
        {actionHref && (
          <Button variant="ghost" size="sm" className="text-xs" asChild>
            <Link href={actionHref}>
              {actionLabel}
              <ExternalLink className="ml-1.5 h-3 w-3" />
            </Link>
          </Button>
        )}
      </div>
      <div className={cn(columnClasses[columns], className)}>{children}</div>
    </section>
  );
}
