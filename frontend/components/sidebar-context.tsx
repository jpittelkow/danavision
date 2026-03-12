"use client";

import { createContext, useContext, useState, useEffect, ReactNode } from "react";

interface SidebarContextType {
  isExpanded: boolean;
  toggleSidebar: () => void;
  isMobileMenuOpen: boolean;
  setMobileMenuOpen: (open: boolean) => void;
}

const SidebarContext = createContext<SidebarContextType | undefined>(undefined);

export function SidebarProvider({ children }: { children: ReactNode }) {
  // Load from localStorage on mount, default to collapsed
  const [isExpanded, setIsExpanded] = useState(false);
  const [isMobileMenuOpen, setMobileMenuOpen] = useState(false);

  useEffect(() => {
    const saved = localStorage.getItem("sidebar-expanded");
    if (saved !== null) {
      setIsExpanded(saved === "true");
    }
  }, []);

  const toggleSidebar = () => {
    setIsExpanded((prev) => {
      const newValue = !prev;
      localStorage.setItem("sidebar-expanded", String(newValue));
      return newValue;
    });
  };

  return (
    <SidebarContext.Provider
      value={{
        isExpanded,
        toggleSidebar,
        isMobileMenuOpen,
        setMobileMenuOpen,
      }}
    >
      {children}
    </SidebarContext.Provider>
  );
}

export function useSidebar() {
  const context = useContext(SidebarContext);
  if (context === undefined) {
    throw new Error("useSidebar must be used within a SidebarProvider");
  }
  return context;
}
