import { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';

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

        <div className="bg-card rounded-2xl p-8 shadow-sm border border-border">
          <h1 className="text-2xl font-bold text-foreground mb-6">Create New List</h1>

          <form onSubmit={submit}>
            {/* Name */}
            <div className="mb-4">
              <label className="block text-foreground font-medium mb-2">
                List Name *
              </label>
              <input
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className="w-full px-4 py-3 rounded-xl border border-input bg-background focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none"
                placeholder="e.g., Electronics Wishlist"
              />
              {errors.name && (
                <p className="text-destructive text-sm mt-1">{errors.name}</p>
              )}
            </div>

            {/* Description */}
            <div className="mb-6">
              <label className="block text-foreground font-medium mb-2">
                Description
              </label>
              <textarea
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                className="w-full px-4 py-3 rounded-xl border border-input bg-background focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none resize-none"
                rows={3}
                placeholder="What's this list for?"
              />
            </div>

            {/* Notifications */}
            <div className="bg-muted rounded-xl p-4 mb-6">
              <h3 className="font-semibold text-foreground mb-4">Price Notifications</h3>
              
              <div className="flex items-center mb-4">
                <input
                  type="checkbox"
                  id="notify_any"
                  checked={data.notify_on_any_drop}
                  onChange={(e) => setData('notify_on_any_drop', e.target.checked)}
                  className="w-4 h-4 text-primary border-input rounded focus:ring-primary"
                />
                <label htmlFor="notify_any" className="ml-2 text-foreground">
                  Notify me on any price drop
                </label>
              </div>

              <div className="flex items-center mb-4">
                <input
                  type="checkbox"
                  id="notify_threshold"
                  checked={data.notify_on_threshold}
                  onChange={(e) => setData('notify_on_threshold', e.target.checked)}
                  className="w-4 h-4 text-primary border-input rounded focus:ring-primary"
                />
                <label htmlFor="notify_threshold" className="ml-2 text-foreground">
                  Only notify when drop exceeds threshold
                </label>
              </div>

              {data.notify_on_threshold && (
                <div className="ml-6">
                  <label className="block text-muted-foreground text-sm mb-2">
                    Minimum drop percentage
                  </label>
                  <div className="flex items-center gap-2">
                    <input
                      type="number"
                      value={data.threshold_percent}
                      onChange={(e) => setData('threshold_percent', parseInt(e.target.value) || 0)}
                      className="w-20 px-3 py-2 rounded-lg border border-input bg-background focus:border-primary outline-none"
                      min="1"
                      max="100"
                    />
                    <span className="text-muted-foreground">%</span>
                  </div>
                </div>
              )}
            </div>

            {/* Submit */}
            <div className="flex gap-4">
              <Link
                href="/lists"
                className="px-6 py-3 rounded-xl border border-border text-foreground hover:bg-muted transition-colors"
              >
                Cancel
              </Link>
              <button
                type="submit"
                disabled={processing}
                className="flex-1 bg-primary text-primary-foreground py-3 px-6 rounded-xl font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50"
              >
                {processing ? 'Creating...' : 'Create List'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
