import { Loader2 } from "lucide-react";

interface SettingsPageSkeletonProps {
  minHeight?: string;
}

export function SettingsPageSkeleton({ minHeight = "400px" }: SettingsPageSkeletonProps) {
  return (
    <div className="flex items-center justify-center" style={{ minHeight }}>
      <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
    </div>
  );
}
