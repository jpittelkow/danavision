"use client";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardFooter,
} from "@/components/ui/card";
import { Check, X, Store as StoreIcon, Calendar, Link2 } from "lucide-react";
import type { ScannedDeal } from "@/lib/api/shopping";

interface DealCardProps {
  deal: ScannedDeal;
  mode: "review" | "library";
  onAccept?: (id: number) => void;
  onDismiss?: (id: number) => void;
  onClick?: (deal: ScannedDeal) => void;
  isPending?: boolean;
}

function getDiscountLabel(deal: ScannedDeal): string {
  switch (deal.discount_type) {
    case "amount_off":
      return `$${Number(deal.discount_value ?? 0).toFixed(2)} off`;
    case "percent_off":
      return `${Number(deal.discount_value ?? 0).toFixed(0)}% off`;
    case "fixed_price":
      return `$${Number(deal.sale_price ?? 0).toFixed(2)}`;
    case "bogo":
      return "BOGO";
    case "buy_x_get_y": {
      const buyQty = (deal.conditions?.buy_quantity as number) ?? 2;
      const getQty = (deal.conditions?.get_quantity as number) ?? 1;
      return `Buy ${buyQty} Get ${getQty}`;
    }
    default:
      return "Deal";
  }
}

function getDateStatus(deal: ScannedDeal): { label: string; variant: "default" | "secondary" | "destructive" | "outline" } {
  if (!deal.valid_from && !deal.valid_to) {
    return { label: "No dates", variant: "outline" };
  }

  const now = new Date();
  now.setHours(0, 0, 0, 0);

  if (deal.valid_from) {
    const from = new Date(deal.valid_from);
    if (from > now) {
      return { label: `Starts ${from.toLocaleDateString()}`, variant: "secondary" };
    }
  }

  if (deal.valid_to) {
    const to = new Date(deal.valid_to);
    const daysLeft = Math.ceil((to.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

    if (daysLeft < 0) {
      return { label: "Expired", variant: "destructive" };
    }
    if (daysLeft <= 2) {
      return { label: `Expires in ${daysLeft}d`, variant: "destructive" };
    }
    return { label: `Until ${to.toLocaleDateString()}`, variant: "default" };
  }

  return { label: "Active", variant: "default" };
}

export function DealCard({ deal, mode, onAccept, onDismiss, onClick, isPending }: DealCardProps) {
  const dateStatus = getDateStatus(deal);

  return (
    <Card
      className={mode === "library" && onClick ? "cursor-pointer hover:border-primary/50 transition-colors" : ""}
      onClick={() => mode === "library" && onClick?.(deal)}
    >
      <CardContent className="pt-4 pb-2 space-y-2">
        <div className="flex items-start justify-between gap-2">
          <div className="flex-1 min-w-0">
            <p className="font-medium text-sm leading-tight truncate">{deal.product_name}</p>
            {deal.product_description && (
              <p className="text-xs text-muted-foreground truncate mt-0.5">{deal.product_description}</p>
            )}
          </div>
          <Badge variant="default" className="shrink-0 text-xs font-bold">
            {getDiscountLabel(deal)}
          </Badge>
        </div>

        <div className="flex items-center gap-2 flex-wrap">
          {(deal.store?.name || deal.store_name_raw) && (
            <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
              <StoreIcon className="h-3 w-3" />
              {deal.store?.name || deal.store_name_raw}
            </span>
          )}
          <Badge variant={dateStatus.variant} className="text-[10px] px-1.5 py-0">
            <Calendar className="h-2.5 w-2.5 mr-0.5" />
            {dateStatus.label}
          </Badge>
          {deal.matched_list_item_id && (
            <Badge variant="outline" className="text-[10px] px-1.5 py-0 text-green-600">
              <Link2 className="h-2.5 w-2.5 mr-0.5" />
              Matched
            </Badge>
          )}
          {deal.confidence != null && (
            <Badge variant="outline" className="text-[10px] px-1.5 py-0">
              {Math.round(deal.confidence * 100)}%
            </Badge>
          )}
        </div>

        {deal.original_price != null && deal.sale_price != null && (
          <div className="flex items-center gap-2 text-xs">
            <span className="line-through text-muted-foreground">${Number(deal.original_price).toFixed(2)}</span>
            <span className="font-semibold text-green-600">${Number(deal.sale_price).toFixed(2)}</span>
          </div>
        )}
      </CardContent>

      {mode === "review" && (
        <CardFooter className="pt-0 pb-3 flex justify-end gap-1">
          <Button
            variant="ghost"
            size="sm"
            className="gap-1 text-destructive hover:text-destructive h-7"
            onClick={(e) => { e.stopPropagation(); onDismiss?.(deal.id); }}
            disabled={isPending}
          >
            <X className="h-3.5 w-3.5" />
            Dismiss
          </Button>
          <Button
            variant="default"
            size="sm"
            className="gap-1 h-7"
            onClick={(e) => { e.stopPropagation(); onAccept?.(deal.id); }}
            disabled={isPending}
          >
            <Check className="h-3.5 w-3.5" />
            Accept
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}
