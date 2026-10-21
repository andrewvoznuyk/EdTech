<?php

namespace App\Service\Money;

use InvalidArgumentException;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;

/**
 * @implements MoneyServiceInterface
 */
final class MoneyService implements MoneyServiceInterface
{
    public const string DEFAULT_CURRENCY = 'USD';

    /**
     * @var ISOCurrencies|null
     */
    private static ?ISOCurrencies $currencies = null;

    /**
     * @var DecimalMoneyParser|null
     */
    private static ?DecimalMoneyParser $parser = null;

    /**
     * @var DecimalMoneyFormatter|null
     */
    private static ?DecimalMoneyFormatter $formatter = null;

    /**
     * @return ISOCurrencies
     */
    private static function getCurrencies(): ISOCurrencies
    {
        if (self::$currencies === null) {
            self::$currencies = new ISOCurrencies();
        }

        return self::$currencies;
    }

    /**
     * @return DecimalMoneyParser
     */
    private static function getParser(): DecimalMoneyParser
    {
        if (self::$parser === null) {
            self::$parser = new DecimalMoneyParser(self::getCurrencies());
        }

        return self::$parser;
    }

    /**
     * @return DecimalMoneyFormatter
     */
    private static function getFormatter(): DecimalMoneyFormatter
    {
        if (self::$formatter === null) {
            self::$formatter = new DecimalMoneyFormatter(self::getCurrencies());
        }

        return self::$formatter;
    }

    /**
     * @param string|int|float|null $amount
     * @param string $currencyCode
     * @return Money
     */
    public static function toMoney(string|int|float|null $amount, string $currencyCode = self::DEFAULT_CURRENCY): Money
    {
        $currency = new Currency(strtoupper($currencyCode));

        if ($amount === null || $amount === '') {
            return new Money(0, $currency);
        }

        if (is_int($amount)) {
            $normalizedAmount = sprintf('%d.00', $amount);
        } elseif (is_float($amount)) {
            $normalizedAmount = number_format($amount, 2, '.', '');
        } else {
            $cleanAmount = str_replace(',', '.', trim($amount));
            if (!is_numeric($cleanAmount)) {
                return new Money(0, $currency);
            }
            $normalizedAmount = str_contains($cleanAmount, '.') ? $cleanAmount : $cleanAmount . '.00';
        }

        try {
            return self::getParser()->parse($normalizedAmount, $currency);
        } catch (InvalidArgumentException) {
            return new Money(0, $currency);
        }
    }

    /**
     * @param int $cents
     * @param string $currencyCode
     * @return Money
     */
    public static function fromCents(int $cents, string $currencyCode = self::DEFAULT_CURRENCY): Money
    {
        return new Money($cents, new Currency(strtoupper($currencyCode)));
    }

    /**
     * @param Money $money
     * @return string
     */
    public static function format(Money $money): string
    {
        return self::getFormatter()->format($money);
    }

    /**
     * @param Money $firstMoney
     * @param Money $secondMoney
     * @return Money
     */
    public static function add(Money $firstMoney, Money $secondMoney): Money
    {
        return $firstMoney->add($secondMoney);
    }

    /**
     * @param Money $money
     * @param int $multiplier
     * @return Money
     */
    public static function multiply(Money $money, int $multiplier): Money
    {
        return $money->multiply($multiplier);
    }
}
