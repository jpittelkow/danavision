import { useState, useCallback, useRef } from "react";
import {
  sendMessageStream,
  type AskDanaEvent,
  type ConversationMessage,
} from "@/lib/api/ask-dana";

interface ToolCallInfo {
  name: string;
  input: Record<string, unknown>;
  result?: Record<string, unknown>;
}

interface StreamState {
  isStreaming: boolean;
  toolCalls: ToolCallInfo[];
  error: string | null;
}

export function useAskDanaStream() {
  const [state, setState] = useState<StreamState>({
    isStreaming: false,
    toolCalls: [],
    error: null,
  });

  const abortRef = useRef<AbortController | null>(null);

  const sendMessage = useCallback(
    async (
      conversationId: number,
      content: string,
      onComplete?: (message: ConversationMessage) => void
    ) => {
      // Abort any previous stream
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setState({
        isStreaming: true,
        toolCalls: [],
        error: null,
      });

      try {
        await sendMessageStream(
          conversationId,
          content,
          (event: AskDanaEvent) => {
            switch (event.event) {
              case "thinking":
                // No action needed — isStreaming is already true
                break;

              case "tool_call":
                setState((prev) => ({
                  ...prev,
                  toolCalls: [
                    ...prev.toolCalls,
                    {
                      name: event.data.name as string,
                      input: event.data.input as Record<string, unknown>,
                    },
                  ],
                }));
                break;

              case "tool_result":
                setState((prev) => {
                  const toolCalls = prev.toolCalls.map((tc) => ({ ...tc }));
                  const idx = toolCalls.findIndex(
                    (tc) =>
                      tc.name === (event.data.name as string) && !tc.result
                  );
                  if (idx !== -1) {
                    toolCalls[idx] = {
                      ...toolCalls[idx],
                      result: event.data.result as Record<string, unknown>,
                    };
                  }
                  return { ...prev, toolCalls };
                });
                break;

              case "message":
                // Don't clear toolCalls here — let the query refetch replace the UI
                if (onComplete) {
                  onComplete(event.data as unknown as ConversationMessage);
                }
                break;

              case "error":
                setState((prev) => ({
                  ...prev,
                  isStreaming: false,
                  error: (event.data.message as string) ?? "An error occurred",
                }));
                break;

              case "done":
                setState((prev) => ({
                  ...prev,
                  isStreaming: false,
                  toolCalls: [],
                }));
                break;
            }
          },
          controller.signal
        );
      } catch (err) {
        if ((err as Error).name !== "AbortError") {
          setState((prev) => ({
            ...prev,
            isStreaming: false,
            error: (err as Error).message || "Failed to send message",
          }));
        }
      }
    },
    []
  );

  const abort = useCallback(() => {
    abortRef.current?.abort();
    setState((prev) => ({ ...prev, isStreaming: false }));
  }, []);

  return {
    ...state,
    sendMessage,
    abort,
  };
}
