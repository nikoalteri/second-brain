<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AskChatbotRequest;
use App\Services\Chatbot\IntentRouter;
use Illuminate\Http\JsonResponse;

/**
 * @group Chatbot
 *
 * Read-only conversational queries over validated finance data.
 */
class ChatbotController extends Controller
{
    public function __construct(private readonly IntentRouter $router) {}

    /**
     * Answer a single read-only chatbot intent.
     *
     * @group Chatbot
     * @authenticated
     */
    public function ask(AskChatbotRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->router->route(
                $request->user(),
                (string) $request->validated('intent'),
                (array) ($request->validated('params') ?? []),
            ),
        ]);
    }
}
