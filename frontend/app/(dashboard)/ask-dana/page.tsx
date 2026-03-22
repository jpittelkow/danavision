"use client";

import { useState, useCallback } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  Plus,
  MessageCircle,
  Pin,
  Trash2,
  PanelLeftClose,
  PanelLeft,
} from "lucide-react";
import { usePageTitle } from "@/lib/use-page-title";
import { useIsMobile } from "@/lib/use-mobile";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Sheet,
  SheetContent,
} from "@/components/ui/sheet";
import { cn, getErrorMessage } from "@/lib/utils";
import {
  fetchConversations,
  createConversation,
  deleteConversation,
  updateConversation,
  type Conversation,
} from "@/lib/api/ask-dana";
import { ChatView } from "@/components/ask-dana/chat-view";

export default function AskDanaPage() {
  usePageTitle("Ask Dana");

  const isMobile = useIsMobile();
  const queryClient = useQueryClient();

  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [showList, setShowList] = useState(false);
  const [sidebarOpen, setSidebarOpen] = useState(true);

  const { data: convData, isLoading: loadingConvs } = useQuery({
    queryKey: ["conversations"],
    queryFn: () => fetchConversations(1, 50),
  });

  const conversations = convData?.data ?? [];

  const createMutation = useMutation({
    mutationFn: () => createConversation(),
    onSuccess: (conv) => {
      queryClient.invalidateQueries({ queryKey: ["conversations"] });
      setSelectedId(conv.id);
      setShowList(false);
    },
    onError: (err) => toast.error(getErrorMessage(err, "Failed to create conversation")),
  });

  const deleteMutation = useMutation({
    mutationFn: deleteConversation,
    onSuccess: (_, deletedId) => {
      queryClient.invalidateQueries({ queryKey: ["conversations"] });
      if (selectedId === deletedId) setSelectedId(null);
    },
    onError: (err) => toast.error(getErrorMessage(err, "Failed to delete conversation")),
  });

  const pinMutation = useMutation({
    mutationFn: ({ id, pinned }: { id: number; pinned: boolean }) =>
      updateConversation(id, { is_pinned: pinned }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["conversations"] }),
  });

  // For ChatView: creates a conversation on-the-fly when user sends first message
  const handleNewConversation = useCallback(async (): Promise<number> => {
    const conv = await createConversation();
    queryClient.invalidateQueries({ queryKey: ["conversations"] });
    setSelectedId(conv.id);
    return conv.id;
  }, [queryClient]);

  const pinnedConvs = conversations.filter((c: Conversation) => c.is_pinned);
  const recentConvs = conversations.filter((c: Conversation) => !c.is_pinned);

  // Conversation list content (shared between sidebar and sheet)
  const listContent = (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between border-b border-border px-3 py-3">
        <h2 className="text-sm font-semibold">Conversations</h2>
        <Button
          size="icon"
          variant="ghost"
          className="h-8 w-8"
          onClick={() => createMutation.mutate()}
          disabled={createMutation.isPending}
          title="New conversation"
        >
          <Plus className="h-4 w-4" />
        </Button>
      </div>

      <div className="flex-1 overflow-y-auto">
        {loadingConvs ? (
          <div className="space-y-2 p-3">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-12 rounded-lg" />
            ))}
          </div>
        ) : conversations.length === 0 ? (
          <div className="px-3 py-8 text-center">
            <MessageCircle className="mx-auto h-8 w-8 text-muted-foreground/40 mb-2" />
            <p className="text-xs text-muted-foreground">No conversations yet</p>
          </div>
        ) : (
          <div className="p-2 space-y-0.5">
            {pinnedConvs.length > 0 && (
              <>
                <p className="px-2 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                  Pinned
                </p>
                {pinnedConvs.map((conv: Conversation) => (
                  <ConversationItem
                    key={conv.id}
                    conversation={conv}
                    isSelected={selectedId === conv.id}
                    onSelect={() => {
                      setSelectedId(conv.id);
                      setShowList(false);
                    }}
                    onDelete={() => deleteMutation.mutate(conv.id)}
                    onTogglePin={() =>
                      pinMutation.mutate({
                        id: conv.id,
                        pinned: !conv.is_pinned,
                      })
                    }
                  />
                ))}
              </>
            )}
            {recentConvs.length > 0 && (
              <>
                {pinnedConvs.length > 0 && (
                  <p className="px-2 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mt-2">
                    Recent
                  </p>
                )}
                {recentConvs.map((conv: Conversation) => (
                  <ConversationItem
                    key={conv.id}
                    conversation={conv}
                    isSelected={selectedId === conv.id}
                    onSelect={() => {
                      setSelectedId(conv.id);
                      setShowList(false);
                    }}
                    onDelete={() => deleteMutation.mutate(conv.id)}
                    onTogglePin={() =>
                      pinMutation.mutate({
                        id: conv.id,
                        pinned: !conv.is_pinned,
                      })
                    }
                  />
                ))}
              </>
            )}
          </div>
        )}
      </div>
    </div>
  );

  // Mobile layout: Sheet for conversation list
  if (isMobile) {
    return (
      <div className="flex h-[calc(100vh-var(--header-height,3.5rem))] flex-col">
        {/* Mobile header */}
        <div className="flex items-center gap-2 border-b border-border px-4 py-2">
          <Button
            size="icon"
            variant="ghost"
            className="h-8 w-8"
            onClick={() => setShowList(true)}
          >
            <PanelLeft className="h-4 w-4" />
          </Button>
          <h1 className="text-sm font-semibold flex-1">Ask Dana</h1>
          <Button
            size="icon"
            variant="ghost"
            className="h-8 w-8"
            onClick={() => {
              setSelectedId(null);
              createMutation.mutate();
            }}
          >
            <Plus className="h-4 w-4" />
          </Button>
        </div>

        <div className="flex-1 min-h-0">
          <ChatView
            conversationId={selectedId}
            onNewConversation={handleNewConversation}
          />
        </div>

        <Sheet open={showList} onOpenChange={setShowList}>
          <SheetContent side="left" className="w-80 max-w-[85vw] p-0">
            {listContent}
          </SheetContent>
        </Sheet>
      </div>
    );
  }

  // Desktop layout: sidebar + chat
  return (
    <div className="flex h-[calc(100vh-var(--header-height,3.5rem))]">
      {/* Conversation sidebar */}
      {sidebarOpen && (
        <div className="w-72 shrink-0 border-r border-border bg-muted/20">
          {listContent}
        </div>
      )}

      {/* Chat area */}
      <div className="flex flex-1 flex-col min-w-0">
        {/* Chat header */}
        <div className="flex items-center gap-2 border-b border-border px-4 py-2">
          <Button
            size="icon"
            variant="ghost"
            className="h-8 w-8"
            onClick={() => setSidebarOpen(!sidebarOpen)}
            title={sidebarOpen ? "Hide conversations" : "Show conversations"}
          >
            {sidebarOpen ? (
              <PanelLeftClose className="h-4 w-4" />
            ) : (
              <PanelLeft className="h-4 w-4" />
            )}
          </Button>
          <h1 className="text-sm font-semibold flex-1">
            {selectedId
              ? conversations.find((c: Conversation) => c.id === selectedId)?.title ??
                "New conversation"
              : "Ask Dana"}
          </h1>
        </div>

        <div className="flex-1 min-h-0">
          <ChatView
            conversationId={selectedId}
            onNewConversation={handleNewConversation}
          />
        </div>
      </div>
    </div>
  );
}

