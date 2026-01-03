import { Head, Link } from '@inertiajs/react';
import { PageProps, ShoppingList } from '@/types';
import AppLayout from '@/Layouts/AppLayout';

interface Props extends PageProps {
  owned_lists: ShoppingList[];
  shared_lists: ShoppingList[];
}

export default function ListsIndex({ auth, owned_lists, shared_lists, flash }: Props) {
  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="My Lists" />
      <div className="p-6 lg:p-8">
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold text-foreground">My Lists</h1>
            <p className="text-muted-foreground">Manage your shopping lists</p>
          </div>
          <Link
            href="/lists/create"
            className="bg-primary text-primary-foreground px-6 py-3 rounded-xl font-semibold hover:bg-primary/90 transition-colors"
          >
            + New List
          </Link>
        </div>

        {/* Owned Lists */}
        <div className="mb-8">
          <h2 className="text-xl font-semibold text-foreground mb-4">Your Lists</h2>
          {owned_lists.length > 0 ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {owned_lists.map((list) => (
                <Link
                  key={list.id}
                  href={`/lists/${list.id}`}
                  className="bg-card border border-border rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow"
                >
                  <h3 className="text-lg font-semibold text-card-foreground mb-2">{list.name}</h3>
                  {list.description && (
                    <p className="text-muted-foreground text-sm mb-3 line-clamp-2">{list.description}</p>
                  )}
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">{list.items_count || 0} items</span>
                    {list.is_shared && (
                      <span className="bg-secondary text-secondary-foreground px-2 py-1 rounded-full text-xs">
                        Shared
                      </span>
                    )}
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <div className="bg-card border border-border rounded-xl p-8 text-center">
              <div className="text-4xl mb-4">📝</div>
              <p className="text-muted-foreground mb-4">No lists yet. Create your first list!</p>
              <Link
                href="/lists/create"
                className="text-primary font-medium hover:underline"
              >
                Create a list
              </Link>
            </div>
          )}
        </div>

        {/* Shared Lists */}
        {shared_lists.length > 0 && (
          <div>
            <h2 className="text-xl font-semibold text-foreground mb-4">Shared With Me</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {shared_lists.map((list) => (
                <Link
                  key={list.id}
                  href={`/lists/${list.id}`}
                  className="bg-card border-2 border-secondary rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow"
                >
                  <h3 className="text-lg font-semibold text-card-foreground mb-2">{list.name}</h3>
                  <div className="text-sm text-muted-foreground">{list.items_count || 0} items</div>
                </Link>
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
