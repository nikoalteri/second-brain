<?php

namespace App\GraphQL\Queries;

use App\Models\Transaction;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MonthlyCashflow
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        $user  = $context->user();
        $year  = (int) $args['year'];
        $month = (int) $args['month'];

        // TransactionType has `is_income` boolean.
        // We eager-load the `type` relation to distinguish income vs expense.
        $transactions = Transaction::query()
            ->with('type')
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();

        $income  = $transactions
            ->filter(fn ($t) => $t->type && $t->type->is_income === true)
            ->sum(fn ($t) => (float) $t->amount);

        $expense = $transactions
            ->filter(fn ($t) => $t->type && $t->type->is_income === false)
            ->sum(fn ($t) => (float) $t->amount);

        return [
            'year'          => $year,
            'month'         => $month,
            'total_income'  => (float) $income,
            'total_expense' => (float) $expense,
            'net'           => (float) ($income - $expense),
        ];
    }
}
