<?php

declare(strict_types=1);

namespace App\Service\Create;

use App\Entity\OutgoingInvoice;
use App\Entity\Settings;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class InvoicePdfRenderer
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(OutgoingInvoice $invoice, Settings $settings): string
    {
        $invoice->recalculateTotals();
        $html = $this->twig->render('outgoing/pdf.html.twig', [
            'invoice' => $invoice,
            'settings' => $settings,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
