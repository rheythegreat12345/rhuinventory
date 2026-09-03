<?php

namespace App;

enum AdjustmentReason: string
{
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Expired = 'expired';
    case Correction = 'inventory_correction';
    case Returned = 'returned';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damaged => 'Damaged medicine',
            self::Lost => 'Lost medicine',
            self::Expired => 'Expired medicine',
            self::Correction => 'Inventory correction',
            self::Returned => 'Returned medicine',
            self::Other => 'Other',
        };
    }
}
