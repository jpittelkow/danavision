<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\HandlesPagination;
use App\Helpers\QueryHelper;
use App\Models\User;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;

class Users
{
    use HandlesPagination;

    public function __invoke($root, array $args, $context): array
    {
        $user = Auth::guard('api-key')->user();

        // Defense in depth: verify authorization at resolver level
        if (!$user->can('users.view')) {
            throw new Error('Unauthorized', extensions: ['code' => 'FORBIDDEN']);
        }

        $query = User::query()->orderBy('created_at', 'desc');

        if (!empty($args['search'])) {
            $escaped = QueryHelper::escapeLike($args['search']);
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%");
            });
        }

        $perPage = $this->clampPerPage($args['first'] ?? 25);
        $paginator = $query->paginate($perPage, ['*'], 'page', $args['page'] ?? 1);

        return $this->paginatorResponse($paginator);
    }
}
