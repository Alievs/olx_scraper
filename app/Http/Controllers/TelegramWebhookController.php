<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        \Log::info('Telegram webhook', $request->all());

        $message = $request->input('message');

        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) $message['from']['id'];
        $text   = trim($message['text'] ?? '');

        if (str_starts_with($text, '/start')) {
            // Deep-link payload: "/start TOKEN" → подписать этот чат на листинг из кэша.
            $parts = explode(' ', $text, 2);
            $token = isset($parts[1]) ? trim($parts[1]) : null;

            $this->subscriptionService->handleStart($chatId, $token);
        } elseif ($text === '/stop') {
            $this->subscriptionService->handleStop($chatId);
        }

        return response()->json(['ok' => true]);
    }
}
