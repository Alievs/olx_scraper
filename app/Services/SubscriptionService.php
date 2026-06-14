<?php

namespace App\Services;

use App\Jobs\CreateListingAndSubscribeJob;
use App\Models\Listing;
use App\Models\Subscription;
use App\Models\TelegramChat;
use Illuminate\Support\Facades\Cache;

class SubscriptionService
{
    public function __construct(private readonly TelegramService $telegram) {}

    public function createIntent(string $url): array
    {
        $token = bin2hex(random_bytes(32));
        Cache::put($this->startTokenKey($token), $url, now()->addHours(24));

        $botLink = $this->telegram->deepLink($token);
        $listing = Listing::firstWhere('url', $url);

        if ($listing) {
            return ['status' => 'exists', 'bot_link' => $botLink, 'listing' => $listing];
        }

        CreateListingAndSubscribeJob::dispatch($url);

        return ['status' => 'pending', 'bot_link' => $botLink, 'listing' => null];
    }

    public function subscribeByChatId(string $url, string $chatId): array
    {
        if (!TelegramChat::where('chat_id', $chatId)->exists()) {
            return ['status' => 'unknown_chat', 'listing' => null];
        }

        $listing = Listing::firstWhere('url', $url);

        if ($listing) {
            $this->subscribeChatToListing($chatId, $listing);

            return ['status' => 'subscribed', 'listing' => $listing];
        }

        $this->addPendingChat($url, $chatId);
        CreateListingAndSubscribeJob::dispatch($url);

        return ['status' => 'pending', 'listing' => null];
    }

    public function handleStart(string $chatId, ?string $token): void
    {
        $this->registerChat($chatId);

        $url = $token ? Cache::pull($this->startTokenKey($token)) : null;

        if (!$url) {
            $idEsc = $this->telegram->escapeMarkdown($chatId);
            $this->telegram->sendMessage($chatId,
                "👋 Вітаємо\\! Ваш чат зареєстровано\\.\n\n" .
                "Ваш chat\\_id: `{$idEsc}`\n" .
                "Вкажіть його у полі `chat_id` при підписці, щоб не тиснути Start щоразу\\.\n\n" .
                "Для відписки: /stop"
            );
            return;
        }

        $listing = Listing::firstWhere('url', $url);

        if ($listing) {
            $this->subscribeChatToListing($chatId, $listing);
            return;
        }

        $this->addPendingChat($url, $chatId);
        $this->telegram->sendMessage($chatId,
            "⏳ Оголошення ще обробляється\\. Сповіщення надійде після завершення\\."
        );
    }

    public function handleStop(string $chatId): void
    {
        $this->unsubscribeChat($chatId);
        $this->telegram->sendMessage($chatId,
            '🛑 Вас відписано від усіх оголошень\\. Чат деактивовано\\.'
        );
    }

    private function startTokenKey(string $token): string
    {
        return "start_token:{$token}";
    }

    public function subscribeChatToListing(string $chatId, Listing $listing, bool $notify = true): void
    {
        $subscription = Subscription::firstOrCreate([
            'listing_id' => $listing->id,
            'chat_id'    => $chatId,
        ]);

        if ($notify && $subscription->wasRecentlyCreated) {
            $title    = $this->telegram->escapeMarkdown((string) $listing->title);
            $price    = $this->telegram->escapeMarkdown("{$listing->current_price} {$listing->currency}");
            $url      = $this->telegram->escapeMarkdownUrl($listing->url);
            $idEsc    = $this->telegram->escapeMarkdown($chatId);

            $this->telegram->sendMessage($chatId,
                "✅ *Підписку оформлено\\!*\n\n" .
                "*{$title}*\n" .
                "Поточна ціна: *{$price}*\n\n" .
                "[Переглянути оголошення]({$url})\n\n" .
                "Ваш chat\\_id: `{$idEsc}`\n" .
                "Вкажіть його у полі `chat_id` при наступній підписці\\, щоб не тиснути Start\\."
            );
        }
    }

    private function pendingKey(string $url): string
    {
        return 'pending_subs:' . sha1($url);
    }

    public function addPendingChat(string $url, string $chatId): void
    {
        $key  = $this->pendingKey($url);
        $list = Cache::get($key, []);
        $list[$chatId] = true;
        Cache::put($key, $list, now()->addHours(24));
    }

    public function subscribePendingChats(Listing $listing): void
    {
        $chatIds = array_keys(Cache::pull($this->pendingKey($listing->url), []));

        foreach ($chatIds as $chatId) {
            $this->registerChat((string) $chatId);
            $this->subscribeChatToListing((string) $chatId, $listing);
        }
    }

    public function notifyPendingFailed(string $url): void
    {
        $chatIds = array_keys(Cache::pull($this->pendingKey($url), []));

        foreach ($chatIds as $chatId) {
            $this->telegram->sendMessage((string) $chatId,
                "⚠️ Не вдалося обробити оголошення\\. Спробуйте підписатися пізніше\\."
            );
        }
    }

    public function registerChat(string $chatId): bool
    {
        $created = !TelegramChat::where('chat_id', $chatId)->exists();

        TelegramChat::firstOrCreate(['chat_id' => $chatId]);

        return $created;
    }

    public function unsubscribeChat(string $chatId): void
    {
        Subscription::where('chat_id', $chatId)->delete();
        TelegramChat::where('chat_id', $chatId)->delete();
    }
}
