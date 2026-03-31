<?php

namespace App\Controller;

use App\Entity\AbsenceType;
use App\Form\AbsenceTypeType;
use App\Repository\AbsenceTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/absence-types')]
final class AbsenceTypeAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: '_admin_absence_type_index', methods: ['GET'])]
    public function index(AbsenceTypeRepository $repo): Response
    {
        return $this->render('admin/absence_type/index.html.twig', [
            'types' => $repo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: '_admin_absence_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $type = new AbsenceType();
        $type->setIsActive(true);

        return $this->handleForm($type, $request, 'Abwesenheitstyp anlegen');
    }

    #[Route('/{id<\d+>}/edit', name: '_admin_absence_type_edit', methods: ['GET', 'POST'])]
    public function edit(AbsenceType $type, Request $request): Response
    {
        return $this->handleForm($type, $request, 'Abwesenheitstyp bearbeiten');
    }

    private function handleForm(AbsenceType $type, Request $request, string $title): Response
    {
        $form = $this->createForm(AbsenceTypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($type);
            $this->em->flush();
            $this->addFlash('success', 'Typ gespeichert.');
            return $this->redirectToRoute('_admin_absence_type_index');
        }

        return $this->render('admin/absence_type/form.html.twig', [
            'form'  => $form,
            'title' => $title,
        ]);
    }
}
