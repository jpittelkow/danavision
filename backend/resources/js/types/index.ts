export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  created_at: string;
  updated_at: string;
}

export interface ShoppingList {
  id: number;
  user_id: number;
  name: string;
  description?: string;
  is_shared: boolean;
  notify_on_any_drop: boolean;
  notify_on_threshold: boolean;
  threshold_percent?: number;
  shop_local: boolean;
  items_count?: number;
  items?: ListItem[];
  shares?: ListShare[];
  created_at: string;
  updated_at: string;
}

export interface VendorPrice {
  id: number;
  vendor: string;
  vendor_sku?: string;
  product_url?: string;
  current_price: number | null;
  previous_price?: number | null;
  lowest_price?: number | null;
  on_sale: boolean;
  sale_percent_off?: number | null;
  in_stock: boolean;
  last_checked_at?: string | null;
  is_at_all_time_low?: boolean;
}

export interface ListItem {
  id: number;
  shopping_list_id: number;
  added_by_user_id: number;
  product_name: string;
  product_query?: string;
  product_url?: string;
  product_image_url?: string;
  sku?: string;
  upc?: string;
  uploaded_image_path?: string;
  current_price?: number;
  previous_price?: number;
  lowest_price?: number;
  highest_price?: number;
  target_price?: number;
  current_retailer?: string;
  notes?: string;
  priority: 'low' | 'medium' | 'high';
  is_purchased: boolean;
  shop_local?: boolean | null;
  is_generic?: boolean;
  unit_of_measure?: string | null;
  purchased_at?: string;
  purchased_price?: number;
  last_checked_at?: string;
  is_at_all_time_low?: boolean;
  price_change_percent?: number;
  vendor_prices?: VendorPrice[];
  created_at: string;
  updated_at: string;
}

export type UnitOfMeasure = 'lb' | 'oz' | 'kg' | 'g' | 'gallon' | 'liter' | 'quart' | 'pint' | 'fl_oz' | 'each' | 'dozen';

export const UNITS_OF_MEASURE: Record<UnitOfMeasure, string> = {
  lb: 'pound',
  oz: 'ounce',
  kg: 'kilogram',
  g: 'gram',
  gallon: 'gallon',
  liter: 'liter',
  quart: 'quart',
  pint: 'pint',
  fl_oz: 'fluid ounce',
  each: 'each',
  dozen: 'dozen',
};

export interface ListShare {
  id: number;
  shopping_list_id: number;
  user_id: number;
  shared_by_user_id: number;
  permission: 'view' | 'edit' | 'admin';
  accepted_at?: string;
  user?: User;
  shared_by?: User;
  list?: ShoppingList;
  created_at: string;
  updated_at: string;
}

export interface PriceHistory {
  id: number;
  list_item_id: number;
  price: number;
  retailer: string;
  url?: string;
  recorded_at: string;
}

/**
 * Price search result from Smart Add feature.
 * Represents a product found during price search.
 */
export interface SmartAddPriceResult {
  title: string;
  price: number;
  url: string;
  image_url?: string;
  retailer: string;
  upc?: string;
  in_stock?: boolean;
  other_prices?: {
    retailer: string;
    price: number;
    url: string;
  }[];
}

export interface DashboardData {
  lists_count: number;
  items_count: number;
  items_with_drops: number;
  total_potential_savings: number;
  recent_drops: ListItem[];
  all_time_lows: ListItem[];
}

export interface Settings {
  ai_provider: 'claude' | 'openai' | 'gemini' | 'local';
  ai_api_key?: string;
  // Firecrawl Web Crawler (primary price search provider)
  firecrawl_api_key?: string;
  has_firecrawl_api_key?: boolean;
  // Google Places API (for nearby store discovery)
  google_places_api_key?: string;
  has_google_places_api_key?: boolean;
  // Email configuration
  mail_driver: string;
  mail_host: string;
  mail_port: string;
  mail_username: string;
  mail_password: string;
  mail_from_address: string;
  mail_from_name: string;
  mail_encryption: string;
  // Location
  home_zip_code: string;
  home_address: string;
  home_latitude?: number | null;
  home_longitude?: number | null;
  // Notification preferences
  notification_email?: string;
  notify_price_drops: boolean;
  notify_daily_summary: boolean;
  notify_all_time_lows: boolean;
  // Price check schedule
  price_check_time: string;
  // Vendor settings
  suppressed_vendors: string[];
}

