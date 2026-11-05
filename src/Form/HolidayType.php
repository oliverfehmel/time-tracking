<?php

namespace App\Form;

use App\Entity\Holiday;
use App\Entity\User;
use App\Repository\SettingsRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HolidayType extends AbstractType
{
    public function __construct(private readonly SettingsRepository $settingsRepository) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'holiday.name',
            ])
            ->add('date', DateType::class, [
                'label' => 'holiday.date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('users', EntityType::class, [
                'label' => 'holiday.users',
                'class' => User::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
            ])
        ;

        // Halbtags-Feiertage nur anbieten, wenn das Feature aktiv ist. Ist es deaktiviert, bleibt ein
        // bestehendes halfDay-Flag beim Speichern unverändert, weil das Feld nicht im Formular ist.
        if ($this->settingsRepository->getOrCreate()->isHalfDaysEnabled()) {
            $builder->add('halfDay', CheckboxType::class, [
                'label' => 'holiday.half_day',
                'help' => 'holiday.half_day_help',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Holiday::class,
        ]);
    }
}
