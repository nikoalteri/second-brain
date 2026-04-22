<?php

namespace App\GraphQL\Queries;

use App\Models\Transaction;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TotalByCategory
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        $user  = $context->user();
        $year  = (int) $args['year'];
        $month = (int) $args['month'];

        // Use withoutGlobalScopes to avoid table-alias conflicts with SoftDeletes/HasUserScoping
        // We handle user filtering and soft-delete filtering manually.
        return Transaction::withoutGlobalScopes()
            ->selectRaw('COALESCE(transaction_categories.name, \'Uncategorised\') as category, SUM(ABS(transactions.amount)) as total, COUNT(*) as cnt')
            ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
            ->leftJoin('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->where('transactions.user_id', $user->id)
            )
            ->where('transactions.is_transfer', false)
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month)
            ->whereNull('transactions.deleted_at')
            ->where('transaction_types.is_income', false)
            ->groupBy('transactions.transaction_category_id', 'transaction_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total'    => (float) $row->total,
                'count'    => (int) $row->cnt,
            ])
            ->toArray();
    }
}
