<?php

namespace Tests\Unit;

use App\Exceptions\ScraperException;
use App\Services\OlxScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class OlxScraperServiceTest extends TestCase
{
    private function buildService(string $html): OlxScraperService
    {
        $client = $this->createMock(Client::class);
        $client->method('get')->willReturn(new Response(200, [], $html));

        return new OlxScraperService($client);
    }

    public function test_parses_price_and_title(): void
    {
        $html = <<<HTML
        <html><body>
            <h4 data-testid="ad_title">Sony WH-1000XM5</h4>
            <div data-testid="ad-price-container"><h3>3 500 ₴</h3></div>
        </body></html>
        HTML;

        $result = $this->buildService($html)->fetch('https://www.olx.ua/d/uk/test/');

        $this->assertEquals('Sony WH-1000XM5', $result['title']);
        $this->assertEquals(3500.0, $result['price']);
        $this->assertEquals('UAH', $result['currency']);
    }

    public function test_throws_when_no_price(): void
    {
        $this->expectException(ScraperException::class);

        $html = '<html><body><h4 data-testid="ad_title">Title only</h4></body></html>';
        $this->buildService($html)->fetch('https://www.olx.ua/d/uk/test/');
    }

    public function test_parses_usd_currency(): void
    {
        $html = <<<HTML
        <html><body>
            <h4 data-testid="ad_title">Item</h4>
            <div data-testid="ad-price-container"><h3>$1,200 USD</h3></div>
        </body></html>
        HTML;

        $result = $this->buildService($html)->fetch('https://www.olx.ua/d/uk/test/');

        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals(1200.0, $result['price']);
    }
}
