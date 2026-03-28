import { api } from "@/lib/api";

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface CreateListData {
  name: string;
  description?: string;
  notify_on_any_drop?: boolean;
  notify_on_threshold?: boolean;
  threshold_percent?: number;
  shop_local?: boolean;
}

export interface CreateItemData {
  product_name: string;
  upc?: string;
  retailer?: string;
  target_price?: number;
  url?: string;
}

export interface CreateShareData {
  email: string;
  permission: "view" | "edit" | "admin";
  message?: string;
}

export interface ShoppingList {
  id: number;
  name: string;
  description?: string;
  items_count?: number;
  price_drops_count?: number;
  last_refreshed_at?: string;
  is_shared?: boolean;
  notify_on_any_drop?: boolean;
  notify_on_threshold?: boolean;
  threshold_percent?: number;
  shop_local?: boolean;
  items?: ShoppingItem[];
  created_at?: string;
  updated_at?: string;
}

export interface ItemVendorPrice {
  id: number;
  list_item_id: number;
  store_id?: number | null;
  vendor: string;
  vendor_sku?: string | null;
  product_url?: string | null;
  current_price: number | null;
  unit_price?: number | null;
  unit_quantity?: number | null;
  unit_type?: string | null;
  package_size?: string | null;
  previous_price?: number | null;
  lowest_price?: number | null;
  highest_price?: number | null;
  on_sale?: boolean;
  sale_percent_off?: number | null;
  in_stock?: boolean;
  last_checked_at?: string | null;
  store?: {
    id: number;
    name: string;
    slug: string;
    is_local: boolean;
  } | null;
}

export interface ShoppingItem {
  id: number;
  shopping_list_id: number;
  product_name: string;
  upc?: string;
  retailer?: string;
  image_url?: string;
  current_price?: number | null;
  previous_price?: number | null;
  lowest_price?: number | null;
  target_price?: number | null;
  in_stock?: boolean;
  is_purchased?: boolean;
  purchased_at?: string | null;
  purchased_price?: number | null;
  url?: string;
  vendor_prices?: ItemVendorPrice[];
  created_at?: string;
  updated_at?: string;
}

export interface PriceHistoryEntry {
  id?: number;
  date?: string;
  captured_at?: string;
  price: number;
  retailer?: string;
  url?: string;
  image_url?: string;
  in_stock?: boolean;
  source?: string;
}

export interface ListShare {
  id: number;
  shopping_list_id: number;
  user_id?: number;
  email?: string;
  permission: "view" | "edit" | "admin";
  status: "pending" | "accepted" | "declined" | "expired";
  user?: { id: number; name: string; email: string };
  shared_by?: { id: number; name: string; email: string };
  created_at?: string;
}

export interface SmartAddSuggestion {
  name?: string;
  product_name?: string;
  brand?: string;
  typical_price?: number;
  category?: string;
  upc?: string;
  retailer?: string;
  price?: number;
  confidence?: number;
  image_url?: string;
}

export interface SmartAddQueueItem {
  id: number;
  status: "pending" | "processing" | "ready" | "accepted" | "rejected" | "dismissed";
  source?: string;
  source_query?: string;
  source_image_path?: string;
  input_text?: string;
  image_url?: string;
  product_data?: SmartAddSuggestion[];
  /** @deprecated Use product_data instead */
  suggestions?: SmartAddSuggestion[];
  created_at?: string;
}

export interface AIJob {
  id: number;
  type: string;
  status: "pending" | "processing" | "completed" | "failed" | "cancelled";
  progress?: number;
  output_data?: unknown;
  error_message?: string;
  input_data?: unknown;
  related_item_id?: number;
  related_list_id?: number;
  started_at?: string;
  completed_at?: string;
  created_at?: string;
  updated_at?: string;
}

export interface ProductSearchResult {
  product_name: string;
  price?: number;
  retailer?: string;
  upc?: string;
  url?: string;
  image_url?: string;
}

export interface ShoppingStats {
  total_lists: number;
  total_items: number;
  price_drops: number;
  all_time_lows: number;
  total_savings: number;
  below_target: number;
  needs_refresh: number;
  pending_shares: number;
  recent_drops: Array<{
    id: number;
    shopping_list_id: number;
    product_name: string;
    current_price: number;
    previous_price: number;
    current_retailer?: string;
    last_checked_at?: string;
    shopping_list?: { id: number; name: string };
  }>;
  seven_day_activity: Array<{ date: string; count: number }>;
  store_leaderboard: Array<{
    vendor: string;
    items_count: number;
    lowest_price: number;
    avg_price: number;
  }>;
}

// ---------------------------------------------------------------------------
// Store Analysis Types
// ---------------------------------------------------------------------------

