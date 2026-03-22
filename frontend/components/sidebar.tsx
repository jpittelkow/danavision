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
import { Home, Settings, ChevronLeft, ShoppingCart, Sparkles, Search, Package, TicketPercent, Bot } from "lucide-react";
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

  const displayName = appName || "DanaVision";
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
              {/* Smart Add — prominent top action */}
              <div className="mb-3">
                <Link href="/smart-add">
                  <Button
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-12 font-semibold text-base shadow-sm transition-all duration-150",
                      pathname === "/smart-add"
                        ? "bg-primary text-primary-foreground shadow-md"
                        : "bg-primary/90 text-primary-foreground hover:bg-primary hover:shadow-md"
                    )}
                    title="Smart Add"
                  >
                    <Sparkles className="h-5 w-5 flex-shrink-0" />
                    <span>Smart Add</span>
                  </Button>
                </Link>
              </div>
              <Separator orientation="horizontal" className="mb-3" />
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
                <Link href="/lists">
                  <Button
                    variant="ghost"
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                      pathname?.startsWith("/lists")
                        ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                        : "hover:bg-accent"
                    )}
                    title="Shopping Lists"
                  >
                    <ShoppingCart className="h-5 w-5 flex-shrink-0" />
                    <span>Shopping Lists</span>
                  </Button>
                </Link>
                <Link href="/items">
                  <Button
                    variant="ghost"
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                      pathname?.startsWith("/items")
                        ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                        : "hover:bg-accent"
                    )}
                    title="All Items"
                  >
                    <Package className="h-5 w-5 flex-shrink-0" />
                    <span>All Items</span>
                  </Button>
                </Link>
                <Link href="/search">
                  <Button
                    variant="ghost"
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                      pathname === "/search"
                        ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                        : "hover:bg-accent"
                    )}
                    title="Search"
                  >
                    <Search className="h-5 w-5 flex-shrink-0" />
                    <span>Search</span>
                  </Button>
                </Link>
                <Link href="/deals">
                  <Button
                    variant="ghost"
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                      pathname?.startsWith("/deals")
                        ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                        : "hover:bg-accent"
                    )}
                    title="Deals"
                  >
                    <TicketPercent className="h-5 w-5 flex-shrink-0" />
                    <span>Deals</span>
                  </Button>
                </Link>
                <Link href="/ask-dana">
                  <Button
                    variant="ghost"
                    size="default"
                    className={cn(
                      "w-full justify-start gap-3 min-h-11 transition-colors duration-150",
                      pathname?.startsWith("/ask-dana")
                        ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                        : "hover:bg-accent"
                    )}
                    title="Ask Dana"
                  >
                    <Bot className="h-5 w-5 flex-shrink-0" />
                    <span>Ask Dana</span>
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
        "fixed left-0 top-0 h-screen flex flex-col border-r bg-card z-30 transition-all duration-300",
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
        {/* Smart Add — prominent top action */}
        <div className="mb-2">
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-12 font-semibold shadow-sm transition-all duration-150",
                  isExpanded ? "w-full justify-start gap-3 text-base" : "w-12 h-12 mx-auto",
                  pathname === "/smart-add"
                    ? "bg-primary text-primary-foreground shadow-md"
                    : "bg-primary/90 text-primary-foreground hover:bg-primary hover:shadow-md"
                )}
                asChild
              >
                <Link href="/smart-add">
                  <Sparkles className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>Smart Add</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">Smart Add</TooltipContent>}
          </Tooltip>
        </div>
        <Separator orientation="horizontal" className="mb-2" />

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

          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-11 transition-colors duration-150",
                  isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                  pathname?.startsWith("/lists")
                    ? isExpanded
                      ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                      : "bg-primary/10 text-primary font-medium"
                    : "hover:bg-accent"
                )}
                asChild
              >
                <Link href="/lists">
                  <ShoppingCart className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>Shopping Lists</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">Shopping Lists</TooltipContent>}
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-11 transition-colors duration-150",
                  isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                  pathname?.startsWith("/items")
                    ? isExpanded
                      ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                      : "bg-primary/10 text-primary font-medium"
                    : "hover:bg-accent"
                )}
                asChild
              >
                <Link href="/items">
                  <Package className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>All Items</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">All Items</TooltipContent>}
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-11 transition-colors duration-150",
                  isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                  pathname === "/search"
                    ? isExpanded
                      ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                      : "bg-primary/10 text-primary font-medium"
                    : "hover:bg-accent"
                )}
                asChild
              >
                <Link href="/search">
                  <Search className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>Search</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">Search</TooltipContent>}
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-11 transition-colors duration-150",
                  isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                  pathname?.startsWith("/deals")
                    ? isExpanded
                      ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                      : "bg-primary/10 text-primary font-medium"
                    : "hover:bg-accent"
                )}
                asChild
              >
                <Link href="/deals">
                  <TicketPercent className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>Deals</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">Deals</TooltipContent>}
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size={isExpanded ? "default" : "icon"}
                className={cn(
                  "min-h-11 transition-colors duration-150",
                  isExpanded ? "w-full justify-start gap-3" : "w-12 h-12 mx-auto",
                  pathname?.startsWith("/ask-dana")
                    ? isExpanded
                      ? "bg-primary/10 text-primary font-medium border-l-2 border-primary rounded-l-none rounded-r-md"
                      : "bg-primary/10 text-primary font-medium"
                    : "hover:bg-accent"
                )}
                asChild
              >
                <Link href="/ask-dana">
                  <Bot className="h-5 w-5 flex-shrink-0" />
                  {isExpanded && <span>Ask Dana</span>}
                </Link>
              </Button>
            </TooltipTrigger>
            {!isExpanded && <TooltipContent side="right">Ask Dana</TooltipContent>}
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
