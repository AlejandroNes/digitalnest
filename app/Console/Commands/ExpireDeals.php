<?php

namespace App\Console\Commands;

use App\Models\Deal;
use Illuminate\Console\Command;

class ExpireDeals extends Command
{
    protected $signature = 'app:expire-deals';

    protected $description = 'Quita el destacado de las ofertas caducadas';

    public function handle(): int
    {
        $count = Deal::query()
            ->where('is_featured', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_featured' => false]);

        $this->info("Ofertas caducadas sin destacar: {$count}");

        return self::SUCCESS;
    }
}
