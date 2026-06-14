<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\OlxScraperService;
use App\Services\SubscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateListingAndSubscribeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public readonly string $url) {}

    public function handle(OlxScraperService $scraper, SubscriptionService $subscriptionService): void
    {
        $listing = Listing::firstWhere('url', $this->url);

        if (!$listing) {
            // Пусть ScraperException всплывает → Laravel retry 3×30s → потом failed() уведомит pending.
            $scraped = $scraper->fetch($this->url);

            $listing = Listing::firstOrCreate(
                ['url' => $this->url],
                [
                    'title'         => $scraped['title'],
                    'current_price' => $scraped['price'],
                    'currency'      => $scraped['currency'],
                ],
            );
        }

        // Подписать чаты, нажавшие Start, пока листинг ещё парсился (pending-список).
        $subscriptionService->subscribePendingChats($listing);
    }

    public function failed(\Throwable $e): void
    {
        // Все retry исчерпаны — уведомить pending-чаты, что подписка не удалась.
        app(SubscriptionService::class)->notifyPendingFailed($this->url);
    }
}
