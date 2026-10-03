<?php

declare(strict_types=1);

namespace App\Service\Create;

use App\Entity\OutgoingInvoice;
use App\Entity\Settings;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;

/**
 * Produces a hybrid ZUGFeRD / Factur-X PDF/A-3 by embedding the CII XML into
 * a human-readable PDF rendered by Dompdf.
 */
final class ZugferdPdfBuilder
{
    public function __construct(
        private readonly XRechnungBuilder $xmlBuilder,
        private readonly InvoicePdfRenderer $pdfRenderer,
    ) {
    }

    public function build(OutgoingInvoice $invoice, Settings $settings): string
    {
        $pdfBytes = $this->pdfRenderer->render($invoice, $settings);
        $documentBuilder = $this->xmlBuilder->buildBuilder($invoice, $settings);

        $pdfBuilder = ZugferdDocumentPdfBuilder::fromPdfString($documentBuilder, $pdfBytes);
        $pdfBuilder->setAdditionalCreatorTool('E-Rechnung Kleinunternehmer-App');
        $pdfBuilder->generateDocument();

        return $pdfBuilder->downloadString();
    }
}
