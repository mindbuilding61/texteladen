<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\IncomingInvoice;
use App\Repository\IncomingInvoiceRepository;
use App\Service\InvoiceStorage;
use App\Service\Receive\IncomingInvoiceImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/eingang')]
final class IncomingInvoiceController extends AbstractController
{
    #[Route('', name: 'incoming_list', methods: ['GET'])]
    public function list(IncomingInvoiceRepository $repo, Request $request): Response
    {
        $status = $request->query->get('status');
        $criteria = $status ? ['status' => $status] : [];
        $invoices = $repo->findBy($criteria, ['receivedAt' => 'DESC'], 500);
        return $this->render('incoming/list.html.twig', [
            'invoices' => $invoices,
            'status' => $status,
        ]);
    }

    #[Route('/upload', name: 'incoming_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, IncomingInvoiceImporter $importer): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('incoming-upload', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            /** @var UploadedFile[] $files */
            $files = $request->files->all('files');
            if ($files === []) {
                $single = $request->files->get('file');
                $files = $single ? [$single] : [];
            }
            if ($files === []) {
                $this->addFlash('warning', 'Bitte wähle eine XML- oder PDF-Datei aus.');
                return $this->redirectToRoute('incoming_upload');
            }

            $imported = $skipped = $failed = 0;
            foreach ($files as $file) {
                if (!$file instanceof UploadedFile || !$file->isValid()) {
                    $failed++;
                    continue;
                }
                $content = (string) file_get_contents($file->getPathname());
                $result = $importer->importBytes($content, $file->getClientOriginalName() ?: 'invoice');
                if ($result->duplicate) {
                    $skipped++;
                } else {
                    $imported++;
                }
            }

            if ($imported) {
                $this->addFlash('success', sprintf('%d Rechnung(en) importiert.', $imported));
            }
            if ($skipped) {
                $this->addFlash('info', sprintf('%d Duplikat(e) übersprungen (gleicher SHA-256).', $skipped));
            }
            if ($failed) {
                $this->addFlash('error', sprintf('%d Datei(en) konnten nicht verarbeitet werden.', $failed));
            }

            return $this->redirectToRoute('incoming_list');
        }

        return $this->render('incoming/upload.html.twig');
    }

    #[Route('/{id}', name: 'incoming_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(IncomingInvoice $invoice, InvoiceStorage $storage): Response
    {
        $xml = null;
        if ($invoice->getXmlStorageKey() && $storage->exists($invoice->getXmlStorageKey())) {
            $xml = $storage->get($invoice->getXmlStorageKey());
            if (strlen($xml) > 200_000) {
                $xml = substr($xml, 0, 200_000)."\n…";
            }
        }

        return $this->render('incoming/show.html.twig', [
            'invoice' => $invoice,
            'xml' => $xml,
        ]);
    }

    #[Route('/{id}/download/{kind}', name: 'incoming_download', requirements: ['id' => '\\d+', 'kind' => 'original|xml|pdf'], methods: ['GET'])]
    public function download(IncomingInvoice $invoice, string $kind, InvoiceStorage $storage): Response
    {
        $key = match ($kind) {
            'xml' => $invoice->getXmlStorageKey(),
            'pdf' => $invoice->getPdfStorageKey(),
            default => $invoice->getStorageKey(),
        };
        if ($key === null || !$storage->exists($key)) {
            throw $this->createNotFoundException('Datei nicht gefunden.');
        }
        $path = $storage->absolutePath($key);
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $kind === 'original' ? $invoice->getOriginalFilename() : basename($key)
        );
        return $response;
    }

    #[Route('/{id}/status/{status}', name: 'incoming_set_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function setStatus(Request $request, IncomingInvoice $invoice, string $status, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('incoming-status-'.$invoice->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $allowed = [IncomingInvoice::STATUS_NEW, IncomingInvoice::STATUS_REVIEWED, IncomingInvoice::STATUS_PAID, IncomingInvoice::STATUS_DISPUTED];
        if (!in_array($status, $allowed, true)) {
            throw $this->createNotFoundException();
        }
        $invoice->setStatus($status);
        if ($status === IncomingInvoice::STATUS_PAID) {
            $invoice->setPaidAt(new \DateTimeImmutable());
        }
        $em->flush();
        $this->addFlash('success', 'Status aktualisiert.');
        return $this->redirectToRoute('incoming_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/loeschen', name: 'incoming_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Request $request, IncomingInvoice $invoice, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('incoming-delete-'.$invoice->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($invoice);
        $em->flush();
        $this->addFlash('success', 'Eingangsrechnung gelöscht (die Originaldatei im Archiv bleibt erhalten).');
        return $this->redirectToRoute('incoming_list');
    }
}
