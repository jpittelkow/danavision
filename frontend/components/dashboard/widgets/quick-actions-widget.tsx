"use client";

import Link from "next/link";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Sparkles, TrendingDown } from "lucide-react";

const actions = [
  { label: "Smart Add", href: "/smart-add", icon: Sparkles },
  { label: "Top Deals", href: "/search", icon: TrendingDown },
];

export function QuickActionsWidget() {
  return (
    <Card
      className="animate-in fade-in slide-in-from-bottom-2"
      style={{ animationDelay: "150ms", animationFillMode: "backwards" }}
    >
      <CardHeader className="pb-2">
        <CardTitle className="text-sm font-medium">Quick Actions</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-2 gap-2">
          {actions.map((action) => (
            <Link
              key={action.href}
              href={action.href}
              className="flex flex-col items-center gap-2 rounded-lg border p-3 text-center transition-colors hover:bg-muted min-h-[72px] justify-center"
            >
              <action.icon className="h-5 w-5 text-muted-foreground" />
              <span className="text-xs font-medium">{action.label}</span>
            </Link>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
