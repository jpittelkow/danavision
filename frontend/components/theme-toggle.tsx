"use client";

import { Moon, Sun, Monitor } from "lucide-react";
import { useTheme } from "@/components/theme-provider";
import { useAuth } from "@/lib/auth";
import { api } from "@/lib/api";
import { Button } from "@/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";

export function ThemeToggle() {
  const { theme, setTheme } = useTheme();
  const { user } = useAuth();

  const handleThemeChange = (newTheme: "light" | "dark" | "system") => {
    setTheme(newTheme);
    if (user) {
      api.put("/user/settings", { theme: newTheme }).catch(() => {
        // Prefer localStorage; API sync is best-effort for cross-device
      });
    }
  };

  const modes: { value: "light" | "dark" | "system"; icon: typeof Sun; label: string }[] = [
    { value: "light", icon: Sun, label: "Light" },
    { value: "dark", icon: Moon, label: "Dark" },
    { value: "system", icon: Monitor, label: "System" },
  ];

  return (
    <div
      role="group"
      aria-label="Theme"
      className="flex items-center gap-0.5 rounded-md border border-transparent p-0.5"
    >
      {modes.map(({ value, icon: Icon, label }) => (
        <Tooltip key={value}>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className={cn(
                "h-8 w-8",
                theme === value && "bg-muted text-foreground hover:bg-muted hover:text-foreground"
              )}
              onClick={() => handleThemeChange(value)}
              aria-label={`${label} theme`}
              aria-pressed={theme === value}
            >
              <Icon className="h-3.5 w-3.5" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>{label}</p>
          </TooltipContent>
        </Tooltip>
      ))}
    </div>
  );
}
