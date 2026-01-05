import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { cn } from '@/lib/utils';
import {
  Package,
  TrendingDown,
  Star,
  Filter,
  CheckCircle2,
  Clock,
  ListTodo,
} from 'lucide-react';

interface ItemWithList {
  id: number;
  product_name: string;
  product_image_url?: string;
  current_price?: number;
  previous_price?: number;
  lowest_price?: number;
  target_price?: number;
  priority: 'low' | 'medium' | 'high';
  is_purchased: boolean;
  is_at_all_time_low?: boolean;
  last_checked_at?: string;
  list: {
    id: number;
    name: string;
  };
}

interface ListOption {
  id: number;
  name: string;
  items_count: number;
}

interface Filters {
  list_id?: string;
  status?: string;
  priority?: string;
  purchased?: string;
  sort: string;
  dir: string;
}

interface PaginatedData<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
  links: { url: string | null; label: string; active: boolean }[];
}

interface Props extends PageProps {
  items: PaginatedData<ItemWithList>;
  lists: ListOption[];
  filters: Filters;
}

const formatPrice = (value: number | string | null | undefined): string => {
  const num = Number(value) || 0;
  return '$' + num.toFixed(2);
};

const formatRelativeTime = (dateString: string | null | undefined): string => {
  if (!dateString) return 'Never checked';
  
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
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
};

const getPriorityColor = (priority: string) => {
  switch (priority) {
    case 'high':
      return 'text-red-500 bg-red-500/10';
    case 'medium':
      return 'text-amber-500 bg-amber-500/10';
    default:
      return 'text-slate-500 bg-slate-500/10';
  }
};

