import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { ThemeToggle } from '@/Components/ThemeToggle';
import {
  LayoutDashboard,
  ListTodo,
  Search,
  Settings,
  LogOut,
  Menu,
  X,
  Sparkles,
} from 'lucide-react';

interface LayoutProps extends PageProps {
  children: React.ReactNode;
}

const navigation = [
  { name: 'Smart Add', href: '/smart-add', icon: Sparkles, primary: true },
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { name: 'Lists', href: '/lists', icon: ListTodo },
  { name: 'Search', href: '/search', icon: Search },
  { name: 'Settings', href: '/settings', icon: Settings },
];

export default function AppLayout({ children, auth, flash }: LayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);

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

        {/* User section */}
        <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-white/10">
          <div className="flex items-center gap-3 px-2 mb-3">
            <div className="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center text-primary-foreground font-bold">
              {auth.user?.name.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-primary-foreground font-medium truncate">{auth.user?.name}</p>
              <p className="text-primary-foreground/60 text-sm truncate">{auth.user?.email}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2 px-4 py-2 text-primary-foreground/80 hover:text-primary-foreground hover:bg-white/10 rounded-lg transition-colors"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign Out</span>
          </button>
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

        {/* Flash Messages */}
        {flash?.message && (
          <div className="mx-4 mt-4">
            <div className="bg-blue-100 dark:bg-blue-900/30 border border-blue-400 dark:border-blue-700 text-blue-700 dark:text-blue-300 px-4 py-3 rounded-xl">
              {flash.message}
            </div>
          </div>
        )}

        {/* Page content */}
        <main>{children}</main>
      </div>
    </div>
  );
}
