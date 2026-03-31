<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'user.name',
            ])
            ->add('email', EmailType::class, [
                'label' => 'user.email',
            ])
            ->add('dailyWorkMinutes', IntegerType::class, [
                'label' => 'user.daily_work_minutes.title',
                'help' => 'user.daily_work_minutes.hint',
                'constraints' => [
                    new Range(min: 0, max: 24 * 60),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'label' => false,
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'user.first_password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'user.second_password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'user.password_mismatch',
                'constraints' => [
                    new Length(min: 8, max: 4096, minMessage: 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein.'),
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
