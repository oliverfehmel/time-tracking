<?php

namespace App\Tests\Service;

use App\Service\DayFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DayFormatterTest extends TestCase
{
    #[DataProvider('provider')]
    public function testFormat(int|float|null $days, string $locale, string $expected): void
    {
        $this->assertSame($expected, DayFormatter::format($days, $locale));
    }

    public static function provider(): iterable
    {
        yield 'null'                 => [null, 'de', ''];
        yield 'int'                  => [3, 'de', '3'];
        yield 'whole float'          => [3.0, 'de', '3'];
        yield 'zero'                 => [0.0, 'en', '0'];
        yield 'half german'          => [2.5, 'de', '2,5'];
        yield 'half english'         => [2.5, 'en', '2.5'];
        yield 'half regional locale' => [0.5, 'en_GB', '0.5'];
        yield 'float noise'          => [0.1 + 0.2 + 2.7, 'de', '3'];
    }
}
