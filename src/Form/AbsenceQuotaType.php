<?php

namespace App\Form;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceType;
use App\Entity\User;
use App\Repository\SettingsRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\DivisibleBy;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class AbsenceQuotaType extends AbstractType
{
    public function __construct(private readonly SettingsRepository $settingsRepository) {}

    /**
     * Kontingent-Feld: Dezimalzahl, bei aktivem Halbtag-Feature in 0,5er-Schritten, sonst ganze Tage.
     */
    public static function quotaDaysOptions(bool $halfDaysEnabled, string $label, string $help): array
    {
        $step = $halfDaysEnabled ? 0.5 : 1;

        return [
            'label' => $label,
            'required' => false,
            'help' => $help,
            'scale' => 1,
            'html5' => true,
            'attr' => ['min' => 0, 'step' => $step],
            'constraints' => [
                new PositiveOrZero(),
                new DivisibleBy(value: $step, message: $halfDaysEnabled ? 'absence.quota.half_day_steps' : 'absence.quota.whole_day_steps'),
            ],
        ];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'label' => 'absence.quota.user',
                'class' => User::class,
                'choice_label' => 'email',
            ])
            ->add('type', EntityType::class, [
                'label' => 'absence.quota.type',
                'class' => AbsenceType::class,
                'choice_label' => 'name',
            ])
            ->add('year', IntegerType::class, [
                'label' => 'absence.quota.year',
            ])
            ->add('quotaDays', NumberType::class, self::quotaDaysOptions(
                $this->settingsRepository->getOrCreate()->isHalfDaysEnabled(),
                'absence.quota.quota_days',
                'absence.quota.empty_unlimited',
            ))
            ->add('allowOverLimit', ChoiceType::class, [
                'label' => 'absence.quota.allow_over_limit',
                'required' => false,
                'choices' => [
                    'general.by_type' => null,
                    'general.allowed' => true,
                    'general.blocked' => false,
                ],
                'help' => 'absence.quota.allow_over_limit_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AbsenceQuota::class]);
    }
}
