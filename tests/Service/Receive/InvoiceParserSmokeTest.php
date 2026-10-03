<?php

declare(strict_types=1);

namespace App\Tests\Service\Receive;

use App\Service\Receive\CiiInvoiceParser;
use App\Service\Receive\InvoiceFormatDetector;
use App\Service\Receive\UblInvoiceParser;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use PHPUnit\Framework\TestCase;

final class InvoiceParserSmokeTest extends TestCase
{
    public function testCiiXrechnungRoundTrip(): void
    {
        $xml = $this->buildSampleCiiXml();

        $detector = new InvoiceFormatDetector();
        self::assertSame(InvoiceFormatDetector::TYPE_XML_CII, $detector->detectType($xml, 'rechnung.xml'));

        $parser = new CiiInvoiceParser();
        $parsed = $parser->parse($xml);

        self::assertSame('RE-2026-0007', $parsed->invoiceNumber);
        self::assertNotNull($parsed->issueDate);
        self::assertSame('EUR', $parsed->currency);
        self::assertSame('Mustermann GmbH', $parsed->sellerName);
        self::assertSame('Mindbuilding61', $parsed->buyerName);
        self::assertSame('119.00', $parsed->totalGross);
        self::assertSame('100.00', $parsed->totalNet);
        self::assertSame('19.00', $parsed->totalTax);
        self::assertNotEmpty($parsed->lines);
        self::assertSame('Beratungsstunde', $parsed->lines[0]['name']);
        self::assertSame('DE12500105170648489890', $parsed->sellerIban);
    }

    public function testUblXrechnungSample(): void
    {
        $xml = $this->buildSampleUblXml();

        $detector = new InvoiceFormatDetector();
        self::assertSame(InvoiceFormatDetector::TYPE_XML_UBL, $detector->detectType($xml, 'rechnung.xml'));

        $parser = new UblInvoiceParser();
        $parsed = $parser->parse($xml);

        self::assertSame('47110815', $parsed->invoiceNumber);
        self::assertSame('EUR', $parsed->currency);
        self::assertSame('Soft-GmbH', $parsed->sellerName);
        self::assertSame('991-01234-56', $parsed->buyerReference);
        self::assertSame('991-01234-56', $parsed->leitwegId);
        self::assertCount(2, $parsed->lines);
        self::assertSame('250.00', $parsed->lines[0]['unitPrice']);
        self::assertSame('500.00', $parsed->lines[0]['lineNet']);
        self::assertSame('DE02500105170648489890', $parsed->sellerIban);
        self::assertSame('500.00', $parsed->totalNet);
        self::assertSame('595.00', $parsed->totalGross);
    }

    private function buildSampleCiiXml(): string
    {
        $b = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3);
        $b->setDocumentInformation('RE-2026-0007', '380', new \DateTime('2026-03-14'), 'EUR');
        $b->setDocumentBuyerReference('991-01234-56');
        $b->setDocumentSeller('Mustermann GmbH');
        $b->addDocumentSellerVATRegistrationNumber('DE123456789');
        $b->setDocumentSellerAddress('Hauptstraße 1', null, null, '10115', 'Berlin', 'DE');
        $b->setDocumentSellerContact('Max Mustermann', null, '+4930123456', null, 'info@mustermann.example');
        $b->setDocumentBuyer('Mindbuilding61');
        $b->setDocumentBuyerAddress('Kundenweg 2', null, null, '12345', 'Hamburg', 'DE');
        $b->addDocumentPaymentMeanToCreditTransfer('DE12500105170648489890', 'Mustermann GmbH', null, 'INGDDEFFXXX');
        $b->addDocumentPaymentTerm('Zahlbar innerhalb von 14 Tagen ohne Abzug.', new \DateTime('2026-03-28'));
        $b->addNewPosition('1');
        $b->setDocumentPositionProductDetails('Beratungsstunde', 'Beratung E-Rechnung');
        $b->setDocumentPositionQuantity(2.0, 'HUR');
        $b->setDocumentPositionNetPrice(50.0);
        $b->addDocumentPositionTax('S', 'VAT', 19.0);
        $b->setDocumentPositionLineSummation(100.0);
        $b->addDocumentTax('S', 'VAT', 100.0, 19.0, 19.0);
        $b->setDocumentSummation(119.0, 119.0, 100.0, 0.0, 0.0, 100.0, 19.0);
        return $b->getContent();
    }

    private function buildSampleUblXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_3.0</cbc:CustomizationID>
    <cbc:ID>47110815</cbc:ID>
    <cbc:IssueDate>2026-04-01</cbc:IssueDate>
    <cbc:DueDate>2026-04-15</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>991-01234-56</cbc:BuyerReference>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PostalAddress>
                <cbc:StreetName>Softweg 1</cbc:StreetName>
                <cbc:CityName>Köln</cbc:CityName>
                <cbc:PostalZone>50667</cbc:PostalZone>
                <cac:Country><cbc:IdentificationCode>DE</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>DE987654321</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>Soft-GmbH</cbc:RegistrationName>
            </cac:PartyLegalEntity>
            <cac:Contact>
                <cbc:ElectronicMail>support@soft-gmbh.example</cbc:ElectronicMail>
            </cac:Contact>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PostalAddress>
                <cbc:StreetName>Dienststraße 10</cbc:StreetName>
                <cbc:CityName>Berlin</cbc:CityName>
                <cbc:PostalZone>10115</cbc:PostalZone>
                <cac:Country><cbc:IdentificationCode>DE</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>Stadt Berlin</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode>58</cbc:PaymentMeansCode>
        <cbc:PaymentID>RECHNUNG-47110815</cbc:PaymentID>
        <cac:PayeeFinancialAccount>
            <cbc:ID>DE02500105170648489890</cbc:ID>
            <cac:FinancialInstitutionBranch><cbc:ID>INGDDEFFXXX</cbc:ID></cac:FinancialInstitutionBranch>
        </cac:PayeeFinancialAccount>
    </cac:PaymentMeans>
    <cac:PaymentTerms><cbc:Note>Zahlbar innerhalb von 14 Tagen ohne Abzug.</cbc:Note></cac:PaymentTerms>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">95.00</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">500.00</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">95.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>19</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">500.00</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">500.00</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">595.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EUR">595.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="HUR">2</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">500.00</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>Software-Lizenz</cbc:Name>
            <cbc:Description>Jahreslizenz für Softwareprodukt</cbc:Description>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="EUR">250.00</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
    <cac:InvoiceLine>
        <cbc:ID>2</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">0</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">0.00</cbc:LineExtensionAmount>
        <cac:Item><cbc:Name>Versand</cbc:Name></cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="EUR">0.00</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
</Invoice>
XML;
    }
}
