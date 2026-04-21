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

        return Transaction::query()
            ->selectRaw('COALESCE(tc.name, "Uncategorised") as category, SUM(t.amount) as total, COUNT(*) as `count`')
            ->from('transactions as t')
            ->leftJoin('transaction_categories as tc', 't.transaction_category_id', '=', 'tc.id')
            ->where('t.user_id', $user->id)
            ->whereYear('t.date', $year)
            ->whereMonth('t.date', $month)
            ->whereNull('t.deleted_at')
            ->groupBy('t.transaction_category_id', 'tc.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total'    => (float) $row->total,
                'count'    => (int) $row->count,
            ])
            ->toArray();
    }
}
