"use client";

import { useState, useEffect } from "react";

const MOBILE_BREAKPOINT = 768; // Tailwind md

/**
 * Returns true when viewport width is below the md breakpoint (768px).
 * Used to switch between desktop sidebar and mobile drawer.
 */
export function useIsMobile(): boolean {
  const [isMobile, setIsMobile] = useState(() => {
    if (typeof window === "undefined") return false;
    return window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`).matches;
  });

  useEffect(() => {
    if (typeof window === "undefined") return;

    const mql = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);
    const handler = () => setIsMobile(mql.matches);
    handler(); // set initial value
    mql.addEventListener("change", handler);
    return () => mql.removeEventListener("change", handler);
  }, []);

  return isMobile;
}
