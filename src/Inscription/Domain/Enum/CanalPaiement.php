<?php

namespace App\Inscription\Domain\Enum;

enum CanalPaiement: string
{
    case ORANGE_MONEY = 'ORANGE_MONEY';
    case MTN_MONEY = 'MTN_MONEY';
    case MOOV_MONEY = 'MOOV_MONEY';
    case WAVE = 'WAVE';
    case WESTERN_UNION = 'WESTERN_UNION';
    case RIA = 'RIA';
    case MONEYGRAM = 'MONEYGRAM';
    case VIREMENT = 'VIREMENT';

    public function label(): string
    {
        return match ($this) {
            self::ORANGE_MONEY => 'Orange Money',
            self::MTN_MONEY => 'MTN Money',
            self::MOOV_MONEY => 'Moov Money',
            self::WAVE => 'Wave',
            self::WESTERN_UNION => 'Western Union',
            self::RIA => 'RIA',
            self::MONEYGRAM => 'MoneyGram',
            self::VIREMENT => 'Virement bancaire',
        };
    }
}
