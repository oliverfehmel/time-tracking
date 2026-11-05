<?php

namespace App\Tests\Controller;

use App\Entity\TimeEntry;
use App\Entity\WorkLocation;
use App\Tests\Support\FunctionalTestCase;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Characterisation tests for the day view: creating, editing and deleting time entries, setting the work location.
 */
final class TimeTrackingDayControllerTest extends FunctionalTestCase
{
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = new DateTimeImmutable('today')->format('Y-m-d');
    }

    public function testCreateEntry(): void
    {
        $user = $this->factory->user();
        $this->loginAs($user);

        $this->submitEntryForm('/time-tracking/entry/new/' . $this->today, '09:00', '12:30');

        self::assertResponseRedirects('/time-tracking/day/' . $this->today);
        $this->assertFlash('success', 'flash.time_entry_created');
        $entries = $this->em->getRepository(TimeEntry::class)->findBy(['user' => $user]);
        self::assertCount(1, $entries);
        self::assertSame($this->berlinToServer('09:00'), $entries[0]->getStartedAt()->format('Y-m-d H:i'));
        self::assertSame($this->berlinToServer('12:30'), $entries[0]->getStoppedAt()->format('Y-m-d H:i'));
    }

    public function testFullyOverlappingEntryIsRejected(): void
    {
        $user = $this->factory->user();
        $this->factory->timeEntry($user, $this->berlinToServer('08:00'), $this->berlinToServer('13:00'));
        $this->loginAs($user);

        $this->submitEntryForm('/time-tracking/entry/new/' . $this->today, '09:00', '12:00');

        self::assertResponseRedirects('/time-tracking/day/' . $this->today);
        $this->assertFlash('error', 'flash.time_range_fully_overlaps');
        self::assertCount(1, $this->em->getRepository(TimeEntry::class)->findBy(['user' => $user]));
    }

    public function testPartiallyOverlappingEntryIsAdjusted(): void
    {
        $user = $this->factory->user();
        $this->factory->timeEntry($user, $this->berlinToServer('08:00'), $this->berlinToServer('10:00'));
        $this->loginAs($user);

        $this->submitEntryForm('/time-tracking/entry/new/' . $this->today, '09:00', '12:00');

        $this->assertFlash('warning', 'flash.time_range_adjusted');
        $new = $this->em->getRepository(TimeEntry::class)->findOneBy(['user' => $user], ['startedAt' => 'DESC']);
        self::assertSame($this->berlinToServer('10:00'), $new->getStartedAt()->format('Y-m-d H:i'));
        self::assertSame($this->berlinToServer('12:00'), $new->getStoppedAt()->format('Y-m-d H:i'));
    }

    public function testEditOwnEntry(): void
    {
        $user  = $this->factory->user();
        $entry = $this->factory->timeEntry($user, $this->berlinToServer('08:00'), $this->berlinToServer('10:00'));
        $this->loginAs($user);

        $this->submitEntryForm('/time-tracking/entry/' . $entry->getId() . '/edit', '08:30', '11:00');

        $this->assertFlash('success', 'flash.time_entry_saved');
        $entry = $this->reload($entry);
        self::assertSame($this->berlinToServer('08:30'), $entry->getStartedAt()->format('Y-m-d H:i'));
        self::assertSame($this->berlinToServer('11:00'), $entry->getStoppedAt()->format('Y-m-d H:i'));
    }

    public function testEditForeignEntryIsForbidden(): void
    {
        $owner = $this->factory->user();
        $entry = $this->factory->timeEntry($owner, $this->berlinToServer('08:00'), $this->berlinToServer('10:00'));
        $this->loginAs($this->factory->user());

        $this->client->request('GET', '/time-tracking/entry/' . $entry->getId() . '/edit');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLockedMonthCannotBeEdited(): void
    {
        $user = $this->factory->user();
        $old  = new DateTimeImmutable('first day of this month')->modify('-3 months')->format('Y-m-d');
        $this->loginAs($user);

        $this->client->request('GET', '/time-tracking/entry/new/' . $old);

        self::assertResponseRedirects('/time-tracking/day/' . $old);
        $this->assertFlash('error', 'flash.month_not_editable');
    }

    public function testDeleteOwnEntry(): void
    {
        $user  = $this->factory->user();
        $entry = $this->factory->timeEntry($user, $this->berlinToServer('08:00'), $this->berlinToServer('10:00'));
        $id    = $entry->getId();
        $this->loginAs($user);

        $token = $this->csrfTokenFor('/time-tracking/day/' . $this->today, '/time-tracking/entry/' . $id . '/delete');
        $this->client->request('POST', '/time-tracking/entry/' . $id . '/delete', ['_token' => $token]);

        self::assertResponseRedirects('/time-tracking/day/' . $this->today);
        $this->assertFlash('success', 'flash.time_entry_deleted');
        $this->em->clear();
        self::assertNull($this->em->find(TimeEntry::class, $id));
    }

    public function testDayViewRedirectsHomeWhenEditingIsDisabled(): void
    {
        $user = $this->factory->user();
        $this->factory->settings(['usersAreAllowedToChangeTimeEntries' => false]);
        $this->loginAs($user);

        $this->client->request('GET', '/time-tracking/day/' . $this->today);

        self::assertResponseRedirects('/');
    }

    public function testSetWorkLocationForDay(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->workLocationType();
        $this->loginAs($user);
        $action = '/time-tracking/day/' . $this->today . '/location';

        $token = $this->csrfTokenFor('/time-tracking/day/' . $this->today, $action);
        $this->client->request('POST', $action, ['_token' => $token, 'location_type_id' => $type->getId()]);

        self::assertResponseRedirects('/time-tracking/day/' . $this->today);
        $this->assertFlash('success', 'flash.work_location_saved');
        $location = $this->em->getRepository(WorkLocation::class)->findOneBy(['user' => $user]);
        self::assertSame($type->getId(), $location?->getLocationType()->getId());
    }

    private function submitEntryForm(string $url, string $start, string $end): void
    {
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="time_entry"]')->form();
        $form['time_entry[startTime]'] = $this->today . 'T' . $start;
        $form['time_entry[endTime]']   = $this->today . 'T' . $end;
        $this->client->submit($form);
    }

    /**
     * The entry form takes Europe/Berlin wall-clock times; entries are stored in the server time zone.
     */
    private function berlinToServer(string $time): string
    {
        return new DateTimeImmutable($this->today . ' ' . $time, new DateTimeZone('Europe/Berlin'))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i');
    }
}
