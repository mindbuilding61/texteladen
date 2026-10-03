<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\OutgoingInvoice;
use App\Entity\OutgoingInvoiceLine;
use App\Form\OutgoingInvoiceType;
use App\Form\SendInvoiceType;
use App\Repository\OutgoingInvoiceRepository;
use App\Repository\SettingsRepository;
use App\Service\Create\XRechnungBuilder;
use App\Service\Create\ZugferdPdfBuilder;
use App\Service\Send\InvoiceMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ausgang')]
final class OutgoingInvoiceController extends AbstractController
{
    #[Route('', name: 'outgoing_list', methods: ['GET'])]
    public function list(OutgoingInvoiceRepository $repo): Response
    {
        return $this->render('outgoing/list.html.twig', [
            'invoices' => $repo->findRecent(),
        ]);
    }

    #[Route('/neu', name: 'outgoing_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SettingsRepository $settingsRepo): Response
    {
        $settings = $settingsRepo->getOrCreate();
        $invoice = new OutgoingInvoice();
        $invoice->setDueDate($invoice->getIssueDate()->modify('+'.$settings->getDefaultPaymentTermDays().' days'));
        $invoice->addLine((new OutgoingInvoiceLine())->setName('')->setQuantity('1.0000')->setUnitPrice('0.00'));
        return $this->handleForm($request, $em, $invoice, 'Entwurf gespeichert.');
    }

    #[Route('/{id}', name: 'outgoing_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(OutgoingInvoice $invoice): Response
    {
        return $this->render('outgoing/show.html.twig', ['invoice' => $invoice]);
    }

    #[Route('/{id}/bearbeiten', name: 'outgoing_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, OutgoingInvoice $invoice, EntityManagerInterface $em): Response
    {
        if ($invoice->getStatus() !== OutgoingInvoice::STATUS_DRAFT) {
            $this->addFlash('warning', 'Nur Entwürfe können bearbeitet werden.');
            return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
        }
        return $this->handleForm($request, $em, $invoice, 'Entwurf aktualisiert.');
    }

