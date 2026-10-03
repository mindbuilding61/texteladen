<?php

declare(strict_types=1);

namespace App\Service\Receive;

use App\Entity\IncomingInvoice;
use App\Repository\IncomingInvoiceRepository;
use App\Service\InvoiceStorage;
use Doctrine\ORM\EntityManagerInterface;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class IncomingInvoiceImporter
{
    public function __construct(
        private readonly InvoiceStorage $storage,
        private readonly InvoiceFormatDetector $detector,
        private readonly CiiInvoiceParser $ciiParser,
        private readonly UblInvoiceParser $ublParser,
        private readonly IncomingInvoiceRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function importBytes(string $content, string $filename, string $source = IncomingInvoice::SOURCE_UPLOAD): ImportResult
    {
        $hash = hash('sha256', $content);
        $existing = $this->repository->findBySha256($hash);
        if ($existing !== null) {
            return new ImportResult($existing, true, []);
        }

        $invoice = new IncomingInvoice();
        $invoice->setSource($source);
        $invoice->setOriginalFilename($filename);
        $invoice->setSha256($hash);
        $invoice->setSizeBytes(strlen($content));

        $storageKey = $this->storage->store($content, $filename);
        $invoice->setStorageKey($storageKey);

        $type = $this->detector->detectType($content, $filename);
        $errors = [];
        $xml = null;

        try {
            if ($type === InvoiceFormatDetector::TYPE_PDF) {
                $invoice->setPdfStorageKey($storageKey);
                try {
                    $xml = ZugferdDocumentPdfReader::getXmlFromContent($content);
                } catch (\Throwable $e) {
                    $errors[] = 'PDF enthält kein eingebettetes XRechnung/ZUGFeRD-XML: '.$e->getMessage();
                }
                if ($xml !== null && $xml !== '') {
                    $xmlKey = $this->storage->store($xml, (pathinfo($filename, PATHINFO_FILENAME) ?: 'invoice').'.xml');
                    $invoice->setXmlStorageKey($xmlKey);
                    $invoice->setFormat($this->detector->classifyFormat($xml, InvoiceFormatDetector::TYPE_XML_CII));
                    $invoice->setProfile($this->detector->detectProfile($xml));
                    $this->parseAndApply($invoice, $xml, InvoiceFormatDetector::TYPE_XML_CII, $errors);
                } else {
                    $invoice->setFormat(IncomingInvoice::FORMAT_UNKNOWN);
                }
            } elseif ($type === InvoiceFormatDetector::TYPE_XML_UBL || $type === InvoiceFormatDetector::TYPE_XML_CII) {
                $xml = $content;
                $invoice->setXmlStorageKey($storageKey);
                $invoice->setFormat($this->detector->classifyFormat($xml, $type));
                $invoice->setProfile($this->detector->detectProfile($xml));
                $this->parseAndApply($invoice, $xml, $type, $errors);
            } else {
                $invoice->setFormat(IncomingInvoice::FORMAT_UNKNOWN);
                $errors[] = 'Dateityp konnte nicht als XRechnung/ZUGFeRD erkannt werden.';
            }
        } catch (\Throwable $e) {
            $errors[] = 'Fehler beim Verarbeiten: '.$e->getMessage();
            $this->logger->warning('Failed to import invoice', ['exception' => $e, 'filename' => $filename]);
        }

        $invoice->setValidationErrors($errors === [] ? null : $errors);
        $invoice->setValidationPassed($errors === [] && $invoice->getFormat() !== IncomingInvoice::FORMAT_UNKNOWN);

        $this->em->persist($invoice);
        $this->em->flush();

        return new ImportResult($invoice, false, $errors);
    }

    private function parseAndApply(IncomingInvoice $invoice, string $xml, string $type, array &$errors): void
    {
        try {
            $parsed = $type === InvoiceFormatDetector::TYPE_XML_UBL
                ? $this->ublParser->parse($xml)
                : $this->ciiParser->parse($xml);
        } catch (\Throwable $e) {
            $errors[] = 'Rechnungsdaten konnten nicht gelesen werden: '.$e->getMessage();
            return;
        }

        $invoice->setInvoiceNumber($parsed->invoiceNumber);
        $invoice->setIssueDate($parsed->issueDate);
        $invoice->setDueDate($parsed->dueDate);
        $invoice->setCurrency($parsed->currency);
        $invoice->setSellerName($parsed->sellerName);
        $invoice->setSellerVatId($parsed->sellerVatId);
        $invoice->setSellerIban($parsed->sellerIban);
        $invoice->setBuyerName($parsed->buyerName);
        $invoice->setTotalNet($parsed->totalNet);
        $invoice->setTotalTax($parsed->totalTax);
        $invoice->setTotalGross($parsed->totalGross);
        $invoice->setAmountDue($parsed->amountDue ?? $parsed->totalGross);
        $invoice->setParsedData($parsed->toArray());
    }
}
