<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\CallbackTransformer;

class UserEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'user.email',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'user.name',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('dailyWorkMinutes', NumberType::class, [
                'label' => 'user.daily_work_minutes.title',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'step' => '0.25',
                    'min' => '0',
                    'placeholder' => 'user.daily_work_minutes.placeholder',
                ],
                'help' => 'user.daily_work_minutes.hint',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'user.roles.title',
                'choices' => [
                    'user.roles.user' => 'ROLE_USER',
                    'user.roles.admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'user.password_optional.title',
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'help' => 'user.password_optional.hint',
                'constraints' => [
                    new Length(min: 8, max: 4096),
                ],
            ]);

        $builder->get('dailyWorkMinutes')->addModelTransformer(new CallbackTransformer(
        // Model (min) -> View (hours)
            fn (?int $minutes) => $minutes === null ? null : $minutes / 60,
            // View (hours) -> Model (min)
            fn ($hours) => $hours === null || $hours === '' ? 0 : (int) round(((float) $hours) * 60)
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
