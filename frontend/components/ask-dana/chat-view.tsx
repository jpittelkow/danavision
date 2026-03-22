"use client";

import { useEffect, useRef, useCallback } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Loader2 } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchConversation, type ConversationMessage } from "@/lib/api/ask-dana";
import { useAskDanaStream } from "@/lib/use-ask-dana-stream";
import { ChatMessage } from "./chat-message";
import { ChatInput } from "./chat-input";
import { ToolCallCard } from "./tool-call-card";
import { SuggestedPrompts } from "./suggested-prompts";

interface ChatViewProps {
  conversationId: number | null;
  onNewConversation?: () => Promise<number>;
}

/**
 * Pair each tool_call message with its subsequent tool_result (same tool_name).
 * Returns a map of tool_call message id → tool_output data.
 */
function buildToolResultMap(
  messages: ConversationMessage[]
): Map<number, Record<string, unknown>> {
  const map = new Map<number, Record<string, unknown>>();
  for (let i = 0; i < messages.length; i++) {
    const msg = messages[i];
    if (msg.role === "tool_call" && msg.tool_name) {
      // Find the next tool_result with the same tool_name
      for (let j = i + 1; j < messages.length; j++) {
        if (
          messages[j].role === "tool_result" &&
          messages[j].tool_name === msg.tool_name
        ) {
          map.set(msg.id, messages[j].tool_output ?? {});
          break;
        }
      }
    }
  }
  return map;
}

export function ChatView({ conversationId, onNewConversation }: ChatViewProps) {
  const queryClient = useQueryClient();
  const scrollRef = useRef<HTMLDivElement>(null);

  const { data: conversation, isLoading } = useQuery({
    queryKey: ["conversation", conversationId],
    queryFn: () => fetchConversation(conversationId!),
    enabled: conversationId !== null,
  });

  const { isStreaming, toolCalls, error, sendMessage, abort } =
    useAskDanaStream();

  const messages = conversation?.messages ?? [];
  const toolResultMap = buildToolResultMap(messages);

  // Auto-scroll to bottom only when user is near the bottom
  useEffect(() => {
    const el = scrollRef.current;
    if (el) {
      const isNearBottom =
        el.scrollHeight - el.scrollTop - el.clientHeight < 100;
      if (isNearBottom) {
        el.scrollTop = el.scrollHeight;
      }
    }
  }, [messages.length, toolCalls.length]);

  const handleSend = useCallback(
    async (text: string) => {
      let targetId = conversationId;

      // Create a new conversation if none is selected
      if (targetId === null && onNewConversation) {
        targetId = await onNewConversation();
      }

      if (targetId === null) return;

      sendMessage(targetId, text, () => {
        // Refresh the conversation and list when done
        queryClient.invalidateQueries({
          queryKey: ["conversation", targetId],
        });
        queryClient.invalidateQueries({
          queryKey: ["conversations"],
        });
      });
    },
    [conversationId, onNewConversation, sendMessage, queryClient]
  );

  // Empty state — no conversation selected
  if (conversationId === null && !isStreaming) {
    return (
      <div className="flex h-full flex-col">
        <div className="flex-1 overflow-auto">
          <SuggestedPrompts onSelect={handleSend} />
        </div>
        <ChatInput onSend={handleSend} disabled={false} />
      </div>
    );
  }

  if (isLoading && conversationId !== null) {
    return (
      <div className="flex h-full flex-col">
        <div className="flex-1 space-y-4 p-4">
          <Skeleton className="h-12 w-3/4 ml-auto rounded-xl" />
          <Skeleton className="h-20 w-3/4 rounded-xl" />
          <Skeleton className="h-16 w-1/2 ml-auto rounded-xl" />
          <Skeleton className="h-24 w-3/4 rounded-xl" />
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-full flex-col">
      {/* Messages area */}
      <div ref={scrollRef} className="flex-1 overflow-y-auto">
        {messages.length === 0 && !isStreaming && (
          <SuggestedPrompts onSelect={handleSend} />
        )}

        <div className="mx-auto max-w-3xl py-4">
          {messages.map((msg: ConversationMessage) => {
            if (msg.role === "tool_call") {
              return (
                <div key={msg.id} className="px-4">
                  <div className="ml-10 max-w-[85%] md:max-w-[75%]">
                    <ToolCallCard
                      name={msg.tool_name ?? "unknown"}
                      input={msg.tool_input ?? {}}
                      result={toolResultMap.get(msg.id)}
                    />
                  </div>
                </div>
              );
            }

            if (msg.role === "tool_result") {
              // Rendered as part of the paired tool_call card above
              return null;
            }

            return <ChatMessage key={msg.id} message={msg} />;
          })}

          {/* Active tool calls during streaming */}
          {isStreaming && toolCalls.length > 0 && (
            <div className="px-4">
              <div className="ml-10 max-w-[85%] md:max-w-[75%]">
                {toolCalls.map((tc, i) => (
                  <ToolCallCard
                    key={`${tc.name}-${i}`}
                    name={tc.name}
                    input={tc.input}
                    result={tc.result}
                    isActive={i === toolCalls.length - 1 && !tc.result}
                  />
                ))}
              </div>
            </div>
          )}

          {/* Thinking/processing indicator */}
          {isStreaming && toolCalls.length === 0 && (
            <div className="flex gap-3 px-4 py-3">
              <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted mt-0.5">
                <Loader2 className="h-3.5 w-3.5 text-muted-foreground animate-spin" />
              </div>
              <div className="rounded-xl bg-muted/60 border border-border/50 px-3.5 py-2.5">
                <div className="flex items-center gap-1.5">
                  <span className="inline-block h-1.5 w-1.5 rounded-full bg-muted-foreground/40 animate-pulse" />
                  <span className="inline-block h-1.5 w-1.5 rounded-full bg-muted-foreground/40 animate-pulse [animation-delay:150ms]" />
                  <span className="inline-block h-1.5 w-1.5 rounded-full bg-muted-foreground/40 animate-pulse [animation-delay:300ms]" />
                </div>
              </div>
            </div>
          )}

          {/* Error display */}
          {error && (
            <div className="px-4 py-2">
              <div className="ml-10 max-w-[85%] md:max-w-[75%] rounded-lg border border-destructive/30 bg-destructive/5 px-3.5 py-2.5 text-sm text-destructive">
                {error}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Input */}
      <ChatInput
        onSend={handleSend}
        onAbort={abort}
        isStreaming={isStreaming}
      />
    </div>
  );
}
