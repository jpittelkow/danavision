import { PageProps, ListItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Plus, Search, TrendingDown, Star, ShoppingCart } from 'lucide-react';

interface Props extends PageProps {
  stats: {
    lists_count: number;
    items_count: number;
    items_with_drops: number;
    total_potential_savings: number;
  };
  recent_drops: ListItem[];
  all_time_lows: ListItem[];
}

// Helper to safely format a number
const formatPrice = (value: number | string | null | undefined, decimals = 2): string => {
  const num = Number(value) || 0;
  return num.toFixed(decimals);
};

export default function Dashboard({ auth, stats, recent_drops, all_time_lows, flash }: Props) {
  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Dashboard" />
      <div className="p-6 lg:p-8">
        <h1 className="text-3xl font-bold text-foreground mb-2">
          Welcome back, {auth.user?.name}! 💜
        </h1>
        <p className="text-muted-foreground mb-8">Here's your shopping overview</p>
        
        {/* Stats */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <Card>
            <CardContent className="p-6">
              <div className="text-3xl font-bold text-foreground">{stats?.lists_count ?? 0}</div>
              <div className="text-muted-foreground">Lists</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="text-3xl font-bold text-foreground">{stats?.items_count ?? 0}</div>
              <div className="text-muted-foreground">Items</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="text-3xl font-bold text-green-600 dark:text-green-400">{stats?.items_with_drops ?? 0}</div>
              <div className="text-muted-foreground">Price Drops</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <div className="text-3xl font-bold text-accent">${formatPrice(stats?.total_potential_savings, 0)}</div>
              <div className="text-muted-foreground">Potential Savings</div>
            </CardContent>
          </Card>
        </div>

        {/* Quick Actions */}
        <div className="flex flex-wrap gap-4 mb-8">
          <Button asChild>
            <Link href="/lists/create">
              <Plus className="h-4 w-4 mr-2" />
              New List
            </Link>
          </Button>
          <Button asChild variant="outline">
            <Link href="/search">
              <Search className="h-4 w-4 mr-2" />
              Search Products
            </Link>
          </Button>
        </div>

        {/* Recent Price Drops */}
        {recent_drops && recent_drops.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-semibold text-foreground mb-4 flex items-center gap-2">
              <TrendingDown className="h-5 w-5 text-green-600 dark:text-green-400" />
              Recent Price Drops
            </h2>
            <div className="space-y-2">
              {recent_drops.map((item) => (
                <Card key={item.id}>
                  <CardContent className="p-4 flex items-center justify-between">
                    <div>
                      <div className="font-medium text-foreground">{item.product_name}</div>
                      <div className="text-sm text-green-600 dark:text-green-400">
                        ${formatPrice(item.current_price)} (was ${formatPrice(item.previous_price)})
                      </div>
                    </div>
                    <div className="text-green-600 dark:text-green-400 font-bold">
                      -{formatPrice(item.price_change_percent, 0)}%
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* All-Time Lows */}
        {all_time_lows && all_time_lows.length > 0 && (
          <div>
            <h2 className="text-xl font-semibold text-foreground mb-4 flex items-center gap-2">
              <Star className="h-5 w-5 text-accent" />
              All-Time Lows
            </h2>
            <div className="space-y-2">
              {all_time_lows.map((item) => (
                <Card key={item.id} className="border-2 border-accent">
                  <CardContent className="p-4">
                    <div className="font-medium text-foreground">{item.product_name}</div>
                    <div className="text-sm text-accent font-semibold">
                      ${formatPrice(item.current_price)} - Lowest ever!
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        )}

        {/* Empty State */}
        {(!recent_drops || recent_drops.length === 0) && (!all_time_lows || all_time_lows.length === 0) && (!stats || stats.items_count === 0) && (
          <Card>
            <CardContent className="text-center py-12">
              <ShoppingCart className="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
              <h2 className="text-xl font-semibold text-foreground mb-2">No items to track yet</h2>
              <p className="text-muted-foreground mb-6">Create a list and add items to start tracking prices!</p>
              <Button asChild>
                <Link href="/lists/create">Create Your First List</Link>
              </Button>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
