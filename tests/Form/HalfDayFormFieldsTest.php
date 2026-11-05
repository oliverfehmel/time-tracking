<?php

namespace App\Tests\Form;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Entity\Holiday;
use App\Entity\Settings;
use App\Entity\User;
use App\Form\AbsenceQuotaType;
use App\Form\AbsenceRequestType;
use App\Form\AbsenceTypeType;
use App\Form\HolidayType;
use App\Form\SettingsType;
use App\Repository\AbsenceTypeRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

/**
 * Prüft, dass die Halbtag-Felder nur bei aktivem Feature angeboten werden und
 * Kontingente in passenden Schritten validiert werden.
 */
#[AllowMockObjectsWithoutExpectations] // TypeTestCase legt intern einen EventDispatcher-Mock an
final class HalfDayFormFieldsTest extends TypeTestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        $this->settings = new Settings();
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $settingsRepo = $this->createStub(SettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturnCallback(fn () => $this->settings);

        // EntityType braucht eine Registry; Choices werden in diesen Tests nie geladen.
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturnCallback(static function (string $class) {
            $metadata = new ClassMetadata($class);
            $metadata->setIdentifier(['id']);
            return $metadata;
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return [
            new PreloadedExtension([
                new EntityType($registry),
                new HolidayType($settingsRepo),
                new AbsenceRequestType($this->createStub(AbsenceTypeRepository::class), $settingsRepo),
                new AbsenceQuotaType($settingsRepo),
                new AbsenceTypeType($settingsRepo),
            ], []),
            new ValidatorExtension(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()),
        ];
    }

    // --- Settings ---

    public function testSettingsForm_togglesHalfDaysEnabled(): void
    {
        $settings = new Settings();
        $form     = $this->factory->create(SettingsType::class, $settings);

        $form->submit([
            'autoPauseAfterSixHours'  => '30',
            'autoPauseAfterNineHours' => '45',
            'halfDaysEnabled'         => '1',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($settings->isHalfDaysEnabled());

        $form = $this->factory->create(SettingsType::class, $settings);
        $form->submit(['autoPauseAfterSixHours' => '30', 'autoPauseAfterNineHours' => '45']);

        $this->assertFalse($settings->isHalfDaysEnabled());
    }

    // --- Holiday / AbsenceRequest: Feld nur bei aktivem Feature ---

    public function testHolidayForm_halfDayFieldOnlyWhenEnabled(): void
    {
        $this->assertFalse($this->factory->create(HolidayType::class, new Holiday())->has('halfDay'));

        $this->settings->setHalfDaysEnabled(true);
        $this->assertTrue($this->factory->create(HolidayType::class, new Holiday())->has('halfDay'));
    }

    public function testAbsenceRequestForm_halfDayFieldOnlyWhenEnabled(): void
    {
        $this->assertFalse($this->factory->create(AbsenceRequestType::class, new AbsenceRequest())->has('halfDay'));

        $this->settings->setHalfDaysEnabled(true);
        $this->assertTrue($this->factory->create(AbsenceRequestType::class, new AbsenceRequest())->has('halfDay'));
    }

    // --- Kontingente ---

    #[DataProvider('quotaProvider')]
    public function testAbsenceTypeForm_quotaSteps(bool $halfDaysEnabled, string $input, bool $expectedValid): void
    {
        $this->settings->setHalfDaysEnabled($halfDaysEnabled);

        $type = new AbsenceType();
        $form = $this->factory->create(AbsenceTypeType::class, $type);
        $form->submit([
            'name'                   => 'Urlaub',
            'keyName'                => 'vacation',
            'defaultYearlyQuotaDays' => $input,
        ]);

        $this->assertSame($expectedValid, $form->get('defaultYearlyQuotaDays')->isValid(), (string) $form->getErrors(true));
        if ($expectedValid) {
            $this->assertSame($input === '' ? null : (float) $input, $type->getDefaultYearlyQuotaDays());
        }
    }

    public static function quotaProvider(): iterable
    {
        yield 'enabled, half day'       => [true, '27.5', true];
        yield 'enabled, whole day'      => [true, '30', true];
        yield 'enabled, empty'          => [true, '', true];
        yield 'enabled, quarter day'    => [true, '27.25', false];
        yield 'enabled, negative'       => [true, '-1', false];
        yield 'disabled, whole day'     => [false, '30', true];
        yield 'disabled, half day'      => [false, '27.5', false];
    }

    public function testAbsenceTypeForm_existingHalfQuotaStaysValidWhenDisabled(): void
    {
        $type = new AbsenceType();
        $type->setName('Urlaub');
        $type->setKeyName('vacation');
        $type->setDefaultYearlyQuotaDays(27.5);

        $form = $this->factory->create(AbsenceTypeType::class, $type);
        $form->submit([
            'name'                   => 'Erholungsurlaub',
            'keyName'                => 'vacation',
            'defaultYearlyQuotaDays' => '27.5',
        ]);

        $this->assertTrue($form->isValid(), (string) $form->getErrors(true));
        $this->assertSame('Erholungsurlaub', $type->getName());
        $this->assertSame(27.5, $type->getDefaultYearlyQuotaDays());
    }

    public function testAbsenceQuotaForm_quotaFieldUsesHalfDaySteps(): void
    {
        $this->settings->setHalfDaysEnabled(true);
        $attr = $this->factory->create(AbsenceQuotaType::class)->get('quotaDays')->getConfig()->getOption('attr');
        $this->assertSame(0.5, $attr['step']);

        $this->settings->setHalfDaysEnabled(false);
        $attr = $this->factory->create(AbsenceQuotaType::class)->get('quotaDays')->getConfig()->getOption('attr');
        $this->assertSame(1, $attr['step']);
    }
}
