"use client";

import { useState, useEffect, useCallback } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { toast } from "sonner";
import { api } from "@/lib/api";
import { getErrorMessage } from "@/lib/utils";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Separator } from "@/components/ui/separator";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { SettingsPageSkeleton } from "@/components/ui/settings-page-skeleton";
import { SaveButton } from "@/components/ui/save-button";
import { HelpTooltip } from "@/components/ui/help-tooltip";
import { TOOLTIP_CONTENT } from "@/lib/tooltip-content";

const priceSearchSchema = z.object({
  serpapi_key: z.string().optional(),
  firecrawl_key: z.string().optional(),
  firecrawl_enabled: z.boolean(),
  google_places_key: z.string().optional(),
  kroger_client_id: z.string().optional(),
  kroger_client_secret: z.string().optional(),
  walmart_api_key: z.string().optional(),
  bestbuy_api_key: z.string().optional(),
  crawl4ai_enabled: z.boolean(),
  crawl4ai_base_url: z.string().optional(),
  crawl4ai_api_token: z.string().optional(),
  default_search_radius_miles: z.number().min(1).max(500),
  price_check_interval_hours: z.number().min(1).max(168),
  max_vendor_prices_per_item: z.number().min(1).max(50),
  store_crawl_enabled: z.boolean(),
  store_crawl_max_products_per_store: z.number().min(1).max(500),
});

type PriceSearchForm = z.infer<typeof priceSearchSchema>;

const DEFAULTS: PriceSearchForm = {
  serpapi_key: "",
  firecrawl_key: "",
  firecrawl_enabled: false,
  google_places_key: "",
  kroger_client_id: "",
  kroger_client_secret: "",
  walmart_api_key: "",
  bestbuy_api_key: "",
  crawl4ai_enabled: false,
  crawl4ai_base_url: "http://crawl4ai:11235",
  crawl4ai_api_token: "",
  default_search_radius_miles: 25,
  price_check_interval_hours: 24,
  max_vendor_prices_per_item: 10,
  store_crawl_enabled: false,
  store_crawl_max_products_per_store: 50,
};

