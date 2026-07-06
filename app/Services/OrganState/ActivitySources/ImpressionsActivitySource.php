<?php

namespace App\Services\OrganState\ActivitySources;

use App\Models\Impressions\ImpressionDreamstateFeed;
use App\Models\Impressions\SensemadeImpression;

class ImpressionsActivitySource extends ModelActivitySource
{
    public function connectionKey(): string
    {
        return 'impressions';
    }

    protected function candidates(): array
    {
        return [
            [ImpressionDreamstateFeed::class, 'observed_at'],
            [SensemadeImpression::class, 'sensemade_at'],
            [SensemadeImpression::class, 'observed_at'],
        ];
    }
}
