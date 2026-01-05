import { PageProps, DashboardStats, DashboardItem, StoreStats, ActiveJob, PriceTrendPoint } from '@/types';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Progress } from '@/Components/ui/progress';
import { cn } from '@/lib/utils';
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
} from 'recharts';
import {
  Plus,
  TrendingDown,
  Star,
  ShoppingCart,
  ListTodo,
  Package,
  DollarSign,
  Clock,
  AlertTriangle,
  Sparkles,
  Activity,
  Trophy,
  ArrowRight,
  RefreshCw,
  CheckCircle2,
} from 'lucide-react';

interface Props extends PageProps {
  stats: DashboardStats;
  recent_drops: DashboardItem[];
  all_time_lows: DashboardItem[];
  store_stats: StoreStats[];
  active_jobs_count: number;
  active_jobs: ActiveJob[];
  last_price_update: string | null;
  price_trend: PriceTrendPoint[];
  items_needing_attention: DashboardItem[];
}

// Helper to safely format a number
const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

// Format relative time
const formatRelativeTime = (dateString: string | null | undefined): string => {
  if (!dateString) return 'Never';
  
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays} days ago`;
  return date.toLocaleDateString();
};

// Format date for chart
const formatChartDate = (dateStr: string): string => {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Store colors for the bar chart
const STORE_COLORS = [
  '#8B5CF6', // Violet
  '#06B6D4', // Cyan
  '#F59E0B', // Amber
  '#10B981', // Emerald
  '#EC4899', // Pink
  '#3B82F6', // Blue
];

// Custom tooltip for charts
const CustomTooltip = ({ active, payload, label }: any) => {
  if (!active || !payload || !payload.length) return null;

  return (
    <div className="bg-popover border border-border rounded-lg shadow-lg p-3">
      <p className="text-sm font-medium text-foreground mb-1">{label}</p>
      {payload.map((entry: any, index: number) => (
        <div key={index} className="flex items-center gap-2 text-sm">
          <div
            className="w-2 h-2 rounded-full"
            style={{ backgroundColor: entry.color }}
          />
          <span className="text-muted-foreground">{entry.name}:</span>
          <span className="font-medium text-foreground">
            ${formatPrice(entry.value)}
          </span>
        </div>
      ))}
    </div>
  );
};

// Stat Card Component
function StatCard({
  icon: Icon,
  label,
  value,
  subtext,
  trend,
  href,
  className,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: string | number;
  subtext?: string;
  trend?: 'up' | 'down' | 'neutral';
  href?: string;
  className?: string;
}) {
  const content = (
    <Card className={cn('relative overflow-hidden group', href && 'hover:shadow-lg transition-shadow cursor-pointer', className)}>
      <CardContent className="p-6">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <p className={cn(
              'text-3xl font-bold mt-1',
              trend === 'up' && 'text-green-600 dark:text-green-400',
              trend === 'down' && 'text-red-500',
              !trend && 'text-foreground'
            )}>
              {value}
            </p>
            {subtext && (
              <p className="text-xs text-muted-foreground mt-1">{subtext}</p>
            )}
          </div>
          <div className={cn(
            'p-3 rounded-xl',
            trend === 'up' && 'bg-green-500/10',
            trend === 'down' && 'bg-red-500/10',
            !trend && 'bg-primary/10'
          )}>
            <Icon className={cn(
              'h-6 w-6',
              trend === 'up' && 'text-green-600 dark:text-green-400',
              trend === 'down' && 'text-red-500',
              !trend && 'text-primary'
            )} />
          </div>
        </div>
        {href && (
          <ArrowRight className="absolute bottom-4 right-4 h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
        )}
      </CardContent>
    </Card>
  );

  return href ? <Link href={href}>{content}</Link> : content;
}

// Item Card Component
function ItemCard({ item, showPriceChange = false }: { item: DashboardItem; showPriceChange?: boolean }) {
  return (
    <Link href={`/items/${item.id}`}>
      <div className="flex items-center gap-3 p-3 rounded-xl hover:bg-muted/50 transition-colors group">
        <div className="w-12 h-12 rounded-lg bg-muted flex items-center justify-center flex-shrink-0 overflow-hidden">
          {item.product_image_url ? (
            <img
              src={item.product_image_url}
              alt={item.product_name}
              className="w-full h-full object-contain"
            />
          ) : (
            <Package className="h-6 w-6 text-muted-foreground" />
          )}
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-medium text-foreground truncate group-hover:text-primary transition-colors">
            {item.product_name}
          </p>
          <div className="flex items-center gap-2 text-sm">
            <span className={cn(
              'font-semibold',
              item.is_at_all_time_low ? 'text-amber-500' : 'text-green-600 dark:text-green-400'
            )}>
              ${formatPrice(item.current_price)}
            </span>
            {item.previous_price && item.previous_price !== item.current_price && (
              <span className="text-muted-foreground line-through text-xs">
                ${formatPrice(item.previous_price)}
              </span>
            )}
            {showPriceChange && item.price_change_percent && (
              <Badge variant="secondary" className="text-xs bg-green-500/10 text-green-600 dark:text-green-400">
                -{Math.abs(item.price_change_percent).toFixed(0)}%
              </Badge>
            )}
          </div>
          {item.list && (
            <p className="text-xs text-muted-foreground truncate">
              in {item.list.name}
            </p>
          )}
        </div>
      </div>
    </Link>
  );
}

export default function Dashboard({
  auth,
  stats,
  recent_drops,
  all_time_lows,
  store_stats,
  active_jobs_count,
  active_jobs,
  last_price_update,
  price_trend,
  items_needing_attention,
  flash,
}: Props) {
  const hasData = stats.items_count > 0;
  const hasPriceTrend = price_trend.some(p => p.avg_price !== null);

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Dashboard" />
      <div className="p-6 lg:p-8 space-y-8">
        {/* Welcome Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-foreground">
              Welcome back, {auth.user?.name?.split(' ')[0]}!
            </h1>
            <p className="text-muted-foreground mt-1">
              Here's your price tracking overview
            </p>
          </div>
          <div className="flex gap-3">
            <Button asChild variant="outline">
              <Link href="/lists/create">
                <Plus className="h-4 w-4 mr-2" />
                New List
              </Link>
            </Button>
            <Button asChild className="bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-700 hover:to-fuchsia-700">
              <Link href="/smart-add">
                <Sparkles className="h-4 w-4 mr-2" />
                Smart Add
              </Link>
            </Button>
          </div>
        </div>

        {/* Quick Stats Grid */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            icon={ListTodo}
            label="Shopping Lists"
            value={stats.lists_count}
            href="/lists"
          />
          <StatCard
            icon={Package}
            label="Total Items"
            value={stats.items_count}
            href="/items"
          />
          <StatCard
            icon={TrendingDown}
            label="Price Drops"
            value={stats.items_with_drops}
            trend={stats.items_with_drops > 0 ? 'up' : undefined}
            href="/items?status=drops"
          />
          <StatCard
            icon={DollarSign}
            label="Potential Savings"
            value={`$${formatPrice(stats.total_potential_savings, 0)}`}
            trend={stats.total_potential_savings > 0 ? 'up' : undefined}
          />
        </div>

        {/* Active Jobs & Last Update Row */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* Active Jobs Card */}
          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-lg flex items-center gap-2">
                  <Activity className="h-5 w-5 text-primary" />
                  Active Jobs
                </CardTitle>
                {active_jobs_count > 0 && (
                  <Badge variant="secondary">{active_jobs_count} running</Badge>
                )}
              </div>
            </CardHeader>
            <CardContent>
              {active_jobs.length > 0 ? (
                <div className="space-y-3">
                  {active_jobs.map((job) => (
                    <div key={job.id} className="space-y-1.5">
                      <div className="flex items-center justify-between text-sm">
                        <span className="font-medium text-foreground truncate flex-1">
                          {job.input_summary || job.type_label}
                        </span>
                        <span className="text-muted-foreground text-xs ml-2">
                          {job.progress}%
                        </span>
                      </div>
                      <Progress value={job.progress} className="h-1.5" />
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-4">
                  <CheckCircle2 className="h-8 w-8 mx-auto text-green-500 mb-2" />
                  <p className="text-sm text-muted-foreground">No active jobs</p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Last Update & Quick Stats */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-lg flex items-center gap-2">
                <Clock className="h-5 w-5 text-primary" />
                Price Check Status
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 gap-4">
                <div className="text-center p-3 rounded-xl bg-muted/50">
                  <p className="text-2xl font-bold text-foreground">
                    {formatRelativeTime(last_price_update)}
                  </p>
                  <p className="text-xs text-muted-foreground">Last Updated</p>
                </div>
                <div className="text-center p-3 rounded-xl bg-amber-500/10">
                  <p className="text-2xl font-bold text-amber-500">
                    {stats.all_time_lows_count}
                  </p>
                  <p className="text-xs text-muted-foreground">All-Time Lows</p>
                </div>
                <div className="text-center p-3 rounded-xl bg-green-500/10">
                  <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                    {stats.items_below_target}
                  </p>
                  <p className="text-xs text-muted-foreground">Below Target</p>
                </div>
                <div className="text-center p-3 rounded-xl bg-muted/50">
                  <p className="text-2xl font-bold text-foreground">
                    {items_needing_attention.length}
                  </p>
                  <p className="text-xs text-muted-foreground">Need Refresh</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Price Trend Chart */}
        {hasPriceTrend && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <TrendingDown className="h-5 w-5 text-primary" />
                7-Day Price Activity
              </CardTitle>
              <CardDescription>
                Average prices across all tracked items
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart
                    data={price_trend.filter(p => p.avg_price !== null)}
                    margin={{ top: 10, right: 10, left: 0, bottom: 0 }}
                  >
                    <defs>
                      <linearGradient id="avgPriceGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#8B5CF6" stopOpacity={0.3} />
                        <stop offset="95%" stopColor="#8B5CF6" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                    <XAxis
                      dataKey="date"
                      tickFormatter={formatChartDate}
                      className="text-xs"
                      tick={{ fill: 'hsl(var(--muted-foreground))' }}
                      axisLine={{ stroke: 'hsl(var(--border))' }}
                      tickLine={false}
                    />
                    <YAxis
                      tickFormatter={(v) => `$${v}`}
                      className="text-xs"
                      tick={{ fill: 'hsl(var(--muted-foreground))' }}
                      axisLine={false}
                      tickLine={false}
                      width={60}
                    />
                    <Tooltip content={<CustomTooltip />} />
                    <Area
                      type="monotone"
                      dataKey="avg_price"
                      name="Avg Price"
                      stroke="#8B5CF6"
                      strokeWidth={2}
                      fill="url(#avgPriceGradient)"
                    />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Price Drops & All-Time Lows */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Recent Price Drops */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                  <TrendingDown className="h-5 w-5 text-green-600 dark:text-green-400" />
                  Recent Price Drops
                </CardTitle>
                {recent_drops.length > 0 && (
                  <Button asChild variant="ghost" size="sm">
                    <Link href="/items?status=drops">
                      View All
                      <ArrowRight className="h-4 w-4 ml-1" />
                    </Link>
                  </Button>
                )}
              </div>
            </CardHeader>
            <CardContent>
              {recent_drops.length > 0 ? (
                <div className="space-y-1">
                  {recent_drops.map((item) => (
                    <ItemCard key={item.id} item={item} showPriceChange />
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <TrendingDown className="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                  <p className="text-muted-foreground">No recent price drops</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Add items to start tracking prices
                  </p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* All-Time Lows */}
          <Card className="border-amber-500/20">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                  <Star className="h-5 w-5 text-amber-500" />
                  All-Time Lows
                </CardTitle>
                {all_time_lows.length > 0 && (
                  <Button asChild variant="ghost" size="sm">
                    <Link href="/items?status=all_time_lows">
                      View All
                      <ArrowRight className="h-4 w-4 ml-1" />
                    </Link>
                  </Button>
                )}
              </div>
            </CardHeader>
            <CardContent>
              {all_time_lows.length > 0 ? (
                <div className="space-y-1">
                  {all_time_lows.map((item) => (
                    <ItemCard key={item.id} item={item} />
                  ))}
                </div>
              ) : (
                <div className="text-center py-8">
                  <Star className="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                  <p className="text-muted-foreground">No all-time lows yet</p>
                  <p className="text-sm text-muted-foreground mt-1">
                    Items at their lowest tracked price appear here
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Store Leaderboard */}
        {store_stats.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Trophy className="h-5 w-5 text-amber-500" />
                Best Value Stores
              </CardTitle>
              <CardDescription>
                Stores with the most "best price" wins across your items
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart
                    data={store_stats}
                    layout="vertical"
                    margin={{ top: 0, right: 30, left: 0, bottom: 0 }}
                  >
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" horizontal={false} />
                    <XAxis
                      type="number"
                      className="text-xs"
                      tick={{ fill: 'hsl(var(--muted-foreground))' }}
                      axisLine={false}
                      tickLine={false}
                    />
                    <YAxis
                      dataKey="vendor"
                      type="category"
                      className="text-xs"
                      tick={{ fill: 'hsl(var(--muted-foreground))' }}
                      axisLine={false}
                      tickLine={false}
                      width={100}
                    />
                    <Tooltip
                      content={({ active, payload }) => {
                        if (!active || !payload?.length) return null;
                        const data = payload[0].payload as StoreStats;
                        return (
                          <div className="bg-popover border border-border rounded-lg shadow-lg p-3">
                            <p className="font-medium text-foreground">{data.vendor}</p>
                            <p className="text-sm text-muted-foreground">
                              {data.wins} best price win{data.wins !== 1 ? 's' : ''}
                            </p>
                            {data.total_savings > 0 && (
                              <p className="text-sm text-green-600 dark:text-green-400">
                                ${formatPrice(data.total_savings)} in savings
                              </p>
                            )}
                          </div>
                        );
                      }}
                    />
                    <Bar dataKey="wins" radius={[0, 4, 4, 0]}>
                      {store_stats.map((_, index) => (
                        <Cell key={index} fill={STORE_COLORS[index % STORE_COLORS.length]} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Items Needing Attention */}
        {items_needing_attention.length > 0 && (
          <Card className="border-amber-500/20">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <AlertTriangle className="h-5 w-5 text-amber-500" />
                Items Needing Price Refresh
              </CardTitle>
              <CardDescription>
                These items haven't been checked in over 7 days
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                {items_needing_attention.map((item) => (
                  <Link key={item.id} href={`/items/${item.id}`}>
                    <div className="flex items-center gap-3 p-3 rounded-xl border border-amber-500/20 hover:bg-amber-500/5 transition-colors">
                      <div className="w-10 h-10 rounded-lg bg-muted flex items-center justify-center flex-shrink-0 overflow-hidden">
                        {item.product_image_url ? (
                          <img
                            src={item.product_image_url}
                            alt={item.product_name}
                            className="w-full h-full object-contain"
                          />
                        ) : (
                          <Package className="h-5 w-5 text-muted-foreground" />
                        )}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-foreground truncate text-sm">
                          {item.product_name}
                        </p>
                        <p className="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                          <RefreshCw className="h-3 w-3" />
                          {formatRelativeTime(item.last_checked_at)}
                        </p>
                      </div>
                    </div>
                  </Link>
                ))}
              </div>
            </CardContent>
          </Card>
        )}

        {/* Empty State */}
        {!hasData && (
          <Card>
            <CardContent className="text-center py-16">
              <ShoppingCart className="h-20 w-20 mx-auto mb-6 text-muted-foreground" />
              <h2 className="text-2xl font-bold text-foreground mb-3">
                Welcome to DanaVision!
              </h2>
              <p className="text-muted-foreground mb-8 max-w-md mx-auto">
                Start tracking prices by creating a shopping list and adding items.
                We'll monitor prices and notify you when they drop.
              </p>
              <div className="flex flex-col sm:flex-row gap-4 justify-center">
                <Button asChild size="lg">
                  <Link href="/lists/create">
                    <Plus className="h-5 w-5 mr-2" />
                    Create Your First List
                  </Link>
                </Button>
                <Button asChild size="lg" variant="outline">
                  <Link href="/smart-add">
                    <Sparkles className="h-5 w-5 mr-2" />
                    Quick Add with AI
                  </Link>
                </Button>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
