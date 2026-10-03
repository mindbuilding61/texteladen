<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SettingsType;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    #[Route('/einstellungen', name: 'settings_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SettingsRepository $repo, EntityManagerInterface $em): Response
    {
        $settings = $repo->getOrCreate();
        $form = $this->createForm(SettingsType::class, $settings);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Einstellungen gespeichert.');
            return $this->redirectToRoute('settings_edit');
        }
        return $this->render('settings/edit.html.twig', [
            'form' => $form,
            'settings' => $settings,
        ]);
    }
}
