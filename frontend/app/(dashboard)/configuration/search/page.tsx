"use client";

import { useState, useEffect, useRef } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { errorLogger } from "@/lib/error-logger";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { SaveButton } from "@/components/ui/save-button";
import { SettingsPageSkeleton } from "@/components/ui/settings-page-skeleton";
import { SettingsSwitchRow } from "@/components/ui/settings-switch-row";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { PasswordInput } from "@/components/ui/password-input";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Loader2, RefreshCw, Server, Settings } from "lucide-react";
import { HelpLink } from "@/components/help/help-link";

interface IndexStat {
  count: number;
  name: string;
}

interface StatsResponse {
  stats: Record<string, IndexStat>;
}

interface SearchSettings {
  results_per_page: number;
  suggestions_limit: number;
  min_query_length: number;
}

interface InstanceSettings {
  enabled: boolean;
  use_embedded: boolean;
  host: string;
  api_key: string;
}

interface HealthResponse {
  status: string;
  healthy: boolean;
  message?: string;
}

const DEFAULT_SEARCH_SETTINGS: SearchSettings = {
  results_per_page: 15,
  suggestions_limit: 5,
  min_query_length: 2,
};

const DEFAULT_INSTANCE_SETTINGS: InstanceSettings = {
  enabled: true,
  use_embedded: true,
  host: "http://127.0.0.1:7700",
  api_key: "",
};

