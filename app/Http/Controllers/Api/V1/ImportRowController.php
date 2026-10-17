<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateImportRowRequest;
use App\Http\Resources\Api\ImportRowResource;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Import\ImportDuplicateDetector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Statement imports
 *
 * Review the draft rows of an import: list, edit and discard. Nothing here posts to the ledger.
 */
class ImportRowController extends Controller
{
    public function __construct(private readonly ImportDuplicateDetector $detector) {}

    /** @group Statement imports @authenticated */
    public function index(Request $request, int $importBatch): AnonymousResourceCollection
    {
        $batch = $this->ownedBatch($request, $importBatch);

        $request->validate([
            'status' => ['nullable', 'in:draft,skipped,discarded,confirmed'],
            'duplicate_class' => ['nullable', 'in:exact,probable,none'],
            'flag' => ['nullable', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
            'errors' => ['nullable', 'boolean'],
        ]);

        $rows = $batch->rows()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('duplicate_class'), fn ($q, $class) => $q->where('duplicate_class', $class))
            ->when($request->query('flag'), fn ($q, $flag) => $q->where('flags', 'like', '%"' . $flag . '"%'))
            ->when($request->boolean('errors'), fn ($q) => $q->whereNotNull('error'))
            ->orderBy('row_number')
            ->cursorPaginate($this->perPage($request));

        return ImportRowResource::collection($rows);
    }

    /** @group Statement imports @authenticated */
    public function update(UpdateImportRowRequest $request, int $importBatch, int $row): ImportRowResource
    {
        $batch = $this->ownedBatch($request, $importBatch);
        abort_if(in_array($batch->status, [ImportBatchStatus::COMPLETED, ImportBatchStatus::DISCARDED], true), 409, 'This import is closed.');

        $record = $batch->rows()->findOrFail($row);
        abort_unless(in_array($record->status, [ImportRowStatus::DRAFT, ImportRowStatus::DISCARDED], true), 409, 'This row can no longer be changed.');

        $data = $request->validated();
        $edits = array_intersect_key($data, array_flip(['description', 'amount', 'date', 'category_id']));

        if ($edits !== []) {
            abort_if($record->normalized === null, 422, 'A row with no valid data cannot be edited; fix the mapping or discard it.');
            abort_if($record->status === ImportRowStatus::DISCARDED, 409, 'Restore the row before editing it.');

            $normalized = $record->normalized;

            if (array_key_exists('description', $edits)) {
                $normalized['description'] = trim((string) $edits['description']);
            }

            if (array_key_exists('amount', $edits)) {
                $normalized['amount_minor'] = (int) round(((float) $edits['amount']) * 100);
            }

            if (array_key_exists('date', $edits)) {
                $normalized['date'] = $edits['date'];
            }

            if (array_key_exists('category_id', $edits)) {
                $normalized['category_id'] = $edits['category_id'];
            }

            $normalized['edited'] = true;
            $record->normalized = $normalized;
        }

        if (isset($data['status'])) {
            $restoring = $data['status'] === 'draft';
            abort_if($restoring && $record->normalized === null && $record->error === null, 422, 'This row cannot be restored.');

            $record->status = $restoring ? ImportRowStatus::DRAFT : ImportRowStatus::DISCARDED;
        }

        $record->save();

        // An edit changes what the row is: its old duplicate classification no longer applies.
        if ($edits !== [] || (isset($data['status']) && $data['status'] === 'draft')) {
            $this->detector->detect($batch, reclassify: true);
            $record->refresh();
        }

        return new ImportRowResource($record);
    }

    private function ownedBatch(Request $request, int $id): ImportBatch
    {
        return ImportBatch::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
