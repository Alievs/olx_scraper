<?php

namespace Tests\Feature;

use App\Jobs\CreateListingAndSubscribeJob;
use App\Models\Listing;
use App\Models\Subscription;
use App\Models\TelegramChat;
use App\Services\OlxScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.olx.ua/d/uk/obyavlenie/test-123';

    /** Stub the Telegram HTTP client so sendMessage/deepLink never hit the network. */
    private function fakeTelegram(): void
    {
        $client = \Mockery::mock(Client::class);
        $client->shouldReceive('post')->andReturn(new Response(200, [], '{"ok":true}'));
        $this->instance(Client::class, $client);
    }

    private function mockScraper(array $data = []): void
    {
        $this->instance(OlxScraperService::class, \Mockery::mock(OlxScraperService::class, function ($mock) use ($data) {
            $mock->shouldReceive('fetch')->andReturn(array_merge([
                'title'    => 'Test Listing',
                'price'    => 1000.00,
                'currency' => 'UAH',
            ], $data));
        }));
    }

    private function makeListing(string $url = self::URL): Listing
    {
        return Listing::create([
            'url'           => $url,
            'title'         => 'Existing',
            'current_price' => 500.00,
            'currency'      => 'UAH',
        ]);
    }

    public function test_new_listing_no_chat_id_returns_202_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/subscriptions', ['url' => self::URL]);

        $response->assertStatus(202)->assertJsonStructure(['message', 'bot_link']);

        Queue::assertPushed(
            CreateListingAndSubscribeJob::class,
            fn ($job) => $job->url === self::URL
        );
    }

    public function test_existing_listing_no_chat_id_returns_201_with_listing(): void
    {
        $this->makeListing();

        $response = $this->postJson('/api/subscriptions', ['url' => self::URL]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'bot_link',
                'listing' => ['url', 'title', 'current_price', 'currency'],
            ]);

        // No chat_id → only intent created, no subscription row.
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_chat_id_unregistered_returns_422(): void
    {
        $this->makeListing();

        $response = $this->postJson('/api/subscriptions', [
            'url'     => self::URL,
            'chat_id' => '000000000',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_chat_id_registered_existing_listing_subscribes_201(): void
    {
        $this->fakeTelegram();
        $listing = $this->makeListing();
        TelegramChat::create(['chat_id' => '570339734']);

        $response = $this->postJson('/api/subscriptions', [
            'url'     => self::URL,
            'chat_id' => '570339734',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'listing' => ['url', 'title', 'current_price', 'currency']]);

        $this->assertDatabaseHas('subscriptions', [
            'listing_id' => $listing->id,
            'chat_id'    => '570339734',
        ]);
    }

    public function test_chat_id_registered_no_listing_returns_202_and_dispatches_job(): void
    {
        Queue::fake();
        TelegramChat::create(['chat_id' => '570339734']);

        $response = $this->postJson('/api/subscriptions', [
            'url'     => self::URL,
            'chat_id' => '570339734',
        ]);

        $response->assertStatus(202)->assertJsonStructure(['message']);

        Queue::assertPushed(
            CreateListingAndSubscribeJob::class,
            fn ($job) => $job->url === self::URL
        );
    }

    public function test_invalid_url_returns_422(): void
    {
        $response = $this->postJson('/api/subscriptions', [
            'url' => 'https://www.amazon.com/product/123',
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_url_returns_422(): void
    {
        $response = $this->postJson('/api/subscriptions', []);

        $response->assertStatus(422);
    }

    public function test_unsubscribe_deletes_subscriptions_and_chat(): void
    {
        $listing = $this->makeListing();
        TelegramChat::create(['chat_id' => '570339734']);
        Subscription::create(['listing_id' => $listing->id, 'chat_id' => '570339734']);

        $response = $this->deleteJson('/api/subscriptions', ['chat_id' => '570339734']);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('subscriptions', ['chat_id' => '570339734']);
        $this->assertDatabaseMissing('telegram_chats', ['chat_id' => '570339734']);
    }

    public function test_webhook_start_registers_chat(): void
    {
        $this->fakeTelegram();

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'from' => ['id' => 570339734],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('telegram_chats', ['chat_id' => '570339734']);
    }

    public function test_webhook_start_with_token_subscribes_to_listing(): void
    {
        $this->fakeTelegram();
        $listing = $this->makeListing();

        // Intent first → creates start_token in cache, returns bot_link with token.
        $intent = $this->postJson('/api/subscriptions', ['url' => self::URL]);
        $botLink = $intent->json('bot_link');
        $token = str_contains($botLink, 'start=') ? explode('start=', $botLink)[1] : '';

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'from' => ['id' => 570339734],
                'text' => "/start {$token}",
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('subscriptions', [
            'listing_id' => $listing->id,
            'chat_id'    => '570339734',
        ]);
    }
}
