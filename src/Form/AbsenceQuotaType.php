<?php

namespace App\Form;

use App\Entity\AbsenceQuota;
use App\Entity\AbsenceType;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceQuotaType extends AbstractType
{
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
            ->add('quotaDays', IntegerType::class, [
                'label' => 'absence.quota.quota_days',
                'required' => false,
                'help' => 'absence.quota.empty_unlimited',
            ])
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
