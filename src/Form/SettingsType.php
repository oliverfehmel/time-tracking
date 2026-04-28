<?php

namespace App\Form;

use App\Entity\Settings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('autoPauseAfterSixHours', IntegerType::class, [
                'label' => 'settings.auto_pause_after_six_hours.title',
                'required' => true,
                'help' => 'settings.auto_pause_after_six_hours.help',
                'attr' => [
                    'min' => 0,
                    'inputmode' => 'numeric',
                ],
                'constraints' => [
                    new NotNull(),
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('autoPauseAfterNineHours', IntegerType::class, [
                'label' => 'settings.auto_pause_after_nine_hours.title',
                'required' => true,
                'help' => 'settings.auto_pause_after_nine_hours.help',
                'attr' => [
                    'min' => 0,
                    'inputmode' => 'numeric',
                ],
                'constraints' => [
                    new NotNull(),
                    new GreaterThanOrEqual(0),
                ],
            ])
            ->add('usersAreAllowedToChangeTimeEntries', CheckboxType::class, [
                'label' => 'settings.users_are_allowed_to_change_time_entries',
                'required' => false,
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'settings.logo.title',
                'required' => false,
                'mapped' => false,
                'help' => 'settings.logo.help',
                'attr' => [
                    'accept' => '.png,.jpg,.jpeg,.webp,.svg',
                ],
                'constraints' => [
                    new File(
                        maxSize: '4M',
                        mimeTypes: [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                            'image/svg+xml',
                        ],
                        mimeTypesMessage: 'settings.logo.invalid_type'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Settings::class,
        ]);
    }
}
