<?php

use App\Jobs\CheckAllListingsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckAllListingsJob)->everyMinute();
