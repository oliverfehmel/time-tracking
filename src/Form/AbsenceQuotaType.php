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
                'help' => 'Leer = unbegrenzt',
            ])
            ->add('allowOverLimit', ChoiceType::class, [
                'label' => 'Überziehung des Kontingents erlauben',
                'required' => false,
                'choices' => [
                    'Vom Abwesenheitstyp übernehmen' => null,
                    'Erlauben' => true,
                    'Sperren' => false,
                ],
                'help' => 'Nur explizite Werte überschreiben die Grundeinstellung des Abwesenheitstyps.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AbsenceQuota::class]);
    }
}
