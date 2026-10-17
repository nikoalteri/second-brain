<?php

namespace App\Services\Import;

use App\Enums\ImportRowStatus;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Classifies each normalised draft row against what already exists. Every result is a proposal for
 * the owner to review: nothing here discards a row or decides it is a duplicate.
 *
 * duplicate_class: exact (same date, amount and a similar description), probable (same amount within
 * the date window) or none. flags.codes adds look-alikes that are not duplicates but need a decision.
 */
class ImportDuplicateDetector
{
    public const DATE_WINDOW_DAYS = 3;
    public const PAYMENT_WINDOW_DAYS = 5;
    private const SIMILARITY_PERCENT = 80;

    /**
     * @param bool $reclassify re-run the ledger matching against the ledger as it is now, keeping each
     *                         row's original fingerprint (it identifies the statement line, not an edit)
     */
    public function detect(ImportBatch $batch, bool $reclassify = false): void
    {
        $rows = $batch->rows()
            ->where('status', ImportRowStatus::DRAFT->value)
            ->whereNotNull('normalized')
            ->orderBy('row_number')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $fingerprints = $reclassify && $rows->every(fn (ImportRow $row) => $row->fingerprint !== null)
            ? $this->storedFingerprints($rows)
            : $this->fingerprints($batch, $rows);
        $alreadyImported = $this->alreadyImported($batch, $fingerprints);
        $ledger = $this->matchLedger($batch, $rows);
        $transfers = $batch->account_id ? $this->transferCandidates($batch, $rows) : [];
        $payments = $batch->account_id ? $this->cardPaymentCandidates($batch, $rows) : [];

        foreach ($rows as $row) {
            $n = $row->normalized;
            $codes = [];
            $match = $ledger[$row->id] ?? null;

            if ($fingerprints[$row->id]['occurrence'] > 1) {
                $codes[] = 'within_file_duplicate';
            }

            if (isset($alreadyImported[$fingerprints[$row->id]['hash']])) {
                $codes[] = 'already_imported';
            }

            if (! empty($n['currency']) && $n['currency'] !== $batch->currency) {
                $codes[] = 'currency_mismatch';
            }

            if ($batch->credit_card_id && $n['amount_minor'] > 0) {
                $codes[] = 'card_credit';
            }

            if ($match && $match['subscription']) {
                $codes[] = 'matches_generated_renewal';
            }

            $related = [];

            if (isset($transfers[$row->id])) {
                $codes[] = 'possible_transfer';
                $related['transaction_id'] = $transfers[$row->id];
            }

            if (isset($payments[$row->id])) {
                $codes[] = 'possible_card_payment';
                $related['card_payment_id'] = $payments[$row->id];
            }

            $row->forceFill([
                'fingerprint' => $fingerprints[$row->id]['hash'],
                'duplicate_class' => $match['class'] ?? 'none',
                'flags' => [
                    'codes' => $codes,
                    'match' => $match ? ['type' => $match['type'], 'id' => $match['id']] : null,
                    'already_imported_in' => $alreadyImported[$fingerprints[$row->id]['hash']] ?? null,
                    'related' => $related,
                ],
            ])->save();
        }
    }

    /**
     * @param Collection<int, ImportRow> $rows
     * @return array<int, array{hash: string, occurrence: int}> keyed by row id
     */
    private function fingerprints(ImportBatch $batch, Collection $rows): array
    {
        $target = $batch->account_id ? 'a' . $batch->account_id : 'c' . $batch->credit_card_id;
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $n = $row->normalized;
            $key = $n['date'] . '|' . $n['amount_minor'] . '|' . $this->normaliseText($n['description']);
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            $result[$row->id] = [
                'hash' => hash_hmac('sha256', "{$batch->user_id}|{$target}|{$key}|{$seen[$key]}", (string) config('app.key')),
                'occurrence' => $seen[$key],
            ];
        }

