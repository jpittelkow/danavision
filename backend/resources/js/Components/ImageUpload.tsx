import { useRef, useState } from 'react';
import { cn, isMobileDevice } from '@/lib/utils';
import { Camera, Upload, Loader2, X, Image as ImageIcon } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface ImageUploadProps {
  onImageSelect: (base64: string) => void;
  onClear?: () => void;
  value?: string | null;
  isLoading?: boolean;
  maxSizeMB?: number;
  accept?: string;
  className?: string;
  showPreview?: boolean;
  previewClassName?: string;
  label?: string;
  hint?: string;
  error?: string;
}

export function ImageUpload({
  onImageSelect,
  onClear,
  value,
  isLoading = false,
  maxSizeMB = 10,
  accept = 'image/jpeg,image/png,image/webp',
  className,
  showPreview = true,
  previewClassName,
  label,
  hint,
  error,
}: ImageUploadProps) {
  const [isDragging, setIsDragging] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);

  const isMobile = isMobileDevice();

  const handleFileSelect = (file: File) => {
    if (!file.type.startsWith('image/')) {
      return;
    }

    if (file.size > maxSizeMB * 1024 * 1024) {
      alert(`Image must be less than ${maxSizeMB}MB`);
      return;
    }

    const reader = new FileReader();
    reader.onloadend = () => {
      onImageSelect(reader.result as string);
    };
    reader.readAsDataURL(file);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      handleFileSelect(file);
    }
    // Reset input so same file can be selected again
    if (e.target) {
      e.target.value = '';
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const file = e.dataTransfer.files[0];
    if (file) {
      handleFileSelect(file);
    }
  };

  const handleClear = () => {
    if (onClear) {
      onClear();
    }
  };

  // If we have a value and showPreview is true, show the preview
  if (value && showPreview) {
    return (
      <div className={cn('relative', className)}>
        {label && (
          <label className="block text-sm font-medium text-foreground mb-2">
            {label}
          </label>
        )}
        <div className={cn(
          'relative rounded-lg overflow-hidden border border-border bg-muted',
          previewClassName
        )}>
          <img
            src={value}
            alt="Preview"
            className="w-full h-full object-contain"
          />
          {onClear && (
            <Button
              type="button"
              variant="destructive"
              size="icon"
              className="absolute top-2 right-2 h-8 w-8"
              onClick={handleClear}
              disabled={isLoading}
            >
              <X className="h-4 w-4" />
            </Button>
          )}
          {isLoading && (
            <div className="absolute inset-0 bg-background/80 flex items-center justify-center">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          )}
        </div>
        {error && (
          <p className="text-sm text-destructive mt-1">{error}</p>
        )}
      </div>
    );
  }

  return (
    <div className={className}>
      {label && (
        <label className="block text-sm font-medium text-foreground mb-2">
          {label}
        </label>
      )}
      
      {/* Hidden file inputs */}
      <input
        ref={fileInputRef}
        type="file"
        accept={accept}
        onChange={handleInputChange}
        className="hidden"
      />
      <input
        ref={cameraInputRef}
        type="file"
        accept={accept}
        capture="environment"
        onChange={handleInputChange}
        className="hidden"
      />

      <div className="flex flex-col sm:flex-row gap-4">
        {/* Camera button - shown on mobile */}
        {isMobile && (
          <button
            type="button"
            onClick={() => cameraInputRef.current?.click()}
            disabled={isLoading}
            className={cn(
              'flex flex-col items-center justify-center gap-3 py-6 px-6 border-2 border-dashed rounded-xl transition-colors',
              'border-primary/50 bg-primary/5 hover:border-primary hover:bg-primary/10',
              'disabled:opacity-50 disabled:cursor-not-allowed'
            )}
          >
            <div className="w-12 h-12 rounded-full bg-primary/20 flex items-center justify-center">
              <Camera className="h-6 w-6 text-primary" />
            </div>
            <span className="text-sm font-medium text-primary">Take Photo</span>
          </button>
        )}

        {/* Upload area */}
        <div
          className={cn(
            'flex-1 border-2 border-dashed rounded-xl p-6 sm:p-8 text-center transition-colors',
            isLoading ? 'cursor-wait' : 'cursor-pointer',
            isDragging
              ? 'border-primary bg-primary/5'
              : 'border-muted-foreground/30 hover:border-muted-foreground/50 hover:bg-muted/50'
          )}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={() => !isLoading && fileInputRef.current?.click()}
        >
          <div className="flex flex-col items-center gap-3">
            <div className="w-12 h-12 rounded-full bg-muted flex items-center justify-center">
              {isLoading ? (
                <Loader2 className="h-6 w-6 text-muted-foreground animate-spin" />
              ) : (
                <Upload className="h-6 w-6 text-muted-foreground" />
              )}
            </div>
            <div>
              <p className="text-sm font-medium text-foreground">
                {isLoading
                  ? 'Processing...'
                  : isMobile
                    ? 'Select from gallery'
                    : 'Drop image here or click to upload'
                }
              </p>
              {hint && !isLoading && (
                <p className="text-xs text-muted-foreground mt-1">{hint}</p>
              )}
              {!hint && !isLoading && (
                <p className="text-xs text-muted-foreground mt-1">
                  Supports JPG, PNG, WebP up to {maxSizeMB}MB
                </p>
              )}
            </div>
          </div>
        </div>
      </div>

      {error && (
        <p className="text-sm text-destructive mt-2">{error}</p>
      )}
    </div>
  );
}

