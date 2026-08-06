<?php

namespace App\GraphQL\Queries;

use App\Models\TransactionCategory;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TransactionCategories
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $user = $context->user();
        $isSuperadmin = $user->hasRole('superadmin');

        return TransactionCategory::withoutGlobalScopes()
            ->with([
                'parent' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->when(
                        ! $isSuperadmin,
                        fn ($parentQuery) => $parentQuery->where('transaction_categories.user_id', $user->id)
                    ),
            ])
            ->when(
                ! $isSuperadmin,
                fn ($query) => $query->where('transaction_categories.user_id', $user->id)
            )
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();
    }
}
