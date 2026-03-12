"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { HelpCenterModal } from "@/components/help/help-center-modal";

interface HelpContextValue {
  isOpen: boolean;
  setIsOpen: (open: boolean) => void;
  openArticle: (articleId: string) => void;
  currentArticle: string | null;
  setCurrentArticle: (articleId: string | null) => void;
}

const HelpContext = createContext<HelpContextValue | null>(null);

export function useHelp() {
  const ctx = useContext(HelpContext);
  if (!ctx) {
    throw new Error("useHelp must be used within HelpProvider");
  }
  return ctx;
}

interface HelpProviderProps {
  children: ReactNode;
}

export function HelpProvider({ children }: HelpProviderProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [currentArticle, setCurrentArticle] = useState<string | null>(null);

  // Open help modal and navigate to a specific article
  const openArticle = useCallback((articleId: string) => {
    setCurrentArticle(articleId);
    setIsOpen(true);
  }, []);

  // Keyboard shortcuts: ? or Ctrl+/ to toggle help
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Don't trigger if user is typing in an input/textarea
      const target = e.target as HTMLElement;
      if (
        target.tagName === "INPUT" ||
        target.tagName === "TEXTAREA" ||
        target.isContentEditable
      ) {
        return;
      }

      // ? key (without modifiers) or Ctrl+/
      if (
        (e.key === "?" && !e.ctrlKey && !e.metaKey && !e.altKey) ||
        ((e.ctrlKey || e.metaKey) && e.key === "/")
      ) {
        e.preventDefault();
        setIsOpen((prev) => !prev);
      }

      // Escape to close
      if (e.key === "Escape" && isOpen) {
        setIsOpen(false);
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [isOpen]);

  // Reset article when modal closes
  useEffect(() => {
    if (!isOpen) {
      // Delay reset to allow close animation
      const timer = setTimeout(() => setCurrentArticle(null), 200);
      return () => clearTimeout(timer);
    }
  }, [isOpen]);

  return (
    <HelpContext.Provider
      value={{
        isOpen,
        setIsOpen,
        openArticle,
        currentArticle,
        setCurrentArticle,
      }}
    >
      {children}
      <HelpCenterModal />
    </HelpContext.Provider>
  );
}
