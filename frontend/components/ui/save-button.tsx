import { Loader2 } from "lucide-react";
import { Button, ButtonProps } from "@/components/ui/button";

interface SaveButtonProps extends Omit<ButtonProps, "type"> {
  isDirty: boolean;
  isSaving: boolean;
  /** Defaults to "submit". Pass "button" for onClick-only save buttons outside forms. */
  type?: "submit" | "button";
  children?: React.ReactNode;
}

export function SaveButton({ 
  isDirty, 
  isSaving, 
  type = "submit",
  children = "Save Changes",
  disabled,
  ...props 
}: SaveButtonProps) {
  return (
    <Button 
      type={type} 
      disabled={!isDirty || isSaving || disabled} 
      {...props}
    >
      {isSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
      {children}
    </Button>
  );
}