export default function SearchConfigurationPage() {
  const queryClient = useQueryClient();
  const [stats, setStats] = useState<Record<string, IndexStat> | null>(null);
  const [searchSettings, setSearchSettings] = useState<SearchSettings>(DEFAULT_SEARCH_SETTINGS);
  const [instanceSettings, setInstanceSettings] = useState<InstanceSettings>(DEFAULT_INSTANCE_SETTINGS);
  const [health, setHealth] = useState<HealthResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSavingSettings, setIsSavingSettings] = useState(false);
  const [isSavingInstance, setIsSavingInstance] = useState(false);
  const [isTestingConnection, setIsTestingConnection] = useState(false);
  const [reindexing, setReindexing] = useState<string | "all" | null>(null);
  const initialInstanceSettings = useRef<InstanceSettings>(DEFAULT_INSTANCE_SETTINGS);
  const initialSearchSettings = useRef<SearchSettings>(DEFAULT_SEARCH_SETTINGS);

  const isInstanceDirty =
    instanceSettings.enabled !== initialInstanceSettings.current.enabled ||
    instanceSettings.use_embedded !== initialInstanceSettings.current.use_embedded ||
    instanceSettings.host !== initialInstanceSettings.current.host ||
    instanceSettings.api_key !== initialInstanceSettings.current.api_key;

  const isSearchSettingsDirty =
    searchSettings.results_per_page !== initialSearchSettings.current.results_per_page ||
    searchSettings.suggestions_limit !== initialSearchSettings.current.suggestions_limit ||
    searchSettings.min_query_length !== initialSearchSettings.current.min_query_length;

  const fetchStats = async () => {
    try {
      const res = await api.get<StatsResponse>("/admin/search/stats");
      setStats(res.data.stats ?? {});
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to load search stats";
      toast.error(message);
      if (err instanceof Error) {
        errorLogger.report(err, { context: "SearchConfigurationPage.fetchStats" });
      }
    }
  };

  const fetchSettings = async () => {
    try {
      const res = await api.get<{ settings: Record<string, Record<string, unknown>> }>("/system-settings");
      const search = res.data?.settings?.search;
      if (search && typeof search === "object") {
        const loadedSearch: SearchSettings = {
          results_per_page: Number(search.results_per_page) || DEFAULT_SEARCH_SETTINGS.results_per_page,
          suggestions_limit: Number(search.suggestions_limit) || DEFAULT_SEARCH_SETTINGS.suggestions_limit,
          min_query_length: Number(search.min_query_length) ?? DEFAULT_SEARCH_SETTINGS.min_query_length,
        };
        const loadedInstance: InstanceSettings = {
          enabled: search.enabled !== false,
          use_embedded: search.use_embedded !== false,
          host: typeof search.host === "string" ? search.host : DEFAULT_INSTANCE_SETTINGS.host,
          api_key: typeof search.api_key === "string" ? search.api_key : "",
        };
        setSearchSettings(loadedSearch);
        setInstanceSettings(loadedInstance);
        initialSearchSettings.current = loadedSearch;
        initialInstanceSettings.current = loadedInstance;
      }
    } catch {
      // Use defaults if fetch fails
    }
  };

  const fetchHealth = async () => {
    try {
      const res = await api.get<HealthResponse>("/admin/search/health");
      setHealth(res.data);
    } catch {
      setHealth({ status: "error", healthy: false, message: "Failed to check connection" });
    }
  };

  useEffect(() => {
    let cancelled = false;
    Promise.all([fetchStats(), fetchSettings()]).finally(() => {
      if (!cancelled) setIsLoading(false);
    });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!isLoading && instanceSettings.enabled) {
      fetchHealth();
    }
  }, [isLoading, instanceSettings.enabled]);

  const handleReindex = async (model: string | null) => {
    setReindexing(model ?? "all");
    try {
      if (model) {
        await api.post("/admin/search/reindex", { model });
        toast.success(`Index "${model}" reindexed successfully.`);
      } else {
        await api.post("/admin/search/reindex");
        toast.success("All indexes reindexed successfully.");
      }
      await fetchStats();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Reindex failed";
      toast.error(message);
      if (err instanceof Error) {
        errorLogger.report(err, { context: "SearchConfigurationPage.reindex" });
      }
    } finally {
      setReindexing(null);
    }
  };

  const handleSaveSettings = async () => {
    setIsSavingSettings(true);
    try {
      const settingsArray = [
        { group: "search", key: "results_per_page", value: searchSettings.results_per_page },
        { group: "search", key: "suggestions_limit", value: searchSettings.suggestions_limit },
        { group: "search", key: "min_query_length", value: searchSettings.min_query_length },
      ];
      await api.put("/system-settings", { settings: settingsArray });
      initialSearchSettings.current = { ...searchSettings };
      toast.success("Search settings saved.");
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to save search settings";
      toast.error(message);
      if (err instanceof Error) {
        errorLogger.report(err, { context: "SearchConfigurationPage.saveSettings" });
      }
    } finally {
      setIsSavingSettings(false);
    }
  };

  const handleSaveInstance = async () => {
    setIsSavingInstance(true);
    try {
      const settingsArray = [
        { group: "search", key: "enabled", value: instanceSettings.enabled },
        { group: "search", key: "use_embedded", value: instanceSettings.use_embedded },
        { group: "search", key: "host", value: instanceSettings.use_embedded ? "http://127.0.0.1:7700" : instanceSettings.host.trim() },
        { group: "search", key: "api_key", value: instanceSettings.use_embedded ? "" : instanceSettings.api_key },
      ];
      await api.put("/system-settings", { settings: settingsArray });
      initialInstanceSettings.current = { ...instanceSettings };
      if (!instanceSettings.use_embedded) {
        toast.success("Instance settings saved. Run Reindex all to populate the external instance.", {
          duration: 6000,
        });
      } else {
        toast.success("Instance settings saved.");
      }
      await fetchHealth();
      queryClient.invalidateQueries({ queryKey: ["app-config"] });
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to save instance settings";
      toast.error(message);
      if (err instanceof Error) {
        errorLogger.report(err, { context: "SearchConfigurationPage.saveInstance" });
      }
    } finally {
      setIsSavingInstance(false);
    }
  };

  const handleTestConnection = async () => {
    const host = instanceSettings.use_embedded ? "http://127.0.0.1:7700" : instanceSettings.host.trim();
    const apiKey = instanceSettings.use_embedded ? "" : instanceSettings.api_key;
    if (!host) {
      toast.error("Enter a Meilisearch URL to test.");
      return;
    }
    setIsTestingConnection(true);
    try {
      const res = await api.post<{ message: string }>("/admin/search/test-connection", { host, api_key: apiKey || undefined });
      toast.success(res.data?.message ?? "Connection successful.");
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Connection test failed";
      toast.error(message);
      if (err instanceof Error) {
        errorLogger.report(err, { context: "SearchConfigurationPage.testConnection" });
      }
    } finally {
      setIsTestingConnection(false);
    }
  };

  if (isLoading) {
    return <SettingsPageSkeleton />;
  }

  const entries = stats ? Object.entries(stats) : [];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Search</h1>
        <p className="text-muted-foreground">
          Manage search indexes and reindex content for full-text search.{" "}
          <HelpLink articleId="search-config" />
        </p>
      </div>

      <Tabs defaultValue="instance" className="space-y-4">
        <TabsList>
          <TabsTrigger value="instance">
            <Server className="mr-2 h-4 w-4" />
            Instance
          </TabsTrigger>
          <TabsTrigger value="indexes">Index statistics</TabsTrigger>
          <TabsTrigger value="settings">
            <Settings className="mr-2 h-4 w-4" />
            Settings
          </TabsTrigger>
        </TabsList>
        <TabsContent value="instance" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Search instance</CardTitle>
              <CardDescription>
                Enable or disable search globally, and choose between embedded or external Meilisearch.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <SettingsSwitchRow
                label="Enable search"
                description="When disabled, the search bar and Cmd+K shortcut are hidden."
                checked={instanceSettings.enabled}
                onCheckedChange={(checked) =>
                  setInstanceSettings((s) => ({ ...s, enabled: checked }))
                }
              />
              <div className="space-y-3">
                <Label>Instance type</Label>
                <RadioGroup
                  value={instanceSettings.use_embedded ? "embedded" : "external"}
                  onValueChange={(v) =>
                    setInstanceSettings((s) => ({
                      ...s,
                      use_embedded: v === "embedded",
                    }))
                  }
                >
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="embedded" id="embedded" />
                    <Label htmlFor="embedded" className="font-normal cursor-pointer">
                      Use embedded instance (runs in this app)
                    </Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="external" id="external" />
                    <Label htmlFor="external" className="font-normal cursor-pointer">
                      Use external instance (Meilisearch Cloud or your own server)
                    </Label>
                  </div>
                </RadioGroup>
              </div>
              {!instanceSettings.use_embedded && (
                <>
                  <Alert variant="warning">
                    <AlertTitle>Switching to external instance</AlertTitle>
                    <AlertDescription>
                      Search data will not migrate from the embedded instance. After saving,
                      go to Index statistics and run &quot;Reindex all&quot; to populate the external instance.
                    </AlertDescription>
                  </Alert>
                <div className="space-y-4 rounded-lg border p-4">
                  <div className="space-y-2">
                    <Label htmlFor="meili-host">Meilisearch URL</Label>
                    <Input
                      id="meili-host"
                      type="url"
                      placeholder="https://your-meilisearch.example.com"
                      value={instanceSettings.host}
                      onChange={(e) =>
                        setInstanceSettings((s) => ({ ...s, host: e.target.value }))
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="meili-key">API key</Label>
                    <PasswordInput
                      id="meili-key"
                      placeholder="Leave empty if no auth"
                      value={instanceSettings.api_key}
                      onChange={(e) =>
                        setInstanceSettings((s) => ({ ...s, api_key: e.target.value }))
                      }
                    />
                  </div>
                  <Button
                    variant="outline"
                    onClick={handleTestConnection}
                    disabled={isTestingConnection}
                  >
                    {isTestingConnection ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : null}
                    Test connection
                  </Button>
                </div>
                </>
              )}
              {instanceSettings.enabled && health && (
                <div className="flex items-center gap-2">
                  <span className="text-sm text-muted-foreground">Status:</span>
                  <Badge variant={health.healthy ? "default" : "destructive"}>
                    {health.healthy ? "Connected" : "Error"}
                  </Badge>
                  {!health.healthy && health.message && (
                    <span className="text-sm text-muted-foreground">{health.message}</span>
                  )}
                </div>
              )}
            </CardContent>
            <CardFooter className="flex justify-end">
              <SaveButton
                type="button"
                isDirty={isInstanceDirty}
                isSaving={isSavingInstance}
                onClick={handleSaveInstance}
              />
            </CardFooter>
          </Card>
        </TabsContent>
        <TabsContent value="indexes" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Index statistics</CardTitle>
              <CardDescription>
                Document counts per searchable model. Use Reindex to refresh after
                bulk changes.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No search indexes configured.
                </p>
              ) : (
                <ul className="space-y-3">
                  {entries.map(([key, stat]) => (
                    <li
                      key={key}
                      className="flex items-center justify-between rounded-lg border px-4 py-3"
                    >
                      <div>
                        <span className="font-medium capitalize">{stat.name.replace(/_/g, " ")}</span>
                        <span className="ml-2 text-sm text-muted-foreground">
                          {stat.count} document{stat.count !== 1 ? "s" : ""}
                        </span>
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={reindexing !== null}
                        onClick={() => handleReindex(key)}
                      >
                        {reindexing === key ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <>
                            <RefreshCw className="mr-1.5 h-4 w-4" />
                            Reindex
                          </>
                        )}
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
              {entries.length > 0 && (
                <div className="pt-2">
                  <Button
                    variant="secondary"
                    disabled={reindexing !== null}
                    onClick={() => handleReindex(null)}
                  >
                    {reindexing === "all" ? (
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                      <RefreshCw className="mr-2 h-4 w-4" />
                    )}
                    Reindex all
                  </Button>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>
        <TabsContent value="settings" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Search settings</CardTitle>
              <CardDescription>
                Configure results per page, suggestions limit, and minimum query length.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="results_per_page">Results per page (5–50)</Label>
                  <Input
                    id="results_per_page"
                    type="number"
                    min={5}
                    max={50}
                    value={searchSettings.results_per_page}
                    onChange={(e) =>
                      setSearchSettings((s) => ({
                        ...s,
                        results_per_page: Math.min(50, Math.max(5, Number(e.target.value) || 15)),
                      }))
                    }
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="suggestions_limit">Suggestions limit (3–10)</Label>
                  <Input
                    id="suggestions_limit"
                    type="number"
                    min={3}
                    max={10}
                    value={searchSettings.suggestions_limit}
                    onChange={(e) =>
                      setSearchSettings((s) => ({
                        ...s,
                        suggestions_limit: Math.min(10, Math.max(3, Number(e.target.value) || 5)),
                      }))
                    }
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="min_query_length">Minimum query length (1–5)</Label>
                  <Input
                    id="min_query_length"
                    type="number"
                    min={1}
                    max={5}
                    value={searchSettings.min_query_length}
                    onChange={(e) =>
                      setSearchSettings((s) => ({
                        ...s,
                        min_query_length: Math.min(5, Math.max(1, Number(e.target.value) ?? 2)),
                      }))
                    }
                  />
                </div>
              </div>
            </CardContent>
            <CardFooter className="flex justify-end">
              <SaveButton
                type="button"
                isDirty={isSearchSettingsDirty}
                isSaving={isSavingSettings}
                onClick={handleSaveSettings}
              />
            </CardFooter>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