    #[Route('/{id}/festschreiben', name: 'outgoing_issue', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function issue(Request $request, OutgoingInvoice $invoice, EntityManagerInterface $em, SettingsRepository $settingsRepo): Response
    {
        if (!$this->isCsrfTokenValid('outgoing-issue-'.$invoice->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($invoice->getStatus() !== OutgoingInvoice::STATUS_DRAFT) {
            $this->addFlash('warning', 'Diese Rechnung ist bereits festgeschrieben.');
            return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
        }
        $invoice->setInvoiceNumber($settingsRepo->nextInvoiceNumber());
        $invoice->setStatus(OutgoingInvoice::STATUS_ISSUED);
        $invoice->recalculateTotals();
        $em->flush();
        $this->addFlash('success', 'Rechnung festgeschrieben: '.$invoice->getInvoiceNumber());
        return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/status/{status}', name: 'outgoing_set_status', requirements: ['id' => '\\d+', 'status' => 'paid|cancelled|sent|issued'], methods: ['POST'])]
    public function setStatus(Request $request, OutgoingInvoice $invoice, string $status, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('outgoing-status-'.$invoice->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($invoice->getStatus() === OutgoingInvoice::STATUS_DRAFT) {
            $this->addFlash('warning', 'Festschreiben zuerst.');
            return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
        }
        $invoice->setStatus($status);
        if ($status === OutgoingInvoice::STATUS_PAID) {
            $invoice->setPaidAt(new \DateTimeImmutable());
        }
        $em->flush();
        $this->addFlash('success', 'Status aktualisiert.');
        return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/export/{format}', name: 'outgoing_export', requirements: ['id' => '\\d+', 'format' => 'xml|zugferd|pdf'], methods: ['GET'])]
    public function export(
        OutgoingInvoice $invoice,
        string $format,
        SettingsRepository $settingsRepo,
        XRechnungBuilder $xmlBuilder,
        ZugferdPdfBuilder $pdfBuilder,
        \App\Service\Create\InvoicePdfRenderer $pdfRenderer,
    ): Response {
        $settings = $settingsRepo->getOrCreate();
        if ($invoice->getStatus() === OutgoingInvoice::STATUS_DRAFT) {
            throw $this->createNotFoundException('Entwürfe können nicht exportiert werden – zuerst festschreiben.');
        }
        $base = $invoice->getInvoiceNumber() ?: ('invoice-'.$invoice->getId());

        [$body, $mime, $filename] = match ($format) {
            'xml' => [$xmlBuilder->build($invoice, $settings), 'application/xml', $base.'.xml'],
            'pdf' => [$pdfRenderer->render($invoice, $settings), 'application/pdf', $base.'.pdf'],
            'zugferd' => [$pdfBuilder->build($invoice, $settings), 'application/pdf', $base.'-zugferd.pdf'],
        };

        $response = new Response($body);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        return $response;
    }

    #[Route('/{id}/senden', name: 'outgoing_send', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function send(
        Request $request,
        OutgoingInvoice $invoice,
        SettingsRepository $settingsRepo,
        InvoiceMailer $mailer,
        EntityManagerInterface $em,
    ): Response {
        if ($invoice->getStatus() === OutgoingInvoice::STATUS_DRAFT) {
            $this->addFlash('warning', 'Zuerst festschreiben, bevor sie verschickt wird.');
            return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
        }
        $settings = $settingsRepo->getOrCreate();

        $form = $this->createForm(SendInvoiceType::class, [
            'to' => $invoice->getCustomer()?->getEmail() ?? '',
            'subject' => sprintf('Rechnung %s von %s', $invoice->getInvoiceNumber(), $settings->getCompanyName()),
            'body' => $this->defaultMailBody($invoice, $settings),
            'attachments' => ['zugferd'],
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $mailer->send(
                    $invoice,
                    $settings,
                    (string) $data['to'],
                    (string) $data['subject'],
                    (string) $data['body'],
                    $data['cc'] ?? null,
                    $data['attachments'] ?? ['zugferd']
                );
                $invoice->setStatus(OutgoingInvoice::STATUS_SENT);
                $em->flush();
                $this->addFlash('success', 'Rechnung erfolgreich versendet.');
                return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Versand fehlgeschlagen: '.$e->getMessage());
            }
        }

        return $this->render('outgoing/send.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
        ]);
    }

    private function defaultMailBody(OutgoingInvoice $invoice, \App\Entity\Settings $settings): string
    {
        $greeting = $invoice->getCustomer()?->getContactName()
            ? 'Hallo '.$invoice->getCustomer()->getContactName().','
            : 'Guten Tag,';
        return <<<TXT
            $greeting

            anbei die Rechnung {$invoice->getInvoiceNumber()} über {$invoice->getTotalNet()} € (zahlbar bis {$invoice->getDueDate()->format('d.m.Y')}).

            Die Rechnung liegt als ZUGFeRD-PDF/A-3 mit eingebettetem XRechnung-XML bei und erfüllt die EN 16931.

            Freundliche Grüße
            {$settings->getContactName()}
            {$settings->getCompanyName()}
            TXT;
    }

    #[Route('/{id}/loeschen', name: 'outgoing_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, OutgoingInvoice $invoice, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('outgoing-delete-'.$invoice->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($invoice->getStatus() !== OutgoingInvoice::STATUS_DRAFT) {
            $this->addFlash('warning', 'Festgeschriebene Rechnungen dürfen nicht gelöscht werden (GoBD). Setze sie stattdessen auf "storniert".');
            return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
        }
        $em->remove($invoice);
        $em->flush();
        $this->addFlash('success', 'Entwurf gelöscht.');
        return $this->redirectToRoute('outgoing_list');
    }

    private function handleForm(Request $request, EntityManagerInterface $em, OutgoingInvoice $invoice, string $flash): Response
    {
        $form = $this->createForm(OutgoingInvoiceType::class, $invoice);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($invoice->getLines() as $line) {
                $line->setInvoice($invoice);
            }
            $invoice->recalculateTotals();
            $em->persist($invoice);
            $em->flush();
            $this->addFlash('success', $flash);
            return $this->redirectToRoute('outgoing_show', ['id' => $invoice->getId()]);
        }
        return $this->render('outgoing/edit.html.twig', [
            'form' => $form,
            'invoice' => $invoice,
        ]);
    }
}