export interface AIProvider {
  id: number;
  user_id: number;
  provider: 'claude' | 'openai' | 'gemini' | 'local';
  api_key?: string;
  masked_api_key?: string;
  model?: string;
  base_url?: string;
  is_active: boolean;
  is_primary: boolean;
  last_tested_at?: string;
  test_status: 'untested' | 'success' | 'failed';
  test_error?: string;
  created_at: string;
  updated_at: string;
}

export interface AIPrompt {
  id: number;
  user_id: number;
  prompt_type: 'product_identification' | 'price_recommendation' | 'aggregation';
  prompt_text: string;
  created_at: string;
  updated_at: string;
}

/**
 * Result from the Smart Fill AI feature.
 * Contains product information discovered by AI analysis.
 */
export interface SmartFillResult {
  success: boolean;
  error?: string;
  product_image_url?: string;
  sku?: string;
  upc?: string;
  description?: string;
  suggested_target_price?: number;
  common_price?: number;
  brand?: string;
  category?: string;
  is_generic?: boolean;
  unit_of_measure?: string;
  providers_used: string[];
}

/**
 * AI Job types
 */
export type AIJobType = 
  | 'product_identification' 
  | 'image_analysis' 
  | 'price_search' 
  | 'smart_fill' 
  | 'price_refresh'
  | 'firecrawl_discovery'
  | 'firecrawl_refresh'
  | 'nearby_store_discovery';
export type AIJobStatus = 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled';

/**
 * AI background job model.
 * Represents an AI task running asynchronously.
 */
export interface AIJob {
  id: number;
  type: AIJobType;
  type_label: string;
  status: AIJobStatus;
  status_label: string;
  progress: number;
  input_summary: string;
  input_data?: Record<string, unknown>;
  output_data?: Record<string, unknown>;
  error_message?: string;
  related_item_id?: number;
  related_list_id?: number;
  started_at?: string;
  completed_at?: string;
  created_at: string;
  duration_ms?: number;
  formatted_duration: string;
  can_cancel: boolean;
  logs_count: number;
  logs?: AIRequestLogSummary[];
}

/**
 * AI request log types
 */
export type AIRequestLogStatus = 'pending' | 'success' | 'failed' | 'timeout';
export type AIRequestType = 'completion' | 'image_analysis' | 'test_connection' | 'price_aggregation';

/**
 * Summary version of AI request log (for job details).
 */
export interface AIRequestLogSummary {
  id: number;
  provider: string;
  model?: string;
  request_type: AIRequestType;
  status: AIRequestLogStatus;
  duration_ms: number;
  formatted_duration: string;
  tokens_input?: number;
  tokens_output?: number;
  created_at: string;
}

/**
 * Full AI request log model.
 * Contains complete request/response data for debugging.
 */
export interface AIRequestLog {
  id: number;
  ai_job_id?: number;
  provider: string;
  provider_display_name: string;
  model?: string;
  request_type: AIRequestType;
  type_label: string;
  status: AIRequestLogStatus;
  duration_ms: number;
  formatted_duration: string;
  tokens_input?: number;
  tokens_output?: number;
  total_tokens: number;
  error_message?: string;
  truncated_prompt: string;
  request_data?: {
    prompt?: string;
    options?: Record<string, unknown>;
  };
  response_data?: Record<string, unknown>;
  serp_data?: Record<string, unknown>;
  serp_data_summary?: {
    results_count: number;
    search_query?: string;
    engine?: string;
  };
  created_at: string;
}

