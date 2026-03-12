"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, useEffect } from "react";
import { Toaster } from "sonner";
import { ThemeProvider } from "@/components/theme-provider";
import { TooltipProvider } from "@/components/ui/tooltip";
import { useAuth } from "@/lib/auth";
import { AppConfigProvider, useAppConfig } from "@/lib/app-config";
import { VersionProvider } from "@/lib/version-provider";
import { NotificationProvider } from "@/lib/notifications";
import { ErrorBoundary } from "@/components/error-boundary";
import { ErrorHandlerSetup } from "@/components/error-handler-setup";
import { ServiceWorkerSetup } from "@/components/service-worker-setup";

/**
 * Defers the Toaster mount to avoid "Cannot update a component while rendering
 * a different component" warnings. Sonner's Toaster triggers internal state
 * updates during its initial render which conflicts with React 18.3+ strict
 * mode when rendered synchronously inside a deep provider tree.
 */
function DeferredToaster() {
  const [mounted, setMounted] = useState(false);
  useEffect(() => {
    setMounted(true);
  }, []);
  if (!mounted) return null;
  return <Toaster richColors closeButton position="top-right" />;
}

/**
 * Reads the admin-configured default theme from AppConfig and passes it
 * to ThemeProvider so that new users see the admin's chosen default
 * instead of the hardcoded "system".
 */
function ConfiguredThemeProvider({ children }: { children: React.ReactNode }) {
  const { defaultTheme } = useAppConfig();
  return (
    <ThemeProvider defaultTheme={defaultTheme} storageKey="sourdough-theme">
      {children}
    </ThemeProvider>
  );
}

function AuthInitializer({ children }: { children: React.ReactNode }) {
  const { initialize, isInitialized } = useAuth();

  useEffect(() => {
    // Initialize auth state after component mounts (post-hydration)
    if (!isInitialized) {
      initialize();
    }
  }, [initialize, isInitialized]);

  return <>{children}</>;
}

export function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 60 * 1000,
            retry: 1,
          },
        },
      })
  );

  return (
    <QueryClientProvider client={queryClient}>
      <ErrorBoundary>
        <AppConfigProvider>
          <VersionProvider>
            <ConfiguredThemeProvider>
              <TooltipProvider delayDuration={300}>
                <ErrorHandlerSetup />
                <ServiceWorkerSetup />
                <AuthInitializer>
                  <NotificationProvider>{children}</NotificationProvider>
                </AuthInitializer>
                <DeferredToaster />
              </TooltipProvider>
            </ConfiguredThemeProvider>
          </VersionProvider>
        </AppConfigProvider>
      </ErrorBoundary>
    </QueryClientProvider>
  );
}
