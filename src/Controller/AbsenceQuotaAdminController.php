<?php

namespace App\Controller;

use App\Entity\AbsenceQuota;
use App\Form\AbsenceQuotaType;
use App\Repository\AbsenceQuotaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/absence-quotas')]
final class AbsenceQuotaAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: '_admin_absence_quota_index', methods: ['GET'])]
    public function index(AbsenceQuotaRepository $repo): Response
    {
        return $this->render('admin/absence_quota/index.html.twig', [
            'quotas' => $repo->findBy([], ['year' => 'DESC']),
        ]);
    }

    #[Route('/new', name: '_admin_absence_quota_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->handleForm(new AbsenceQuota(), $request, 'admin_quota.create');
    }

    #[Route('/{id<\d+>}/edit', name: '_admin_absence_quota_edit', methods: ['GET', 'POST'])]
    public function edit(AbsenceQuota $quota, Request $request): Response
    {
        return $this->handleForm($quota, $request, 'admin_quota.edit');
    }

    private function handleForm(AbsenceQuota $quota, Request $request, string $title): Response
    {
        $form = $this->createForm(AbsenceQuotaType::class, $quota);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($quota);
            $this->em->flush();
            $this->addFlash('success', 'flash.absence_quota_saved');
            return $this->redirectToRoute('_admin_absence_quota_index');
        }

        return $this->render('admin/absence_quota/form.html.twig', [
            'form'  => $form,
            'title' => $title,
        ]);
    }
}
