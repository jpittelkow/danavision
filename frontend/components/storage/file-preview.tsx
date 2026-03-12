"use client";

import { useState, useEffect, useRef, useCallback } from "react";
import Image from "next/image";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Download, FileText } from "lucide-react";
import { fileManagerApi } from "@/lib/api";
import type { FileManagerItem } from "@/lib/api";

const IMAGE_EXTENSIONS = ["jpg", "jpeg", "png", "gif", "webp", "svg"];
const TEXT_EXTENSIONS = ["txt", "json", "md", "log", "csv", "xml", "yaml", "yml", "html", "css", "js", "ts"];

export interface FilePreviewProps {
  item: FileManagerItem;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onDownload: () => void;
}

export function FilePreview({ item, open, onOpenChange, onDownload }: FilePreviewProps) {
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [textContent, setTextContent] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const blobUrlRef = useRef<string | null>(null);

  const ext = item.name.split(".").pop()?.toLowerCase();
  const isImage = ext && IMAGE_EXTENSIONS.includes(ext);
  const isPdf = ext === "pdf";
  const isText = ext && TEXT_EXTENSIONS.includes(ext);

  const loadPreview = useCallback(async () => {
    try {
      const res = await fileManagerApi.getFile(item.path);
      const data = res.data as FileManagerItem & { previewUrl?: string | null };
      if (data.previewUrl) {
        setPreviewUrl(data.previewUrl);
        setLoading(false);
        return;
      }
      if (isImage || isPdf) {
        const blobRes = await fileManagerApi.downloadFile(item.path);
        const blob = blobRes.data as unknown as Blob;
        const url = URL.createObjectURL(blob);
        blobUrlRef.current = url;
        setPreviewUrl(url);
      } else if (isText) {
        const blobRes = await fileManagerApi.downloadFile(item.path);
        const blob = blobRes.data as unknown as Blob;
        const text = await blob.text();
        setTextContent(text);
      }
    } catch {
      setError("Could not load preview.");
    } finally {
      setLoading(false);
    }
  }, [item.path, isImage, isPdf, isText]);

  useEffect(() => {
    if (!open || !item) return;
    setLoading(true);
    setError(null);
    setPreviewUrl(null);
    setTextContent(null);
    if (blobUrlRef.current) {
      URL.revokeObjectURL(blobUrlRef.current);
      blobUrlRef.current = null;
    }

    loadPreview();
    return () => {
      if (blobUrlRef.current) {
        URL.revokeObjectURL(blobUrlRef.current);
        blobUrlRef.current = null;
      }
    };
  }, [open, item, loadPreview]);

  const handleClose = () => {
    setPreviewUrl(null);
    setTextContent(null);
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-3xl max-h-[90vh] flex flex-col">
        <DialogHeader>
          <DialogTitle className="truncate pr-8">{item.name}</DialogTitle>
          <DialogDescription>
            {item.path} {item.size != null && `• ${(item.size / 1024).toFixed(1)} KB`}
          </DialogDescription>
        </DialogHeader>

        <div className="flex-1 min-h-0 overflow-auto rounded-md border bg-muted/30 p-4">
          {loading && (
            <div className="flex items-center justify-center py-16 text-muted-foreground">
              Loading…
            </div>
          )}
          {error && (
            <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
              <FileText className="h-12 w-12 mb-2 opacity-50" />
              <p>{error}</p>
            </div>
          )}
          {!loading && !error && previewUrl && isImage && (
            <div className="relative w-full min-h-[200px]">
              <Image
                src={previewUrl}
                alt={item.name}
                fill
                className="object-contain"
                unoptimized
              />
            </div>
          )}
          {!loading && !error && previewUrl && isPdf && (
            <iframe
              src={previewUrl}
              title={item.name}
              className="w-full h-[70vh] border-0 rounded"
            />
          )}
          {!loading && !error && textContent !== null && (
            <pre className="text-sm overflow-auto whitespace-pre-wrap break-words font-mono">
              {textContent}
            </pre>
          )}
          {!loading && !error && !previewUrl && textContent === null && !isImage && !isPdf && !isText && (
            <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
              <FileText className="h-12 w-12 mb-2 opacity-50" />
              <p>Preview not available for this file type.</p>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={handleClose}>
            Close
          </Button>
          <Button onClick={onDownload}>
            <Download className="h-4 w-4 mr-2" />
            Download
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
