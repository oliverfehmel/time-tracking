<?php

namespace App\Form;

use App\Entity\WorkLocationType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkLocationTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'work_location_type.name',
            ])
            ->add('keyName', TextType::class, [
                'label' => 'work_location_type.key_name',
                'help' => 'work_location_type.key_name_help',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'work_location_type.is_active',
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'work_location_type.is_default',
                'required' => false,
            ])
            ->add('icon', TextType::class, [
                'label' => 'work_location_type.icon',
                'help' => 'work_location_type.icon_help',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WorkLocationType::class]);
    }
}
