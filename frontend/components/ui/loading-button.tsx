import { Loader2 } from "lucide-react";
import { Button, ButtonProps } from "@/components/ui/button";

interface LoadingButtonProps extends ButtonProps {
  isLoading: boolean;
  loadingText?: string;
  children: React.ReactNode;
}

export function LoadingButton({
  isLoading,
  loadingText,
  children,
  disabled,
  ...props
}: LoadingButtonProps) {
  return (
    <Button disabled={isLoading || disabled} {...props}>
      {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
      {isLoading && loadingText ? loadingText : children}
    </Button>
  );
}
