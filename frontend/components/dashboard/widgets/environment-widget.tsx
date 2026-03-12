"use client";

import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Server } from "lucide-react";

interface EnvironmentResponse {
  environment: string;
  php_version: string;
  laravel_version: string;
  database: string;
}

export function EnvironmentWidget() {
  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", "environment"],
    queryFn: async (): Promise<EnvironmentResponse> => {
      const res = await api.get<EnvironmentResponse>("/dashboard/environment");
      return res.data;
    },
  });

  const envBadge = data?.environment === "production"
    ? { label: "Live", variant: "default" as const }
    : data?.environment === "local"
      ? { label: "Dev", variant: "secondary" as const }
      : { label: data?.environment ?? "", variant: "outline" as const };

  const info = data
    ? [
        {
          label: "Environment",
          value: data.environment.charAt(0).toUpperCase() + data.environment.slice(1),
          badge: envBadge,
        },
        { label: "PHP Version", value: data.php_version },
        { label: "Laravel", value: data.laravel_version },
        { label: "Database", value: data.database.charAt(0).toUpperCase() + data.database.slice(1) },
      ]
    : [];

  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: "300ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-3">
        <div className="flex items-center gap-2">
          <Server className="h-4 w-4 text-muted-foreground" />
          <CardTitle className="text-sm font-medium">Environment</CardTitle>
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        {isLoading ? (
          <div className="space-y-2">
            {[1, 2, 3, 4].map((i) => (
              <Skeleton key={i} className="h-5 w-full" />
            ))}
          </div>
        ) : (
          <div className="space-y-2">
            {info.map((row) => (
              <div
                key={row.label}
                className="flex items-center justify-between text-sm"
              >
                <span className="text-muted-foreground">{row.label}</span>
                <div className="flex items-center gap-2">
                  <span className="font-medium">{row.value}</span>
                  {row.badge && (
                    <Badge
                      variant={row.badge.variant}
                      className="text-[10px] h-5"
                    >
                      {row.badge.label}
                    </Badge>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
