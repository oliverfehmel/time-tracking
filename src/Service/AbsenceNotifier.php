<?php

namespace App\Service;

use App\Entity\AbsenceRequest;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AbsenceNotifier
{
    public function __construct(
        private MailerInterface       $mailer,
        private LoggerInterface       $logger,
        private AbsenceDayCalculator  $dayCalc,
        private UrlGeneratorInterface $urlGenerator,
        private string                $mailFrom,
    ) {}

    public function sendDecision(AbsenceRequest $absence, bool $approved): bool
    {
        $recipient = $absence->getRequestedBy();

        if (!$recipient->getEmail()) {
            $this->logger->warning('Absence decision mail skipped: recipient has no email.', [
                'absenceId' => $absence->getId(),
                'userId'    => $recipient->getId(),
            ]);
            return false;
        }

        $start = $absence->getStartDate();
        $end   = $absence->getEndDate();

        $email = new TemplatedEmail()
            ->from(new Address($this->mailFrom, 'TimeTracker'))
            ->to(new Address($recipient->getEmail(), $recipient->getName() ?: $recipient->getEmail()))
            ->subject($this->buildSubject($approved, $start, $end))
            ->htmlTemplate($approved
                ? 'emails/absence_request_approved.html.twig'
                : 'emails/absence_request_rejected.html.twig'
            )
            ->context([
                'absence'     => $absence,
                'user'        => $recipient,
                'days'        => $this->dayCalc->countChargeableDays($start, $end),
                'overviewUrl' => $this->urlGenerator->generate(
                    '_absence_index',
                    ['year' => (int) $start->format('Y')],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send absence decision mail.', [
                'error'      => $e->getMessage(),
                'absenceId'  => $absence->getId(),
                'approved'   => $approved,
                'recipient'  => $recipient->getEmail(),
            ]);
            return false;
        }
    }

    private function buildSubject(bool $approved, DateTimeInterface $start, DateTimeInterface $end): string
    {
        $range = sprintf('%s – %s', $start->format('d.m.Y'), $end->format('d.m.Y'));

        return $approved
            ? "Dein Abwesenheitsantrag wurde genehmigt ($range)"
            : "Dein Abwesenheitsantrag wurde abgelehnt ($range)";
    }

    public function sendCreatedNotification(AbsenceRequest $absence, array $adminEmails, int $year): bool
    {
        if (empty($adminEmails)) {
            $this->logger->warning('No admin recipients found for absence notification.', [
                'absenceId' => $absence->getId(),
            ]);
            return false;
        }

        $user  = $absence->getRequestedBy();
        $start = $absence->getStartDate();
        $end   = $absence->getEndDate();

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'TimeTracker'))
            ->to(...array_map(fn(string $addr) => new Address($addr), $adminEmails))
            ->subject(sprintf(
                'Neue Abwesenheit beantragt: %s (%s – %s)',
                $user->getName() ?: $user->getEmail(),
                $start->format('d.m.Y'),
                $end->format('d.m.Y'),
            ))
            ->htmlTemplate('emails/absence_request_created.html.twig')
            ->context([
                'absence'     => $absence,
                'user'        => $user,
                'days'        => $this->dayCalc->countChargeableDays($start, $end),
                'year'        => $year,
                'overviewUrl' => $this->urlGenerator->generate(
                    '_admin_absence_approvals',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]);

        try {
            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send admin absence notification mail.', [
                'error'     => $e->getMessage(),
                'absenceId' => $absence->getId(),
            ]);
            return false;
        }
    }
}