// ─── Conversation List Item ──────────────────────────────────────────────────

function ConversationItem({
  conversation,
  isSelected,
  onSelect,
  onDelete,
  onTogglePin,
}: {
  conversation: Conversation;
  isSelected: boolean;
  onSelect: () => void;
  onDelete: () => void;
  onTogglePin: () => void;
}) {
  const title = conversation.title || "New conversation";
  const date = new Date(conversation.updated_at);
  const timeStr = date.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
  });

  return (
    <div
      className={cn(
        "group relative flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors",
        isSelected
          ? "bg-primary/10 text-primary"
          : "text-foreground hover:bg-muted"
      )}
      onClick={onSelect}
    >
      <MessageCircle className="h-4 w-4 shrink-0 text-muted-foreground" />
      <div className="flex-1 min-w-0">
        <p className="truncate font-medium text-xs">{title}</p>
        <p className="text-[10px] text-muted-foreground">{timeStr}</p>
      </div>
      {/* Actions on hover */}
      <div className="hidden group-hover:flex items-center gap-0.5">
        <button
          onClick={(e) => {
            e.stopPropagation();
            onTogglePin();
          }}
          className={cn(
            "rounded p-1 hover:bg-accent transition-colors",
            conversation.is_pinned && "text-primary"
          )}
          title={conversation.is_pinned ? "Unpin" : "Pin"}
        >
          <Pin className="h-3 w-3" />
        </button>
        <button
          onClick={(e) => {
            e.stopPropagation();
            onDelete();
          }}
          className="rounded p-1 hover:bg-destructive/10 hover:text-destructive transition-colors"
          title="Delete"
        >
          <Trash2 className="h-3 w-3" />
        </button>
      </div>
    </div>
  );
}
