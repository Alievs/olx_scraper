<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'     => ['required', 'url', 'regex:/olx\.(ua|kz|pl|ro|bg|ba|hr|rs|by|uz|md|pt|br|pk|ng|za|eg|ma|gh|tz|ke|ci|sn|uy)/'],
            'chat_id' => ['sometimes', 'string'],
        ]);

        if (!empty($data['chat_id'])) {
            return $this->respondChatId(
                $this->subscriptionService->subscribeByChatId($data['url'], $data['chat_id'])
            );
        }

        return $this->respondIntent(
            $this->subscriptionService->createIntent($data['url'])
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_id' => ['required', 'string'],
        ]);

        $this->subscriptionService->unsubscribeChat($data['chat_id']);

        return response()->json(['message' => 'Unsubscribed from all listings.']);
    }

    private function respondIntent(array $result): JsonResponse
    {
        if ($result['status'] === 'exists') {
            $listing = $result['listing'];

            return response()->json([
                'message'  => 'Open the bot and press Start to confirm your subscription.',
                'bot_link' => $result['bot_link'],
                'listing'  => $this->listingPayload($listing),
            ], 201);
        }

        return response()->json([
            'message'  => 'Listing is being fetched. Open the bot and press Start to confirm your subscription.',
            'bot_link' => $result['bot_link'],
        ], 202);
    }

    private function respondChatId(array $result): JsonResponse
    {
        return match ($result['status']) {
            'unknown_chat' => response()->json([
                'message' => 'Unknown chat_id. Open the bot, press Start, and copy the chat_id it shows.',
            ], 422),
            'subscribed' => response()->json([
                'message' => 'Subscribed. A confirmation was sent to your Telegram chat.',
                'listing' => $this->listingPayload($result['listing']),
            ], 201),
            default => response()->json([
                'message' => 'Listing is being fetched. You will be subscribed and notified in Telegram shortly.',
            ], 202),
        };
    }

    private function listingPayload($listing): array
    {
        return [
            'url'           => $listing->url,
            'title'         => $listing->title,
            'current_price' => (float) $listing->current_price,
            'currency'      => $listing->currency,
        ];
    }
}
