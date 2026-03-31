<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Form\HolidayType;
use App\Repository\HolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/holidays', name: '_admin_holiday_')]
final class HolidayController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(HolidayRepository $repo): Response
    {
        return $this->render('admin/holiday/index.html.twig', [
            'holidays' => $repo->findBy([], ['date' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $holiday = new Holiday();
        $form = $this->createForm(HolidayType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($holiday);
            $em->flush();

            $this->addFlash('success', 'Feiertag angelegt.');
            return $this->redirectToRoute('_admin_holiday_index');
        }

        return $this->render('admin/holiday/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Holiday $holiday, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(HolidayType::class, $holiday);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Feiertag gespeichert.');
            return $this->redirectToRoute('_admin_holiday_index');
        }

        return $this->render('admin/holiday/edit.html.twig', [
            'holiday' => $holiday,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Holiday $holiday, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_holiday_'.$holiday->getId(), (string)$request->request->get('_token'))) {
            $em->remove($holiday);
            $em->flush();
            $this->addFlash('success', 'Feiertag gelöscht.');
        }

        return $this->redirectToRoute('_admin_holiday_index');
    }
}
