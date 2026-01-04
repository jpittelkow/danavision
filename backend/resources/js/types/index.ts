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
  price_provider: 'serpapi' | 'rainforest';
  price_api_key?: string;
  has_price_api_key?: boolean;
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
export type AIJobType = 'product_identification' | 'image_analysis' | 'price_search' | 'smart_fill' | 'price_refresh';
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
