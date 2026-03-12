"use client";

import { HelpCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { useHelp } from "@/components/help/help-provider";

interface HelpIconProps {
  className?: string;
}

export function HelpIcon({ className }: HelpIconProps) {
  const { setIsOpen } = useHelp();

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className={className}
          onClick={() => setIsOpen(true)}
          aria-label="Open help center"
        >
          <HelpCircle className="h-5 w-5" />
        </Button>
      </TooltipTrigger>
      <TooltipContent>
        <p>Help (Press ?)</p>
      </TooltipContent>
    </Tooltip>
  );
}
