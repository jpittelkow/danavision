"use client";

import { Sparkles, TrendingDown, BarChart3, ShoppingCart, Search, Target } from "lucide-react";

interface SuggestedPromptsProps {
  onSelect: (prompt: string) => void;
}

const suggestions = [
  {
    icon: TrendingDown,
    label: "Best deals",
    prompt: "What are the best deals across my lists right now?",
  },
  {
    icon: BarChart3,
    label: "Compare stores",
    prompt: "Compare store prices for my most active shopping list",
  },
  {
    icon: Sparkles,
    label: "Price trends",
    prompt: "Show me price trends for items I'm tracking",
  },
  {
    icon: Target,
    label: "All-time lows",
    prompt: "Which of my items are at all-time low prices?",
  },
  {
    icon: ShoppingCart,
    label: "Quick add",
    prompt: "Add milk, eggs, and bread to my grocery list",
  },
  {
    icon: Search,
    label: "Needs refresh",
    prompt: "What items haven't had their prices checked recently?",
  },
];

export function SuggestedPrompts({ onSelect }: SuggestedPromptsProps) {
  return (
    <div className="flex flex-col items-center justify-center h-full px-4 py-12">
      <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
        <Sparkles className="h-6 w-6 text-primary" />
      </div>
      <h2 className="text-lg font-semibold mb-1">Ask Dana</h2>
      <p className="text-sm text-muted-foreground mb-8 text-center max-w-md">
        Your shopping assistant. Ask about prices, deals, lists, or
        let Dana help you manage your shopping.
      </p>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-lg">
        {suggestions.map((s) => {
          const Icon = s.icon;
          return (
            <button
              key={s.label}
              onClick={() => onSelect(s.prompt)}
              className="group flex items-start gap-3 rounded-lg border border-border bg-card p-3.5 text-left transition-all hover:border-primary/40 hover:bg-primary/5 hover:shadow-sm"
            >
              <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted group-hover:bg-primary/10 transition-colors">
                <Icon className="h-4 w-4 text-muted-foreground group-hover:text-primary transition-colors" />
              </div>
              <div className="min-w-0">
                <p className="text-sm font-medium leading-none mb-1">{s.label}</p>
                <p className="text-xs text-muted-foreground leading-snug line-clamp-2">
                  {s.prompt}
                </p>
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}
