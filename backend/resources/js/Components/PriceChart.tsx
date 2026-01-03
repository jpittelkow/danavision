import { useMemo, useState } from 'react';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

interface PricePoint {
  date: string;
  price: number;
  retailer: string;
  in_stock?: boolean;
}

interface PriceChartProps {
  data: Record<string, PricePoint[]>;
  className?: string;
}

// Color palette for different retailers
const RETAILER_COLORS: Record<string, string> = {
  'Amazon': '#FF9900',
  'Walmart': '#0071CE',
  'Target': '#CC0000',
  'Best Buy': '#0046BE',
  'Costco': '#E31837',
  'eBay': '#E53238',
  'Newegg': '#F7941D',
  'Home Depot': '#F96302',
  "Lowe's": '#004990',
};

const DEFAULT_COLORS = [
  '#8B5CF6', // Purple
  '#10B981', // Green
  '#F59E0B', // Yellow
  '#EF4444', // Red
  '#3B82F6', // Blue
  '#EC4899', // Pink
  '#6366F1', // Indigo
  '#14B8A6', // Teal
];

function getRetailerColor(retailer: string, index: number): string {
  return RETAILER_COLORS[retailer] || DEFAULT_COLORS[index % DEFAULT_COLORS.length];
}

// Format price for tooltip
const formatPrice = (value: number): string => {
  return `$${value.toFixed(2)}`;
};

// Format date for X axis
const formatDate = (dateStr: string): string => {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Custom tooltip component
const CustomTooltip = ({ active, payload, label }: any) => {
  if (!active || !payload || !payload.length) {
    return null;
  }

  const date = new Date(label);
  const formattedDate = date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });

  return (
    <div className="bg-popover border border-border rounded-lg shadow-lg p-3">
      <p className="text-sm font-medium text-foreground mb-2">{formattedDate}</p>
      <div className="space-y-1">
        {payload.map((entry: any, index: number) => (
          <div key={index} className="flex items-center justify-between gap-4 text-sm">
            <div className="flex items-center gap-2">
              <div
                className="w-2 h-2 rounded-full"
                style={{ backgroundColor: entry.color }}
              />
              <span className="text-muted-foreground">{entry.name}</span>
            </div>
            <span className="font-medium text-foreground">
              {formatPrice(entry.value)}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
};

type TimeRange = '7d' | '30d' | '90d' | 'all';

export function PriceChart({ data, className }: PriceChartProps) {
  const [timeRange, setTimeRange] = useState<TimeRange>('30d');

  // Get all retailers
  const retailers = useMemo(() => Object.keys(data), [data]);

  // Combine and filter data based on time range
  const chartData = useMemo(() => {
    const now = new Date();
    let cutoffDate: Date;

    switch (timeRange) {
      case '7d':
        cutoffDate = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        break;
      case '30d':
        cutoffDate = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
        break;
      case '90d':
        cutoffDate = new Date(now.getTime() - 90 * 24 * 60 * 60 * 1000);
        break;
      default:
        cutoffDate = new Date(0);
    }

    // Collect all unique dates
    const allDates = new Set<string>();
    Object.values(data).forEach((points) => {
      points.forEach((p) => {
        const date = new Date(p.date);
        if (date >= cutoffDate) {
          allDates.add(p.date);
        }
      });
    });

    // Sort dates
    const sortedDates = Array.from(allDates).sort(
      (a, b) => new Date(a).getTime() - new Date(b).getTime()
    );

    // Build chart data with all retailers for each date
    return sortedDates.map((date) => {
      const point: Record<string, any> = { date };
      
      retailers.forEach((retailer) => {
        const retailerData = data[retailer];
        const pricePoint = retailerData.find((p) => p.date === date);
        if (pricePoint) {
          point[retailer] = pricePoint.price;
        }
      });

      return point;
    });
  }, [data, retailers, timeRange]);

  // Calculate min/max for Y axis
  const { minPrice, maxPrice } = useMemo(() => {
    let min = Infinity;
    let max = -Infinity;

    chartData.forEach((point) => {
      retailers.forEach((retailer) => {
        const price = point[retailer];
        if (typeof price === 'number') {
          min = Math.min(min, price);
          max = Math.max(max, price);
        }
      });
    });

    // Add some padding
    const padding = (max - min) * 0.1 || 10;
    return {
      minPrice: Math.max(0, min - padding),
      maxPrice: max + padding,
    };
  }, [chartData, retailers]);

  if (retailers.length === 0 || chartData.length === 0) {
    return (
      <div className={cn('h-64 flex items-center justify-center bg-muted rounded-lg', className)}>
        <p className="text-muted-foreground">No price history data available</p>
      </div>
    );
  }

  return (
    <div className={className}>
      {/* Time range selector */}
      <div className="flex gap-2 mb-4">
        {(['7d', '30d', '90d', 'all'] as TimeRange[]).map((range) => (
          <Button
            key={range}
            variant={timeRange === range ? 'default' : 'outline'}
            size="sm"
            onClick={() => setTimeRange(range)}
          >
            {range === 'all' ? 'All Time' : range}
          </Button>
        ))}
      </div>

      {/* Chart */}
      <div className="h-64">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart
            data={chartData}
            margin={{ top: 5, right: 5, left: 0, bottom: 5 }}
          >
            <CartesianGrid
              strokeDasharray="3 3"
              className="stroke-border"
              vertical={false}
            />
            <XAxis
              dataKey="date"
              tickFormatter={formatDate}
              className="text-xs"
              tick={{ fill: 'hsl(var(--muted-foreground))' }}
              axisLine={{ stroke: 'hsl(var(--border))' }}
              tickLine={{ stroke: 'hsl(var(--border))' }}
            />
            <YAxis
              tickFormatter={formatPrice}
              domain={[minPrice, maxPrice]}
              className="text-xs"
              tick={{ fill: 'hsl(var(--muted-foreground))' }}
              axisLine={{ stroke: 'hsl(var(--border))' }}
              tickLine={{ stroke: 'hsl(var(--border))' }}
              width={70}
            />
            <Tooltip content={<CustomTooltip />} />
            <Legend
              wrapperStyle={{ paddingTop: '10px' }}
              iconType="circle"
              iconSize={8}
            />
            {retailers.map((retailer, index) => (
              <Line
                key={retailer}
                type="monotone"
                dataKey={retailer}
                name={retailer}
                stroke={getRetailerColor(retailer, index)}
                strokeWidth={2}
                dot={false}
                activeDot={{ r: 4, strokeWidth: 0 }}
                connectNulls
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}

export default PriceChart;