        return $result;
    }

    /**
     * @param Collection<int, ImportRow> $rows
     * @return array<int, array{hash: string, occurrence: int}> keyed by row id
     */
    private function storedFingerprints(Collection $rows): array
    {
        return $rows->mapWithKeys(fn (ImportRow $row) => [$row->id => [
            'hash' => $row->fingerprint,
            'occurrence' => in_array('within_file_duplicate', $row->flags['codes'] ?? [], true) ? 2 : 1,
        ]])->all();
    }

    /**
     * @param array<int, array{hash: string, occurrence: int}> $fingerprints
     * @return array<string, int> fingerprint => batch id of the earlier import
     */
    private function alreadyImported(ImportBatch $batch, array $fingerprints): array
    {
        $found = [];

        foreach (array_chunk(array_column($fingerprints, 'hash'), 500) as $hashes) {
            ImportRow::query()
                ->join('import_batches', 'import_batches.id', '=', 'import_rows.import_batch_id')
                ->where('import_batches.user_id', $batch->user_id)
                ->where('import_batches.id', '!=', $batch->id)
                ->whereIn('import_rows.status', [ImportRowStatus::DRAFT->value, ImportRowStatus::CONFIRMED->value])
                ->whereIn('import_rows.fingerprint', $hashes)
                ->get(['import_rows.fingerprint', 'import_rows.import_batch_id'])
                ->each(function ($hit) use (&$found) {
                    $found[$hit->fingerprint] = (int) $hit->import_batch_id;
                });
        }

        return $found;
    }

    /**
     * Greedy one-to-one matching so two identical imported charges cannot both claim one ledger row.
     *
     * @param Collection<int, ImportRow> $rows
     * @return array<int, array{class: string, type: string, id: int, subscription: bool}> keyed by row id
     */
    private function matchLedger(ImportBatch $batch, Collection $rows): array
    {
        $candidates = $this->ledgerCandidates($batch, $rows);
        $used = [];
        $result = [];

        foreach (['exact', 'probable'] as $class) {
            foreach ($rows as $row) {
                if (isset($result[$row->id])) {
                    continue;
                }

                $best = $this->pick($batch, $row->normalized, $candidates, $used, $class);

                if ($best) {
                    $used[$best['id']] = true;
                    $result[$row->id] = [
                        'class' => $class,
                        'type' => $best['type'],
                        'id' => $best['id'],
                        'subscription' => $best['subscription'],
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * The match of ONE row against the ledger as it is right now, for the moment of posting: the
     * caller holds the target lock, so nothing can be added between this check and the insert.
     * Ledger movements this batch already posted, and those another draft row of the batch has
     * already been matched to, are not available (the one-to-one rule).
     *
     * @return array{class: string, type: string, id: int, subscription: bool}|null
     */
    public function matchOne(ImportBatch $batch, ImportRow $row): ?array
    {
        $candidates = $this->ledgerCandidates($batch, collect([$row]));
        $n = $row->normalized;

        // Cheap exit first: only rows with a same-size movement nearby need the claimed-ids lookup.
        $nearby = array_values(array_filter($candidates, fn (array $c) => $c['amount_minor'] === $this->comparable($batch, $n['amount_minor'])));

        if ($nearby === []) {
            return null;
        }

        $claimed = $this->claimedBy($batch, $row, array_column($nearby, 'id'));

        foreach (['exact', 'probable'] as $class) {
            $best = $this->pick($batch, $n, $nearby, $claimed, $class);

            if ($best) {
                return ['class' => $class, 'type' => $best['type'], 'id' => $best['id'], 'subscription' => $best['subscription']];
            }
        }

        return null;
    }

    /**
     * @param list<int> $ledgerIds
     * @return array<int, true> ledger ids that another draft row of this batch is already matched to
     */
    private function claimedBy(ImportBatch $batch, ImportRow $row, array $ledgerIds): array
    {
        $type = $batch->account_id ? 'transaction' : 'credit_card_expense';

        $others = $batch->rows()
            ->where('status', ImportRowStatus::DRAFT->value)
            ->where('id', '!=', $row->id)
            ->where(function ($query) use ($ledgerIds, $type) {
                foreach ($ledgerIds as $id) {
                    // The encoded form written by detect(): {"type":"transaction","id":12} (no spaces).
                    $query->orWhere('flags', 'like', '%"match":{"type":"' . $type . '","id":' . (int) $id . '}%');
                }
            })
            ->get(['flags']);

        $claimed = [];

        foreach ($others as $other) {
            $claimed[(int) ($other->flags['match']['id'] ?? 0)] = true;
        }

        return $claimed;
    }

    /**
     * @param array{date: string, amount_minor: int, description: string} $n
     * @param list<array{id: int, type: string, date: string, amount_minor: int, description: string, subscription: bool}> $candidates
     * @param array<int, mixed> $used
     * @return array{id: int, type: string, date: string, amount_minor: int, description: string, subscription: bool}|null
     */
    private function pick(ImportBatch $batch, array $n, array $candidates, array $used, string $class): ?array
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            if (isset($used[$candidate['id']]) || $candidate['amount_minor'] !== $this->comparable($batch, $n['amount_minor'])) {
                continue;
            }

            $distance = abs(Carbon::parse($candidate['date'])->diffInDays(Carbon::parse($n['date']), false));

            if ($distance > self::DATE_WINDOW_DAYS) {
                continue;
            }

            if ($class === 'exact' && ($distance !== 0.0 || ! $this->similar($candidate['description'], $n['description']))) {
                continue;
            }

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /** The import amount expressed the way the ledger stores it for this target. */
    private function comparable(ImportBatch $batch, int $importMinor): int
    {
        // Card expenses are stored as positive charges, while the import sign is negative = spending.
        return $batch->credit_card_id ? -$importMinor : $importMinor;
    }

    /**
     * @param Collection<int, ImportRow> $rows
     * @return list<array{id: int, type: string, date: string, amount_minor: int, description: string, subscription: bool}>
     */
    private function ledgerCandidates(ImportBatch $batch, Collection $rows): array
    {
        [$from, $to] = $this->dateRange($rows, self::DATE_WINDOW_DAYS);

        // Movements this very import already posted are not duplicates of its remaining rows: two
        // identical legitimate charges must stay importable one after the other.
        $own = $batch->rows()->where('status', ImportRowStatus::CONFIRMED->value)
            ->pluck($batch->account_id ? 'transaction_id' : 'credit_card_expense_id')
            ->filter()->all();

        if ($batch->account_id) {
            return Transaction::query()->withoutUserScope()
                ->where('account_id', $batch->account_id)
                ->whereNotIn('id', $own)
                ->whereBetween('date', [$from, $to])
                ->get(['id', 'date', 'amount', 'description', 'subscription_id'])
                ->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'type' => 'transaction',
                    'date' => $t->date->toDateString(),
                    'amount_minor' => $this->minor($t->amount),
                    'description' => (string) $t->description,
                    'subscription' => $t->subscription_id !== null,
                ])->all();
        }

        return CreditCardExpense::query()
            ->where('credit_card_id', $batch->credit_card_id)
            ->whereNotIn('id', $own)
            ->whereBetween('spent_at', [$from, $to])
            ->get(['id', 'spent_at', 'amount', 'description', 'subscription_id'])
            ->map(fn (CreditCardExpense $e) => [
                'id' => $e->id,
                'type' => 'credit_card_expense',
                'date' => $e->spent_at->toDateString(),
                'amount_minor' => $this->minor($e->amount),
                'description' => (string) $e->description,
                'subscription' => $e->subscription_id !== null,
            ])->all();
    }

    /**
     * A movement of the same size and opposite sign on another account of the same owner.
     *
     * @param Collection<int, ImportRow> $rows
     * @return array<int, int> row id => transaction id
     */
    private function transferCandidates(ImportBatch $batch, Collection $rows): array
    {
        [$from, $to] = $this->dateRange($rows, self::DATE_WINDOW_DAYS);

        $others = Transaction::query()->withoutUserScope()
            ->where('user_id', $batch->user_id)
            ->where('account_id', '!=', $batch->account_id)
            ->whereBetween('date', [$from, $to])
            ->get(['id', 'date', 'amount']);

        $result = [];

        foreach ($rows as $row) {
            $n = $row->normalized;
            $hit = $others->first(fn (Transaction $t) => $this->minor($t->amount) === -$n['amount_minor']
                && abs(Carbon::parse($t->date)->diffInDays(Carbon::parse($n['date']), false)) <= self::DATE_WINDOW_DAYS);

            if ($hit) {
                $result[$row->id] = $hit->id;
            }
        }

        return $result;
    }

    /**
     * A card cycle payment of the same size as an outgoing row, around its date.
     *
     * @param Collection<int, ImportRow> $rows
     * @return array<int, int> row id => card payment id
     */
    private function cardPaymentCandidates(ImportBatch $batch, Collection $rows): array
    {
        [$from, $to] = $this->dateRange($rows, self::PAYMENT_WINDOW_DAYS);

        $payments = CreditCardPayment::query()
            ->whereHas('creditCard', fn ($q) => $q->withoutUserScope()->where('user_id', $batch->user_id))
            ->where(fn ($q) => $q->whereBetween('due_date', [$from, $to])->orWhereBetween('actual_date', [$from, $to]))
            ->get(['id', 'due_date', 'actual_date', 'total_amount']);

        $result = [];

        foreach ($rows as $row) {
            $n = $row->normalized;

            if ($n['amount_minor'] >= 0) {
                continue;
            }

            $hit = $payments->first(function (CreditCardPayment $p) use ($n) {
                $date = $p->actual_date ?? $p->due_date;

                return $this->minor($p->total_amount) === abs($n['amount_minor'])
                    && abs(Carbon::parse($date)->diffInDays(Carbon::parse($n['date']), false)) <= self::PAYMENT_WINDOW_DAYS;
            });

            if ($hit) {
                $result[$row->id] = $hit->id;
            }
        }

        return $result;
    }

    /**
     * @param Collection<int, ImportRow> $rows
     * @return array{0: string, 1: string}
     */
    private function dateRange(Collection $rows, int $windowDays): array
    {
        $dates = $rows->map(fn (ImportRow $row) => $row->normalized['date']);

        return [
            Carbon::parse($dates->min())->subDays($windowDays)->toDateString(),
            Carbon::parse($dates->max())->addDays($windowDays)->toDateString(),
        ];
    }

    private function minor(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function normaliseText(string $text): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($text)) ?? '';
    }

    private function similar(string $ledgerText, string $importText): bool
    {
        $a = $this->normaliseText($ledgerText);
        $b = $this->normaliseText($importText);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b || (min(strlen($a), strlen($b)) >= 4 && (str_contains($a, $b) || str_contains($b, $a)))) {
            return true;
        }

        similar_text($a, $b, $percent);

        return $percent >= self::SIMILARITY_PERCENT;
    }
}
