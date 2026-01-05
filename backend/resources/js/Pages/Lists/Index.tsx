import { Head, Link } from '@inertiajs/react';
import { PageProps, ShoppingList } from '@/types';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Plus, FileText, Users } from 'lucide-react';

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
          <Button asChild>
            <Link href="/lists/create">
              <Plus className="h-4 w-4 mr-2" />
              New List
            </Link>
          </Button>
        </div>

        {/* Owned Lists */}
        <div className="mb-8">
          <h2 className="text-xl font-semibold text-foreground mb-4">Your Lists</h2>
          {owned_lists.length > 0 ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {owned_lists.map((list) => (
                <Link key={list.id} href={`/lists/${list.id}`}>
                  <Card className="h-full hover:shadow-md transition-shadow cursor-pointer">
                    <CardHeader className="pb-2">
                      <CardTitle className="text-lg">{list.name}</CardTitle>
                      {list.description && (
                        <CardDescription className="line-clamp-2">
                          {list.description}
                        </CardDescription>
                      )}
                    </CardHeader>
                    <CardContent>
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{list.items_count || 0} items</span>
                        {list.is_shared && (
                          <Badge variant="secondary" className="gap-1">
                            <Users className="h-3 w-3" />
                            Shared
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
              <CardContent className="flex flex-col items-center justify-center py-12 text-center">
                <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-muted-foreground mb-4">No lists yet. Create your first list!</p>
                <Button variant="link" asChild>
                  <Link href="/lists/create">Create a list</Link>
                </Button>
              </CardContent>
            </Card>
          )}
        </div>

        {/* Shared Lists */}
        {shared_lists.length > 0 && (
          <div>
            <h2 className="text-xl font-semibold text-foreground mb-4">Shared With Me</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {shared_lists.map((list) => (
                <Link key={list.id} href={`/lists/${list.id}`}>
                  <Card className="h-full border-2 border-secondary hover:shadow-md transition-shadow cursor-pointer">
                    <CardHeader className="pb-2">
                      <CardTitle className="text-lg">{list.name}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <span className="text-sm text-muted-foreground">{list.items_count || 0} items</span>
                    </CardContent>
                  </Card>
                </Link>
              ))}
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
