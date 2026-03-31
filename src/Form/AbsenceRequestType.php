<?php

namespace App\Form;

use App\Entity\AbsenceRequest;
use App\Entity\AbsenceType;
use App\Repository\AbsenceTypeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbsenceRequestType extends AbstractType
{
    public function __construct(private readonly AbsenceTypeRepository $typeRepo) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EntityType::class, [
                'label' => 'absence.request.type',
                'class' => AbsenceType::class,
                'choice_label' => 'name',
                'query_builder' => fn() => $this->typeRepo->createQueryBuilder('t')
                    ->andWhere('t.isActive = 1')
                    ->orderBy('t.name', 'ASC'),
                'placeholder' => 'general.please_choose',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'absence.request.start_date',
                'widget' => 'single_text',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'absence.request.end_date',
                'widget' => 'single_text',
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'absence.request.comment',
                'required' => false,
                'attr' => [
                    'rows' => 3
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AbsenceRequest::class,
        ]);
    }
}
