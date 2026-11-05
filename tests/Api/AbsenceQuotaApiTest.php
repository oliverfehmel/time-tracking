<?php

namespace App\Tests\Api;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceRequest;
use App\Tests\Support\ApiTestCase;

final class AbsenceQuotaApiTest extends ApiTestCase
{
    public function testOverviewCountsHalfDays(): void
    {
        $me   = $this->actAs($this->factory->user());
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 30);
        $this->factory->quota($me, $type, 2030, 27.5);
        $this->factory->absenceRequest($me, $type, '2030-03-04', '2030-03-06', AbsenceRequest::STATUS_APPROVED);
        $this->factory->absenceRequest($me, $type, '2030-03-07', status: AbsenceRequest::STATUS_APPROVED, halfDay: true);
        $this->factory->absenceRequest($me, $type, '2030-03-08', halfDay: true);

        $body = $this->api('GET', '/api/v1/users/' . $me->getId() . '/absence-quotas?year=2030');

        $this->assertStatus(200);
        self::assertSame(2030, $body['data']['year']);
        $row = $this->rowFor($body, $type->getId());
        self::assertSame(27.5, (float) $row['quotaDays']);
        self::assertSame(3.5, (float) $row['usedApprovedDays']);
        self::assertSame(0.5, (float) $row['usedPendingDays']);
        self::assertSame(24.0, (float) $row['remainingDays']);
        self::assertFalse($row['allowOverLimit']);
    }

    public function testDefaultYearIsCurrentYear(): void
    {
        self::mockTime('2031-02-01 10:00:00');
        $me = $this->actAs($this->factory->user());

        $body = $this->api('GET', '/api/v1/users/' . $me->getId() . '/absence-quotas');

        self::assertSame(2031, $body['data']['year']);
    }

    public function testForeignOverviewIsNotFound(): void
    {
        $this->actAs($this->factory->user());

        $this->api('GET', '/api/v1/users/' . $this->factory->user()->getId() . '/absence-quotas');

        $this->assertProblem(404, 'not_found');
    }

    public function testAdminSetsAndRemovesIndividualQuota(): void
    {
        $this->factory->settings(['halfDaysEnabled' => true]);
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();
        $type = $this->factory->absenceType(requiresQuota: true, defaultYearlyQuotaDays: 30);
        $url  = sprintf('/api/v1/users/%d/absence-quotas/2030/%d', $user->getId(), $type->getId());

        $body = $this->api('PUT', $url, ['quotaDays' => 27.5, 'allowOverLimit' => false]);
        $this->assertStatus(200);
        self::assertSame(['userId' => $user->getId(), 'typeId' => $type->getId(), 'year' => 2030, 'quotaDays' => 27.5, 'allowOverLimit' => false], $body['data']);

        $body = $this->api('PUT', $url, ['quotaDays' => null]);
        self::assertNull($body['data']['quotaDays']);
        self::assertNull($body['data']['allowOverLimit']);
        self::assertCount(1, $this->em->getRepository(AbsenceQuota::class)->findBy(['user' => $user->getId()]));

        // An individual quota without days falls back to the type default
        $overview = $this->api('GET', sprintf('/api/v1/users/%d/absence-quotas?year=2030', $user->getId()));
        self::assertSame(30.0, (float) $this->rowFor($overview, $type->getId())['quotaDays']);

        $this->api('PUT', $url, ['quotaDays' => 12]);
        $overview = $this->api('GET', sprintf('/api/v1/users/%d/absence-quotas?year=2030', $user->getId()));
        self::assertSame(12.0, (float) $this->rowFor($overview, $type->getId())['quotaDays']);

        $this->api('DELETE', $url);
        $this->assertStatus(204);
        $this->api('DELETE', $url);
        $this->assertProblem(404, 'not_found');

        $overview = $this->api('GET', sprintf('/api/v1/users/%d/absence-quotas?year=2030', $user->getId()));
        self::assertSame(30.0, (float) $this->rowFor($overview, $type->getId())['quotaDays']);
    }

    public function testQuotaValidation(): void
    {
        $this->actAs($this->factory->admin());
        $user = $this->factory->user();
        $type = $this->factory->absenceType(requiresQuota: true);
        $url  = sprintf('/api/v1/users/%d/absence-quotas/2030/%d', $user->getId(), $type->getId());

        $body = $this->api('PUT', $url, ['quotaDays' => 12.5]);
        $this->assertProblem(422, 'validation_failed');
        $this->assertViolation($body, 'quotaDays');

        $this->api('PUT', $url, ['quotaDays' => -1]);
        $this->assertProblem(422, 'validation_failed');

        $this->api('PUT', sprintf('/api/v1/users/%d/absence-quotas/2030/999999', $user->getId()), ['quotaDays' => 1]);
        $this->assertProblem(404, 'not_found');
    }

    public function testRegularUserCannotSetQuota(): void
    {
        $me   = $this->actAs($this->factory->user());
        $type = $this->factory->absenceType(requiresQuota: true);

        $this->api('PUT', sprintf('/api/v1/users/%d/absence-quotas/2030/%d', $me->getId(), $type->getId()), ['quotaDays' => 99]);

        $this->assertProblem(403, 'forbidden');
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function rowFor(array $body, int $typeId): array
    {
        foreach ($body['data']['types'] as $row) {
            if ($row['type']['id'] === $typeId) {
                return $row;
            }
        }

        self::fail("No quota row for type $typeId");
    }
}
