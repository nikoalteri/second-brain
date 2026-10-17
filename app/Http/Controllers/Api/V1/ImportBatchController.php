<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportBatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MapImportBatchRequest;
use App\Http\Requests\Api\StoreImportBatchRequest;
use App\Http\Resources\Api\ImportBatchResource;
use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Services\Import\ImportMappingService;
use App\Services\Import\ImportBatchService;
use Illuminate\Http\JsonResponse;
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

    /** @group Statement imports @authenticated */
    public function destroy(Request $request, int $importBatch): Response
    {
        $batch = $this->owned($request, $importBatch);

        // A batch with confirmed rows is ledger history; it is never deleted from here.
        abort_if($batch->status === ImportBatchStatus::COMPLETED, 409, 'A completed import cannot be deleted.');

        $batch->delete();

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
