import { api } from "./api";

export interface SearchResult {
  id: number | string;
  type: string;
  title: string;
  subtitle?: string;
  url: string;
  highlight?: {
    title?: string;
    subtitle?: string;
  };
}

export interface SearchResponse {
  data: SearchResult[];
  meta: {
    query: string;
    total: number;
    page: number;
    per_page: number;
  };
}

export async function search(
  query: string,
  type?: string,
  page = 1
): Promise<SearchResponse> {
  const params = new URLSearchParams();
  params.set("q", query);
  params.set("page", String(page));
  if (type) params.set("type", type);
  const res = await api.get<SearchResponse>(`/search?${params.toString()}`);
  return res.data;
}

export async function getSuggestions(
  query: string,
  limit = 5
): Promise<SearchResult[]> {
  const params = new URLSearchParams();
  params.set("q", query);
  params.set("limit", String(limit));
  const res = await api.get<{ data: SearchResult[] }>(
    `/search/suggestions?${params.toString()}`
  );
  return res.data.data ?? [];
}
