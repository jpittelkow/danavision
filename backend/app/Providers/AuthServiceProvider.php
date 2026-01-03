<?php

namespace App\Providers;

use App\Models\ListItem;
use App\Models\ListShare;
use App\Models\ShoppingList;
use App\Policies\ListItemPolicy;
use App\Policies\ListSharePolicy;
use App\Policies\ShoppingListPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        ShoppingList::class => ShoppingListPolicy::class,
        ListItem::class => ListItemPolicy::class,
        ListShare::class => ListSharePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
