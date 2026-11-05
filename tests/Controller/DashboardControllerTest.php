<?php

namespace App\Tests\Controller;

use App\Entity\TimeEntry;
use App\Entity\WorkLocation;
use App\Repository\TimeEntryRepository;
use App\Tests\Support\FunctionalTestCase;
use DateTimeImmutable;

/**
 * Characterisation tests for the dashboard actions (timer, today's work location).
 */
final class DashboardControllerTest extends FunctionalTestCase
{
    public function testStartCreatesRunningEntry(): void
    {
        $user = $this->factory->user();
        $this->loginAs($user);

        $token = $this->csrfTokenFor('/', '/time/start');
        $this->client->request('POST', '/time/start', ['_token' => $token]);

        self::assertResponseRedirects('/');
        $this->assertFlash('success', 'flash.timer_started');
        self::assertNotNull(static::getContainer()->get(TimeEntryRepository::class)->findRunningForUser($user));
    }

    public function testStartWhileRunningIsRejected(): void
    {
        $user = $this->factory->user();
        $this->loginAs($user);
        // Render the start form first, then start a timer behind the page's back.
        $token = $this->csrfTokenFor('/', '/time/start');
        $this->factory->timeEntry($user, '-1 hour', null);

        $this->client->request('POST', '/time/start', ['_token' => $token]);

        self::assertResponseRedirects('/');
        $this->assertFlash('error', 'flash.timer_already_running');
        self::assertCount(1, $this->em->getRepository(TimeEntry::class)->findBy(['user' => $user]));
    }

    public function testStopFinishesRunningEntry(): void
    {
        $user  = $this->factory->user();
        $entry = $this->factory->timeEntry($user, '-1 hour', null);
        $this->loginAs($user);

        $token = $this->csrfTokenFor('/', '/time/stop');
        $this->client->request('POST', '/time/stop', ['_token' => $token]);

        self::assertResponseRedirects('/');
        $this->assertFlash('success', 'flash.timer_stopped');
        self::assertNotNull($this->reload($entry)->getStoppedAt());
    }

    public function testStopWithoutRunningTimer(): void
    {
        $user  = $this->factory->user();
        $entry = $this->factory->timeEntry($user, '-1 hour', null);
        $this->loginAs($user);
        $token = $this->csrfTokenFor('/', '/time/stop');
        $entry->setStoppedAt(new DateTimeImmutable());
        $this->em->flush();

        $this->client->request('POST', '/time/stop', ['_token' => $token]);

        self::assertResponseRedirects('/');
        $this->assertFlash('error', 'flash.no_running_timer');
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $user = $this->factory->user();
        $this->loginAs($user);

        $this->client->request('POST', '/time/start', ['_token' => 'invalid']);

        self::assertResponseRedirects('/');
        $this->assertFlash('error', 'flash.invalid_csrf');
        self::assertNull(static::getContainer()->get(TimeEntryRepository::class)->findRunningForUser($user));
    }

    public function testSetTodayWorkLocation(): void
    {
        $user   = $this->factory->user();
        $office = $this->factory->workLocationType(default: true);
        $home   = $this->factory->workLocationType();
        $this->loginAs($user);

        $token = $this->csrfTokenFor('/', '/time/work-location');
        $this->client->request('POST', '/time/work-location', ['_token' => $token, 'location_type_id' => $home->getId()]);

        self::assertResponseRedirects('/');
        $this->assertFlash('success', 'flash.work_location_saved');
        $location = $this->em->getRepository(WorkLocation::class)->findOneBy(['user' => $user, 'date' => new DateTimeImmutable('today')]);
        self::assertSame($home->getId(), $location?->getLocationType()->getId());
        self::assertNotSame($office->getId(), $location->getLocationType()->getId());
    }

    public function testSetTodayWorkLocationRejectsInactiveType(): void
    {
        $user     = $this->factory->user();
        $this->factory->workLocationType(default: true);
        $inactive = $this->factory->workLocationType(active: false);
        $this->loginAs($user);

        $token = $this->csrfTokenFor('/', '/time/work-location');
        $this->client->request('POST', '/time/work-location', ['_token' => $token, 'location_type_id' => $inactive->getId()]);

        self::assertResponseRedirects('/');
        $this->assertFlash('error', 'flash.work_location_invalid');
        self::assertNull($this->em->getRepository(WorkLocation::class)->findOneBy(['user' => $user]));
    }

    public function testSetTodayWorkLocationIsBlockedWhenEditingIsDisabled(): void
    {
        $user = $this->factory->user();
        $home = $this->factory->workLocationType(default: true);
        $this->loginAs($user);
        $token = $this->csrfTokenFor('/', '/time/work-location');
        $this->factory->settings(['usersAreAllowedToChangeTimeEntries' => false]);

        $this->client->request('POST', '/time/work-location', ['_token' => $token, 'location_type_id' => $home->getId()]);

        self::assertResponseRedirects('/');
        self::assertNull($this->em->getRepository(WorkLocation::class)->findOneBy(['user' => $user]));
    }
}
