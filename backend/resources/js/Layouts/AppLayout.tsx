import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { ThemeToggle } from '@/Components/ThemeToggle';
import { JobNotifications } from '@/Components/JobNotifications';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { useFlashToast } from '@/hooks/useFlashToast';
import {
  LayoutDashboard,
  ListTodo,
  Package,
  Settings,
  LogOut,
  Menu,
  X,
  Sparkles,
} from 'lucide-react';

interface LayoutProps extends PageProps {
  children: React.ReactNode;
  app: {
    version: string;
    name: string;
  };
}

const navigation = [
  { name: 'Smart Add', href: '/smart-add', icon: Sparkles, primary: true },
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { name: 'Lists', href: '/lists', icon: ListTodo },
  { name: 'Items', href: '/items', icon: Package },
];

export default function AppLayout({ children, auth, app }: LayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  
  // Show flash messages as toasts
  useFlashToast();

  const handleLogout = () => {
    router.post('/logout');
  };

  const isActive = (href: string) => {
    if (typeof window === 'undefined') return false;
    return window.location.pathname === href || 
           (href !== '/' && window.location.pathname.startsWith(href));
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed top-0 left-0 z-50 h-full w-64 bg-primary transform transition-transform duration-300 ease-in-out lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        {/* Logo */}
        <div className="p-6 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <img
              src="/images/danavision_icon.png"
              alt="DanaVision"
              className="w-10 h-10"
            />
            <span className="text-xl font-bold text-primary-foreground">DanaVision</span>
          </div>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden text-primary-foreground/70 hover:text-primary-foreground"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation */}
        <nav className="mt-4 px-3 space-y-1">
          {navigation.map((item) => {
            const Icon = item.icon;
            const active = isActive(item.href);
            const isPrimary = 'primary' in item && item.primary;
            
            if (isPrimary) {
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={`flex items-center gap-3 px-4 py-3 rounded-xl transition-all mb-2 ${
                    active
                      ? 'bg-gradient-to-r from-violet-500 to-fuchsia-500 text-white shadow-lg shadow-violet-500/25'
                      : 'bg-gradient-to-r from-violet-500/80 to-fuchsia-500/80 text-white hover:from-violet-500 hover:to-fuchsia-500 hover:shadow-lg hover:shadow-violet-500/25'
                  }`}
                >
                  <Icon className="w-5 h-5" />
                  <span className="font-semibold">{item.name}</span>
                </Link>
              );
            }
            
            return (
              <Link
                key={item.name}
                href={item.href}
                className={`flex items-center gap-3 px-4 py-3 rounded-xl text-primary-foreground/90 transition-colors ${
                  active
                    ? 'bg-white/20 text-primary-foreground'
                    : 'hover:bg-white/10'
                }`}
              >
                <Icon className="w-5 h-5" />
                <span className="font-medium">{item.name}</span>
              </Link>
            );
          })}
        </nav>

        {/* Bottom section: Settings + User */}
        <div className="absolute bottom-0 left-0 right-0">
          {/* Settings */}
          <div className="px-3 pb-2">
            <Link
              href="/settings"
              className={`flex items-center gap-3 px-4 py-3 rounded-xl text-primary-foreground/90 transition-colors ${
                isActive('/settings')
                  ? 'bg-white/20 text-primary-foreground'
                  : 'hover:bg-white/10'
              }`}
            >
              <Settings className="w-5 h-5" />
              <span className="font-medium">Settings</span>
            </Link>
          </div>

          {/* User section */}
          <div className="p-4 border-t border-white/10">
            <div className="flex items-center gap-3 px-2 mb-3">
              <Avatar className="bg-white/20">
                <AvatarFallback className="bg-white/20 text-primary-foreground font-bold">
                  {auth.user?.name.charAt(0).toUpperCase()}
                </AvatarFallback>
              </Avatar>
              <div className="flex-1 min-w-0">
                <p className="text-primary-foreground font-medium truncate">{auth.user?.name}</p>
                <p className="text-primary-foreground/60 text-sm truncate">{auth.user?.email}</p>
              </div>
            </div>
            <Button
              variant="ghost"
              onClick={handleLogout}
              className="w-full justify-start gap-2 text-primary-foreground/80 hover:text-primary-foreground hover:bg-white/10"
            >
              <LogOut className="w-4 h-4" />
              <span>Sign Out</span>
            </Button>
            {/* Version display */}
            <div className="mt-3 pt-3 border-t border-white/10 text-center">
              <span className="text-xs text-primary-foreground/50">{app?.version || 'dev'}</span>
            </div>
          </div>
        </div>
      </aside>

      {/* Main content */}
      <div className="lg:ml-64">
        {/* Header */}
        <header className="sticky top-0 z-30 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-b border-border">
          <div className="flex items-center justify-between px-4 py-3">
            <button
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden p-2 -ml-2 text-foreground"
            >
              <Menu className="w-6 h-6" />
            </button>
            
            <div className="flex items-center gap-2 lg:hidden">
              <img
                src="/images/danavision_icon.png"
                alt="DanaVision"
                className="w-8 h-8"
              />
              <span className="font-bold text-foreground">DanaVision</span>
            </div>

            <div className="flex items-center gap-2 ml-auto">
              <ThemeToggle />
            </div>
          </div>
        </header>

        {/* Page content */}
        <main>{children}</main>
      </div>

      {/* Job notifications */}
      <JobNotifications />
    </div>
  );
}
