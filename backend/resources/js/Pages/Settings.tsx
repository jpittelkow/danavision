import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { PageProps, Settings as SettingsType, Store, StoreCategory } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import AddressTypeahead from '@/Components/AddressTypeahead';
import { JobsTab } from '@/Components/JobsTab';
import { AILogsTab } from '@/Components/AILogsTab';
import { StorePreferences } from '@/Components/StorePreferences';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
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
import {
  User,
  Bell,
  MapPin,
  Mail,
  CreditCard,
  Clock,
  Eye,
  EyeOff,
  Send,
  Loader2,
  Settings as SettingsIcon,
  Star,
  Plus,
  Trash2,
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
  Store,
  Ban,
  X,
  Activity,
  ScrollText,
  Globe,
  MapPin,
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
  settings: SettingsType | null;
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
  stores: Store[];
  storeCategories: Record<StoreCategory, string>;
}

const providerIcons: Record<string, React.ReactNode> = {
  claude: <Brain className="h-5 w-5" />,
  openai: <Sparkles className="h-5 w-5" />,
  gemini: <Zap className="h-5 w-5" />,
  local: <Server className="h-5 w-5" />,
};

// AI Provider Card Component
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

            {provider.provider === 'local' && (
              <div>
                <Label htmlFor={`base_url_${provider.id}`}>Base URL</Label>
                <Input
                  id={`base_url_${provider.id}`}
                  type="url"
                  value={data.base_url}
                  onChange={(e) => setData('base_url', e.target.value)}
                  placeholder="http://localhost:11434"
                  className="mt-1"
                />
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
              <span className="font-mono">
                {provider.has_api_key ? provider.masked_api_key : 'Not configured'}
              </span>
            </div>

            {provider.provider === 'local' && provider.base_url && (
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">Base URL</span>
                <span className="font-mono text-xs">{provider.base_url}</span>
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

// Add Provider Dialog
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

          {selectedProvider?.provider === 'local' && (
            <div>
              <Label htmlFor="base_url">Ollama Base URL</Label>
              <Input
                id="base_url"
                type="url"
                value={data.base_url}
                onChange={(e) => setData('base_url', e.target.value)}
                placeholder="http://localhost:11434"
                className="mt-1"
              />
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

// Prompt Editor Component
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

export default function Settings({ auth, settings, providers, availableProviders, prompts, stores, storeCategories, flash }: Props) {
  const [showMailPassword, setShowMailPassword] = useState(false);
  const [showFirecrawlKey, setShowFirecrawlKey] = useState(false);
  const [showGooglePlacesKey, setShowGooglePlacesKey] = useState(false);
  const [testingEmail, setTestingEmail] = useState(false);

  const { data, setData, patch, processing } = useForm({
    // Notification settings
    notification_email: settings?.notification_email || auth.user?.email || '',
    notify_price_drops: settings?.notify_price_drops ?? true,
    notify_daily_summary: settings?.notify_daily_summary ?? false,
    notify_all_time_lows: settings?.notify_all_time_lows ?? true,
    // Email/SMTP settings
    mail_driver: settings?.mail_driver || 'smtp',
    mail_host: settings?.mail_host || '',
    mail_port: settings?.mail_port || '587',
    mail_username: settings?.mail_username || '',
    mail_password: settings?.mail_password || '',
    mail_from_address: settings?.mail_from_address || '',
    mail_from_name: settings?.mail_from_name || '',
    mail_encryption: settings?.mail_encryption || 'tls',
    // Location
    home_zip_code: settings?.home_zip_code || '',
    home_address: settings?.home_address || '',
    home_latitude: settings?.home_latitude || null,
    home_longitude: settings?.home_longitude || null,
    // Price check schedule
    price_check_time: settings?.price_check_time || '03:00',
    // Vendor settings
    suppressed_vendors: settings?.suppressed_vendors || [],
    // Firecrawl Web Crawler
    firecrawl_api_key: settings?.firecrawl_api_key || '',
    // Google Places API
    google_places_api_key: settings?.google_places_api_key || '',
  });

  // State for adding new suppressed vendor
  const [newVendor, setNewVendor] = useState('');

  const submit = (e: FormEvent) => {
    e.preventDefault();
    patch('/settings');
  };

  const testEmail = () => {
    setTestingEmail(true);
    router.post('/settings/test-email', {}, {
      onFinish: () => setTestingEmail(false),
    });
  };

  const handleAIUpdate = () => {
    router.reload({ only: ['providers', 'prompts'] });
  };

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Settings" />
      <div className="p-6 lg:p-8 max-w-4xl mx-auto">
        <h1 className="text-3xl font-bold text-foreground mb-2">Settings</h1>
        <p className="text-muted-foreground mb-8">Manage your account, configurations, and AI providers</p>

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

        <Tabs defaultValue="general" className="space-y-6">
          <TabsList className="grid w-full grid-cols-6">
            <TabsTrigger value="general" className="gap-2">
              <User className="h-4 w-4" />
              <span className="hidden sm:inline">General</span>
            </TabsTrigger>
            <TabsTrigger value="configurations" className="gap-2">
              <SettingsIcon className="h-4 w-4" />
              <span className="hidden sm:inline">Config</span>
            </TabsTrigger>
            <TabsTrigger value="stores" className="gap-2">
              <Store className="h-4 w-4" />
              <span className="hidden sm:inline">Stores</span>
            </TabsTrigger>
            <TabsTrigger value="ai" className="gap-2">
              <Brain className="h-4 w-4" />
              <span className="hidden sm:inline">AI</span>
            </TabsTrigger>
            <TabsTrigger value="jobs" className="gap-2">
              <Activity className="h-4 w-4" />
              <span className="hidden sm:inline">Jobs</span>
            </TabsTrigger>
            <TabsTrigger value="logs" className="gap-2">
              <ScrollText className="h-4 w-4" />
              <span className="hidden sm:inline">Logs</span>
            </TabsTrigger>
          </TabsList>

          {/* General Tab */}
          <TabsContent value="general" className="space-y-6">
            {/* Account Info */}
            <Card>
              <CardHeader>
                <CardTitle>Account</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 bg-primary rounded-full flex items-center justify-center text-primary-foreground text-2xl font-bold">
                    {auth.user?.name.charAt(0).toUpperCase()}
                  </div>
                  <div>
                    <p className="font-semibold text-foreground">{auth.user?.name}</p>
                    <p className="text-muted-foreground">{auth.user?.email}</p>
                  </div>
                </div>
              </CardContent>
            </Card>

            <form onSubmit={submit} className="space-y-6">
              {/* Location Settings */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <MapPin className="h-5 w-5" />
                    Location
                  </CardTitle>
                  <CardDescription>
                    Set your home address for local price searches and store discovery
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div>
                    <Label>Home Address</Label>
                    <AddressTypeahead
                      value={data.home_address}
                      latitude={data.home_latitude}
                      longitude={data.home_longitude}
                      onChange={(address, lat, lon, postcode) => {
                        setData((prev) => ({
                          ...prev,
                          home_address: address,
                          home_latitude: lat,
                          home_longitude: lon,
                          home_zip_code: postcode || prev.home_zip_code,
                        }));
                      }}
                      placeholder="Search for your address..."
                      className="mt-1"
                    />
                    <p className="text-xs text-muted-foreground mt-2">
                      Used to find local deals, nearby stores, and accurate shipping estimates
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Notifications */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Bell className="h-5 w-5" />
                    Notifications
                  </CardTitle>
                  <CardDescription>
                    Configure how you want to be notified about price changes
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <Label htmlFor="notification_email">Notification Email</Label>
                    <Input
                      id="notification_email"
                      type="email"
                      value={data.notification_email}
                      onChange={(e) => setData('notification_email', e.target.value)}
                      className="mt-1"
                    />
                  </div>

                  <div className="space-y-4 pt-2">
                    <div className="flex items-center justify-between">
                      <div>
                        <Label htmlFor="notify_price_drops">Price drop alerts</Label>
                        <p className="text-sm text-muted-foreground">
                          Get notified when prices drop
                        </p>
                      </div>
                      <Switch
                        id="notify_price_drops"
                        checked={data.notify_price_drops}
                        onCheckedChange={(checked) => setData('notify_price_drops', checked)}
                      />
                    </div>

                    <div className="flex items-center justify-between">
                      <div>
                        <Label htmlFor="notify_all_time_lows">All-time low alerts</Label>
                        <p className="text-sm text-muted-foreground">
                          Get notified when prices hit all-time lows
                        </p>
                      </div>
                      <Switch
                        id="notify_all_time_lows"
                        checked={data.notify_all_time_lows}
                        onCheckedChange={(checked) => setData('notify_all_time_lows', checked)}
                      />
                    </div>

                    <div className="flex items-center justify-between">
                      <div>
                        <Label htmlFor="notify_daily_summary">Daily summary</Label>
                        <p className="text-sm text-muted-foreground">
                          Receive a daily email with price updates
                        </p>
                      </div>
                      <Switch
                        id="notify_daily_summary"
                        checked={data.notify_daily_summary}
                        onCheckedChange={(checked) => setData('notify_daily_summary', checked)}
                      />
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Save Button */}
              <Button
                type="submit"
                className="w-full"
                disabled={processing}
              >
                {processing ? 'Saving...' : 'Save General Settings'}
              </Button>
            </form>
          </TabsContent>

          {/* Configurations Tab */}
          <TabsContent value="configurations" className="space-y-6">
            <form onSubmit={submit} className="space-y-6">
              {/* Email/SMTP Configuration */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Mail className="h-5 w-5" />
                    Email Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure SMTP settings for sending notification emails
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="mail_driver">Mail Driver</Label>
                      <select
                        id="mail_driver"
                        value={data.mail_driver}
                        onChange={(e) => setData('mail_driver', e.target.value)}
                        className="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      >
                        <option value="smtp">SMTP</option>
                        <option value="sendmail">Sendmail</option>
                        <option value="mailgun">Mailgun</option>
                        <option value="ses">Amazon SES</option>
                        <option value="postmark">Postmark</option>
                      </select>
                    </div>
                    <div>
                      <Label htmlFor="mail_encryption">Encryption</Label>
                      <select
                        id="mail_encryption"
                        value={data.mail_encryption}
                        onChange={(e) => setData('mail_encryption', e.target.value)}
                        className="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      >
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="mail_host">SMTP Host</Label>
                      <Input
                        id="mail_host"
                        type="text"
                        value={data.mail_host}
                        onChange={(e) => setData('mail_host', e.target.value)}
                        placeholder="smtp.example.com"
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="mail_port">Port</Label>
                      <Input
                        id="mail_port"
                        type="text"
                        value={data.mail_port}
                        onChange={(e) => setData('mail_port', e.target.value)}
                        placeholder="587"
                        className="mt-1"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="mail_username">Username</Label>
                      <Input
                        id="mail_username"
                        type="text"
                        value={data.mail_username}
                        onChange={(e) => setData('mail_username', e.target.value)}
                        placeholder="your@email.com"
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="mail_password">Password</Label>
                      <div className="relative mt-1">
                        <Input
                          id="mail_password"
                          type={showMailPassword ? 'text' : 'password'}
                          value={data.mail_password}
                          onChange={(e) => setData('mail_password', e.target.value)}
                          placeholder="••••••••"
                          className="pr-10"
                        />
                        <button
                          type="button"
                          onClick={() => setShowMailPassword(!showMailPassword)}
                          className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                          {showMailPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="mail_from_address">From Address</Label>
                      <Input
                        id="mail_from_address"
                        type="email"
                        value={data.mail_from_address}
                        onChange={(e) => setData('mail_from_address', e.target.value)}
                        placeholder="noreply@example.com"
                        className="mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="mail_from_name">From Name</Label>
                      <Input
                        id="mail_from_name"
                        type="text"
                        value={data.mail_from_name}
                        onChange={(e) => setData('mail_from_name', e.target.value)}
                        placeholder="DanaVision"
                        className="mt-1"
                      />
                    </div>
                  </div>

                  <div className="pt-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={testEmail}
                      disabled={testingEmail || !data.mail_host || !data.mail_from_address}
                      className="w-full sm:w-auto"
                    >
                      {testingEmail ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Sending...
                        </>
                      ) : (
                        <>
                          <Send className="mr-2 h-4 w-4" />
                          Send Test Email
                        </>
                      )}
                    </Button>
                    <p className="text-xs text-muted-foreground mt-2">
                      Save your settings first, then send a test email to verify configuration
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Price Search Info */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Brain className="h-5 w-5" />
                    Price Search
                  </CardTitle>
                  <CardDescription>
                    AI-powered price search and product identification
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="bg-primary/10 rounded-lg p-4 space-y-3">
                    <div className="flex items-center gap-2">
                      <Sparkles className="h-5 w-5 text-primary" />
                      <span className="font-medium text-foreground">AI-Powered Search</span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      DanaVision uses your configured AI providers to search for current prices across retailers. 
                      This provides more accurate and comprehensive results than traditional price APIs.
                    </p>
                    {providers.length === 0 ? (
                      <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <AlertCircle className="h-4 w-4" />
                        <span className="text-sm">No AI providers configured. Please set up an AI provider in the AI Providers tab.</span>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <CheckCircle2 className="h-4 w-4" />
                        <span className="text-sm">{providers.filter(p => p.is_active).length} active AI provider(s) configured for price search</span>
                      </div>
                    )}
                  </div>
                  <p className="text-xs text-muted-foreground">
                    For best results, configure multiple AI providers with web search capabilities. 
                    The system will aggregate results from all providers for comprehensive price data.
                  </p>
                </CardContent>
              </Card>

              {/* Firecrawl Web Crawler Configuration */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Globe className="h-5 w-5" />
                    Web Crawler (Firecrawl)
                  </CardTitle>
                  <CardDescription>
                    Configure Firecrawl.dev for intelligent price discovery across the web
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="bg-blue-500/10 rounded-lg p-4 space-y-3">
                    <div className="flex items-center gap-2">
                      <Globe className="h-5 w-5 text-blue-500" />
                      <span className="font-medium text-foreground">Firecrawl Price Discovery</span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Firecrawl automatically searches and crawls retailer websites to find real-time prices 
                      for your products. It discovers new stores, updates prices daily, and finds the best deals.
                    </p>
                    {settings?.has_firecrawl_api_key ? (
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <CheckCircle2 className="h-4 w-4" />
                        <span className="text-sm">Firecrawl API key configured</span>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <AlertCircle className="h-4 w-4" />
                        <span className="text-sm">No Firecrawl API key configured</span>
                      </div>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="firecrawl_api_key">Firecrawl API Key</Label>
                    <div className="relative mt-1">
                      <Input
                        id="firecrawl_api_key"
                        type={showFirecrawlKey ? 'text' : 'password'}
                        value={data.firecrawl_api_key}
                        onChange={(e) => setData('firecrawl_api_key', e.target.value)}
                        placeholder={settings?.has_firecrawl_api_key ? '••••••••' : 'fc-...'}
                        className="pr-10"
                      />
                      <button
                        type="button"
                        onClick={() => setShowFirecrawlKey(!showFirecrawlKey)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                      >
                        {showFirecrawlKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    <p className="text-xs text-muted-foreground mt-2">
                      Get your API key from{' '}
                      <a 
                        href="https://firecrawl.dev" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        className="text-primary hover:underline"
                      >
                        firecrawl.dev
                      </a>
                      . Free tier includes 500 credits.
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Google Places API Configuration */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <MapPin className="h-5 w-5" />
                    Nearby Store Discovery (Google Places)
                  </CardTitle>
                  <CardDescription>
                    Configure Google Places API to discover stores near your location
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="bg-purple-500/10 rounded-lg p-4 space-y-3">
                    <div className="flex items-center gap-2">
                      <MapPin className="h-5 w-5 text-purple-500" />
                      <span className="font-medium text-foreground">Find Nearby Stores</span>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      Use Google Places API to discover grocery stores, pharmacies, electronics shops, 
                      and more near your location. Found stores are automatically added to your registry 
                      for price tracking.
                    </p>
                    {settings?.has_google_places_api_key ? (
                      <div className="flex items-center gap-2 text-green-600 dark:text-green-400">
                        <CheckCircle2 className="h-4 w-4" />
                        <span className="text-sm">Google Places API key configured</span>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                        <AlertCircle className="h-4 w-4" />
                        <span className="text-sm">No Google Places API key configured</span>
                      </div>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="google_places_api_key">Google Places API Key</Label>
                    <div className="relative mt-1">
                      <Input
                        id="google_places_api_key"
                        type={showGooglePlacesKey ? 'text' : 'password'}
                        value={data.google_places_api_key}
                        onChange={(e) => setData('google_places_api_key', e.target.value)}
                        placeholder={settings?.has_google_places_api_key ? '••••••••' : 'AIza...'}
                        className="pr-10"
                      />
                      <button
                        type="button"
                        onClick={() => setShowGooglePlacesKey(!showGooglePlacesKey)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                      >
                        {showGooglePlacesKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                    <p className="text-xs text-muted-foreground mt-2">
                      Get your API key from{' '}
                      <a 
                        href="https://console.cloud.google.com/apis/credentials" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        className="text-primary hover:underline"
                      >
                        Google Cloud Console
                      </a>
                      . Enable the Places API (New) in your project.
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Vendor Suppression */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Ban className="h-5 w-5" />
                    Suppressed Vendors
                  </CardTitle>
                  <CardDescription>
                    Hide specific vendors from price results and comparisons
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex gap-2">
                    <Input
                      type="text"
                      value={newVendor}
                      onChange={(e) => setNewVendor(e.target.value)}
                      placeholder="Enter vendor name (e.g., Amazon, eBay)"
                      className="flex-1"
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          if (newVendor.trim() && !data.suppressed_vendors.includes(newVendor.trim())) {
                            setData('suppressed_vendors', [...data.suppressed_vendors, newVendor.trim()]);
                            setNewVendor('');
                          }
                        }
                      }}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => {
                        if (newVendor.trim() && !data.suppressed_vendors.includes(newVendor.trim())) {
                          setData('suppressed_vendors', [...data.suppressed_vendors, newVendor.trim()]);
                          setNewVendor('');
                        }
                      }}
                    >
                      <Plus className="h-4 w-4" />
                    </Button>
                  </div>
                  
                  {data.suppressed_vendors.length > 0 ? (
                    <div className="flex flex-wrap gap-2">
                      {data.suppressed_vendors.map((vendor, index) => (
                        <Badge 
                          key={index} 
                          variant="secondary" 
                          className="flex items-center gap-1 px-3 py-1"
                        >
                          <Store className="h-3 w-3" />
                          {vendor}
                          <button
                            type="button"
                            className="ml-1 hover:text-destructive"
                            onClick={() => {
                              setData('suppressed_vendors', data.suppressed_vendors.filter((_, i) => i !== index));
                            }}
                          >
                            <X className="h-3 w-3" />
                          </button>
                        </Badge>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      No vendors suppressed. Add vendor names above to hide them from results.
                    </p>
                  )}
                  
                  <p className="text-xs text-muted-foreground">
                    Suppressed vendors will be hidden from all price comparisons, search results, and price history.
                    This is useful for excluding vendors you don't want to purchase from.
                  </p>
                </CardContent>
              </Card>

              {/* Daily Price Check */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Clock className="h-5 w-5" />
                    Daily Price Check
                  </CardTitle>
                  <CardDescription>
                    Configure when to automatically check prices for all your tracked items
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div>
                    <Label htmlFor="price_check_time">Check Time</Label>
                    <Input
                      id="price_check_time"
                      type="time"
                      value={data.price_check_time}
                      onChange={(e) => setData('price_check_time', e.target.value)}
                      className="mt-1 max-w-[200px]"
                    />
                    <p className="text-xs text-muted-foreground mt-1">
                      Prices will be checked daily at this time (24-hour format)
                    </p>
                  </div>
                </CardContent>
              </Card>

              {/* Save Button */}
              <Button
                type="submit"
                className="w-full"
                disabled={processing}
              >
                {processing ? 'Saving...' : 'Save Configuration Settings'}
              </Button>
            </form>
          </TabsContent>

          {/* Stores Tab */}
          <TabsContent value="stores">
            <StorePreferences
              stores={stores}
              storeCategories={storeCategories}
              onUpdate={() => router.reload({ only: ['stores'] })}
            />
          </TabsContent>

          {/* AI Providers Tab */}
          <TabsContent value="ai" className="space-y-6">
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
                  <AddProviderDialog availableProviders={availableProviders} onAdd={handleAIUpdate} />
                </div>

                {providers.length === 0 ? (
                  <Card>
                    <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                      <Brain className="h-12 w-12 text-muted-foreground mb-4" />
                      <h3 className="text-lg font-semibold mb-2">No AI providers configured</h3>
                      <p className="text-muted-foreground mb-4">
                        Add an AI provider to enable smart features like product identification and price analysis.
                      </p>
                      <AddProviderDialog availableProviders={availableProviders} onAdd={handleAIUpdate} />
                    </CardContent>
                  </Card>
                ) : (
                  <div className="grid gap-4 md:grid-cols-2">
                    {providers.map((provider) => (
                      <ProviderCard key={provider.id} provider={provider} onUpdate={handleAIUpdate} />
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
                      <PromptEditor key={prompt.type} prompt={prompt} onUpdate={handleAIUpdate} />
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
          </TabsContent>

          {/* Jobs Tab */}
          <TabsContent value="jobs">
            <JobsTab />
          </TabsContent>

          {/* AI Logs Tab */}
          <TabsContent value="logs">
            <AILogsTab />
          </TabsContent>
        </Tabs>
      </div>
    </AppLayout>
  );
}
