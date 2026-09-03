<?php

namespace App;

enum TransactionType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Dispensed = 'dispensed';
    case Returned = 'returned';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock In',
            self::StockOut => 'Stock Out',
            self::Dispensed => 'Dispensed',
            self::Returned => 'Returned',
            self::Damaged => 'Damaged',
            self::Expired => 'Expired',
            self::Adjustment => 'Adjustment',
            self::Transfer => 'Transfer',
        };
    }
}
