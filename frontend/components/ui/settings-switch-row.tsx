"use client";

import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { HelpTooltip } from "@/components/ui/help-tooltip";
import { cn } from "@/lib/utils";

export interface SettingsSwitchRowProps {
  /** Label shown on the left */
  label: string;
  /** Optional description below the label */
  description?: string;
  /** Optional tooltip shown next to the label */
  tooltip?: string;
  /** Controlled checked state */
  checked: boolean;
  /** Called when the switch is toggled */
  onCheckedChange: (checked: boolean) => void;
  /** Disable the switch */
  disabled?: boolean;
  /** Additional class name for the row container */
  className?: string;
}

/**
 * A settings row with label (and optional description) on the left and a switch on the right.
 * The switch is wrapped in a 44px touch target for accessibility without stretching the switch track.
 * Use for configuration toggles (e.g. "Enable SSO", "Allow account linking").
 */
export function SettingsSwitchRow({
  label,
  description,
  tooltip,
  checked,
  onCheckedChange,
  disabled = false,
  className,
}: SettingsSwitchRowProps) {
  return (
    <div
      className={cn(
        "flex items-center justify-between gap-4 rounded-lg border p-4 min-h-[44px]",
        className
      )}
    >
      <div>
        <Label className="flex items-center gap-1.5">
          {label}
          {tooltip && <HelpTooltip content={tooltip} />}
        </Label>
        {description && (
          <p className="text-sm text-muted-foreground">{description}</p>
        )}
      </div>
      <div className="flex shrink-0 items-center justify-center min-h-[44px] min-w-[44px]">
        <Switch
          checked={checked}
          onCheckedChange={onCheckedChange}
          disabled={disabled}
        />
      </div>
    </div>
  );
}
