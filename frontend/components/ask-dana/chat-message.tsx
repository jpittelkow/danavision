"use client";

import { Bot, User } from "lucide-react";
import { cn } from "@/lib/utils";
import type { ConversationMessage } from "@/lib/api/ask-dana";

interface ChatMessageProps {
  message: ConversationMessage;
}

/**
 * Escape HTML entities to prevent XSS.
 */
function escapeHtml(text: string): string {
  return text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

/**
 * Basic markdown-like formatting for assistant messages.
 * Handles: **bold**, *italic*, `code`, headers, lists, line breaks.
 * HTML is escaped first to prevent XSS from LLM-generated content.
 */
function formatMarkdown(text: string): string {
  return escapeHtml(text)
    // Code blocks (```...```)
    .replace(
      /```(\w*)\n?([\s\S]*?)```/g,
      '<pre class="my-2 rounded-md bg-muted p-3 text-xs overflow-x-auto"><code>$2</code></pre>'
    )
    // Inline code
    .replace(/`([^`]+)`/g, '<code class="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">$1</code>')
    // Bold
    .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
    // Italic
    .replace(/\*(.+?)\*/g, "<em>$1</em>")
    // Headers
    .replace(/^### (.+)$/gm, '<h4 class="font-semibold text-sm mt-3 mb-1">$1</h4>')
    .replace(/^## (.+)$/gm, '<h3 class="font-semibold mt-3 mb-1">$1</h3>')
    .replace(/^# (.+)$/gm, '<h2 class="font-semibold text-lg mt-3 mb-1">$1</h2>')
    // Unordered lists
    .replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
    // Ordered lists
    .replace(/^\d+\. (.+)$/gm, '<li class="ml-4 list-decimal">$1</li>')
    // Wrap consecutive list items
    .replace(
      /(<li class="ml-4 list-disc">[\s\S]*?<\/li>\n?)+/g,
      '<ul class="my-1.5 space-y-0.5">$&</ul>'
    )
    .replace(
      /(<li class="ml-4 list-decimal">[\s\S]*?<\/li>\n?)+/g,
      '<ol class="my-1.5 space-y-0.5">$&</ol>'
    )
    // Line breaks (double newline → paragraph break)
    .replace(/\n\n/g, '<div class="h-2"></div>')
    .replace(/\n/g, "<br />");
}

export function ChatMessage({ message }: ChatMessageProps) {
  const isUser = message.role === "user";
  const isAssistant = message.role === "assistant";

  if (!isUser && !isAssistant) return null;

  const metadata = message.metadata;

  return (
    <div
      className={cn(
        "flex gap-3 px-4 py-3",
        isUser ? "flex-row-reverse" : "flex-row"
      )}
    >
      {/* Avatar */}
      <div
        className={cn(
          "flex h-7 w-7 shrink-0 items-center justify-center rounded-full mt-0.5",
          isUser ? "bg-primary text-primary-foreground" : "bg-muted"
        )}
      >
        {isUser ? (
          <User className="h-3.5 w-3.5" />
        ) : (
          <Bot className="h-3.5 w-3.5 text-muted-foreground" />
        )}
      </div>

      {/* Message content */}
      <div
        className={cn(
          "max-w-[85%] md:max-w-[75%] rounded-xl px-3.5 py-2.5",
          isUser
            ? "bg-primary text-primary-foreground"
            : "bg-muted/60 text-foreground border border-border/50"
        )}
      >
        {isUser ? (
          <p className="text-sm whitespace-pre-wrap">{message.content}</p>
        ) : (
          <div
            className="text-sm leading-relaxed [&_pre]:my-2 [&_code]:text-xs [&_ul]:my-1 [&_ol]:my-1 [&_li]:text-sm"
            dangerouslySetInnerHTML={{
              __html: formatMarkdown(message.content ?? ""),
            }}
          />
        )}

        {/* Metadata footer for assistant messages */}
        {isAssistant && metadata && (metadata.model || metadata.tokens) && (
          <div className="mt-2 flex items-center gap-2 border-t border-border/30 pt-1.5 text-[10px] text-muted-foreground">
            {metadata.model && <span>{metadata.model}</span>}
            {metadata.tokens && (
              <span>
                {metadata.tokens.total.toLocaleString()} tokens
              </span>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

