<?php

declare(strict_types=1);

namespace App\Service\Send;

use App\Entity\OutgoingInvoice;
use App\Entity\Settings;
use App\Service\Create\InvoicePdfRenderer;
use App\Service\Create\XRechnungBuilder;
use App\Service\Create\ZugferdPdfBuilder;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class InvoiceMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly XRechnungBuilder $xmlBuilder,
        private readonly ZugferdPdfBuilder $pdfBuilder,
        private readonly InvoicePdfRenderer $renderer,
    ) {
    }

    /**
     * @param non-empty-string[] $attachments  'zugferd', 'xml' or 'pdf'
     */
    public function send(
        OutgoingInvoice $invoice,
        Settings $settings,
        string $to,
        string $subject,
        string $body,
        ?string $cc = null,
        array $attachments = ['zugferd'],
    ): void {
        if ($settings->getEmail() === null || $settings->getEmail() === '') {
            throw new \RuntimeException('Keine Absenderadresse in den Einstellungen hinterlegt.');
        }

        $email = (new Email())
            ->from(new Address($settings->getEmail(), $settings->getCompanyName()))
            ->to($to)
            ->subject($subject)
            ->text($body);

        if ($cc !== null && trim($cc) !== '') {
            foreach (preg_split('/[,;]/', $cc) ?: [] as $addr) {
                $addr = trim($addr);
                if ($addr !== '') {
                    $email->addCc($addr);
                }
            }
        }

        $base = $invoice->getInvoiceNumber() ?: ('invoice-'.$invoice->getId());

        if (in_array('zugferd', $attachments, true)) {
            $email->attach($this->pdfBuilder->build($invoice, $settings), $base.'.pdf', 'application/pdf');
        }
        if (in_array('pdf', $attachments, true)) {
            $email->attach($this->renderer->render($invoice, $settings), $base.'-sichtkopie.pdf', 'application/pdf');
        }
        if (in_array('xml', $attachments, true)) {
            $email->attach($this->xmlBuilder->build($invoice, $settings), $base.'.xml', 'application/xml');
        }

        $this->mailer->send($email);
    }
}