export interface AnalysisItemResult {
  item_id: number;
  name: string;
  price: number;
  unit_price: number | null;
  unit_quantity: number | null;
  unit_type: string | null;
  package_size: string | null;
  is_cheapest: boolean;
  deal_discount?: number;
  effective_price?: number;
  deals?: Array<{ id: number; type: string; discount_type: string; description: string; discount_amount: number }>;
}

export interface AnalysisStore {
  store_id: number | null;
  store_name: string;
  total_cost: number;
  items_found: number;
  items_missing: number;
  coverage_percent: number;
  savings_vs_highest: number | null;
  items: AnalysisItemResult[];
}

export interface SplitShoppingStore {
  store_id: number | null;
  store_name: string;
  subtotal: number;
  items: Pick<AnalysisItemResult, "item_id" | "name" | "price" | "unit_price" | "unit_type">[];
}

export interface StoreAnalysis {
  stores: AnalysisStore[];
  cheapest_store: AnalysisStore | null;
  split_shopping: {
    stores: SplitShoppingStore[];
    total_cost: number;
    total_savings: number | null;
    store_count: number;
  };
  total_items: number;
  analyzed_at: string;
}

// ---------------------------------------------------------------------------
// Lists
// ---------------------------------------------------------------------------

export async function fetchLists() {
  return api.get<{ data: ShoppingList[] }>("/lists");
}

export async function fetchList(id: number) {
  return api.get<{ data: ShoppingList }>(`/lists/${id}`);
}

export async function createList(data: CreateListData) {
  return api.post<{ data: ShoppingList }>("/lists", data);
}

export async function updateList(id: number, data: Partial<CreateListData>) {
  return api.put<{ data: ShoppingList }>(`/lists/${id}`, data);
}

export async function deleteList(id: number) {
  return api.delete(`/lists/${id}`);
}

export async function refreshList(id: number) {
  return api.post<{ data: ShoppingList }>(`/lists/${id}/refresh`);
}

export async function analyzeList(id: number) {
  return api.post<{ data: StoreAnalysis }>(`/lists/${id}/analyze`);
}

export async function fetchListAnalysis(id: number) {
  return api.get<{ data: StoreAnalysis | null; last_analyzed_at?: string }>(`/lists/${id}/analysis`);
}

// ---------------------------------------------------------------------------
// Items
// ---------------------------------------------------------------------------

export async function fetchAllItems(params?: {
  list_id?: number;
  status?: "active" | "purchased" | "all";
  price_status?: "drop" | "all_time_low" | "below_target";
  priority?: "low" | "medium" | "high";
  sort?: "name" | "price" | "updated";
  direction?: "asc" | "desc";
  page?: number;
}) {
  return api.get<{
    data: (ShoppingItem & { shopping_list?: { id: number; name: string } })[];
    current_page: number;
    last_page: number;
    total: number;
  }>("/items", { params });
}

export async function smartFillItem(id: number) {
  return api.post<{ data: { item: ShoppingItem; job: AIJob } }>(`/items/${id}/smart-fill`);
}

export async function addItem(listId: number, data: CreateItemData) {
  return api.post<{ data: ShoppingItem }>(`/lists/${listId}/items`, data);
}

export async function updateItem(id: number, data: Partial<CreateItemData>) {
  return api.put<{ data: ShoppingItem }>(`/items/${id}`, data);
}

export async function deleteItem(id: number) {
  return api.delete(`/items/${id}`);
}

export async function refreshItem(id: number) {
  return api.post<{ data: ShoppingItem }>(`/items/${id}/refresh`);
}

export async function markPurchased(id: number, price?: number) {
  return api.post<{ data: ShoppingItem }>(`/items/${id}/purchased`, { price });
}

export async function fetchItemHistory(id: number) {
  return api.get<{ data: PriceHistoryEntry[] }>(`/items/${id}/history`);
}

// ---------------------------------------------------------------------------
// Sharing
// ---------------------------------------------------------------------------

export async function fetchShares(listId: number) {
  return api.get<{ data: ListShare[] }>(`/lists/${listId}/shares`);
}

export async function createShare(listId: number, data: CreateShareData) {
  return api.post<{ data: ListShare }>(`/lists/${listId}/shares`, data);
}

export async function acceptShare(shareId: number) {
  return api.post(`/shares/${shareId}/accept`);
}

export async function declineShare(shareId: number) {
  return api.post(`/shares/${shareId}/decline`);
}

export async function revokeShare(shareId: number) {
  return api.delete(`/shares/${shareId}`);
}

export async function fetchPendingShares() {
  return api.get<{ data: ListShare[] }>("/shares/pending");
}

