<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowStatus;
use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Models\TransactionCategory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Applies a column mapping to a batch: normalises every draft row, then classifies duplicates.
 * Re-applying only touches rows still in `draft` or `skipped`; rows the owner discarded or confirmed
 * are left exactly as they are.
 */
class ImportMappingService
{
    public function __construct(
        private readonly ImportRowNormalizer $normalizer,
        private readonly ImportDuplicateDetector $detector,
    ) {}

    /**
     * @param array<string, mixed> $mapping validated mapping
     */
    public function apply(ImportBatch $batch, array $mapping): void
    {
        DB::transaction(function () use ($batch, $mapping) {
            $locked = ImportBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ImportBatchStatus::UPLOADED, ImportBatchStatus::MAPPED], true)) {
                throw new HttpException(409, 'This import can no longer be re-mapped.');
            }

            $categories = TransactionCategory::query()->withoutUserScope()
                ->where('user_id', $locked->user_id)
                ->where('scope', 'generic') // subscription-only categories are not offered for plain transactions
                ->get(['id', 'name'])
                ->mapWithKeys(fn ($category) => [mb_strtolower(trim($category->name)) => $category->id]);

            $headerRow = (int) ($mapping['header_row'] ?? 0);

            $locked->rows()
                ->whereIn('status', [ImportRowStatus::DRAFT->value, ImportRowStatus::SKIPPED->value])
                ->orderBy('row_number')
                ->each(function ($row) use ($mapping, $headerRow, $locked, $categories) {
                    $row->forceFill(['normalized' => null, 'error' => null, 'fingerprint' => null, 'duplicate_class' => null, 'flags' => null]);

                    if ($row->row_number <= $headerRow) {
                        $row->status = ImportRowStatus::SKIPPED;
                        $row->save();

                        return;
                    }

                    $row->status = ImportRowStatus::DRAFT;

                    try {
                        $normalized = $this->normalizer->normalize($row->raw_cells, $mapping, $locked->file_format, $row->numeric_columns ?? []);
                        $normalized['category_id'] = $normalized['category'] !== null
                            ? $categories->get(mb_strtolower($normalized['category']))
                            : null;
                        $row->normalized = $normalized;
                    } catch (ImportFileException $e) {
                        $row->error = $e->getMessage();
                    }

                    $row->save();
                });

            $locked->forceFill([
                'mapping' => $mapping,
                'mapping_version' => $locked->mapping_version + 1,
                'status' => ImportBatchStatus::MAPPED,
            ])->save();

            // Only a mapping that read at least one row is worth offering again: a wrong guess that made
            // every line unreadable must not become the target's default for its next import.
            if ($locked->rows()->whereNotNull('normalized')->exists()) {
                ImportMapping::query()->updateOrCreate(
                    ['user_id' => $locked->user_id, 'account_id' => $locked->account_id, 'credit_card_id' => $locked->credit_card_id],
                    ['mapping' => $mapping],
                );
            }

            $this->detector->detect($locked);
        });

        $batch->refresh();
    }

    /**
     * @return array<string, int>
     */
    public function summary(ImportBatch $batch): array
    {
        $rows = $batch->rows();

        return [
            'rows' => (clone $rows)->count(),
            'skipped' => (clone $rows)->where('status', ImportRowStatus::SKIPPED->value)->count(),
            'discarded' => (clone $rows)->where('status', ImportRowStatus::DISCARDED->value)->count(),
            'confirmed' => (clone $rows)->where('status', ImportRowStatus::CONFIRMED->value)->count(),
            'errors' => (clone $rows)->whereNotNull('error')->count(),
            'ready' => (clone $rows)->where('status', ImportRowStatus::DRAFT->value)->whereNotNull('normalized')->count(),
            'exact' => (clone $rows)->where('duplicate_class', 'exact')->count(),
            'probable' => (clone $rows)->where('duplicate_class', 'probable')->count(),
            'already_imported' => (clone $rows)->where('flags', 'like', '%"already_imported"%')->count(),
        ];
    }
}
