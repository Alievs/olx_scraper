<?php

namespace App\Services;

use App\Exceptions\ScraperException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

class OlxScraperService
{
    public function __construct(private readonly Client $client) {}

    public function fetch(string $url): array
    {
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'uk-UA,uk;q=0.9,en;q=0.8',
                ],
                'timeout' => 30,
                'allow_redirects' => true,
            ]);
        } catch (GuzzleException $e) {
            throw new ScraperException("Page not reachable: {$url}", previous: $e);
        }

        $html = (string) $response->getBody();
        $crawler = new Crawler($html);

        $priceSelector = config('olx.price_selector');
        $titleSelector = config('olx.title_selector');

        $titleNode = $crawler->filter($titleSelector);
        $priceNode = $crawler->filter($priceSelector);

        if ($priceNode->count() === 0) {
            throw new ScraperException("Price not found on page: {$url}");
        }

        $priceText = $priceNode->first()->text();
        [$price, $currency] = $this->parsePrice($priceText);

        $title = $titleNode->count() > 0 ? trim($titleNode->first()->text()) : null;

        return [
            'title' => $title,
            'price' => $price,
            'currency' => $currency,
        ];
    }

    private function parsePrice(string $raw): array
    {
        // Remove non-numeric chars except comma/dot, extract currency
        $currency = 'UAH';
        if (str_contains($raw, '₴') || str_contains($raw, 'грн')) {
            $currency = 'UAH';
        } elseif (str_contains($raw, '$') || str_contains($raw, 'USD')) {
            $currency = 'USD';
        } elseif (str_contains($raw, '€') || str_contains($raw, 'EUR')) {
            $currency = 'EUR';
        } elseif (str_contains($raw, '₸') || str_contains($raw, 'KZT')) {
            $currency = 'KZT';
        }

        $numeric = preg_replace('/[^\d,.]/', '', $raw);

        // Detect thousands separator vs decimal separator
        // If comma followed by exactly 3 digits (then end or another separator) → thousands sep, remove it
        // e.g. "1,200" or "1,200,000" → "1200" / "1200000"
        // e.g. "1,50" → decimal "1.50"
        if (preg_match('/,\d{3}/', $numeric) && ! preg_match('/\.\d+$/', $numeric)) {
            $numeric = str_replace(',', '', $numeric);
        } elseif (str_contains($numeric, ',') && str_contains($numeric, '.')) {
            // Both present: last separator is decimal (e.g. "1.200,50" or "1,200.50")
            if (strrpos($numeric, ',') > strrpos($numeric, '.')) {
                $numeric = str_replace('.', '', $numeric);
                $numeric = str_replace(',', '.', $numeric);
            } else {
                $numeric = str_replace(',', '', $numeric);
            }
        } else {
            $numeric = str_replace(',', '.', $numeric);
        }

        $price = (float) $numeric;

        if ($price <= 0) {
            throw new ScraperException("Could not parse price from: {$raw}");
        }

        return [$price, $currency];
    }
}
