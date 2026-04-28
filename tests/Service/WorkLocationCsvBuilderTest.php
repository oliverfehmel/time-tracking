<?php

namespace App\Tests\Service;

use App\Service\WorkLocationCsvBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class WorkLocationCsvBuilderTest extends TestCase
{
    private WorkLocationCsvBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new WorkLocationCsvBuilder();
    }

    // --- helpers ---

    private function makeDay(string $date, int $netSeconds, ?string $locationName): array
    {
        return [
            'date'             => new DateTimeImmutable($date),
            'netSeconds'       => $netSeconds,
            'workLocationName' => $locationName,
            'workLocationIcon' => null,
        ];
    }

    private function lines(array $months): array
    {
        $raw   = $this->builder->build($months);
        $clean = ltrim($raw, "\xEF\xBB\xBF");
        return array_values(array_filter(explode("\r\n", $clean)));
    }

    // --- BOM & header ---

    public function testOutput_startsWithUtf8Bom(): void
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->builder->build([]));
    }

    public function testOutput_firstLine_isHeader(): void
    {
        $this->assertSame('Arbeitsort,Tage', $this->lines([])[0] ?? '');
    }

    public function testOutput_emptyMonths_onlyHeader(): void
    {
        $this->assertCount(1, $this->lines([]));
    }

    // --- counting ---

    public function testSingleLocation_counted(): void
    {
        $months = [[
            'days' => [
                $this->makeDay('2024-01-15', 28800, 'Büro'),
                $this->makeDay('2024-01-16', 28800, 'Büro'),
            ],
        ]];

        $this->assertContains('Büro,2', $this->lines($months));
    }

    public function testMultipleLocations_allCounted(): void
    {
        $months = [[
            'days' => [
                $this->makeDay('2024-01-15', 28800, 'Büro'),
                $this->makeDay('2024-01-16', 28800, 'Home Office'),
                $this->makeDay('2024-01-17', 28800, 'Büro'),
            ],
        ]];

        $lines = $this->lines($months);
        $this->assertContains('Büro,2', $lines);
        $this->assertContains('Home Office,1', $lines);
    }

    public function testAcrossMultipleMonths_counted(): void
    {
        $months = [
            ['days' => [$this->makeDay('2024-01-15', 28800, 'Büro')]],
            ['days' => [$this->makeDay('2024-02-05', 28800, 'Büro')]],
            ['days' => [$this->makeDay('2024-03-11', 28800, 'Home Office')]],
        ];

        $lines = $this->lines($months);
        $this->assertContains('Büro,2', $lines);
        $this->assertContains('Home Office,1', $lines);
    }

    // --- exclusions ---

    public function testZeroNetSeconds_excluded(): void
    {
        $months = [[
            'days' => [
                $this->makeDay('2024-01-15', 0, 'Büro'),
                $this->makeDay('2024-01-16', 28800, 'Home Office'),
            ],
        ]];

        $lines = $this->lines($months);
        $this->assertNotContains('Büro,1', $lines);
        $this->assertContains('Home Office,1', $lines);
    }

    public function testNullLocationName_excluded(): void
    {
        $months = [[
            'days' => [
                $this->makeDay('2024-01-15', 28800, null),
                $this->makeDay('2024-01-16', 28800, 'Home Office'),
            ],
        ]];

        $lines = $this->lines($months);
        $this->assertCount(2, $lines); // header + Home Office only
        $this->assertContains('Home Office,1', $lines);
    }

    public function testZeroNetSecondsAndNullLocation_allExcluded(): void
    {
        $months = [[
            'days' => [
                $this->makeDay('2024-01-15', 0, null),
                $this->makeDay('2024-01-16', 0, 'Büro'),
                $this->makeDay('2024-01-17', 28800, null),
            ],
        ]];

        $this->assertCount(1, $this->lines($months)); // header only
    }

    // --- sorting ---

    public function testSortedByDayCountDescending(): void
    {
        $months = [[
            'days' => [
                $this->makeDay('2024-01-15', 28800, 'Home Office'),
                $this->makeDay('2024-01-16', 28800, 'Büro'),
                $this->makeDay('2024-01-17', 28800, 'Büro'),
                $this->makeDay('2024-01-18', 28800, 'Büro'),
            ],
        ]];

        $dataLines = array_slice($this->lines($months), 1); // skip header

        $this->assertSame('Büro,3', $dataLines[0]);
        $this->assertSame('Home Office,1', $dataLines[1]);
    }

    // --- CSV escaping ---

    public function testLocationNameWithComma_isQuoted(): void
    {
        $months = [[
            'days' => [$this->makeDay('2024-01-15', 28800, 'Büro, Etage 3')],
        ]];

        $this->assertContains('"Büro, Etage 3",1', $this->lines($months));
    }

    public function testLocationNameWithQuote_isDoubleEscaped(): void
    {
        $months = [[
            'days' => [$this->makeDay('2024-01-15', 28800, 'Büro "Mitte"')],
        ]];

        $this->assertContains('"Büro ""Mitte""",1', $this->lines($months));
    }

    public function testPlainLocationName_notQuoted(): void
    {
        $months = [[
            'days' => [$this->makeDay('2024-01-15', 28800, 'Büro')],
        ]];

        $this->assertContains('Büro,1', $this->lines($months));
    }

    public function testLocationNameWithNewline_isQuoted(): void
    {
        $months = [[
            'days' => [$this->makeDay('2024-01-15', 28800, "Home\nOffice")],
        ]];

        $this->assertStringContainsString('"Home' . "\n" . 'Office",1', $this->builder->build($months));
    }
}