export default function ItemsIndex({ auth, items, lists, filters, flash }: Props) {
  const [showFilters, setShowFilters] = useState(false);

  const updateFilter = (key: string, value: string | null) => {
    const params: Record<string, string> = { ...filters };
    if (value && value !== 'all') {
      params[key] = value;
    } else {
      delete params[key];
    }
    router.get('/items', params, { preserveState: true });
  };

  const hasDropped = (item: ItemWithList) => {
    return item.previous_price && item.current_price && item.current_price < item.previous_price;
  };

  const priceChangePercent = (item: ItemWithList) => {
    if (!item.previous_price || !item.current_price) return null;
    return ((item.previous_price - item.current_price) / item.previous_price) * 100;
  };

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="All Items" />
      <div className="p-6 lg:p-8">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 className="text-3xl font-bold text-foreground flex items-center gap-3">
              <Package className="h-8 w-8 text-primary" />
              All Items
            </h1>
            <p className="text-muted-foreground mt-1">
              {items.total} item{items.total !== 1 ? 's' : ''} across all your lists
            </p>
          </div>
          <Button
            variant="outline"
            onClick={() => setShowFilters(!showFilters)}
            className="gap-2"
          >
            <Filter className="h-4 w-4" />
            Filters
            {(filters.list_id || filters.status || filters.priority) && (
              <Badge variant="secondary" className="ml-1">Active</Badge>
            )}
          </Button>
        </div>

        {/* Filters */}
        {showFilters && (
          <Card className="mb-6">
            <CardContent className="p-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                {/* List Filter */}
                <div>
                  <label className="text-sm font-medium text-muted-foreground mb-1.5 block">
                    List
                  </label>
                  <Select
                    value={filters.list_id || 'all'}
                    onValueChange={(v) => updateFilter('list_id', v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="All Lists" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Lists</SelectItem>
                      {lists.map((list) => (
                        <SelectItem key={list.id} value={String(list.id)}>
                          {list.name} ({list.items_count})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {/* Status Filter */}
                <div>
                  <label className="text-sm font-medium text-muted-foreground mb-1.5 block">
                    Price Status
                  </label>
                  <Select
                    value={filters.status || 'all'}
                    onValueChange={(v) => updateFilter('status', v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Any Status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Any Status</SelectItem>
                      <SelectItem value="drops">Price Drops</SelectItem>
                      <SelectItem value="all_time_lows">All-Time Lows</SelectItem>
                      <SelectItem value="below_target">Below Target</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Priority Filter */}
                <div>
                  <label className="text-sm font-medium text-muted-foreground mb-1.5 block">
                    Priority
                  </label>
                  <Select
                    value={filters.priority || 'all'}
                    onValueChange={(v) => updateFilter('priority', v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Any Priority" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Any Priority</SelectItem>
                      <SelectItem value="high">High</SelectItem>
                      <SelectItem value="medium">Medium</SelectItem>
                      <SelectItem value="low">Low</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Purchased Filter */}
                <div>
                  <label className="text-sm font-medium text-muted-foreground mb-1.5 block">
                    Status
                  </label>
                  <Select
                    value={filters.purchased ?? 'all'}
                    onValueChange={(v) => updateFilter('purchased', v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Any" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">All Items</SelectItem>
                      <SelectItem value="0">Not Purchased</SelectItem>
                      <SelectItem value="1">Purchased</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                {/* Sort */}
                <div>
                  <label className="text-sm font-medium text-muted-foreground mb-1.5 block">
                    Sort By
                  </label>
                  <Select
                    value={`${filters.sort}_${filters.dir}`}
                    onValueChange={(v) => {
                      const [sort, dir] = v.split('_');
                      router.get('/items', { ...filters, sort, dir }, { preserveState: true });
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Sort..." />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="updated_at_desc">Recently Updated</SelectItem>
                      <SelectItem value="updated_at_asc">Oldest Updated</SelectItem>
                      <SelectItem value="product_name_asc">Name (A-Z)</SelectItem>
                      <SelectItem value="product_name_desc">Name (Z-A)</SelectItem>
                      <SelectItem value="current_price_asc">Price (Low-High)</SelectItem>
                      <SelectItem value="current_price_desc">Price (High-Low)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Items Grid */}
        {items.data.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {items.data.map((item) => (
              <Link key={item.id} href={`/items/${item.id}`}>
                <Card className={cn(
                  'h-full hover:shadow-lg transition-all cursor-pointer group',
                  item.is_at_all_time_low && 'ring-2 ring-amber-400 dark:ring-amber-500',
                  item.is_purchased && 'opacity-60'
                )}>
                  <CardContent className="p-4">
                    {/* Image */}
                    <div className="aspect-square rounded-lg bg-muted mb-3 overflow-hidden relative">
                      {item.product_image_url ? (
                        <img
                          src={item.product_image_url}
                          alt={item.product_name}
                          className="w-full h-full object-contain group-hover:scale-105 transition-transform"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center">
                          <Package className="h-12 w-12 text-muted-foreground/40" />
                        </div>
                      )}
                      {/* Badges overlay */}
                      <div className="absolute top-2 left-2 flex flex-col gap-1">
                        {item.is_at_all_time_low && (
                          <Badge className="bg-amber-500 hover:bg-amber-500 text-white gap-1 text-xs">
                            <Star className="h-3 w-3" />
                            All-Time Low
                          </Badge>
                        )}
                        {hasDropped(item) && !item.is_at_all_time_low && (
                          <Badge className="bg-green-600 hover:bg-green-600 text-white gap-1 text-xs">
                            <TrendingDown className="h-3 w-3" />
                            {priceChangePercent(item)?.toFixed(0)}% off
                          </Badge>
                        )}
                      </div>
                      {item.is_purchased && (
                        <div className="absolute top-2 right-2">
                          <Badge variant="secondary" className="gap-1 text-xs">
                            <CheckCircle2 className="h-3 w-3" />
                            Purchased
                          </Badge>
                        </div>
                      )}
                    </div>

                    {/* Content */}
                    <div className="space-y-2">
                      <h3 className="font-medium text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                        {item.product_name}
                      </h3>

                      {/* Price */}
                      <div className="flex items-baseline gap-2">
                        {item.current_price != null ? (
                          <>
                            <span className={cn(
                              'text-lg font-bold',
                              item.is_at_all_time_low ? 'text-amber-500' : hasDropped(item) ? 'text-green-600' : 'text-foreground'
                            )}>
                              {formatPrice(item.current_price)}
                            </span>
                            {item.previous_price && item.previous_price !== item.current_price && (
                              <span className="text-sm text-muted-foreground line-through">
                                {formatPrice(item.previous_price)}
                              </span>
                            )}
                          </>
                        ) : (
                          <span className="text-muted-foreground">No price yet</span>
                        )}
                      </div>

                      {/* List & Meta */}
                      <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span className="flex items-center gap-1 truncate">
                          <ListTodo className="h-3 w-3 flex-shrink-0" />
                          {item.list.name}
                        </span>
                        <span className="flex items-center gap-1">
                          <Clock className="h-3 w-3" />
                          {formatRelativeTime(item.last_checked_at)}
                        </span>
                      </div>

                      {/* Priority Badge */}
                      {item.priority !== 'low' && (
                        <Badge
                          variant="outline"
                          className={cn('text-xs capitalize', getPriorityColor(item.priority))}
                        >
                          {item.priority} priority
                        </Badge>
                      )}
                    </div>
                  </CardContent>
                </Card>
              </Link>
            ))}
          </div>
        ) : (
          <Card>
            <CardContent className="text-center py-12">
              <Package className="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
              <h2 className="text-xl font-semibold text-foreground mb-2">No items found</h2>
              <p className="text-muted-foreground mb-6">
                {filters.list_id || filters.status || filters.priority
                  ? 'Try adjusting your filters to see more items.'
                  : 'Add items to your lists to start tracking prices!'}
              </p>
              <Button asChild>
                <Link href="/lists">Go to Lists</Link>
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Pagination */}
        {items.last_page > 1 && (
          <div className="flex justify-center gap-2 mt-8">
            {items.links.map((link, index) => (
              <Button
                key={index}
                variant={link.active ? 'default' : 'outline'}
                size="sm"
                disabled={!link.url}
                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
