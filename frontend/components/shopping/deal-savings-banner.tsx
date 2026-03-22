"use client";

import { useQuery } from "@tanstack/react-query";
import { TicketPercent } from "lucide-react";
import Link from "next/link";
import { fetchDealSavings, type DealSavingsSummary } from "@/lib/api/shopping";

interface DealSavingsBannerProps {
  listId: number;
}

export function DealSavingsBanner({ listId }: DealSavingsBannerProps) {
  const { data } = useQuery({
    queryKey: ["deal-savings", listId],
    queryFn: () => fetchDealSavings(listId),
  });

  const savings: DealSavingsSummary | undefined = data?.data?.data;

  if (!savings || savings.total_savings <= 0) {
    return null;
  }

  return (
    <Link href="/deals">
      <div className="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30 p-3 cursor-pointer hover:border-green-300 dark:hover:border-green-800 transition-colors">
        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/50 shrink-0">
          <TicketPercent className="h-4 w-4 text-green-600 dark:text-green-400" />
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-green-800 dark:text-green-200">
            ${savings.total_savings.toFixed(2)} in deal savings
          </p>
          <p className="text-xs text-green-600 dark:text-green-400">
            {savings.deals_applied} deal{savings.deals_applied !== 1 ? "s" : ""} on {savings.items_with_deals} item{savings.items_with_deals !== 1 ? "s" : ""}
          </p>
        </div>
      </div>
    </Link>
  );
}
