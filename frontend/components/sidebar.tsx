"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth, isAdminUser } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Logo } from "@/components/logo";
import {
  Sheet,
  SheetContent,
} from "@/components/ui/sheet";
import { Home, Settings, ChevronLeft } from "lucide-react";
import { cn } from "@/lib/utils";
import {
  Tooltip,
  TooltipTrigger,
  TooltipContent,
} from "@/components/ui/tooltip";
import { useSidebar } from "@/components/sidebar-context";
import { useIsMobile } from "@/lib/use-mobile";
import { useVersion } from "@/lib/version-provider";
import { useAppConfig } from "@/lib/app-config";

// Version footer component for sidebar
function SidebarVersionFooter({ isExpanded }: { isExpanded: boolean }) {
  const { version, buildSha } = useVersion();
  const { appName } = useAppConfig();
  
  if (!version || !isExpanded) {
    return null;
  }

  const displayName = appName || "Sourdough";
  const shortSha = buildSha && buildSha !== "development" 
    ? buildSha.substring(0, 7) 
    : null;

  return (
    <div className="pt-3 border-t px-2 pb-2">
      <Link href="/configuration/changelog" className="block text-center">
        <p className="text-xs text-muted-foreground hover:text-foreground transition-colors">
          {displayName} v{version}
          {shortSha && ` • ${shortSha}`}
        </p>
      </Link>
    </div>
  );
}

export function Sidebar() {
  const { user } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const { isExpanded, toggleSidebar, isMobileMenuOpen, setMobileMenuOpen } =
    useSidebar();
  const isMobile = useIsMobile();

  const isAdmin = isAdminUser(user);

  useEffect(() => {
    setMobileMenuOpen(false);
  }, [pathname, isMobile, setMobileMenuOpen]);

  if (isMobile) {
    return (
      <Sheet open={isMobileMenuOpen} onOpenChange={setMobileMenuOpen}>
        <SheetContent
          side="left"
          className="w-96 max-w-[100vw] p-0 flex flex-col"
        >
          <div className="flex flex-col h-full pt-14 px-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div className="flex items-center border-b pb-3 mb-4">
              <Logo variant="full" size="md" />
            </div>
            <div className="flex-1 flex flex-col">
              <nav className="flex flex-col gap-2">
                <Link href="/dashboard">
                  <Button
                    variant="ghost"
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                      pathname === "/dashboard"
                        ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                        : "hover:bg-accent"
                    )}
                    title="Home"
                  >
                    <Home className="h-5 w-5 flex-shrink-0" />
                    <span>Home</span>
                  </Button>
                </Link>
              </nav>
              <div className="mt-auto">
                {isAdmin && (
                  <>
                    <Separator orientation="horizontal" className="my-2" />
                    <nav className="flex flex-col gap-2">
                    <Button
                      variant="ghost"
                      size="default"
                      className={cn(
                        "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                        pathname?.startsWith("/configuration")
                          ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                          : "hover:bg-accent"
                      )}
                      title="Configuration"
                      onClick={() => {
                        setMobileMenuOpen(false);
                        router.push("/configuration");
                      }}
                    >
                      <Settings className="h-5 w-5 flex-shrink-0" />
                      <span>Configuration</span>
                    </Button>
                    </nav>
                  </>
                )}
                <SidebarVersionFooter isExpanded={true} />
              </div>
            </div>
          </div>
        </SheetContent>
      </Sheet>
    );
  }

  return (
    <aside
      className={cn(
        "fixed left-0 top-0 h-screen flex flex-col border-r bg-muted/30 z-30 transition-all duration-300",
        isExpanded ? "w-56" : "w-16"
      )}
    >
      <div
        className={cn(
          "flex items-center border-b p-3 h-14",
          isExpanded ? "justify-between" : "justify-center"
        )}
      >
        {isExpanded ? (
          <>
            <Logo variant="full" size="md" />
            <Button
              variant="ghost"
              size="icon"
              onClick={toggleSidebar}
              className="h-11 w-11 flex-shrink-0"
              title="Collapse sidebar"
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
          </>
        ) : (
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                onClick={toggleSidebar}
                className="flex items-center justify-center hover:opacity-80 transition-opacity"
                aria-label="Expand sidebar"
              >
                <Logo variant="icon" size="sm" />
              </button>
            </TooltipTrigger>
            <TooltipContent side="right">Expand sidebar</TooltipContent>
          </Tooltip>
        )}
      </div>

      <div className="flex-1 p-2 flex flex-col pt-4">
        <nav className="flex flex-col">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-11 transition-colors duration-150",
                  isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                  pathname === "/dashboard"
                    ? isExpanded
                      ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                      : "bg-primary/10 text-primary font-medium"
                    : "hover:bg-accent"
                )}
                asChild
              >
                <Link href="/dashboard">
                  <Home className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>Home</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">Home</TooltipContent>}
          </Tooltip>
        </nav>

        <div className="mt-auto">
          {isAdmin && (
            <>
              <Separator orientation="horizontal" className="my-2" />
              <nav className="flex flex-col gap-2">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size={isExpanded ? "default" : "icon"}
                    className={cn(
                      "min-h-11 transition-colors duration-150",
                      isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                      pathname?.startsWith("/configuration")
                        ? isExpanded
                          ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                          : "bg-primary/10 text-primary font-medium"
                        : "hover:bg-accent"
                    )}
                    asChild
                  >
                    <Link href="/configuration">
                      <Settings className="h-5 w-5 flex-shrink-0" />
                      {isExpanded && <span>Configuration</span>}
                    </Link>
                  </Button>
                </TooltipTrigger>
                {!isExpanded && <TooltipContent side="right">Configuration</TooltipContent>}
              </Tooltip>
              </nav>
            </>
          )}
          <SidebarVersionFooter isExpanded={isExpanded} />
        </div>
      </div>
    </aside>
  );
}