/**
 * AI job statistics response.
 */
export interface AIJobStats {
  total: number;
  completed: number;
  failed: number;
  cancelled: number;
  active: number;
  success_rate: number;
  by_type: Record<AIJobType, number>;
}

/**
 * AI log statistics response.
 */
export interface AILogStats {
  total_requests: number;
  successful_requests: number;
  failed_requests: number;
  success_rate: number;
  total_tokens: number;
  by_provider: Record<string, number>;
  by_type: Record<AIRequestType, number>;
}

/**
 * Store Registry types
 */
export type StoreCategory =
  | 'general'
  | 'electronics'
  | 'grocery'
  | 'home'
  | 'clothing'
  | 'pharmacy'
  | 'warehouse'
  | 'pet'
  | 'specialty';

/**
 * Store model with user preference data.
 */
export interface Store {
  id: number;
  name: string;
  slug: string;
  domain: string;
  logo_url?: string;
  category?: StoreCategory;
  is_default: boolean;
  is_local: boolean;
  has_search_template: boolean;
  auto_configured?: boolean;
  address?: string;
  phone?: string;
  default_priority: number;
  // User-specific preferences (merged from user_store_preferences)
  enabled: boolean;
  is_favorite: boolean;
  priority: number;
}

/**
 * Store preference update payload.
 */
export interface StorePreferenceUpdate {
  enabled?: boolean;
  is_favorite?: boolean;
  priority?: number;
}

/**
 * Custom store creation payload.
 */
export interface CustomStorePayload {
  name: string;
  domain: string;
  search_url_template?: string;
  category?: StoreCategory;
  is_local?: boolean;
}

/**
 * Nearby store discovery request payload.
 */
export interface NearbyStoreDiscoveryRequest {
  radius_miles?: number;
  categories?: StoreCategory[];
  latitude?: number;
  longitude?: number;
}

/**
 * Nearby store discovery result from Google Places.
 */
export interface NearbyStoreResult {
  place_id: string;
  name: string;
  address: string;
  latitude: number;
  longitude: number;
  website?: string;
  phone?: string;
  types: string[];
  rating?: number;
  review_count?: number;
  distance_miles: number;
  category: StoreCategory;
}

/**
 * Nearby store discovery job result.
 */
export interface NearbyStoreDiscoveryResult {
  stores_found: number;
  stores_added: number;
  stores_skipped: number;
  stores_configured: number;
  added_store_ids?: number[];
}

/**
 * Feature availability check response.
 */
export interface NearbyStoreAvailability {
  available: boolean;
  has_google_places_key: boolean;
  has_firecrawl_key: boolean;
  has_location: boolean;
  can_auto_configure: boolean;
}

/**
 * Discovery category option for UI.
 */
export interface DiscoveryCategory {
  id: StoreCategory;
  label: string;
  description?: string;
}

/**
 * Smart Add queue item status.
 */
export type SmartAddQueueStatus = 'pending' | 'reviewed' | 'added' | 'dismissed';

/**
 * Product suggestion from AI identification.
 */
export interface ProductSuggestion {
  product_name: string;
  brand: string | null;
  model: string | null;
  category: string | null;
  upc: string | null;
  image_url: string | null;
  is_generic: boolean;
  unit_of_measure: string | null;
  confidence: number;
}

/**
 * Smart Add queue item - represents a pending product identification.
 * Users can review the AI-identified products and add them to a list or dismiss.
 */
export interface SmartAddQueueItem {
  id: number;
  status: SmartAddQueueStatus;
  status_label: string;
  source_type: 'image' | 'text';
  source_query: string | null;
  display_title: string;
  display_image: string | null;
  suggestions_count: number;
  product_data: ProductSuggestion[];
  providers_used: string[] | null;
  created_at: string;
  expires_at: string;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User | null;
  };
  flash: {
    success?: string;
    error?: string;
    message?: string;
  };
};
