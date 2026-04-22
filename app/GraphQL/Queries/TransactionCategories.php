<?php

namespace App\GraphQL\Queries;

use App\Models\TransactionCategory;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TransactionCategories
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return TransactionCategory::withoutGlobalScopes()
            ->with([
                'parent' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();
    }
}
