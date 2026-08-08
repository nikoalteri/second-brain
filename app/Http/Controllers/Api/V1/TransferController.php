<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransferRequest;
use App\Http\Resources\Api\TransactionResource;
use App\Models\Account;
use App\Services\AccountTransferService;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __construct(private readonly AccountTransferService $transferService) {}

    /**
     * @group Transfers
     * @authenticated
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        // Scoped queries: HasUserScoping already filters to the caller's own accounts, so a
        // foreign account id 404s here rather than silently moving someone else's money.
        $from = Account::query()->findOrFail($request->validated('from_account_id'));
        $to = Account::query()->findOrFail($request->validated('to_account_id'));

        $result = $this->transferService->transfer(
            $from,
            $to,
            (float) $request->validated('amount'),
            $request->validated('date'),
            $request->validated('description'),
            $request->validated('notes'),
        );

        $result['out']->load('account');
        $result['in']->load('account');

        return response()->json([
            'out' => new TransactionResource($result['out']),
            'in' => new TransactionResource($result['in']),
        ], 201);
    }
}
