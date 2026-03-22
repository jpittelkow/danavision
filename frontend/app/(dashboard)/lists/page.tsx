"use client";

import { useQuery } from "@tanstack/react-query";
import { Plus, ShoppingCart } from "lucide-react";
import Link from "next/link";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { ListCard } from "@/components/shopping/list-card";
import { fetchLists, type ShoppingList } from "@/lib/api/shopping";

export default function ShoppingListsPage() {
  usePageTitle("Shopping Lists");

  const { data, isLoading } = useQuery({
    queryKey: ["shopping-lists"],
    queryFn: fetchLists,
  });

  const lists: ShoppingList[] = data?.data?.data ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Shopping Lists</h1>
          <p className="text-muted-foreground mt-1">
            Track prices and manage your shopping lists.
          </p>
        </div>
        <Button asChild className="gap-2">
          <Link href="/lists/new">
            <Plus className="h-4 w-4" />
            New List
          </Link>
        </Button>
      </div>

      {isLoading ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-36 rounded-lg" />
          ))}
        </div>
      ) : lists.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-12 text-center">
          <ShoppingCart className="h-12 w-12 text-muted-foreground mb-4" />
          <h3 className="text-lg font-semibold">No shopping lists yet</h3>
          <p className="text-sm text-muted-foreground mt-1 mb-4">
            Create your first list to start tracking prices!
          </p>
          <Button asChild className="gap-2">
            <Link href="/lists/new">
              <Plus className="h-4 w-4" />
              Create your first list
            </Link>
          </Button>
        </div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {lists.map((list) => (
            <ListCard key={list.id} list={list} />
          ))}
        </div>
      )}
    </div>
  );
}
