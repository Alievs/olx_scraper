<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TelegramService
{
    private string $baseUrl;

    public function __construct(private readonly Client $client)
    {
        $this->baseUrl = 'https://api.telegram.org/bot' . config('telegram.bot_token');
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        try {
            $this->client->post("{$this->baseUrl}/sendMessage", [
                'json' => [
                    'chat_id'                  => $chatId,
                    'text'                     => $text,
                    'parse_mode'               => 'MarkdownV2',
                    'disable_web_page_preview' => true,
                ],
            ]);
        } catch (GuzzleException $e) {
            rescue(fn () => \Log::error('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]), null, false);
        }
    }

    public function setWebhook(string $url): bool
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/setWebhook", [
                'json' => ['url' => $url],
            ]);
            $body = json_decode((string) $response->getBody(), true);
            return $body['ok'] ?? false;
        } catch (GuzzleException) {
            return false;
        }
    }

    public function escapeMarkdown(string $text): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
    }

    public function escapeMarkdownUrl(string $url): string
    {
        return str_replace(['\\', ')'], ['\\\\', '\\)'], $url);
    }

    public function deepLink(string $token): string
    {
        return 'https://t.me/' . config('telegram.bot_name') . '?start=' . $token;
    }

    public function botLink(): string
    {
        return 'https://t.me/' . config('telegram.bot_name');
    }
}
