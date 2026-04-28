<?php

namespace App\Controller;

use App\Entity\WorkLocationType;
use App\Form\WorkLocationTypeType;
use App\Repository\WorkLocationTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/work-location-types')]
final class WorkLocationTypeAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: '_admin_work_location_type_index', methods: ['GET'])]
    public function index(WorkLocationTypeRepository $repo): Response
    {
        return $this->render('admin/work_location_type/index.html.twig', [
            'types' => $repo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: '_admin_work_location_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $type = new WorkLocationType();
        $type->setIsActive(true);

        return $this->handleForm($type, $request, 'work_location_type.create');
    }

    #[Route('/{id<\d+>}/edit', name: '_admin_work_location_type_edit', methods: ['GET', 'POST'])]
    public function edit(WorkLocationType $type, Request $request): Response
    {
        return $this->handleForm($type, $request, 'work_location_type.edit');
    }

    private function handleForm(WorkLocationType $type, Request $request, string $title): Response
    {
        $form = $this->createForm(WorkLocationTypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure only one type is marked as default
            if ($type->isDefault()) {
                foreach ($this->em->getRepository(WorkLocationType::class)->findAll() as $other) {
                    if ($other->getId() !== $type->getId() && $other->isDefault()) {
                        $other->setIsDefault(false);
                    }
                }
            }

            $this->em->persist($type);
            $this->em->flush();
            $this->addFlash('success', 'flash.work_location_type_saved');
            return $this->redirectToRoute('_admin_work_location_type_index');
        }

        return $this->render('admin/work_location_type/form.html.twig', [
            'form'  => $form,
            'title' => $title,
        ]);
    }
}
