<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Weekday abbreviations come from the "weekday.<ISO day>" translation keys in every view
 * (the absence overview was hard-coded German, the year views always English).
 */
final class WeekdayLabelTest extends FunctionalTestCase
{
    // 1 October 2025 is a Wednesday (ISO day 3).
    private const string DAY = '01.10.2025';

    public function testCatalogsContainAllWeekdays(): void
    {
        $translator = static::getContainer()->get(TranslatorInterface::class);
        $expected   = [
            'de' => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            'en' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];

        foreach ($expected as $locale => $names) {
            $actual = array_map(
                static fn (int $day): string => $translator->trans('weekday.' . $day, [], 'messages', $locale),
                range(1, 7),
            );
            self::assertSame($names, $actual, $locale);
        }
    }

    public function testOwnYearViewTranslatesWeekday(): void
    {
        $user = $this->factory->user();
        $this->loginAs($user);

        $crawler = $this->client->request('GET', '/time-tracking/2025');

        self::assertResponseIsSuccessful();
        self::assertSame($this->wednesday(), $this->weekdayOfRow($crawler, self::DAY));
    }

    public function testAdminYearViewTranslatesWeekday(): void
    {
        $admin = $this->factory->user(admin: true);
        $user  = $this->factory->user();
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', sprintf('/admin/time-tracking/%d/2025', $user->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame($this->wednesday(), $this->weekdayOfRow($crawler, self::DAY));
    }

    public function testAbsenceOverviewTranslatesWeekday(): void
    {
        $this->loginAs($this->factory->user());

        $crawler = $this->client->request('GET', '/absences/overview?month=2025-10');

        self::assertResponseIsSuccessful();
        self::assertSame($this->wednesday(), trim($crawler->filter('.day-dow')->first()->text()));
    }

    private function wednesday(): string
    {
        $label = static::getContainer()->get(TranslatorInterface::class)->trans('weekday.3');
        self::assertNotSame('weekday.3', $label);

        return $label;
    }

    /**
     * First word of the small muted line below the date (the weekday, followed by optional badges).
     */
    private function weekdayOfRow(Crawler $crawler, string $date): string
    {
        $cell = $crawler->filter('td')->reduce(
            static fn (Crawler $td): bool => $td->filter('.fw-semibold')->count() > 0
                && trim($td->filter('.fw-semibold')->text()) === $date,
        );
        self::assertCount(1, $cell, sprintf('No day row for %s', $date));

        return strtok(trim($cell->filter('.text-muted.small')->text()), " \n") ?: '';
    }
}