export default function PriceSearchSettingsPage() {
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isDirty },
    setValue,
    watch,
    reset,
  } = useForm<PriceSearchForm>({
    resolver: zodResolver(priceSearchSchema),
    mode: "onBlur",
    defaultValues: DEFAULTS,
  });

  const fetchSettings = useCallback(async () => {
    setIsLoading(true);
    try {
      const response = await api.get("/system-settings");
      const data = response.data.settings;
      const ps = data.price_search || {};

      const formData: PriceSearchForm = {
        serpapi_key: ps.serpapi_key || "",
        firecrawl_key: ps.firecrawl_key || "",
        firecrawl_enabled: ps.firecrawl_enabled ?? false,
        google_places_key: ps.google_places_key || "",
        kroger_client_id: ps.kroger_client_id || "",
        kroger_client_secret: ps.kroger_client_secret || "",
        walmart_api_key: ps.walmart_api_key || "",
        bestbuy_api_key: ps.bestbuy_api_key || "",
        crawl4ai_enabled: ps.crawl4ai_enabled ?? false,
        crawl4ai_base_url: ps.crawl4ai_base_url || "http://crawl4ai:11235",
        crawl4ai_api_token: ps.crawl4ai_api_token || "",
        default_search_radius_miles: ps.default_search_radius_miles ?? 25,
        price_check_interval_hours: ps.price_check_interval_hours ?? 24,
        max_vendor_prices_per_item: ps.max_vendor_prices_per_item ?? 10,
        store_crawl_enabled: ps.store_crawl_enabled ?? false,
        store_crawl_max_products_per_store: ps.store_crawl_max_products_per_store ?? 50,
      };

      reset(formData);
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to load price search settings"));
    } finally {
      setIsLoading(false);
    }
  }, [reset]);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const onSubmit = async (data: PriceSearchForm) => {
    setIsSaving(true);
    try {
      const settingsArray = Object.entries(data).map(([key, value]) => ({
        group: "price_search",
        key,
        value: value === "" ? null : value,
        is_public: false,
      }));

      await api.put("/system-settings", { settings: settingsArray });
      toast.success("Price search settings updated");
      await fetchSettings();
    } catch (error: unknown) {
      toast.error(getErrorMessage(error, "Failed to update price search settings"));
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return <SettingsPageSkeleton />;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Price Search</h1>
        <p className="text-muted-foreground mt-2">
          Configure price comparison providers, web crawling, and store discovery.
        </p>
      </div>

      <form onSubmit={handleSubmit(onSubmit)}>
        <Tabs defaultValue="api-keys" className="space-y-6">
          <TabsList>
            <TabsTrigger value="api-keys">API Keys</TabsTrigger>
            <TabsTrigger value="behavior">Behavior</TabsTrigger>
          </TabsList>

          <TabsContent value="api-keys">
            <div className="space-y-6">
              {/* SerpAPI */}
              <Card>
                <CardHeader>
                  <CardTitle>SerpAPI (Google Shopping)</CardTitle>
                  <CardDescription>
                    Used for Tier 1 price lookups via Google Shopping results.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="serpapi_key" className="flex items-center gap-1.5">
                      API Key
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.serpapi_key} />
                    </Label>
                    <Input
                      id="serpapi_key"
                      type="password"
                      {...register("serpapi_key")}
                      placeholder="Enter SerpAPI key"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Firecrawl */}
              <Card>
                <CardHeader>
                  <CardTitle>Firecrawl (Web Scraping)</CardTitle>
                  <CardDescription>
                    Used for scraping store product pages and web-based store discovery.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label className="flex items-center gap-1.5">
                        Enable Firecrawl
                        <HelpTooltip content={TOOLTIP_CONTENT.price_search.firecrawl_enabled} />
                      </Label>
                      <p className="text-sm text-muted-foreground">
                        Enable web scraping for store discovery and price extraction
                      </p>
                    </div>
                    <Switch
                      checked={watch("firecrawl_enabled")}
                      onCheckedChange={(checked) =>
                        setValue("firecrawl_enabled", checked, { shouldDirty: true })
                      }
                    />
                  </div>

                  <Separator />

                  <div className="space-y-2">
                    <Label htmlFor="firecrawl_key" className="flex items-center gap-1.5">
                      API Key
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.firecrawl_key} />
                    </Label>
                    <Input
                      id="firecrawl_key"
                      type="password"
                      {...register("firecrawl_key")}
                      placeholder="Enter Firecrawl API key"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Google Places */}
              <Card>
                <CardHeader>
                  <CardTitle>Google Places (Local Stores)</CardTitle>
                  <CardDescription>
                    Used for discovering nearby stores based on user location.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="google_places_key" className="flex items-center gap-1.5">
                      API Key
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.google_places_key} />
                    </Label>
                    <Input
                      id="google_places_key"
                      type="password"
                      {...register("google_places_key")}
                      placeholder="Enter Google Places API key"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Kroger API */}
              <Card>
                <CardHeader>
                  <CardTitle>Kroger API (Direct Pricing)</CardTitle>
                  <CardDescription>
                    Free API providing exact per-store pricing for Kroger and 20+ sub-brands
                    (Ralphs, Fred Meyer, King Soopers, etc.). Register at developer.kroger.com.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="kroger_client_id" className="flex items-center gap-1.5">
                      Client ID
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.kroger_client_id} />
                    </Label>
                    <Input
                      id="kroger_client_id"
                      type="password"
                      {...register("kroger_client_id")}
                      placeholder="Enter Kroger Client ID"
                      autoComplete="off"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="kroger_client_secret" className="flex items-center gap-1.5">
                      Client Secret
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.kroger_client_secret} />
                    </Label>
                    <Input
                      id="kroger_client_secret"
                      type="password"
                      {...register("kroger_client_secret")}
                      placeholder="Enter Kroger Client Secret"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Walmart API */}
              <Card>
                <CardHeader>
                  <CardTitle>Walmart API (Product Search)</CardTitle>
                  <CardDescription>
                    Affiliate API providing product search, pricing, and availability
                    for Walmart.com. Register at walmart.io.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="walmart_api_key" className="flex items-center gap-1.5">
                      API Key
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.walmart_api_key} />
                    </Label>
                    <Input
                      id="walmart_api_key"
                      type="password"
                      {...register("walmart_api_key")}
                      placeholder="Enter Walmart API key"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Best Buy API */}
              <Card>
                <CardHeader>
                  <CardTitle>Best Buy API (Product Search)</CardTitle>
                  <CardDescription>
                    Free API providing product search, pricing, store inventory,
                    and reviews. Register at developer.bestbuy.com.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="bestbuy_api_key" className="flex items-center gap-1.5">
                      API Key
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.bestbuy_api_key} />
                    </Label>
                    <Input
                      id="bestbuy_api_key"
                      type="password"
                      {...register("bestbuy_api_key")}
                      placeholder="Enter Best Buy API key"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>

              {/* Crawl4AI */}
              <Card>
                <CardHeader>
                  <CardTitle>Crawl4AI (Self-Hosted Scraping)</CardTitle>
                  <CardDescription>
                    Free, self-hosted web crawler for scraping store product pages.
                    Enable the crawl4ai Docker Compose profile to use.
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label className="flex items-center gap-1.5">
                        Enable Crawl4AI
                        <HelpTooltip content={TOOLTIP_CONTENT.price_search.crawl4ai_enabled} />
                      </Label>
                      <p className="text-sm text-muted-foreground">
                        Use Crawl4AI for store scraping (requires Docker container)
                      </p>
                    </div>
                    <Switch
                      checked={watch("crawl4ai_enabled")}
                      onCheckedChange={(checked) =>
                        setValue("crawl4ai_enabled", checked, { shouldDirty: true })
                      }
                    />
                  </div>

                  <Separator />

                  <div className="space-y-2">
                    <Label htmlFor="crawl4ai_base_url" className="flex items-center gap-1.5">
                      Base URL
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.crawl4ai_base_url} />
                    </Label>
                    <Input
                      id="crawl4ai_base_url"
                      {...register("crawl4ai_base_url")}
                      placeholder="http://crawl4ai:11235"
                      autoComplete="off"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="crawl4ai_api_token" className="flex items-center gap-1.5">
                      API Token
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.crawl4ai_api_token} />
                    </Label>
                    <Input
                      id="crawl4ai_api_token"
                      type="password"
                      {...register("crawl4ai_api_token")}
                      placeholder="Optional — leave blank if not configured"
                      autoComplete="off"
                    />
                  </div>
                </CardContent>
              </Card>
            </div>

            <div className="flex justify-end mt-6">
              <SaveButton isDirty={isDirty} isSaving={isSaving} />
            </div>
          </TabsContent>

          <TabsContent value="behavior">
            <Card>
              <CardHeader>
                <CardTitle>Search Behavior</CardTitle>
                <CardDescription>
                  Configure how price searches operate and how results are stored.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="default_search_radius_miles" className="flex items-center gap-1.5">
                    Default Search Radius (miles)
                    <HelpTooltip content={TOOLTIP_CONTENT.price_search.default_search_radius_miles} />
                  </Label>
                  <Input
                    id="default_search_radius_miles"
                    type="number"
                    {...register("default_search_radius_miles", { valueAsNumber: true })}
                    min={1}
                    max={500}
                  />
                  {errors.default_search_radius_miles && (
                    <p className="text-sm text-destructive">
                      {errors.default_search_radius_miles.message}
                    </p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="price_check_interval_hours" className="flex items-center gap-1.5">
                    Price Check Interval (hours)
                    <HelpTooltip content={TOOLTIP_CONTENT.price_search.price_check_interval_hours} />
                  </Label>
                  <Input
                    id="price_check_interval_hours"
                    type="number"
                    {...register("price_check_interval_hours", { valueAsNumber: true })}
                    min={1}
                    max={168}
                  />
                  {errors.price_check_interval_hours && (
                    <p className="text-sm text-destructive">
                      {errors.price_check_interval_hours.message}
                    </p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="max_vendor_prices_per_item" className="flex items-center gap-1.5">
                    Max Vendor Prices Per Item
                    <HelpTooltip content={TOOLTIP_CONTENT.price_search.max_vendor_prices_per_item} />
                  </Label>
                  <Input
                    id="max_vendor_prices_per_item"
                    type="number"
                    {...register("max_vendor_prices_per_item", { valueAsNumber: true })}
                    min={1}
                    max={50}
                  />
                  {errors.max_vendor_prices_per_item && (
                    <p className="text-sm text-destructive">
                      {errors.max_vendor_prices_per_item.message}
                    </p>
                  )}
                </div>
              </CardContent>
              <CardFooter className="flex justify-end">
                <SaveButton isDirty={isDirty} isSaving={isSaving} />
              </CardFooter>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Scheduled Crawling</CardTitle>
                <CardDescription>
                  Automatically crawl store websites in the background to keep prices fresh.
                  Grocery stores are checked every 6 hours; electronics and other categories every 12 hours.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label className="flex items-center gap-1.5">
                      Enable Scheduled Crawling
                      <HelpTooltip content={TOOLTIP_CONTENT.price_search.store_crawl_enabled} />
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Requires Crawl4AI to be enabled and running
                    </p>
                  </div>
                  <Switch
                    checked={watch("store_crawl_enabled")}
                    onCheckedChange={(checked) =>
                      setValue("store_crawl_enabled", checked, { shouldDirty: true })
                    }
                  />
                </div>

                <Separator />

                <div className="space-y-2">
                  <Label htmlFor="store_crawl_max_products_per_store" className="flex items-center gap-1.5">
                    Max Products Per Store
                    <HelpTooltip content={TOOLTIP_CONTENT.price_search.store_crawl_max_products_per_store} />
                  </Label>
                  <Input
                    id="store_crawl_max_products_per_store"
                    type="number"
                    {...register("store_crawl_max_products_per_store", { valueAsNumber: true })}
                    min={1}
                    max={500}
                  />
                  {errors.store_crawl_max_products_per_store && (
                    <p className="text-sm text-destructive">
                      {errors.store_crawl_max_products_per_store.message}
                    </p>
                  )}
                </div>
              </CardContent>
              <CardFooter className="flex justify-end">
                <SaveButton isDirty={isDirty} isSaving={isSaving} />
              </CardFooter>
            </Card>
          </TabsContent>
        </Tabs>
      </form>
    </div>
  );
}
