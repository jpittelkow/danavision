"use client";

import Link from "next/link";
import { ShoppingCart, Clock, TrendingDown, Users } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { formatDistanceToNow } from "date-fns";

export interface ListCardProps {
  list: {
    id: number;
    name: string;
    description?: string;
    items_count?: number;
    price_drops_count?: number;
    last_refreshed_at?: string;
    is_shared?: boolean;
  };
}

export function ListCard({ list }: ListCardProps) {
  const hasDrops = (list.price_drops_count ?? 0) > 0;

  return (
    <Link href={`/lists/${list.id}`}>
      <Card className="h-full transition-colors hover:bg-muted/50 cursor-pointer">
        <CardHeader className="pb-3">
          <div className="flex items-start justify-between gap-2">
            <CardTitle className="text-base line-clamp-1">{list.name}</CardTitle>
            <div className="flex items-center gap-1.5 shrink-0">
              {list.is_shared && (
                <Badge variant="secondary" className="gap-1">
                  <Users className="h-3 w-3" />
                  Shared
                </Badge>
              )}
              {hasDrops && (
                <Badge variant="success" className="gap-1">
                  <TrendingDown className="h-3 w-3" />
                  {list.price_drops_count}
                </Badge>
              )}
            </div>
          </div>
          {list.description && (
            <p className="text-sm text-muted-foreground line-clamp-2">
              {list.description}
            </p>
          )}
        </CardHeader>
        <CardContent className="pt-0">
          <div className="flex items-center gap-4 text-sm text-muted-foreground">
            <span className="inline-flex items-center gap-1.5">
              <ShoppingCart className="h-3.5 w-3.5" />
              {list.items_count ?? 0} item{(list.items_count ?? 0) !== 1 ? "s" : ""}
            </span>
            {list.last_refreshed_at && (
              <span className="inline-flex items-center gap-1.5">
                <Clock className="h-3.5 w-3.5" />
                {formatDistanceToNow(new Date(list.last_refreshed_at), {
                  addSuffix: true,
                })}
              </span>
            )}
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}
