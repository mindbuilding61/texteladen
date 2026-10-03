<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/kunden')]
final class CustomerController extends AbstractController
{
    #[Route('', name: 'customer_list', methods: ['GET'])]
    public function list(CustomerRepository $repo): Response
    {
        return $this->render('customer/list.html.twig', [
            'customers' => $repo->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/neu', name: 'customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $customer = new Customer();
        return $this->handleForm($request, $em, $customer, 'Kunde angelegt.');
    }

    #[Route('/{id}', name: 'customer_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, Customer $customer): Response
    {
        return $this->handleForm($request, $em, $customer, 'Kunde aktualisiert.');
    }

    #[Route('/{id}/loeschen', name: 'customer_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, Customer $customer): Response
    {
        if (!$this->isCsrfTokenValid('customer-delete-'.$customer->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($customer);
        $em->flush();
        $this->addFlash('success', 'Kunde gelöscht.');
        return $this->redirectToRoute('customer_list');
    }

    private function handleForm(Request $request, EntityManagerInterface $em, Customer $customer, string $successMessage): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($customer);
            $em->flush();
            $this->addFlash('success', $successMessage);
            return $this->redirectToRoute('customer_list');
        }
        return $this->render('customer/edit.html.twig', [
            'form' => $form,
            'customer' => $customer,
        ]);
    }
}
