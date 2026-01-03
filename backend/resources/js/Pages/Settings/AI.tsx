import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import {
  Star,
  Plus,
  Trash2,
  Eye,
  EyeOff,
  RefreshCw,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Server,
  Zap,
  Brain,
  Sparkles,
  FileText,
  RotateCcw,
  Save,
  Loader2,
} from 'lucide-react';

interface AIProviderData {
  id: number;
  provider: string;
  model: string | null;
  base_url: string | null;
  is_active: boolean;
  is_primary: boolean;
  has_api_key: boolean;
  masked_api_key: string | null;
  test_status: 'untested' | 'success' | 'failed';
  test_error: string | null;
  last_tested_at: string | null;
  display_name: string;
  available_models: Record<string, string>;
}

interface AvailableProvider {
  provider: string;
  name: string;
  company: string;
  models: Record<string, string>;
  default_model: string;
  default_base_url: string | null;
  requires_api_key: boolean;
}

interface PromptData {
  type: string;
  name: string;
  description: string;
  prompt_text: string;
  is_customized: boolean;
  default_text: string;
}

interface Props extends PageProps {
  providers: AIProviderData[];
  availableProviders: AvailableProvider[];
  providerInfo: Record<string, {
    name: string;
    company: string;
    icon: string;
    models: Record<string, string>;
    default_model: string;
    requires_api_key: boolean;
  }>;
  prompts: Record<string, PromptData>;
}

const providerIcons: Record<string, React.ReactNode> = {
  claude: <Brain className="h-5 w-5" />,
  openai: <Sparkles className="h-5 w-5" />,
  gemini: <Zap className="h-5 w-5" />,
  local: <Server className="h-5 w-5" />,
};

