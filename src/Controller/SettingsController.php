<?php

namespace App\Controller;

use App\Form\SettingsType;
use App\Repository\SettingsRepository;
use App\Service\BrandingManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/settings', name: '_settings')]
final class SettingsController extends AbstractController
{
    #[Route('', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        SettingsRepository $settingsRepository,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        BrandingManager $brandingManager,
    ): Response {

        $settings = $settingsRepository->getOrCreate();

        $form = $this->createForm(SettingsType::class, $settings, [
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();

            if ($logoFile !== null) {
                $result = $brandingManager->handleLogoUpload(
                    $logoFile,
                    $settings->getLogoFilename()
                );

                $settings->setLogoFilename($result['logoFilename']);
                $settings->setFaviconVersion($result['faviconVersion']);
            }

            $em->flush();

            $this->addFlash('success', $translator->trans('settings.successfully_saved'));

            return $this->redirectToRoute('_settings_edit');
        }

        return $this->render('admin/settings/edit.html.twig', [
            'settings' => $settings,
            'form' => $form->createView(),
        ]);
    }
}
