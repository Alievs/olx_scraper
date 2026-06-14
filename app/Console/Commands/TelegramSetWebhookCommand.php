<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhookCommand extends Command
{
    protected $signature   = 'telegram:set-webhook {url : Public HTTPS URL of your server}';
    protected $description = 'Register Telegram webhook URL with BotFather';

    public function handle(TelegramService $telegram): int
    {
        $url = $this->argument('url');
        $ok  = $telegram->setWebhook(rtrim($url, '/') . '/api/telegram/webhook');

        if ($ok) {
            $this->info("Webhook set: {$url}/api/telegram/webhook");
            return self::SUCCESS;
        }

        $this->error('Failed to set webhook. Check TELEGRAM_BOT_TOKEN in .env');
        return self::FAILURE;
    }
}
