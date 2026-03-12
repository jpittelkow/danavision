"use client";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Variable } from "lucide-react";

export interface VariablePickerProps {
  variables: string[];
  onSelect: (variable: string) => void;
  disabled?: boolean;
}

export function VariablePicker({
  variables,
  onSelect,
  disabled = false,
}: VariablePickerProps) {
  if (variables.length === 0) {
    return null;
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={disabled}
          className="gap-1.5"
          title="Insert variable"
        >
          <Variable className="h-4 w-4" />
          Insert variable
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="max-h-64 overflow-y-auto">
        {variables.map((variable) => (
          <DropdownMenuItem
            key={variable}
            onSelect={() => onSelect(variable)}
            className="font-mono text-sm"
          >
            {`{{${variable}}}`}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
