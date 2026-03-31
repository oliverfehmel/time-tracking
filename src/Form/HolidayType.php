<?php

namespace App\Form;

use App\Entity\Holiday;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HolidayType extends AbstractType
{
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
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Holiday::class,
        ]);
    }
}
