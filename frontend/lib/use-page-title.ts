"use client";

import { useEffect } from "react";
import { usePathname } from "next/navigation";
import { useAppConfig } from "@/lib/app-config";

/**
 * Hook to set page title and meta tags dynamically.
 *
 * @param pageTitle - Optional page-specific title (e.g., "Dashboard")
 * @param description - Optional meta description
 *
 * @example
 * // Just app name
 * usePageTitle();
 *
 * // Page name + app name
 * usePageTitle('Dashboard');
 *
 * // With description
 * usePageTitle('Dashboard', 'Manage your account settings');
 */
export function usePageTitle(pageTitle?: string, description?: string) {
  const { appName, isLoading } = useAppConfig();
  const pathname = usePathname();

  useEffect(() => {
    if (!isLoading && appName) {
      const fullTitle = (pageTitle && pageTitle.trim()) ? `${pageTitle} | ${appName}` : appName;

      document.title = fullTitle;

      if (description) {
        let metaDescription = document.querySelector('meta[name="description"]');
        if (!metaDescription) {
          metaDescription = document.createElement('meta');
          metaDescription.setAttribute('name', 'description');
          document.head.appendChild(metaDescription);
        }
        metaDescription.setAttribute('content', description);
      }

      let ogTitle = document.querySelector('meta[property="og:title"]');
      if (!ogTitle) {
        ogTitle = document.createElement('meta');
        ogTitle.setAttribute('property', 'og:title');
        document.head.appendChild(ogTitle);
      }
      ogTitle.setAttribute('content', fullTitle);

      // Single deferred update to override any framework title changes
      const timeoutId = setTimeout(() => {
        document.title = fullTitle;
      }, 0);

      return () => {
        clearTimeout(timeoutId);
      };
    }
  }, [appName, pageTitle, description, pathname, isLoading]);
}
