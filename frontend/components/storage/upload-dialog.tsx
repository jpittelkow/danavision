"use client";

import { useState, useCallback } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Upload, X, Loader2, File } from "lucide-react";
import { fileManagerApi } from "@/lib/api";

export interface UploadDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  currentPath: string;
  /** Max upload size in bytes (matches storage settings). */
  maxUploadSizeBytes?: number;
  allowedFileTypes?: string[];
  onUploadComplete: () => void;
}

export function UploadDialog({
  open,
  onOpenChange,
  currentPath,
  maxUploadSizeBytes = 10485760,
  allowedFileTypes = [],
  onUploadComplete,
}: UploadDialogProps) {
  const [files, setFiles] = useState<File[]>([]);
  const [dragActive, setDragActive] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const maxBytes = maxUploadSizeBytes;

  const validateFiles = useCallback(
    (fileList: File[]): string[] => {
      const errors: string[] = [];
      for (const f of fileList) {
        if (f.size > maxBytes) {
          errors.push(`${f.name}: exceeds max size (${(maxBytes / 1024 / 1024).toFixed(1)} MB).`);
        } else if (allowedFileTypes.length > 0) {
          const ext = f.name.split(".").pop()?.toLowerCase();
          if (!ext || !allowedFileTypes.map((t) => t.toLowerCase()).includes(ext)) {
            errors.push(`${f.name}: type not allowed.`);
          }
        }
      }
      return errors;
    },
    [maxBytes, allowedFileTypes]
  );

  const addFiles = useCallback(
    (newFiles: FileList | File[]) => {
      const list = Array.from(newFiles);
      const errs = validateFiles(list);
      if (errs.length > 0) {
        setError(errs[0]);
        return;
      }
      setError(null);
      setFiles((prev) => [...prev, ...list]);
    },
    [validateFiles]
  );

  const removeFile = (index: number) => {
    setFiles((prev) => prev.filter((_, i) => i !== index));
    setError(null);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setDragActive(false);
    if (e.dataTransfer.files?.length) addFiles(e.dataTransfer.files);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setDragActive(true);
  };

  const handleDragLeave = () => setDragActive(false);

  const handleUpload = async () => {
    if (files.length === 0) return;
    setUploading(true);
    setError(null);
    try {
      await fileManagerApi.uploadFiles(currentPath, files);
      setFiles([]);
      onUploadComplete();
      onOpenChange(false);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Upload failed.");
    } finally {
      setUploading(false);
    }
  };

  const handleClose = () => {
    if (!uploading) {
      setFiles([]);
      setError(null);
      onOpenChange(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Upload files</DialogTitle>
          <DialogDescription>
            {currentPath ? `Upload to: ${currentPath}` : "Upload to root. Max size and allowed types follow storage settings."}
          </DialogDescription>
        </DialogHeader>

        <div
          onDrop={handleDrop}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
            dragActive ? "border-primary bg-primary/5" : "border-muted-foreground/25"
          }`}
        >
          <input
            type="file"
            multiple
            className="hidden"
            id="file-manager-upload-input"
            onChange={(e) => {
              if (e.target.files?.length) addFiles(e.target.files);
              e.target.value = "";
            }}
          />
          <label htmlFor="file-manager-upload-input" className="cursor-pointer block">
            <Upload className="h-10 w-10 mx-auto text-muted-foreground mb-2" />
            <p className="text-sm font-medium">Drop files here or click to browse</p>
            <p className="text-xs text-muted-foreground mt-1">
              Max {(maxUploadSizeBytes / 1024 / 1024).toFixed(1)} MB per file
              {allowedFileTypes.length > 0 && ` • Allowed: ${allowedFileTypes.join(", ")}`}
            </p>
          </label>
        </div>

        {files.length > 0 && (
          <div className="space-y-2 max-h-40 overflow-y-auto">
            <p className="text-sm font-medium">Selected ({files.length})</p>
            <ul className="space-y-1">
              {files.map((f, i) => (
                <li key={`${f.name}-${i}`} className="flex items-center justify-between text-sm gap-2">
                  <span className="flex items-center gap-2 truncate">
                    <File className="h-4 w-4 shrink-0 text-muted-foreground" />
                    {f.name} ({(f.size / 1024).toFixed(1)} KB)
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-11 w-11 shrink-0"
                    onClick={() => removeFile(i)}
                    disabled={uploading}
                  >
                    <X className="h-4 w-4" />
                  </Button>
                </li>
              ))}
            </ul>
          </div>
        )}

        {error && (
          <p className="text-sm text-destructive">{error}</p>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={handleClose} disabled={uploading}>
            Cancel
          </Button>
          <Button onClick={handleUpload} disabled={files.length === 0 || uploading}>
            {uploading ? (
              <>
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                Uploading…
              </>
            ) : (
              "Upload"
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
