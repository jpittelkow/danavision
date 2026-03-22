"use client";

import { useState } from "react";
import { ChevronDown, ChevronRight, Loader2, Wrench } from "lucide-react";
import { cn } from "@/lib/utils";

interface ToolCallCardProps {
  name: string;
  input: Record<string, unknown>;
  result?: Record<string, unknown>;
  isActive?: boolean;
}

const toolLabels: Record<string, string> = {
  get_shopping_lists: "Looking up your lists",
  get_list_items: "Checking list items",
  get_item_details: "Getting item details",
  get_price_history: "Pulling price history",
  get_dashboard_stats: "Gathering your stats",
  analyze_list_by_store: "Analyzing store prices",
  get_price_drops: "Finding price drops",
  get_savings_summary: "Calculating savings",
  get_stores: "Checking your stores",
  get_best_price: "Finding best price",
  search_prices: "Searching for prices",
  add_item_to_list: "Adding item to list",
  create_shopping_list: "Creating new list",
  mark_item_purchased: "Marking as purchased",
  refresh_list_prices: "Refreshing prices",
};

export function ToolCallCard({ name, input, result, isActive }: ToolCallCardProps) {
  const [expanded, setExpanded] = useState(false);
  const label = toolLabels[name] ?? name.replace(/_/g, " ");

  return (
    <div className="my-1.5">
      <button
        onClick={() => setExpanded(!expanded)}
        className={cn(
          "flex w-full items-center gap-2 rounded-md border px-3 py-2 text-xs transition-colors",
          isActive
            ? "border-primary/30 bg-primary/5 text-primary"
            : "border-border bg-muted/50 text-muted-foreground hover:bg-muted"
        )}
      >
        {isActive ? (
          <Loader2 className="h-3.5 w-3.5 animate-spin shrink-0" />
        ) : (
          <Wrench className="h-3.5 w-3.5 shrink-0" />
        )}
        <span className="flex-1 text-left font-medium">{label}</span>
        {expanded ? (
          <ChevronDown className="h-3.5 w-3.5 shrink-0" />
        ) : (
          <ChevronRight className="h-3.5 w-3.5 shrink-0" />
        )}
      </button>
      {expanded && (
        <div className="mt-1 rounded-md border border-border bg-muted/30 p-2.5 text-xs">
          {Object.keys(input).length > 0 && (
            <div className="mb-2">
              <p className="font-medium text-muted-foreground mb-1">Input</p>
              <pre className="whitespace-pre-wrap break-all text-foreground/80">
                {JSON.stringify(input, null, 2)}
              </pre>
            </div>
          )}
          {result && (
            <div>
              <p className="font-medium text-muted-foreground mb-1">Result</p>
              <pre className="whitespace-pre-wrap break-all text-foreground/80 max-h-40 overflow-auto">
                {JSON.stringify(result, null, 2)}
              </pre>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
