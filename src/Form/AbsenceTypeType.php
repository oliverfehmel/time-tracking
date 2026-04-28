<?php

namespace App\Form;

use App\Entity\AbsenceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'absence.type.name',
            ])
            ->add('keyName', TextType::class, [
                'label' => 'absence.type.key_name.title',
                'help' => 'absence.type.key_name.hint'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'absence.type.is_active',
                'required' => false
            ])
            ->add('requiresApproval', CheckboxType::class, [
                'label' => 'absence.type.requires_approval',
                'required' => false
            ])
            ->add('requiresQuota', CheckboxType::class, [
                'label' => 'absence.type.requires_quota',
                'required' => false
            ])
            ->add('defaultYearlyQuotaDays', IntegerType::class, [
                'label' => 'absence.type.default_yearly_quota_days.title',
                'required' => false,
                'help' => 'absence.type.default_yearly_quota_days.hint',
            ])
            ->add('allowOverLimit', CheckboxType::class, [
                'label' => 'absence.type.allow_over_limit',
                'required' => false,
                'help' => 'absence.type.allow_over_limit_help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AbsenceType::class]);
    }
}
