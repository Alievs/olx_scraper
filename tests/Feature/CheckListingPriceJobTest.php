<?php

namespace Tests\Feature;

use App\Exceptions\ScraperException;
use App\Jobs\CheckListingPriceJob;
use App\Models\Listing;
use App\Models\Subscription;
use App\Services\OlxScraperService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckListingPriceJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeListing(float $price = 500.00): Listing
    {
        return Listing::create([
            'url'           => 'https://www.olx.ua/d/uk/obyavlenie/test-123',
            'title'         => 'Old Title',
            'current_price' => $price,
            'currency'      => 'UAH',
        ]);
    }

    /** Scraper stub returning fixed data. */
    private function scraper(array $data): OlxScraperService
    {
        return \Mockery::mock(OlxScraperService::class, function ($m) use ($data) {
            $m->shouldReceive('fetch')->andReturn(array_merge([
                'title' => 'New Title', 'price' => 1000.00, 'currency' => 'UAH',
            ], $data));
        });
    }

    /** Telegram mock: escape* pass through, sendMessage is the spy. */
    private function telegram(): TelegramService
    {
        $m = \Mockery::mock(TelegramService::class);
        $m->shouldReceive('escapeMarkdown')->andReturnUsing(fn ($s) => $s);
        $m->shouldReceive('escapeMarkdownUrl')->andReturnUsing(fn ($s) => $s);

        return $m;
    }

    public function test_price_changed_updates_listing_and_notifies_all_subscribers(): void
    {
        $listing = $this->makeListing(500.00);
        Subscription::create(['listing_id' => $listing->id, 'chat_id' => '111']);
        Subscription::create(['listing_id' => $listing->id, 'chat_id' => '222']);

        $telegram = $this->telegram();
        $telegram->shouldReceive('sendMessage')->twice();

        (new CheckListingPriceJob($listing))->handle(
            $this->scraper(['price' => 1000.00, 'title' => 'New Title']),
            $telegram,
        );

        $this->assertDatabaseHas('listings', [
            'id'            => $listing->id,
            'current_price' => 1000.00,
            'title'         => 'New Title',
        ]);
        $this->assertNotNull($listing->fresh()->last_checked_at);
    }

    public function test_same_price_updates_title_only_and_sends_no_notification(): void
    {
        $listing = $this->makeListing(500.00);
        Subscription::create(['listing_id' => $listing->id, 'chat_id' => '111']);

        $telegram = $this->telegram();
        $telegram->shouldNotReceive('sendMessage');

        (new CheckListingPriceJob($listing))->handle(
            $this->scraper(['price' => 500.00, 'title' => 'Refreshed Title']),
            $telegram,
        );

        $this->assertDatabaseHas('listings', [
            'id'            => $listing->id,
            'current_price' => 500.00,
            'title'         => 'Refreshed Title',
        ]);
    }

    public function test_failed_marks_listing_inactive_and_notifies_subscribers(): void
    {
        $listing = $this->makeListing(500.00);
        Subscription::create(['listing_id' => $listing->id, 'chat_id' => '111']);

        $telegram = $this->telegram();
        $telegram->shouldReceive('sendMessage')->once();
        $this->instance(TelegramService::class, $telegram); // failed() resolves via app()

        (new CheckListingPriceJob($listing))->failed(new ScraperException('dead'));

        $this->assertFalse($listing->fresh()->is_active);
    }
}
