<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\OlxScraperService;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckListingPriceJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(public readonly Listing $listing) {}

    public function handle(OlxScraperService $scraper, TelegramService $telegram): void
    {
        // ScraperException bubbles up → Laravel retries 3×60s before calling failed()
        $data = $scraper->fetch($this->listing->url);

        $newPrice = (float) $data['price'];
        $oldPrice = (float) $this->listing->current_price;

        if ($oldPrice !== $newPrice) {
            $this->listing->update([
                'title'         => $data['title'],
                'current_price' => $newPrice,
                'currency'      => $data['currency'],
            ]);

            $this->notifyPriceChanged($telegram, $oldPrice, $newPrice, $data['currency']);
        } else {
            $this->listing->update(['title' => $data['title']]);
        }

        $this->listing->update(['last_checked_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        $this->listing->update(['is_active' => false]);

        $telegram = app(TelegramService::class);
        $this->notifyListingRemoved($telegram);
    }

    private function notifyPriceChanged(TelegramService $telegram, float $oldPrice, float $newPrice, string $currency): void
    {
        $chatIds = $this->listing->subscriptions()->pluck('chat_id');

        $title = $telegram->escapeMarkdown((string) $this->listing->title);
        $old   = $telegram->escapeMarkdown("{$oldPrice} {$currency}");
        $new   = $telegram->escapeMarkdown("{$newPrice} {$currency}");
        $url   = $telegram->escapeMarkdownUrl($this->listing->url);

        $text = "💰 *Зміна ціни\\!*\n\n" .
                "*{$title}*\n" .
                "{$old} → *{$new}*\n\n" .
                "[Переглянути оголошення]({$url})";

        foreach ($chatIds as $chatId) {
            $telegram->sendMessage($chatId, $text);
        }
    }

    private function notifyListingRemoved(TelegramService $telegram): void
    {
        $chatIds = $this->listing->subscriptions()->pluck('chat_id');

        $title    = $telegram->escapeMarkdown((string) $this->listing->title);
        $url      = $telegram->escapeMarkdownUrl($this->listing->url);
        $urlText  = $telegram->escapeMarkdown($this->listing->url);

        $text = "❌ *Оголошення знято*\n\n" .
                "*{$title}*\n" .
                "[{$urlText}]({$url})";

        foreach ($chatIds as $chatId) {
            $telegram->sendMessage($chatId, $text);
        }
    }
}
