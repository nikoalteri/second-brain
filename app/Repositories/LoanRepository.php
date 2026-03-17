<?php

namespace App\Repositories;

use App\Models\Loan;

class LoanRepository
{
    public function find(int $id): ?Loan
    {
        return Loan::find($id);
    }

    public function all()
    {
        return Loan::all();
    }

    public function create(array $data): Loan
    {
        if (! array_key_exists('remaining_amount', $data)) {
            $data['remaining_amount'] = $data['total_amount'] ?? 0;
        }

        return Loan::create($data);
    }

    public function update(Loan $loan, array $data): bool
    {
        return $loan->update($data);
    }

    public function delete(Loan $loan): bool
    {
        return $loan->delete();
    }
}
