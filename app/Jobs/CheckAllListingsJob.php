<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckAllListingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Listing::where('is_active', true)->each(function (Listing $listing) {
            CheckListingPriceJob::dispatch($listing);
        });
    }
}
