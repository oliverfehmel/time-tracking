<?php

namespace App\Tests\Service;

use App\Service\DomainRuleViolation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class DomainRuleViolationTest extends TestCase
{
    public function testFromKeyUsesLastSegmentAsRule(): void
    {
        $violation = DomainRuleViolation::fromKey('absence.request.end_before_start', propertyPath: 'endDate');

        self::assertSame('end_before_start', $violation->rule);
        self::assertSame('absence.request.end_before_start', $violation->messageKey);
        self::assertSame(422, $violation->status);
        self::assertSame('endDate', $violation->propertyPath);
    }

    public function testTransFormatsDayCountsPerLocale(): void
    {
        $translator = new Translator('de');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['quota' => '%already% von %quota%, neu %requested% (%who%)'], 'de');
        $translator->addResource('array', ['quota' => '%already% of %quota%, new %requested% (%who%)'], 'en');

        $violation = new DomainRuleViolation('quota_exceeded', 'quota', [
            'already' => 2.5, 'quota' => 3.0, 'requested' => 1.0, 'who' => 'Anna',
        ]);

        self::assertSame('2,5 von 3, neu 1 (Anna)', $violation->trans($translator));
        self::assertSame('2.5 of 3, new 1 (Anna)', $violation->trans($translator, 'en'));
    }
}