// ---------------------------------------------------------------------------
// Smart Add
// ---------------------------------------------------------------------------

export async function uploadSmartAdd(formData: FormData) {
  return api.post("/smart-add/upload", formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
}

export async function fetchSmartAddQueue() {
  return api.get<{ data: SmartAddQueueItem[] }>("/smart-add/queue");
}

export async function acceptSmartAddItem(
  id: number,
  data: { selected_index: number; shopping_list_id: number }
) {
  return api.post(`/smart-add/${id}/accept`, data);
}

export async function rejectSmartAddItem(id: number) {
  return api.delete(`/smart-add/${id}`);
}

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

export async function searchProducts(
  query: string,
  options?: { shop_local?: boolean }
) {
  return api.post<{ data: ProductSearchResult[] }>("/product-search", {
    query,
    ...options,
  });
}

export async function searchProductsByImage(formData: FormData) {
  return api.post<{ data: ProductSearchResult[] }>("/product-search/image", formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
}

export async function fetchSearchHistory() {
  return api.get<{ data: Array<{ id: number; query: string; query_type: string; results_count: number; created_at: string }> }>("/search-history");
}

// ---------------------------------------------------------------------------
// AI Jobs
// ---------------------------------------------------------------------------

export async function fetchAIJobs() {
  return api.get<{ data: AIJob[] }>("/ai-jobs");
}

export async function fetchAIJob(id: number) {
  return api.get<{ data: AIJob }>(`/ai-jobs/${id}`);
}

export async function cancelAIJob(id: number) {
  return api.post(`/ai-jobs/${id}/cancel`);
}

// ---------------------------------------------------------------------------
// AI Prompts
// ---------------------------------------------------------------------------

export interface AIPrompt {
  id: number;
  user_id: number;
  prompt_type: "product_identification" | "price_recommendation" | "product_aggregation";
  prompt_text: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export async function fetchAIPrompts() {
  return api.get<{ data: AIPrompt[] }>("/ai-prompts");
}

export async function createAIPrompt(data: { prompt_type: string; prompt_text: string }) {
  return api.post<{ data: AIPrompt }>("/ai-prompts", data);
}

export async function fetchActivePrompt(type: string) {
  return api.get<{ data: AIPrompt | null }>("/ai-prompts/active", { params: { prompt_type: type } });
}

export async function updateAIPrompt(id: number, data: { prompt_text?: string; is_active?: boolean }) {
  return api.put<{ data: AIPrompt }>(`/ai-prompts/${id}`, data);
}

export async function deleteAIPrompt(id: number) {
  return api.delete(`/ai-prompts/${id}`);
}

// ---------------------------------------------------------------------------
// Stores
// ---------------------------------------------------------------------------

export interface Store {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  search_url_template?: string;
  product_url_pattern?: string;
  category?: string;
  logo_url?: string;
  is_default: boolean;
  is_local: boolean;
  is_active: boolean;
  local_stock: boolean;
  local_price: boolean;
  parent_store_id?: number;
  user_enabled?: boolean;
  user_priority?: number;
  is_favorite?: boolean;
  parent_store?: Store;
  subsidiaries?: Store[];
}

export interface StorePreference {
  id: number;
  user_id: number;
  store_id: number;
  enabled: boolean;
  priority?: number;
  is_favorite: boolean;
  store?: Store;
}

export interface NearbyPlace {
  place_id: string;
  name: string;
  address: string;
  location: { lat: number; lng: number };
  rating?: number;
  types?: string[];
  distance_miles?: number;
}

export async function fetchStores() {
  return api.get<{ data: Store[] }>("/stores");
}

export async function fetchStore(id: number) {
  return api.get<{ data: Store }>(`/stores/${id}`);
}

export async function createStore(data: { name: string; domain?: string; search_url_template?: string; category?: string; is_local?: boolean }) {
  return api.post<{ data: Store }>("/stores", data);
}

export async function updateStore(id: number, data: Partial<Store>) {
  return api.put<{ data: Store }>(`/stores/${id}`, data);
}

export async function deleteStore(id: number) {
  return api.delete(`/stores/${id}`);
}

export async function fetchStorePreferences() {
  return api.get<{ data: StorePreference[] }>("/stores/preferences");
}

export async function updateStorePreferences(preferences: Array<{ store_id: number; enabled: boolean; priority?: number; is_favorite?: boolean }>) {
  return api.put("/stores/preferences", { preferences });
}

export async function suppressStore(id: number) {
  return api.post(`/stores/${id}/suppress`);
}

export async function restoreStore(id: number) {
  return api.post(`/stores/${id}/restore`);
}

export async function toggleStoreFavorite(id: number) {
  return api.post(`/stores/${id}/favorite`);
}

export async function updateStorePriorities(priorities: Array<{ store_id: number; priority: number }>) {
  return api.patch("/stores/priorities", { priorities });
}

export async function fetchNearbyAvailability() {
  return api.get<{ data: { available: boolean } }>("/stores/nearby/availability");
}

export async function previewNearbyStores(lat: number, lng: number, radiusMiles?: number, type?: string) {
  return api.post<{ data: NearbyPlace[] }>("/stores/nearby/preview", { lat, lng, radius_miles: radiusMiles, type });
}

export async function addNearbyStores(places: NearbyPlace[]) {
  return api.post<{ data: Store[] }>("/stores/nearby/add", { places });
}

export async function fetchSuppressedVendors() {
  return api.get<{ data: string[] }>("/stores/suppressed");
}

export async function searchAddress(query: string) {
  return api.get<{ data: Array<{ place_id: string; description: string }> }>("/stores/address-search", { params: { query } });
}

export async function geocodePlace(placeId: string) {
  return api.get<{ data: { address: string; zip_code: string | null; latitude: number | null; longitude: number | null } }>("/stores/address-geocode", { params: { place_id: placeId } });
}

// ---------------------------------------------------------------------------
// Deals & Coupons
// ---------------------------------------------------------------------------

export interface ScannedDeal {
  id: number;
  store_id?: number;
  store_name_raw?: string;
  product_name: string;
  product_description?: string;
  deal_type: "coupon" | "circular" | "flyer" | "bogo" | "clearance";
  discount_type: "amount_off" | "percent_off" | "fixed_price" | "bogo" | "buy_x_get_y";
  discount_value?: number;
  sale_price?: number;
  original_price?: number;
  conditions?: Record<string, unknown>;
  valid_from?: string;
  valid_to?: string;
  status: "pending" | "active" | "expired" | "dismissed";
  confidence?: number;
  matched_list_item_id?: number;
  source_scan_id?: number;
  content_hash?: string;
  store?: Store;
  matched_item?: ShoppingItem;
  created_at?: string;
  updated_at?: string;
}

export interface DealScan {
  id: number;
  image_path?: string;
  scan_type: string;
  deals_extracted: number;
  deals_accepted: number;
  deals_dismissed: number;
  status: "processing" | "completed" | "failed";
  error_message?: string;
  deals?: ScannedDeal[];
  created_at?: string;
}

export interface CreateDealData {
  product_name: string;
  product_description?: string;
  store_name?: string;
  store_id?: number;
  deal_type?: string;
  discount_type: string;
  discount_value?: number;
  sale_price?: number;
  original_price?: number;
  conditions?: Record<string, unknown>;
  valid_from?: string;
  valid_to?: string;
}

export interface DealSavingsSummary {
  total_savings: number;
  items_with_deals: number;
  deals_applied: number;
}

export async function uploadDealScan(formData: FormData) {
  return api.post<{ data: DealScan[] }>("/deals/scan", formData, {
    headers: { "Content-Type": "multipart/form-data" },
  });
}

export async function createDeal(data: CreateDealData) {
  return api.post<{ data: ScannedDeal }>("/deals", data);
}

export async function updateDeal(id: number, data: Partial<CreateDealData>) {
  return api.put<{ data: ScannedDeal }>(`/deals/${id}`, data);
}

export async function fetchDealQueue() {
  return api.get<{ data: DealScan[] }>("/deals/queue");
}

export async function fetchDeals(params?: { status?: string; store_id?: number }) {
  return api.get<{ data: ScannedDeal[] }>("/deals", { params });
}

export async function fetchDeal(id: number) {
  return api.get<{ data: ScannedDeal }>(`/deals/${id}`);
}

export async function acceptDeal(id: number) {
  return api.post<{ data: ScannedDeal }>(`/deals/${id}/accept`);
}

export async function acceptAllDeals(scanId: number) {
  return api.post<{ data: { count: number } }>(`/deals/scans/${scanId}/accept-all`);
}

export async function dismissDeal(id: number) {
  return api.delete(`/deals/${id}`);
}

export async function matchDealToItem(dealId: number, itemId: number) {
  return api.post<{ data: ScannedDeal }>(`/deals/${dealId}/match/${itemId}`);
}

export async function unmatchDeal(dealId: number) {
  return api.delete<{ data: ScannedDeal }>(`/deals/${dealId}/match`);
}

export async function fetchDealSavings(listId: number) {
  return api.get<{ data: DealSavingsSummary }>(`/deals/savings/${listId}`);
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

export async function fetchShoppingStats() {
  return api.get<{ data: ShoppingStats }>("/dashboard/shopping-stats");
}
