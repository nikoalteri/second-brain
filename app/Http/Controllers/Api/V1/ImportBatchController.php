<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportBatchStatus;
use App\Http\Controllers\Controller;
use App\Enums\ImportRowStatus;
use App\Http\Requests\Api\ConfirmImportBatchRequest;
use App\Http\Requests\Api\MapImportBatchRequest;
use App\Http\Requests\Api\StoreImportBatchRequest;
use App\Http\Resources\Api\ImportBatchResource;
use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Services\Import\ImportMappingService;
use App\Services\Import\ImportRowPromotionService;
use App\Services\Import\ImportBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * @group Statement imports
 *
 * Upload a CSV/XLSX statement into a reviewable draft batch. Nothing here touches the ledger.
 */
class ImportBatchController extends Controller
{
    public function __construct(
        private readonly ImportBatchService $service,
        private readonly ImportMappingService $mappings,
        private readonly ImportRowPromotionService $promotion,
    ) {}

    /** @group Statement imports @authenticated */
    public function index(Request $request): AnonymousResourceCollection
    {
        $batches = ImportBatch::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return ImportBatchResource::collection($batches);
    }

    /** @group Statement imports @authenticated */
    public function store(StoreImportBatchRequest $request): JsonResponse
    {
        $batch = $this->service->stage($request->user(), $request->file('file'), $request->validated());

        return $this->respond($batch)->response()->setStatusCode(201);
    }

    /** @group Statement imports @authenticated */
    public function show(Request $request, int $importBatch): ImportBatchResource
    {
        return $this->respond($this->owned($request, $importBatch));
    }

    /**
     * Apply (or re-apply) a column mapping: rows are normalised and duplicates classified. The
     * mapping is remembered for the target's next import.
     *
     * @group Statement imports @authenticated
     */
    public function mapping(MapImportBatchRequest $request, int $importBatch): ImportBatchResource
    {
        $batch = $this->owned($request, $importBatch);

        $this->mappings->apply($batch, $request->mapping());

        return $this->respond($batch);
    }

    /**
     * Post draft rows to the ledger, exactly once each. `row_ids` confirms exactly those rows (the
     * owner may include flagged ones); `all_ready` only takes rows with no duplicate or review flag.
     * Each row is its own unit: one failing never blocks the others, and a retry is safe.
     *
     * @group Statement imports @authenticated
     */
    public function confirm(ConfirmImportBatchRequest $request, int $importBatch): JsonResponse
    {
        $batch = $this->owned($request, $importBatch);
        abort_unless($batch->status === ImportBatchStatus::MAPPED, 409, 'Map the columns before confirming rows.');

        $rowIds = $request->boolean('all_ready') && ! $request->has('row_ids')
            ? null
            : array_map('intval', $request->validated('row_ids'));

        $outcomes = $this->promotion->confirm($batch, $rowIds);

        $batch->refresh();

        return response()->json([
            'data' => [
                'status' => $batch->status->value,
                'outcomes' => $outcomes,
                'confirmed' => count(array_filter($outcomes, fn ($o) => $o['outcome'] === 'confirmed')),
                'skipped' => count(array_filter($outcomes, fn ($o) => $o['outcome'] === 'skipped')),
                'failed' => count(array_filter($outcomes, fn ($o) => $o['outcome'] === 'failed')),
            ],
            'summary' => $this->mappings->summary($batch),
        ]);
    }

    /**
     * Finish a partially confirmed import: the rows still waiting are discarded and the batch is kept
     * as the history of what it posted.
     *
     * @group Statement imports @authenticated
     */
    public function close(Request $request, int $importBatch): ImportBatchResource
    {
        $batch = $this->owned($request, $importBatch);

        $this->promotion->close($batch);

        return $this->respond($batch->refresh());
    }

    /** @group Statement imports @authenticated */
    public function destroy(Request $request, int $importBatch): Response
    {
        $batch = $this->owned($request, $importBatch);

        DB::transaction(function () use ($batch) {
            // Locked like a promotion is: a row cannot be confirmed while its batch is being deleted.
            $locked = ImportBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            // Any posted row makes the batch the provenance of ledger movements: it is kept.
            abort_if(
                $locked->status === ImportBatchStatus::COMPLETED || $locked->rows()->where('status', ImportRowStatus::CONFIRMED->value)->exists(),
                409,
                'This import already added entries to your accounts and is kept as their history.',
            );

            $locked->delete();
        });

        return response()->noContent();
    }

    /** The owner is always explicit: a superadmin has no access to someone else's statement. */
    private function owned(Request $request, int $id): ImportBatch
    {
        return ImportBatch::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function respond(ImportBatch $batch): ImportBatchResource
    {
        $saved = ImportMapping::query()
            ->where('user_id', $batch->user_id)
            ->where('account_id', $batch->account_id)
            ->where('credit_card_id', $batch->credit_card_id)
            ->value('mapping');

        return (new ImportBatchResource($this->withPreview($batch)))->additional([
            'summary' => $this->mappings->summary($batch),
            'saved_mapping' => $saved,
        ]);
    }

    private function withPreview(ImportBatch $batch): ImportBatch
    {
        return $batch->setRelation(
            'rows',
            $batch->rows()->orderBy('row_number')->limit((int) config('import.preview_rows'))->get(),
        );
    }
}
