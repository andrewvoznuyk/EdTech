<?php

namespace App\Enum;

use App\Enum\Trait\EnumValuesTrait;

enum OrderStatus: string
{
    use EnumValuesTrait;

    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    /**
     * @param OrderStatus $targetStatus
     * @return bool
     */
    public function canTransitionTo(self $targetStatus): bool
    {
        if ($this === $targetStatus) {
            return true;
        }

        return match ($this) {
            self::PENDING => in_array($targetStatus, [self::PAID, self::CANCELLED], true),
            self::PAID => in_array($targetStatus, [self::CANCELLED], true),
            self::CANCELLED => false,
        };
    }
}
