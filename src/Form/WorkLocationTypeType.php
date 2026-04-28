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
                'label' => 'Name',
            ])
            ->add('keyName', TextType::class, [
                'label' => 'Schlüssel',
                'help' => 'Interner eindeutiger Bezeichner (z. B. office, home_office)',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Aktiv',
                'required' => false,
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Standard (wird bei neuen Tagen vorausgewählt)',
                'required' => false,
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icon (FontAwesome-Klasse)',
                'help' => 'Optional, z. B. fa-solid fa-house oder fa-solid fa-building',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WorkLocationType::class]);
    }
}
