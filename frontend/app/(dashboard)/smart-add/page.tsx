"use client";

import { useState, useCallback, useRef } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
  Upload,
  Sparkles,
  Check,
  X,
  FileText,
  Image as ImageIcon,
  Camera,
  Loader2,
  AlertTriangle,
} from "lucide-react";
import { usePageTitle } from "@/lib/use-page-title";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import Link from "next/link";
import { getErrorMessage } from "@/lib/utils";
import { useIsMobile } from "@/lib/use-mobile";
import {
  uploadSmartAdd,
  fetchSmartAddQueue,
  acceptSmartAddItem,
  rejectSmartAddItem,
  fetchAIJobs,
  fetchLists,
  type SmartAddQueueItem,
  type AIJob,
  type ShoppingList,
} from "@/lib/api/shopping";

export default function SmartAddPage() {
  usePageTitle("Smart Add");
  const queryClient = useQueryClient();
  const isMobile = useIsMobile();
  const cameraInputRef = useRef<HTMLInputElement>(null);

  const [textInput, setTextInput] = useState("");
  const [isDragOver, setIsDragOver] = useState(false);
  const [selectedListIds, setSelectedListIds] = useState<Record<string, string>>({});

  // Fetch data
  const { data: queueResponse, isLoading: queueLoading } = useQuery({
    queryKey: ["smart-add-queue"],
    queryFn: fetchSmartAddQueue,
    refetchInterval: 5000,
  });

  const { data: jobsResponse } = useQuery({
    queryKey: ["ai-jobs"],
    queryFn: fetchAIJobs,
    refetchInterval: 5000,
  });

  const { data: listsResponse } = useQuery({
    queryKey: ["shopping-lists"],
    queryFn: fetchLists,
  });

  const queue: SmartAddQueueItem[] = queueResponse?.data?.data ?? [];
  const activeJobs: AIJob[] = (jobsResponse?.data?.data ?? []).filter(
    (j: AIJob) => j.status === "pending" || j.status === "processing"
  );
  const lists: ShoppingList[] = listsResponse?.data?.data ?? [];
  const readyItems = queue.filter((q) => q.status === "ready");

  // Upload mutation
  const uploadMutation = useMutation({
    mutationFn: (formData: FormData) => uploadSmartAdd(formData),
    onSuccess: () => {
      toast.success("Upload received. Processing with AI...");
      setTextInput("");
      queryClient.invalidateQueries({ queryKey: ["smart-add-queue"] });
      queryClient.invalidateQueries({ queryKey: ["ai-jobs"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to upload"));
    },
  });

  const acceptMutation = useMutation({
    mutationFn: ({
      id,
      selectedIndex,
      listId,
    }: {
      id: number;
      selectedIndex: number;
      listId: number;
    }) =>
      acceptSmartAddItem(id, {
        selected_index: selectedIndex,
        shopping_list_id: listId,
      }),
    onSuccess: () => {
      toast.success("Item added to list");
      queryClient.invalidateQueries({ queryKey: ["smart-add-queue"] });
      queryClient.invalidateQueries({ queryKey: ["shopping-lists"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to accept item"));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: (id: number) => rejectSmartAddItem(id),
    onSuccess: () => {
      toast.success("Item rejected");
      queryClient.invalidateQueries({ queryKey: ["smart-add-queue"] });
    },
    onError: (error: unknown) => {
      toast.error(getErrorMessage(error, "Failed to reject item"));
    },
  });

  // Drag and drop handlers
  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback(() => {
    setIsDragOver(false);
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setIsDragOver(false);
      const files = Array.from(e.dataTransfer.files);
      if (files.length > 0) {
        const formData = new FormData();
        files.forEach((file) => formData.append("files[]", file));
        uploadMutation.mutate(formData);
      }
    },
    [uploadMutation]
  );

  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files ?? []);
    if (files.length > 0) {
      const formData = new FormData();
      files.forEach((file) => formData.append("files[]", file));
      uploadMutation.mutate(formData);
    }
  }

  function handleTextSubmit() {
    if (!textInput.trim()) return;
    const formData = new FormData();
    formData.append("text", textInput.trim());
    uploadMutation.mutate(formData);
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
          <Sparkles className="h-6 w-6" />
          Smart Add
        </h1>
        <p className="text-muted-foreground mt-1">
          Upload images of receipts, shopping lists, or type product names to
          automatically add items.
        </p>
      </div>

      {/* Upload Zone */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Upload or Enter Items</CardTitle>
          <CardDescription>
            Drag and drop images, or paste a list of products below.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Hidden camera input for mobile */}
          <input
            ref={cameraInputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            capture="environment"
            onChange={handleFileSelect}
            className="hidden"
          />

          {/* Upload zone with camera button */}
          <div className="flex flex-col sm:flex-row gap-4">
            {/* Camera button - mobile only */}
            {isMobile && (
              <button
                type="button"
                onClick={() => cameraInputRef.current?.click()}
                className="flex flex-col items-center justify-center gap-3 py-8 px-8 border-2 border-dashed rounded-lg transition-all border-primary/50 bg-primary/5 hover:border-primary hover:bg-primary/10"
              >
                <div className="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                  <Camera className="h-7 w-7 text-primary-foreground" />
                </div>
                <span className="text-base font-medium text-primary">
                  Take Photo
                </span>
              </button>
            )}

            {/* Drop zone / file picker */}
            <div
              className={`flex-1 flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors cursor-pointer ${
                isDragOver
                  ? "border-primary bg-primary/5"
                  : "border-muted-foreground/25 hover:border-muted-foreground/50"
              }`}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
            >
              <Upload className="h-10 w-10 text-muted-foreground mb-3" />
              <p className="text-sm font-medium">
                {isMobile ? "Select from gallery" : "Drop image here or click to upload"}
              </p>
              <p className="text-xs text-muted-foreground mt-1">
                Supports JPG, PNG, WebP up to 10MB
              </p>
              <label className="mt-3">
                <input
                  type="file"
                  multiple
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={handleFileSelect}
                />
                <Button
                  variant="outline"
                  size="sm"
                  className="gap-2 cursor-pointer"
                  asChild
                >
                  <span>
                    <ImageIcon className="h-4 w-4" />
                    Browse files
                  </span>
                </Button>
              </label>
            </div>
          </div>

          {/* Text input */}
          <div className="space-y-2">
            <Label htmlFor="text-input" className="flex items-center gap-1.5">
              <FileText className="h-3.5 w-3.5" />
              Or type / paste product names
            </Label>
            <Textarea
              id="text-input"
              value={textInput}
              onChange={(e) => setTextInput(e.target.value)}
              placeholder="Milk, eggs, bread&#10;Organic chicken breast&#10;Tide laundry detergent 64oz"
              rows={4}
            />
          </div>
        </CardContent>
        <CardFooter className="flex justify-end">
          <Button
            onClick={handleTextSubmit}
            disabled={uploadMutation.isPending || !textInput.trim()}
            className="gap-2"
          >
            {uploadMutation.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Sparkles className="h-4 w-4" />
            )}
            {uploadMutation.isPending ? "Processing..." : "Process with AI"}
          </Button>
        </CardFooter>
      </Card>

      {/* Active AI Jobs */}
      {activeJobs.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Processing</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {activeJobs.map((job) => (
              <div
                key={job.id}
                className="flex items-center gap-3 rounded-md border p-3"
              >
                <Loader2 className="h-4 w-4 animate-spin text-primary shrink-0" />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium capitalize">{job.type}</p>
                  <p className="text-xs text-muted-foreground capitalize">
                    {job.status}
                  </p>
                </div>
                {job.progress != null && (
                  <div className="w-32">
                    <div className="h-2 rounded-full bg-muted overflow-hidden">
                      <div
                        className="h-full rounded-full bg-primary transition-all"
                        style={{ width: `${job.progress}%` }}
                      />
                    </div>
                    <p className="text-xs text-muted-foreground text-right mt-0.5">
                      {job.progress}%
                    </p>
                  </div>
                )}
              </div>
            ))}
          </CardContent>
        </Card>
      )}

      {/* No lists warning */}
      {!queueLoading && lists.length === 0 && readyItems.length > 0 && (
        <div className="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/50 p-4">
          <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0" />
          <div className="flex-1">
            <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
              No shopping lists found
            </p>
            <p className="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
              Create a shopping list first so you can add items from the queue.
            </p>
          </div>
          <Button size="sm" variant="outline" asChild>
            <Link href="/lists/new">Create List</Link>
          </Button>
        </div>
      )}

      {/* Review Queue */}
      <div className="space-y-4">
        <h2 className="text-lg font-semibold">
          Review Queue
          {readyItems.length > 0 && (
            <Badge variant="default" className="ml-2">
              {readyItems.length}
            </Badge>
          )}
        </h2>

        {queueLoading ? (
          <div className="grid gap-4 md:grid-cols-2">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-40 rounded-lg" />
            ))}
          </div>
        ) : readyItems.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-lg border border-dashed p-8 text-center">
            <Sparkles className="h-8 w-8 text-muted-foreground mb-3" />
            <p className="text-sm text-muted-foreground">
              No items waiting for review. Upload something to get started.
            </p>
          </div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2">
            {readyItems.map((queueItem) => (
              <Card key={queueItem.id}>
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <CardTitle className="text-base">
                      {queueItem.source_query || queueItem.input_text || "Uploaded image"}
                    </CardTitle>
                    <Badge variant="secondary">{queueItem.status}</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  {(queueItem.product_data ?? queueItem.suggestions ?? []).map((suggestion, idx) => (
                    <div
                      key={idx}
                      className="flex items-center justify-between rounded-md border p-2 text-sm"
                    >
                      <div>
                        <p className="font-medium">
                          {suggestion.product_name || suggestion.name}
                        </p>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                          {suggestion.retailer && (
                            <span>{suggestion.retailer}</span>
                          )}
                          {(suggestion.price ?? suggestion.typical_price) != null && (
                            <span>${(suggestion.price ?? suggestion.typical_price)!.toFixed(2)}</span>
                          )}
                          {suggestion.confidence != null && (
                            <Badge
                              variant="outline"
                              className="text-[10px] px-1"
                            >
                              {Math.round(suggestion.confidence * 100)}%
                            </Badge>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-1 shrink-0">
                        <Select
                          value={selectedListIds[`${queueItem.id}-${idx}`] ?? ""}
                          onValueChange={(value) =>
                            setSelectedListIds((prev) => ({
                              ...prev,
                              [`${queueItem.id}-${idx}`]: value,
                            }))
                          }
                        >
                          <SelectTrigger className="h-7 w-32 text-xs">
                            <SelectValue placeholder="Select list" />
                          </SelectTrigger>
                          <SelectContent>
                            {lists.map((l) => (
                              <SelectItem key={l.id} value={l.id.toString()}>
                                {l.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7 text-green-600 hover:text-green-700"
                          disabled={
                            acceptMutation.isPending || !selectedListIds[`${queueItem.id}-${idx}`]
                          }
                          onClick={() =>
                            acceptMutation.mutate({
                              id: queueItem.id,
                              selectedIndex: idx,
                              listId: parseInt(selectedListIds[`${queueItem.id}-${idx}`]),
                            })
                          }
                          title="Accept"
                        >
                          <Check className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  ))}
                </CardContent>
                <CardFooter className="flex justify-end pt-0">
                  <Button
                    variant="ghost"
                    size="sm"
                    className="gap-1 text-destructive hover:text-destructive"
                    onClick={() => rejectMutation.mutate(queueItem.id)}
                    disabled={rejectMutation.isPending}
                  >
                    <X className="h-4 w-4" />
                    Reject All
                  </Button>
                </CardFooter>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
