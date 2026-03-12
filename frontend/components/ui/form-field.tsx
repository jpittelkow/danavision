import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { ExternalLink, HelpCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { HelpTooltip } from "@/components/ui/help-tooltip";

export interface FormFieldHelpLink {
  label: string;
  url?: string;
  onClick?: () => void;
}

interface FormFieldProps {
  id: string;
  label: string | React.ReactNode;
  description?: string;
  tooltip?: string;
  helpLink?: FormFieldHelpLink;
  error?: string;
  children: React.ReactNode;
  className?: string;
}

export function FormField({
  id,
  label,
  description,
  tooltip,
  helpLink,
  error,
  children,
  className,
}: FormFieldProps) {
  return (
    <div className={cn("space-y-2", className)}>
      <div className="flex items-center gap-2">
        {typeof label === "string" ? (
          <Label htmlFor={id} className="flex items-center gap-1.5">
            {label}
            {tooltip && <HelpTooltip content={tooltip} iconClassName="h-3.5 w-3.5" />}
          </Label>
        ) : (
          <span className="flex items-center gap-1.5 w-full">
            {label}
            {tooltip && <HelpTooltip content={tooltip} iconClassName="h-3.5 w-3.5" />}
          </span>
        )}
        {helpLink && (
          helpLink.url ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="min-h-[44px] min-w-[44px] h-auto gap-1.5 px-2 text-muted-foreground hover:text-foreground"
              asChild
            >
              <a
                href={helpLink.url}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={helpLink.label}
              >
                <HelpCircle className="h-3.5 w-3.5" aria-hidden />
                <span className="text-xs">{helpLink.label}</span>
                <ExternalLink className="h-3 w-3" aria-hidden />
              </a>
            </Button>
          ) : (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="min-h-[44px] min-w-[44px] h-auto gap-1.5 px-2 text-muted-foreground hover:text-foreground"
              onClick={helpLink.onClick}
              aria-label={helpLink.label}
            >
              <HelpCircle className="h-3.5 w-3.5" aria-hidden />
              <span className="text-xs">{helpLink.label}</span>
            </Button>
          )
        )}
      </div>
      {description && (
        <p id={`${id}-description`} className="text-sm text-muted-foreground">
          {description}
        </p>
      )}
      {children}
      {error && (
        <p id={`${id}-error`} className="text-sm text-destructive" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