/**
 * Compact image input for forms - shows a small preview or placeholder
 */
interface CompactImageInputProps {
  onImageSelect: (base64: string) => void;
  onClear?: () => void;
  value?: string | null;
  isLoading?: boolean;
  maxSizeMB?: number;
  accept?: string;
  className?: string;
  label?: string;
  error?: string;
}

export function CompactImageInput({
  onImageSelect,
  onClear,
  value,
  isLoading = false,
  maxSizeMB = 10,
  accept = 'image/jpeg,image/png,image/webp',
  className,
  label,
  error,
}: CompactImageInputProps) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const cameraInputRef = useRef<HTMLInputElement>(null);

  const isMobile = isMobileDevice();

  const handleFileSelect = (file: File) => {
    if (!file.type.startsWith('image/')) {
      return;
    }

    if (file.size > maxSizeMB * 1024 * 1024) {
      alert(`Image must be less than ${maxSizeMB}MB`);
      return;
    }

    const reader = new FileReader();
    reader.onloadend = () => {
      onImageSelect(reader.result as string);
    };
    reader.readAsDataURL(file);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      handleFileSelect(file);
    }
    if (e.target) {
      e.target.value = '';
    }
  };

  return (
    <div className={className}>
      {label && (
        <label className="block text-sm font-medium text-foreground mb-2">
          {label}
        </label>
      )}
      
      <input
        ref={fileInputRef}
        type="file"
        accept={accept}
        onChange={handleInputChange}
        className="hidden"
      />
      <input
        ref={cameraInputRef}
        type="file"
        accept={accept}
        capture="environment"
        onChange={handleInputChange}
        className="hidden"
      />

      <div className="flex items-center gap-3">
        {/* Preview or placeholder */}
        <div 
          className={cn(
            'w-16 h-16 rounded-lg border-2 border-dashed overflow-hidden flex items-center justify-center',
            isLoading ? 'cursor-wait' : 'cursor-pointer',
            value ? 'border-border' : 'border-muted-foreground/30 hover:border-muted-foreground/50'
          )}
          onClick={() => !isLoading && fileInputRef.current?.click()}
        >
          {isLoading ? (
            <Loader2 className="h-5 w-5 text-muted-foreground animate-spin" />
          ) : value ? (
            <img src={value} alt="Preview" className="w-full h-full object-cover" />
          ) : (
            <ImageIcon className="h-6 w-6 text-muted-foreground" />
          )}
        </div>

        <div className="flex gap-2">
          {isMobile && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => cameraInputRef.current?.click()}
              disabled={isLoading}
            >
              <Camera className="h-4 w-4 mr-1" />
              Camera
            </Button>
          )}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => fileInputRef.current?.click()}
            disabled={isLoading}
          >
            <Upload className="h-4 w-4 mr-1" />
            Upload
          </Button>
          {value && onClear && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onClear}
              disabled={isLoading}
            >
              <X className="h-4 w-4" />
            </Button>
          )}
        </div>
      </div>

      {error && (
        <p className="text-sm text-destructive mt-1">{error}</p>
      )}
    </div>
  );
}

export default ImageUpload;
