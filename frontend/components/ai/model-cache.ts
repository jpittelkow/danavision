import type { DiscoveredModel } from "@/components/ai/ai-types";

const LLM_MODELS_CACHE_KEY = "llm_discovered_models";
const LLM_MODELS_CACHE_TTL_MS = 60 * 60 * 1000;

export function getCachedModels(provider: string): DiscoveredModel[] | null {
  if (typeof sessionStorage === "undefined") return null;
  try {
    const raw = sessionStorage.getItem(`${LLM_MODELS_CACHE_KEY}_${provider}`);
    if (!raw) return null;
    const { models, ts } = JSON.parse(raw) as { models: DiscoveredModel[]; ts: number };
    if (Date.now() - ts > LLM_MODELS_CACHE_TTL_MS) return null;
    return models;
  } catch {
    return null;
  }
}

export function setCachedModels(provider: string, models: DiscoveredModel[]) {
  try {
    sessionStorage.setItem(
      `${LLM_MODELS_CACHE_KEY}_${provider}`,
      JSON.stringify({ models, ts: Date.now() })
    );
  } catch {
    // ignore
  }
}

export function clearCachedModels(provider: string) {
  try {
    sessionStorage.removeItem(`${LLM_MODELS_CACHE_KEY}_${provider}`);
  } catch {
    // ignore
  }
}
