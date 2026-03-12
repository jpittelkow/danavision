"use client";

import { useState, useMemo } from "react";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { ArrowUpDown } from "lucide-react";
import { formatCurrency } from "@/lib/utils";

interface ProviderData {
  provider: string;
  integration: string;
  total_cost: number;
  total_quantity: number;
}

interface UsageProviderTableProps {
  data: ProviderData[];
  className?: string;
}

type SortField = "provider" | "integration" | "total_quantity" | "total_cost";
type SortDirection = "asc" | "desc";

function formatQuantity(value: number, integration: string): string {
  if (integration === "llm") {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(1)}K`;
  }
  if (integration === "storage") {
    if (value >= 1_073_741_824) return `${(value / 1_073_741_824).toFixed(1)} GB`;
    if (value >= 1_048_576) return `${(value / 1_048_576).toFixed(1)} MB`;
    if (value >= 1_024) return `${(value / 1_024).toFixed(1)} KB`;
    return `${value} B`;
  }
  return value.toLocaleString();
}

function integrationLabel(integration: string): string {
  const labels: Record<string, string> = {
    llm: "LLM",
    email: "Email",
    sms: "SMS",
    storage: "Storage",
    broadcasting: "Broadcasting",
  };
  return labels[integration] ?? integration;
}

export function UsageProviderTable({ data, className }: UsageProviderTableProps) {
  const [sortField, setSortField] = useState<SortField>("total_cost");
  const [sortDirection, setSortDirection] = useState<SortDirection>("desc");

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDirection((d) => (d === "asc" ? "desc" : "asc"));
    } else {
      setSortField(field);
      setSortDirection("desc");
    }
  };

  const sorted = useMemo(() => {
    return [...data].sort((a, b) => {
      const aVal = a[sortField];
      const bVal = b[sortField];
      const cmp = typeof aVal === "string"
        ? aVal.localeCompare(bVal as string)
        : (aVal as number) - (bVal as number);
      return sortDirection === "asc" ? cmp : -cmp;
    });
  }, [data, sortField, sortDirection]);

  const totalCost = useMemo(() => {
    return data.reduce((acc, row) => acc + row.total_cost, 0);
  }, [data]);

  if (data.length === 0) {
    return (
      <div
        className={`flex min-h-[100px] items-center justify-center rounded-lg border border-dashed bg-muted/30 text-sm text-muted-foreground ${className ?? ""}`}
      >
        No provider data available
      </div>
    );
  }

  const SortButton = ({ field, children }: { field: SortField; children: React.ReactNode }) => (
    <Button
      variant="ghost"
      size="sm"
      className="-ml-3 h-8"
      onClick={() => toggleSort(field)}
    >
      {children}
      <ArrowUpDown className="ml-1 h-3 w-3" />
    </Button>
  );

  return (
    <div className={`rounded-md border overflow-x-auto ${className ?? ""}`}>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>
              <SortButton field="provider">Provider</SortButton>
            </TableHead>
            <TableHead>
              <SortButton field="integration">Integration</SortButton>
            </TableHead>
            <TableHead className="text-right">
              <SortButton field="total_quantity">Total Units</SortButton>
            </TableHead>
            <TableHead className="text-right">
              <SortButton field="total_cost">Est. Cost</SortButton>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {sorted.map((row, i) => (
            <TableRow key={`${row.provider}-${row.integration}-${i}`}>
              <TableCell className="font-medium capitalize">{row.provider}</TableCell>
              <TableCell>{integrationLabel(row.integration)}</TableCell>
              <TableCell className="text-right tabular-nums">
                {formatQuantity(row.total_quantity, row.integration)}
              </TableCell>
              <TableCell className="text-right tabular-nums">
                {formatCurrency(row.total_cost)}
              </TableCell>
            </TableRow>
          ))}
          <TableRow className="bg-muted/50 font-semibold">
            <TableCell colSpan={2}>Total</TableCell>
            <TableCell className="text-right text-muted-foreground">
              &mdash;
            </TableCell>
            <TableCell className="text-right tabular-nums">
              {formatCurrency(totalCost)}
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  );
}
