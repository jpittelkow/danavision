"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { SearchModal } from "@/components/search/search-modal";
import { useAppConfig } from "@/lib/app-config";

interface SearchContextValue {
  open: boolean;
  setOpen: (open: boolean) => void;
  setFocusInlineSearch: (fn: (() => void) | null) => void;
  searchEnabled: boolean;
}

const SearchContext = createContext<SearchContextValue | null>(null);

export function useSearch() {
  const ctx = useContext(SearchContext);
  if (!ctx) {
    throw new Error("useSearch must be used within SearchProvider");
  }
  return ctx;
}

const DESKTOP_BREAKPOINT = 768;

interface SearchProviderProps {
  children: ReactNode;
}

export function SearchProvider({ children }: SearchProviderProps) {
  const [open, setOpen] = useState(false);
  const focusInlineRef = useRef<(() => void) | null>(null);
  const { features } = useAppConfig();
  const searchEnabled = features?.searchEnabled !== false;

  const setFocusInlineSearch = useCallback((fn: (() => void) | null) => {
    focusInlineRef.current = fn;
  }, []);

  useEffect(() => {
    if (!searchEnabled) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === "k") {
        e.preventDefault();
        const isDesktop = typeof window !== "undefined" && window.innerWidth >= DESKTOP_BREAKPOINT;
        if (isDesktop && focusInlineRef.current) {
          focusInlineRef.current();
        } else {
          setOpen((prev) => !prev);
        }
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [searchEnabled]);

  return (
    <SearchContext.Provider value={{ open, setOpen, setFocusInlineSearch, searchEnabled }}>
      {children}
      {searchEnabled && <SearchModal open={open} onOpenChange={setOpen} />}
    </SearchContext.Provider>
  );
}
