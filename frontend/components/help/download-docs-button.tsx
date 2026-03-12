"use client";

import type React from "react";
import { Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { HelpArticle } from "@/lib/help/help-content";

interface DownloadDocsButtonProps {
  articles: HelpArticle[];
  filename?: string;
}

export function DownloadDocsButton({
  articles,
  filename = "graphql-api-documentation.md",
}: DownloadDocsButtonProps) {
  const handleDownload = (e: React.MouseEvent) => {
    // Stop propagation to prevent Radix Dialog from intercepting the event
    e.stopPropagation();

    const header = `# GraphQL API Documentation\n\nGenerated from the in-app help center.\n\n---\n\n`;
    const body = articles.map((article) => article.content).join("\n\n---\n\n");
    const markdown = header + body;

    const blob = new Blob([markdown], { type: "text/markdown;charset=utf-8" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", filename);
    // Use a hidden, positioned element so Radix Dialog portal doesn't interfere
    link.style.position = "fixed";
    link.style.left = "-9999px";
    link.style.top = "-9999px";
    document.body.appendChild(link);
    link.click();
    link.parentNode?.removeChild(link);
    // Delay revocation so async download processing can complete in all browsers
    setTimeout(() => window.URL.revokeObjectURL(url), 100);
  };

  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      onClick={handleDownload}
      onPointerDown={(e) => e.stopPropagation()}
      className="gap-1.5"
    >
      <Download className="h-3.5 w-3.5" />
      Download Full API Docs
    </Button>
  );
}
