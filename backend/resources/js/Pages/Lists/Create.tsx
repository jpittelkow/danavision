import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

export default function ListsCreate({ auth, flash }: PageProps) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    description: '',
    notify_on_any_drop: true,
    notify_on_threshold: false,
    threshold_percent: 10,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    post('/lists');
  };

  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Create List" />
      <div className="p-6 lg:p-8 max-w-2xl mx-auto">
        <div className="mb-8">
          <Link href="/lists" className="text-primary hover:underline">
            ← Back to Lists
          </Link>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="text-2xl">Create New List</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-6">
              {/* Name */}
              <div className="space-y-2">
                <Label htmlFor="name">List Name *</Label>
                <Input
                  id="name"
                  type="text"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="e.g., Electronics Wishlist"
                />
                {errors.name && (
                  <p className="text-destructive text-sm">{errors.name}</p>
                )}
              </div>

              {/* Description */}
              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  rows={3}
                  placeholder="What's this list for?"
                />
              </div>

              {/* Notifications */}
              <Card className="bg-muted/50">
                <CardContent className="pt-6">
                  <h3 className="font-semibold text-foreground mb-4">Price Notifications</h3>
                  
                  <div className="space-y-4">
                    <div className="flex items-center space-x-2">
                      <Checkbox
                        id="notify_any"
                        checked={data.notify_on_any_drop}
                        onCheckedChange={(checked) => setData('notify_on_any_drop', !!checked)}
                      />
                      <Label htmlFor="notify_any" className="font-normal cursor-pointer">
                        Notify me on any price drop
                      </Label>
                    </div>

                    <div className="flex items-center space-x-2">
                      <Checkbox
                        id="notify_threshold"
                        checked={data.notify_on_threshold}
                        onCheckedChange={(checked) => setData('notify_on_threshold', !!checked)}
                      />
                      <Label htmlFor="notify_threshold" className="font-normal cursor-pointer">
                        Only notify when drop exceeds threshold
                      </Label>
                    </div>

                    {data.notify_on_threshold && (
                      <div className="ml-6 space-y-2">
                        <Label htmlFor="threshold_percent" className="text-sm text-muted-foreground">
                          Minimum drop percentage
                        </Label>
                        <div className="flex items-center gap-2">
                          <Input
                            id="threshold_percent"
                            type="number"
                            value={data.threshold_percent}
                            onChange={(e) => setData('threshold_percent', parseInt(e.target.value) || 0)}
                            className="w-20"
                            min="1"
                            max="100"
                          />
                          <span className="text-muted-foreground">%</span>
                        </div>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Submit */}
              <div className="flex gap-4">
                <Button variant="outline" asChild>
                  <Link href="/lists">Cancel</Link>
                </Button>
                <Button type="submit" disabled={processing} className="flex-1">
                  {processing ? 'Creating...' : 'Create List'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