function ProviderCard({ provider, onUpdate }: { provider: AIProviderData; onUpdate: () => void }) {
  const [showKey, setShowKey] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [isTesting, setIsTesting] = useState(false);

  const { data, setData, patch, processing } = useForm({
    api_key: '',
    model: provider.model || '',
    base_url: provider.base_url || '',
    is_active: provider.is_active,
  });

  const handleTest = () => {
    setIsTesting(true);
    router.post(`/ai-providers/${provider.id}/test`, {}, {
      preserveScroll: true,
      onFinish: () => {
        setIsTesting(false);
        onUpdate();
      },
    });
  };

  const handleSetPrimary = () => {
    router.post(`/ai-providers/${provider.id}/primary`, {}, {
      preserveScroll: true,
      onFinish: onUpdate,
    });
  };

  const handleToggleActive = () => {
    router.post(`/ai-providers/${provider.id}/toggle`, {}, {
      preserveScroll: true,
      onFinish: onUpdate,
    });
  };

  const handleDelete = () => {
    if (confirm(`Are you sure you want to remove ${provider.display_name}?`)) {
      router.delete(`/ai-providers/${provider.id}`, {
        preserveScroll: true,
        onFinish: onUpdate,
      });
    }
  };

  const handleSave = (e: FormEvent) => {
    e.preventDefault();
    patch(`/ai-providers/${provider.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setIsEditing(false);
        onUpdate();
      },
    });
  };

  const testStatusBadge = () => {
    switch (provider.test_status) {
      case 'success':
        return (
          <Badge variant="success" className="gap-1">
            <CheckCircle2 className="h-3 w-3" />
            Verified
          </Badge>
        );
      case 'failed':
        return (
          <Badge variant="destructive" className="gap-1">
            <XCircle className="h-3 w-3" />
            Failed
          </Badge>
        );
      default:
        return (
          <Badge variant="secondary" className="gap-1">
            <AlertCircle className="h-3 w-3" />
            Untested
          </Badge>
        );
    }
  };

  return (
    <Card className={`relative ${!provider.is_active ? 'opacity-60' : ''}`}>
      {provider.is_primary && (
        <div className="absolute -top-2 -right-2 bg-yellow-500 text-white rounded-full p-1">
          <Star className="h-4 w-4 fill-current" />
        </div>
      )}

      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-primary/10 text-primary">
              {providerIcons[provider.provider] || <Brain className="h-5 w-5" />}
            </div>
            <div>
              <CardTitle className="text-lg">{provider.display_name}</CardTitle>
              <CardDescription>{provider.model}</CardDescription>
            </div>
          </div>
          <div className="flex items-center gap-2">
            {testStatusBadge()}
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-4">
        {isEditing ? (
          <form onSubmit={handleSave} className="space-y-4">
            {provider.provider !== 'local' && (
              <div>
                <Label htmlFor={`api_key_${provider.id}`}>API Key</Label>
                <div className="relative mt-1">
                  <Input
                    id={`api_key_${provider.id}`}
                    type={showKey ? 'text' : 'password'}
                    value={data.api_key}
                    onChange={(e) => setData('api_key', e.target.value)}
                    placeholder={provider.has_api_key ? 'Leave blank to keep current' : 'Enter API key'}
                    className="pr-10"
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="absolute right-0 top-0 h-full px-3"
                    onClick={() => setShowKey(!showKey)}
                  >
                    {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </Button>
                </div>
              </div>
            )}

            <div>
              <Label htmlFor={`model_${provider.id}`}>Model</Label>
              <Select
                value={data.model}
                onValueChange={(value) => setData('model', value)}
              >
                <SelectTrigger className="mt-1">
                  <SelectValue placeholder="Select a model" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(provider.available_models).map(([key, name]) => (
                    <SelectItem key={key} value={key}>
                      {name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {(provider.provider === 'local' || provider.provider === 'gemini') && (
              <div>
                <Label htmlFor={`base_url_${provider.id}`}>
                  {provider.provider === 'gemini' ? 'Base URL (Optional)' : 'Base URL'}
                </Label>
                <Input
                  id={`base_url_${provider.id}`}
                  type="url"
                  value={data.base_url}
                  onChange={(e) => setData('base_url', e.target.value)}
                  placeholder={provider.provider === 'gemini' 
                    ? 'https://generativelanguage.googleapis.com/v1beta' 
                    : 'http://localhost:11434'}
                  className="mt-1"
                />
                {provider.provider === 'gemini' && (
                  <p className="text-xs text-muted-foreground mt-1">
                    Leave blank to use the default Google API endpoint
                  </p>
                )}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsEditing(false)}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={processing}>
                Save Changes
              </Button>
            </div>
          </form>
        ) : (
          <>
            <div className="flex items-center justify-between text-sm">
              <span className="text-muted-foreground">API Key</span>
              <span className="font-mono text-muted-foreground">
                {provider.has_api_key ? 'API key saved ••••••••' : 'Not configured'}
              </span>
            </div>

            {(provider.provider === 'local' || provider.provider === 'gemini') && provider.base_url && (
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Base URL</span>
                <span className="font-mono text-xs truncate max-w-[200px]" title={provider.base_url}>
                  {provider.base_url}
                </span>
              </div>
            )}

            {provider.last_tested_at && (
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Last tested</span>
                <span>{new Date(provider.last_tested_at).toLocaleDateString()}</span>
              </div>
            )}

            {provider.test_error && (
              <div className="text-sm text-destructive bg-destructive/10 rounded-lg p-2">
                {provider.test_error}
              </div>
            )}

            <div className="flex items-center justify-between pt-2 border-t">
              <div className="flex items-center gap-2">
                <Switch
                  checked={provider.is_active}
                  onCheckedChange={handleToggleActive}
                />
                <span className="text-sm">{provider.is_active ? 'Active' : 'Inactive'}</span>
              </div>

              <div className="flex items-center gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setIsEditing(true)}
                >
                  Edit
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleTest}
                  disabled={isTesting}
                >
                  <RefreshCw className={`h-4 w-4 mr-1 ${isTesting ? 'animate-spin' : ''}`} />
                  Test
                </Button>
                {!provider.is_primary && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleSetPrimary}
                    title="Set as primary"
                  >
                    <Star className="h-4 w-4" />
                  </Button>
                )}
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleDelete}
                  className="text-destructive hover:text-destructive"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
}

function AddProviderDialog({ availableProviders, onAdd }: { availableProviders: AvailableProvider[]; onAdd: () => void }) {
  const [open, setOpen] = useState(false);
  const [showKey, setShowKey] = useState(false);

  const { data, setData, post, processing, reset, errors } = useForm({
    provider: '',
    api_key: '',
    model: '',
    base_url: '',
    is_primary: false,
  });

  const selectedProvider = availableProviders.find((p) => p.provider === data.provider);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/settings/ai', {
      preserveScroll: true,
      onSuccess: () => {
        setOpen(false);
        reset();
        onAdd();
      },
    });
  };

  const handleProviderChange = (value: string) => {
    const provider = availableProviders.find((p) => p.provider === value);
    setData({
      ...data,
      provider: value,
      model: provider?.default_model || '',
      base_url: provider?.default_base_url || '',
    });
  };

  if (availableProviders.length === 0) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button className="gap-2">
          <Plus className="h-4 w-4" />
          Add Provider
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add AI Provider</DialogTitle>
          <DialogDescription>
            Configure a new AI provider for multi-model queries.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="provider">Provider</Label>
            <Select
              value={data.provider}
              onValueChange={handleProviderChange}
            >
              <SelectTrigger className="mt-1">
                <SelectValue placeholder="Select a provider" />
              </SelectTrigger>
              <SelectContent>
                {availableProviders.map((p) => (
                  <SelectItem key={p.provider} value={p.provider}>
                    <div className="flex items-center gap-2">
                      {providerIcons[p.provider]}
                      <span>{p.name}</span>
                      <span className="text-muted-foreground text-xs">({p.company})</span>
                    </div>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.provider && <p className="text-destructive text-sm mt-1">{errors.provider}</p>}
          </div>

          {selectedProvider && selectedProvider.requires_api_key && (
            <div>
              <Label htmlFor="api_key">API Key</Label>
              <div className="relative mt-1">
                <Input
                  id="api_key"
                  type={showKey ? 'text' : 'password'}
                  value={data.api_key}
                  onChange={(e) => setData('api_key', e.target.value)}
                  placeholder="Enter your API key"
                  className="pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="absolute right-0 top-0 h-full px-3"
                  onClick={() => setShowKey(!showKey)}
                >
                  {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </Button>
              </div>
            </div>
          )}

          {selectedProvider && Object.keys(selectedProvider.models).length > 0 && (
            <div>
              <Label htmlFor="model">Model</Label>
              <Select
                value={data.model}
                onValueChange={(value) => setData('model', value)}
              >
                <SelectTrigger className="mt-1">
                  <SelectValue placeholder="Select a model" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(selectedProvider.models).map(([key, name]) => (
                    <SelectItem key={key} value={key}>
                      {name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          {(selectedProvider?.provider === 'local' || selectedProvider?.provider === 'gemini') && (
            <div>
              <Label htmlFor="base_url">
                {selectedProvider.provider === 'gemini' ? 'Base URL (Optional)' : 'Ollama Base URL'}
              </Label>
              <Input
                id="base_url"
                type="url"
                value={data.base_url}
                onChange={(e) => setData('base_url', e.target.value)}
                placeholder={selectedProvider.provider === 'gemini' 
                  ? 'https://generativelanguage.googleapis.com/v1beta' 
                  : 'http://localhost:11434'}
                className="mt-1"
              />
              {selectedProvider.provider === 'gemini' && (
                <p className="text-xs text-muted-foreground mt-1">
                  Leave blank to use the default Google API endpoint
                </p>
              )}
            </div>
          )}

          <div className="flex items-center gap-2">
            <Switch
              id="is_primary"
              checked={data.is_primary}
              onCheckedChange={(checked) => setData('is_primary', checked)}
            />
            <Label htmlFor="is_primary">Set as primary provider</Label>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={processing || !data.provider}>
              Add Provider
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function PromptEditor({ prompt, onUpdate }: { prompt: PromptData; onUpdate: () => void }) {
  const [isEditing, setIsEditing] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isResetting, setIsResetting] = useState(false);
  const [editedText, setEditedText] = useState(prompt.prompt_text);

  const handleSave = () => {
    setIsSaving(true);
    router.patch('/settings/ai/prompts', {
      prompt_type: prompt.type,
      prompt_text: editedText,
    }, {
      preserveScroll: true,
      onFinish: () => {
        setIsSaving(false);
        setIsEditing(false);
        onUpdate();
      },
    });
  };

  const handleReset = () => {
    if (!confirm('Are you sure you want to reset this prompt to the default?')) {
      return;
    }
    setIsResetting(true);
    router.post('/settings/ai/prompts/reset', {
      prompt_type: prompt.type,
    }, {
      preserveScroll: true,
      onFinish: () => {
        setIsResetting(false);
        setEditedText(prompt.default_text);
        onUpdate();
      },
    });
  };

  const handleCancel = () => {
    setEditedText(prompt.prompt_text);
    setIsEditing(false);
  };

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-lg flex items-center gap-2">
              <FileText className="h-5 w-5" />
              {prompt.name}
              {prompt.is_customized && (
                <Badge variant="secondary" className="ml-2">Customized</Badge>
              )}
            </CardTitle>
            <CardDescription>{prompt.description}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent>
        {isEditing ? (
          <div className="space-y-4">
            <textarea
              value={editedText}
              onChange={(e) => setEditedText(e.target.value)}
              className="w-full h-64 px-3 py-2 text-sm font-mono rounded-md border border-input bg-background focus:border-primary focus:ring-1 focus:ring-primary resize-y"
              placeholder="Enter your custom prompt..."
            />
            <div className="flex justify-between">
              <Button
                variant="outline"
                onClick={handleReset}
                disabled={isResetting || !prompt.is_customized}
                className="gap-2"
              >
                {isResetting ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <RotateCcw className="h-4 w-4" />
                )}
                Reset to Default
              </Button>
              <div className="flex gap-2">
                <Button variant="outline" onClick={handleCancel}>
                  Cancel
                </Button>
                <Button onClick={handleSave} disabled={isSaving} className="gap-2">
                  {isSaving ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4" />
                  )}
                  Save Prompt
                </Button>
              </div>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="bg-muted rounded-lg p-4 max-h-48 overflow-y-auto">
              <pre className="text-sm font-mono whitespace-pre-wrap text-muted-foreground">
                {prompt.prompt_text}
              </pre>
            </div>
            <div className="flex justify-end">
              <Button variant="outline" onClick={() => setIsEditing(true)}>
                Edit Prompt
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default function AISettings({ auth, providers, availableProviders, providerInfo, prompts, flash }: Props) {
  const handleUpdate = () => {
    router.reload({ only: ['providers', 'prompts'] });
  };

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="AI Settings" />

      <div className="p-6 lg:p-8 max-w-4xl mx-auto">
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-foreground">AI Configuration</h1>
          <p className="text-muted-foreground mt-1">
            Configure AI providers and customize prompts for DanaVision's smart features.
          </p>
        </div>

        {/* Flash Messages */}
        {flash?.success && (
          <div className="bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded-xl mb-6">
            {flash.success}
          </div>
        )}
        {flash?.error && (
          <div className="bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded-xl mb-6">
            {flash.error}
          </div>
        )}

        <Tabs defaultValue="providers" className="space-y-6">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="providers" className="gap-2">
              <Brain className="h-4 w-4" />
              Providers
            </TabsTrigger>
            <TabsTrigger value="prompts" className="gap-2">
              <FileText className="h-4 w-4" />
              Prompts
            </TabsTrigger>
          </TabsList>

          <TabsContent value="providers" className="space-y-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-xl font-semibold text-foreground">AI Providers</h2>
                <p className="text-sm text-muted-foreground">
                  Configure multiple AI providers. All active providers will be queried, and the primary provider will aggregate responses.
                </p>
              </div>
              <AddProviderDialog availableProviders={availableProviders} onAdd={handleUpdate} />
            </div>

            {providers.length === 0 ? (
              <Card>
                <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                  <Brain className="h-12 w-12 text-muted-foreground mb-4" />
                  <h3 className="text-lg font-semibold mb-2">No AI providers configured</h3>
                  <p className="text-muted-foreground mb-4">
                    Add an AI provider to enable smart features like product identification and price analysis.
                  </p>
                  <AddProviderDialog availableProviders={availableProviders} onAdd={handleUpdate} />
                </CardContent>
              </Card>
            ) : (
              <div className="grid gap-4 md:grid-cols-2">
                {providers.map((provider) => (
                  <ProviderCard key={provider.id} provider={provider} onUpdate={handleUpdate} />
                ))}
              </div>
            )}

            {/* Info about multi-AI */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Sparkles className="h-5 w-5" />
                  Multi-AI Aggregation
                </CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-muted-foreground space-y-2">
                <p>
                  When you have multiple active AI providers, DanaVision will query all of them simultaneously for better accuracy.
                </p>
                <p>
                  The <strong>primary provider</strong> (marked with a star) is used to synthesize and aggregate responses from all other providers into a single, comprehensive answer.
                </p>
                <p>
                  This multi-model approach helps identify products more accurately and provides more reliable price recommendations.
                </p>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="prompts" className="space-y-6">
            <div>
              <h2 className="text-xl font-semibold text-foreground">AI Prompts</h2>
              <p className="text-sm text-muted-foreground">
                Customize the prompts used by AI providers. Changes affect how DanaVision interprets products and prices.
              </p>
            </div>

            {prompts && Object.values(prompts).length > 0 ? (
              <div className="space-y-6">
                {Object.values(prompts).map((prompt) => (
                  <PromptEditor key={prompt.type} prompt={prompt} onUpdate={handleUpdate} />
                ))}
              </div>
            ) : (
              <Card>
                <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                  <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                  <h3 className="text-lg font-semibold mb-2">No prompts available</h3>
                  <p className="text-muted-foreground">
                    Prompts will appear here once configured.
                  </p>
                </CardContent>
              </Card>
            )}
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}
