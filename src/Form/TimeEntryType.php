<?php

namespace App\Form;

use App\Entity\TimeEntry;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TimeEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DateTimeImmutable|null $dayStart */
        $dayStart = $options['day_start'];
        if (!$dayStart instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('TimeEntryType requires option "day_start" as DateTimeImmutable.');
        }

        // Initialwerte aus Entity (DateTime -> Time)
        $startedAt = $builder->getData()?->getStartedAt();
        $stoppedAt = $builder->getData()?->getStoppedAt();

        $builder
            ->add('startTime', DateTimeType::class, [
                'label' => 'time_entry.start_time',
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'with_seconds' => false,
                'constraints' => [new Assert\NotNull()],
                'data' => $startedAt ?: $dayStart->setTime(9, 0),
                'model_timezone' => 'UTC',
                'view_timezone'  => 'Europe/Berlin'
            ])
            ->add('endTime', DateTimeType::class, [
                'label' => 'time_entry.end_time',
                'mapped' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'with_seconds' => false,
                'required' => false,
                'data' => $stoppedAt,
                'model_timezone' => 'UTC',
                'view_timezone'  => 'Europe/Berlin'
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($dayStart) {
            /**
             * @var TimeEntry $entry
             * @var DateTimeImmutable $startTime
             * @var DateTimeImmutable|null $endTime
             */
            $entry = $event->getData();
            $form = $event->getForm();

            $startTime = $form->get('startTime')->getData();
            $endTime = $form->get('endTime')->getData();

            $start = $dayStart->setTime((int)$startTime->format('H'), (int)$startTime->format('i'));
            $entry->setStartedAt($start);

            if ($endTime instanceof DateTimeImmutable) {
                $end = $dayStart->setTime((int)$endTime->format('H'), (int)$endTime->format('i'));

                if ($end < $start) {
                    $end = $end->modify('+1 day');
                }

                $entry->setStoppedAt($end);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeEntry::class,
            'day_start' => null,
        ]);
    }
}
