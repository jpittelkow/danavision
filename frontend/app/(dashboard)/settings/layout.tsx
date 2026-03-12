"use client";

import { useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";

/**
 * Legacy settings layout - redirects to new /configuration routes
 * Kept for backward compatibility with bookmarks and external links
 */
export default function SettingsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    // Map old settings paths to new configuration paths
    const redirectMap: Record<string, string> = {
      "/settings": "/configuration",
      "/settings/profile": "/user/profile",
      "/settings/security": "/configuration/security",
      "/settings/notifications": "/configuration/notifications",
      "/settings/ai": "/configuration/ai",
      "/settings/system": "/configuration/system",
      "/settings/email": "/configuration/notifications?tab=email",
      "/settings/storage": "/configuration/storage",
      "/settings/api": "/configuration/api",
      "/settings/branding": "/configuration/branding",
    };

    const newPath = redirectMap[pathname] || "/configuration";
    router.replace(newPath);
  }, [pathname, router]);

  // Show loading while redirecting
  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
        <p className="text-muted-foreground text-sm">Redirecting to new location...</p>
      </div>
    </div>
  );
}
