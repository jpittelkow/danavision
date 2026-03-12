"use client";

import { HexColorPicker, HexColorInput } from "react-colorful";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";

interface ColorPickerProps {
  value: string;
  onChange: (color: string) => void;
  label?: string;
}

export function ColorPicker({ value, onChange, label }: ColorPickerProps) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" className="w-full justify-start gap-2">
          <div 
            className="h-4 w-4 rounded border" 
            style={{ backgroundColor: value || "#3b82f6" }} 
          />
          <span>{value || "Select color"}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-3">
        <HexColorPicker color={value || "#3b82f6"} onChange={onChange} />
        <HexColorInput 
          color={value || "#3b82f6"} 
          onChange={onChange}
          prefixed
          className="mt-2 w-full px-2 py-1 border rounded text-sm"
        />
      </PopoverContent>
    </Popover>
  );
}
