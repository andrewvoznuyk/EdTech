<?php

namespace App\Tests\Unit\Service\Money;

use App\Service\Money\MoneyService;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testToMoneyWithDecimalString(): void
    {
        $money = MoneyService::toMoney('99.95');

        $this->assertSame('9995', $money->getAmount());
        $this->assertSame('USD', $money->getCurrency()->getCode());
        $this->assertSame('99.95', MoneyService::format($money));
    }

    /**
     * @return void
     */
    public function testToMoneyWithIntegerString(): void
    {
        $money = MoneyService::toMoney('50');

        $this->assertSame('5000', $money->getAmount());
        $this->assertSame('50.00', MoneyService::format($money));
    }

    /**
     * @return void
     */
    public function testToMoneyWithFloat(): void
    {
        $money = MoneyService::toMoney(19.99);

        $this->assertSame('1999', $money->getAmount());
        $this->assertSame('19.99', MoneyService::format($money));
    }

    /**
     * @return void
     */
    public function testToMoneyWithNullOrEmptyReturnsZero(): void
    {
        $this->assertSame('0.00', MoneyService::format(MoneyService::toMoney(null)));
        $this->assertSame('0.00', MoneyService::format(MoneyService::toMoney('')));
        $this->assertSame('0.00', MoneyService::format(MoneyService::toMoney('invalid')));
    }

    /**
     * @return void
     */
    public function testFromCents(): void
    {
        $money = MoneyService::fromCents(1250, 'UAH');

        $this->assertSame('1250', $money->getAmount());
        $this->assertSame('UAH', $money->getCurrency()->getCode());
        $this->assertSame('12.50', MoneyService::format($money));
    }

    /**
     * @return void
     */
    public function testAddAndMultiply(): void
    {
        $itemPrice = MoneyService::toMoney('15.50');
        $itemTotal = MoneyService::multiply($itemPrice, 3);
        $this->assertSame('46.50', MoneyService::format($itemTotal));

        $secondItem = MoneyService::toMoney('10.25');
        $grandTotal = MoneyService::add($itemTotal, $secondItem);
        $this->assertSame('56.75', MoneyService::format($grandTotal));
    }
}
