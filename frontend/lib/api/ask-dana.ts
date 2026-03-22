import { api } from "@/lib/api";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface Conversation {
  id: number;
  title: string | null;
  is_pinned: boolean;
  created_at: string;
  updated_at: string;
  messages?: ConversationMessage[];
}

export interface ConversationMessage {
  id: number;
  conversation_id: number;
  role: "user" | "assistant" | "tool_call" | "tool_result";
  content: string | null;
  tool_name: string | null;
  tool_input: Record<string, unknown> | null;
  tool_output: Record<string, unknown> | null;
  metadata: MessageMetadata | null;
  created_at: string;
}

export interface MessageMetadata {
  tokens?: { input: number; output: number; total: number };
  model?: string;
  provider?: string;
  tool_iterations?: number;
  error?: string;
}

export type AskDanaEventType =
  | "thinking"
  | "tool_call"
  | "tool_result"
  | "delta"
  | "message"
  | "error"
  | "done";

export interface AskDanaEvent {
  event: AskDanaEventType;
  data: Record<string, unknown>;
}

// ---------------------------------------------------------------------------
// Conversation CRUD
// ---------------------------------------------------------------------------

export async function fetchConversations(
  page = 1,
  perPage = 20
): Promise<{
  data: Conversation[];
  current_page: number;
  last_page: number;
  total: number;
}> {
  const res = await api.get("/ask-dana/conversations", {
    params: { page, per_page: perPage },
  });
  return res.data;
}

export async function createConversation(
  title?: string
): Promise<Conversation> {
  const res = await api.post("/ask-dana/conversations", { title });
  return res.data.data;
}

export async function fetchConversation(
  id: number
): Promise<Conversation> {
  const res = await api.get(`/ask-dana/conversations/${id}`);
  return res.data.data;
}

export async function updateConversation(
  id: number,
  data: { title?: string; is_pinned?: boolean }
): Promise<Conversation> {
  const res = await api.patch(`/ask-dana/conversations/${id}`, data);
  return res.data.data;
}

export async function deleteConversation(id: number): Promise<void> {
  await api.delete(`/ask-dana/conversations/${id}`);
}

// ---------------------------------------------------------------------------
// Send Message (SSE streaming)
// ---------------------------------------------------------------------------

export async function sendMessageStream(
  conversationId: number,
  content: string,
  onEvent: (event: AskDanaEvent) => void,
  signal?: AbortSignal
): Promise<void> {
  const baseURL = process.env.NEXT_PUBLIC_API_URL
    ? `${process.env.NEXT_PUBLIC_API_URL}/api`
    : "/api";

  // Read XSRF token from cookie (required for Laravel Sanctum POST requests)
  const xsrfToken = typeof document !== "undefined"
    ? document.cookie
        .split("; ")
        .find((c) => c.startsWith("XSRF-TOKEN="))
        ?.split("=")[1]
    : undefined;

  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "text/event-stream",
  };
  if (xsrfToken) {
    headers["X-XSRF-TOKEN"] = decodeURIComponent(xsrfToken);
  }

  const response = await fetch(
    `${baseURL}/ask-dana/conversations/${conversationId}/messages`,
    {
      method: "POST",
      headers,
      credentials: "include",
      body: JSON.stringify({ content }),
      signal,
    }
  );

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(errorText || `Request failed with status ${response.status}`);
  }

  const reader = response.body?.getReader();
  if (!reader) {
    throw new Error("No response body");
  }

  const decoder = new TextDecoder();
  let buffer = "";
  let currentEvent = "";
  let currentData = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });

    // Parse SSE events from buffer
    const lines = buffer.split("\n");
    buffer = lines.pop() ?? "";

    for (const line of lines) {
      if (line.startsWith("event: ")) {
        currentEvent = line.slice(7).trim();
      } else if (line.startsWith("data: ")) {
        currentData = line.slice(6);
      } else if (line === "" && currentEvent && currentData) {
        try {
          const parsed = JSON.parse(currentData);
          onEvent({
            event: currentEvent as AskDanaEventType,
            data: parsed,
          });
        } catch {
          // Skip malformed JSON
        }
        currentEvent = "";
        currentData = "";
      }
    }
  }
}

// ---------------------------------------------------------------------------
// Non-streaming fallback
// ---------------------------------------------------------------------------

export async function sendMessageSync(
  conversationId: number,
  content: string
): Promise<{
  message: ConversationMessage;
  events: AskDanaEvent[];
}> {
  const res = await api.post(
    `/ask-dana/conversations/${conversationId}/messages`,
    { content },
    { params: { stream: "false" } }
  );
  return res.data.data;
}
