<?php

namespace App\Tests\Controller;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Tests\Support\FunctionalTestCase;

/**
 * Characterisation tests for the user's absence pages (request, cancel).
 */
final class AbsenceControllerTest extends FunctionalTestCase
{
    public function testCreatePendingRequestNotifiesAdmins(): void
    {
        $user = $this->factory->user();
        $this->factory->admin();
        $type = $this->factory->absenceType();
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-04', '2030-03-08');

        self::assertResponseRedirects('/absence/2030');
        $this->assertFlash('success', 'absence.request.created_success');
        self::assertQueuedEmailCount(1);
        $request = $this->findRequest($user);
        self::assertSame(AbsenceRequest::STATUS_PENDING, $request->getStatus());
        self::assertFalse($request->isHalfDay());
    }

    public function testTypeWithoutApprovalIsApprovedImmediately(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->absenceType(requiresApproval: false);
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-04', '2030-03-04');

        self::assertSame(AbsenceRequest::STATUS_APPROVED, $this->findRequest($user)->getStatus());
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->absenceType();
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-08', '2030-03-04');

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->findRequest($user));
    }

    public function testOverlapWithExistingRequestIsRejected(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->absenceType();
        $this->factory->absenceRequest($user, $type, '2030-03-05', '2030-03-06');
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-04', '2030-03-08');

        self::assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->em->getRepository(AbsenceRequest::class)->findBy(['requestedBy' => $user]));
    }

    public function testQuotaExceededIsRejected(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 2);
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-04', '2030-03-08');

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->findRequest($user));
    }

    public function testHalfDayFieldIsOnlyOfferedWhenEnabled(): void
    {
        $this->loginAs($this->factory->user());

        $crawler = $this->client->request('GET', '/absence/2030/new');
        self::assertCount(0, $crawler->filter('input[name="absence_request[halfDay]"]'));

        $this->factory->settings(['halfDaysEnabled' => true]);
        $crawler = $this->client->request('GET', '/absence/2030/new');
        self::assertCount(1, $crawler->filter('input[name="absence_request[halfDay]"]'));
    }

    public function testHalfDayRequest(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 0.5);
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-04', '2030-03-04', halfDay: true);

        self::assertResponseRedirects('/absence/2030');
        self::assertTrue($this->findRequest($user)->isHalfDay());
    }

    public function testHalfDayMustBeSingleDay(): void
    {
        $user = $this->factory->user();
        $type = $this->factory->absenceType();
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->loginAs($user);

        $this->submitRequest($type, '2030-03-04', '2030-03-05', halfDay: true);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->findRequest($user));
    }

    public function testCancelOwnRequest(): void
    {
        $user    = $this->factory->user();
        $request = $this->factory->absenceRequest($user, $this->factory->absenceType(), '2030-03-04');
        $this->loginAs($user);

        $action = '/absence/' . $request->getId() . '/cancel';
        $token  = $this->csrfTokenFor('/absence/2030', $action);
        $this->client->request('POST', $action, ['_token' => $token]);

        self::assertResponseRedirects('/absence/2030');
        $this->assertFlash('success', 'absence.request.cancelled');
        self::assertSame(AbsenceRequest::STATUS_CANCELLED, $this->reload($request)->getStatus());
    }

    public function testCancelForeignRequestIsForbidden(): void
    {
        $request = $this->factory->absenceRequest($this->factory->user(), $this->factory->absenceType(), '2030-03-04');
        $this->loginAs($this->factory->user());

        $this->client->request('POST', '/absence/' . $request->getId() . '/cancel', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRejectedRequestCannotBeCancelled(): void
    {
        $user    = $this->factory->user();
        $type    = $this->factory->absenceType();
        $pending = $this->factory->absenceRequest($user, $type, '2030-03-04');
        $this->loginAs($user);
        $action = '/absence/' . $pending->getId() . '/cancel';
        $token  = $this->csrfTokenFor('/absence/2030', $action);
        $pending->setStatus(AbsenceRequest::STATUS_REJECTED);
        $this->em->flush();

        $this->client->request('POST', $action, ['_token' => $token]);

        $this->assertFlash('error', 'absence.request.cannot_cancel');
        self::assertSame(AbsenceRequest::STATUS_REJECTED, $this->reload($pending)->getStatus());
    }

    private function submitRequest(AbsenceType $type, string $start, string $end, bool $halfDay = false): void
    {
        $crawler = $this->client->request('GET', '/absence/' . substr($start, 0, 4) . '/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="absence_request"]')->form();
        $form['absence_request[type]']->select((string) $type->getId());
        $form['absence_request[startDate]'] = $start;
        $form['absence_request[endDate]']   = $end;
        if ($halfDay) {
            $form['absence_request[halfDay]']->tick();
        }
        $this->client->submit($form);
    }

    private function findRequest(User $user): ?AbsenceRequest
    {
        return $this->em->getRepository(AbsenceRequest::class)->findOneBy(['requestedBy' => $user]);
    }
}
