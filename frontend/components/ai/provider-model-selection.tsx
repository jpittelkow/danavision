"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Loader2, RefreshCw } from "lucide-react";
import type { DiscoveredModel, ProviderTemplate } from "@/components/ai/ai-types";

interface ProviderModelSelectionProps {
  template: ProviderTemplate;
  isEditing: boolean;
  model: string;
  onModelChange: (value: string) => void;
  discoveredModels: DiscoveredModel[];
  isDiscovering: boolean;
  isDiscoverDisabled: boolean;
  discoveryError: string | null;
  onDiscoverModels: () => void;
  onRefreshModels: () => void;
}

export function ProviderModelSelection({
  template,
  isEditing,
  model,
  onModelChange,
  discoveredModels,
  isDiscovering,
  isDiscoverDisabled,
  discoveryError,
  onDiscoverModels,
  onRefreshModels,
}: ProviderModelSelectionProps) {
  if (!template.supports_discovery) return null;

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2 flex-wrap">
        <Label className="mb-0">{isEditing ? "Model" : "Models"}</Label>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onDiscoverModels}
          disabled={isDiscoverDisabled}
        >
          {isDiscovering ? (
            <Loader2 className="mr-1 h-3 w-3 animate-spin" />
          ) : null}
          Fetch Models
        </Button>
        {discoveredModels.length > 0 && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onRefreshModels}
            disabled={isDiscovering}
          >
            <RefreshCw className={`mr-1 h-3 w-3 ${isDiscovering ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        )}
      </div>
      {discoveryError && (
        <p className="text-sm text-destructive">{discoveryError}</p>
      )}
      {discoveredModels.length > 0 ? (
        <Select value={model} onValueChange={onModelChange}>
          <SelectTrigger>
            <SelectValue placeholder="Select a model" />
          </SelectTrigger>
          <SelectContent>
            {isEditing && model && !discoveredModels.some((m) => m.id === model) && (
              <SelectItem key={model} value={model}>
                {model} (current)
              </SelectItem>
            )}
            {discoveredModels.map((m) => (
              <SelectItem key={m.id} value={m.id}>
                {m.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ) : isEditing ? (
        <div className="space-y-1">
          <Input
            value={model}
            onChange={(e) => onModelChange(e.target.value)}
            placeholder="Enter model name"
          />
          <p className="text-sm text-muted-foreground">
            Enter your API key above and click Fetch Models to browse, or type a model name directly.
          </p>
        </div>
      ) : (
        <p className="text-sm text-muted-foreground">
          Enter your API key (or host for Ollama) and click Fetch Models.
        </p>
      )}
    </div>
  );
}
