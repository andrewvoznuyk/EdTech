<?php

namespace App\Service\Money;

use Money\Money;

interface MoneyServiceInterface
{
    /**
     * @param string|int|float|null $amount
     * @param string $currencyCode
     * @return Money
     */
    public static function toMoney(string|int|float|null $amount, string $currencyCode = MoneyService::DEFAULT_CURRENCY): Money;

    /**
     * @param int $cents
     * @param string $currencyCode
     * @return Money
     */
    public static function fromCents(int $cents, string $currencyCode = MoneyService::DEFAULT_CURRENCY): Money;

    /**
     * @param Money $money
     * @return string
     */
    public static function format(Money $money): string;

    /**
     * @param Money $firstMoney
     * @param Money $secondMoney
     * @return Money
     */
    public static function add(Money $firstMoney, Money $secondMoney): Money;

    /**
     * @param Money $money
     * @param int $multiplier
     * @return Money
     */
    public static function multiply(Money $money, int $multiplier): Money;
}
