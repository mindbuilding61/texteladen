<?php

declare(strict_types=1);

namespace App\Tests\Service\Create;

use App\Entity\Customer;
use App\Entity\OutgoingInvoice;
use App\Entity\OutgoingInvoiceLine;
use App\Entity\Settings;
use App\Service\Create\XRechnungBuilder;
use App\Service\Receive\CiiInvoiceParser;
use App\Service\Receive\InvoiceFormatDetector;
use PHPUnit\Framework\TestCase;

final class XRechnungBuilderTest extends TestCase
{
    public function testKleinunternehmerRoundtrip(): void
    {
        $settings = (new Settings())
            ->setCompanyName('Mindbuilding61 (Kleinunternehmer)')
            ->setStreet('Hauptstraße 1')
            ->setPostalCode('10115')
            ->setCity('Berlin')
            ->setCountry('DE')
            ->setEmail('kontakt@mindbuilding61.example')
            ->setPhone('+49 30 1234567')
            ->setTaxNumber('13/123/45678')
            ->setIban('DE12500105170648489890')
            ->setBic('INGDDEFFXXX')
            ->setBankName('ING-DiBa')
            ->setKleinunternehmer(true)
            ->setKleinunternehmerNote('Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.');

        $customer = (new Customer())
            ->setName('Beispielkunde GmbH')
            ->setStreet('Kundenweg 7')
            ->setPostalCode('80331')
            ->setCity('München')
            ->setCountry('DE')
            ->setVatId('DE123456789')
            ->setBuyerReference('BESTELL-2026-00042');

        $invoice = new OutgoingInvoice();
        $invoice->setInvoiceNumber('RE-2026-0001');
        $invoice->setCustomer($customer);
        $invoice->setIssueDate(new \DateTimeImmutable('2026-10-03'));
        $invoice->setDueDate(new \DateTimeImmutable('2026-10-17'));

        $line1 = (new OutgoingInvoiceLine())
            ->setName('Projektarbeit E-Rechnung')
            ->setDescription('Konzeption und Umsetzung')
            ->setQuantity('10.0000')
            ->setUnit('HUR')
            ->setUnitPrice('75.0000');
        $invoice->addLine($line1);

        $line2 = (new OutgoingInvoiceLine())
            ->setName('Dokumentation')
            ->setQuantity('1.0000')
            ->setUnit('LS')
            ->setUnitPrice('150.00');
        $invoice->addLine($line2);

        $xml = (new XRechnungBuilder())->build($invoice, $settings);

        self::assertStringContainsString('CrossIndustryInvoice', $xml);
        self::assertStringContainsString('xrechnung', strtolower($xml));
        self::assertStringContainsString('§ 19 UStG', $xml);
        self::assertStringContainsString('VATEX-EU-O', $xml);
        self::assertStringContainsString('Mindbuilding61', $xml);
        self::assertStringContainsString('BESTELL-2026-00042', $xml);
        self::assertStringContainsString('DE12500105170648489890', $xml);

        $detector = new InvoiceFormatDetector();
        self::assertSame(InvoiceFormatDetector::TYPE_XML_CII, $detector->detectType($xml, 'rechnung.xml'));

        $parsed = (new CiiInvoiceParser())->parse($xml);
        self::assertSame('RE-2026-0001', $parsed->invoiceNumber);
        self::assertSame('Mindbuilding61 (Kleinunternehmer)', $parsed->sellerName);
        self::assertSame('Beispielkunde GmbH', $parsed->buyerName);
        self::assertSame('EUR', $parsed->currency);
        self::assertSame('900.00', $parsed->totalGross);
        self::assertSame('900.00', $parsed->totalNet);
        self::assertSame('0.00', $parsed->totalTax);
        self::assertCount(2, $parsed->lines);
        self::assertSame('Projektarbeit E-Rechnung', $parsed->lines[0]['name']);
        self::assertSame('750.00', $parsed->lines[0]['lineNet']);
    }
}
