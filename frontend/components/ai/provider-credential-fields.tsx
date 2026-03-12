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
import { Loader2, CheckCircle, XCircle } from "lucide-react";
import type { AIProvider, ProviderTemplate } from "@/components/ai/ai-types";

interface ProviderCredentialFieldsProps {
  template: ProviderTemplate;
  activeProvider: string;
  isEditing: boolean;
  editingProvider?: AIProvider | null;
  apiKey: string;
  onApiKeyChange: (value: string) => void;
  baseUrl: string;
  onBaseUrlChange: (value: string) => void;
  endpoint: string;
  onEndpointChange: (value: string) => void;
  region: string;
  onRegionChange: (value: string) => void;
  accessKey: string;
  onAccessKeyChange: (value: string) => void;
  secretKey: string;
  onSecretKeyChange: (value: string) => void;
  isTestingKey: boolean;
  isTestDisabled: boolean;
  keyValid: boolean | null;
  keyError: string | null;
  onTestApiKey: () => void;
}

export function ProviderCredentialFields({
  template,
  activeProvider,
  isEditing,
  editingProvider,
  apiKey,
  onApiKeyChange,
  baseUrl,
  onBaseUrlChange,
  endpoint,
  onEndpointChange,
  region,
  onRegionChange,
  accessKey,
  onAccessKeyChange,
  secretKey,
  onSecretKeyChange,
  isTestingKey,
  isTestDisabled,
  keyValid,
  keyError,
  onTestApiKey,
}: ProviderCredentialFieldsProps) {
  return (
    <>
      {template.requires_api_key && (
        <div className="space-y-2">
          <Label>API Key</Label>
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Input
                type="password"
                value={apiKey}
                onChange={(e) => onApiKeyChange(e.target.value)}
                placeholder={isEditing && editingProvider?.api_key_set ? "Leave blank to keep current" : "Enter your API key"}
              />
              {keyValid === true && (
                <CheckCircle className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600 dark:text-green-400" />
              )}
              {keyValid === false && (
                <XCircle className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-destructive" />
              )}
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={onTestApiKey}
              disabled={isTestDisabled}
            >
              {isTestingKey ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                "Test"
              )}
            </Button>
          </div>
          {keyError && (
            <p className="text-sm text-destructive">{keyError}</p>
          )}
        </div>
      )}

      {activeProvider === "azure" && (
        <div className="space-y-2">
          <Label>Azure OpenAI endpoint</Label>
          <Input
            type="url"
            value={endpoint}
            onChange={(e) => onEndpointChange(e.target.value)}
            placeholder="https://your-resource.openai.azure.com"
          />
        </div>
      )}

      {activeProvider === "bedrock" && (
        <>
          <div className="space-y-2">
            <Label>AWS Region</Label>
            <Select value={region} onValueChange={onRegionChange}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="us-east-1">us-east-1</SelectItem>
                <SelectItem value="us-west-2">us-west-2</SelectItem>
                <SelectItem value="eu-west-1">eu-west-1</SelectItem>
                <SelectItem value="eu-central-1">eu-central-1</SelectItem>
                <SelectItem value="ap-northeast-1">ap-northeast-1</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>Access Key ID</Label>
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Input
                  type="password"
                  value={accessKey}
                  onChange={(e) => onAccessKeyChange(e.target.value)}
                  placeholder={isEditing && editingProvider?.access_key_set ? "Leave blank to keep current" : "AKIA..."}
                />
                {keyValid === true && (
                  <CheckCircle className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600 dark:text-green-400" />
                )}
                {keyValid === false && (
                  <XCircle className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-destructive" />
                )}
              </div>
              <Button
                type="button"
                variant="outline"
                onClick={onTestApiKey}
                disabled={!accessKey?.trim() || !secretKey?.trim() || isTestingKey}
              >
                {isTestingKey ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  "Test"
                )}
              </Button>
            </div>
          </div>
          <div className="space-y-2">
            <Label>Secret Access Key</Label>
            <Input
              type="password"
              value={secretKey}
              onChange={(e) => onSecretKeyChange(e.target.value)}
              placeholder={isEditing && editingProvider?.secret_key_set ? "Leave blank to keep current" : "Enter secret key"}
            />
          </div>
          {keyError && (
            <p className="text-sm text-destructive">{keyError}</p>
          )}
        </>
      )}

      {activeProvider === "ollama" && (
        <div className="space-y-2">
          <Label>Ollama host</Label>
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Input
                type="text"
                value={baseUrl}
                onChange={(e) => onBaseUrlChange(e.target.value)}
                placeholder="http://localhost:11434"
              />
              {keyValid === true && (
                <CheckCircle className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600 dark:text-green-400" />
              )}
              {keyValid === false && (
                <XCircle className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-destructive" />
              )}
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={onTestApiKey}
              disabled={!baseUrl?.trim() || isTestingKey}
            >
              {isTestingKey ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                "Test"
              )}
            </Button>
          </div>
          {keyError && (
            <p className="text-sm text-destructive">{keyError}</p>
          )}
        </div>
      )}
    </>
  );
}
