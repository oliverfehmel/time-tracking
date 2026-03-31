<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'label' => false,
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        new NotBlank(
                            message: 'password_reset.password.first.blank',
                        ),
                        new Length(
                            min: 12,
                            max: 4096,
                            minMessage: 'Your password should be at least {{ limit }} characters',
                        ),
                        new PasswordStrength(
                            minScore: PasswordStrength::STRENGTH_MEDIUM
                        ),
                        new NotCompromisedPassword(),
                    ],
                    'label' => 'password_reset.password.first.title',
                ],
                'second_options' => [
                    'label' => 'password_reset.password.second',
                ],
                'invalid_message' => 'password_reset.password_mismatch',
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
