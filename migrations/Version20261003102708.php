<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003102708 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, sku VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, unit_price NUMERIC(12, 4) NOT NULL, unit VARCHAR(10) NOT NULL --UN/ECE Rec. 20 unit code
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23A0E66F9038C4 ON article (sku)');
        $this->addSql('CREATE TABLE customer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, contact_name VARCHAR(255) DEFAULT NULL, street VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(255) NOT NULL, country VARCHAR(2) NOT NULL, email VARCHAR(255) DEFAULT NULL, vat_id VARCHAR(50) DEFAULT NULL, leitweg_id VARCHAR(20) DEFAULT NULL --Leitweg-ID für B2G
        , buyer_reference VARCHAR(100) DEFAULT NULL, notes CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE incoming_invoice (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, received_at DATETIME NOT NULL, source VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, format VARCHAR(30) NOT NULL, profile VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, storage_key VARCHAR(128) NOT NULL, sha256 VARCHAR(64) DEFAULT NULL, size_bytes INTEGER NOT NULL, xml_storage_key VARCHAR(128) DEFAULT NULL, pdf_storage_key VARCHAR(128) DEFAULT NULL, invoice_number VARCHAR(100) DEFAULT NULL, issue_date DATE DEFAULT NULL, due_date DATE DEFAULT NULL, seller_name VARCHAR(255) DEFAULT NULL, seller_vat_id VARCHAR(50) DEFAULT NULL, seller_iban VARCHAR(50) DEFAULT NULL, buyer_name VARCHAR(255) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, total_net NUMERIC(14, 2) DEFAULT NULL, total_tax NUMERIC(14, 2) DEFAULT NULL, total_gross NUMERIC(14, 2) DEFAULT NULL, amount_due NUMERIC(14, 2) DEFAULT NULL, paid_at DATE DEFAULT NULL, validation_errors CLOB DEFAULT NULL, validation_passed BOOLEAN NOT NULL, notes CLOB DEFAULT NULL, parsed_data CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE outgoing_invoice (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, invoice_number VARCHAR(50) DEFAULT NULL, issue_date DATE NOT NULL, due_date DATE NOT NULL, service_period_start DATE DEFAULT NULL, service_period_end DATE DEFAULT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, intro_text CLOB DEFAULT NULL, outro_text CLOB DEFAULT NULL, buyer_reference VARCHAR(100) DEFAULT NULL, leitweg_id VARCHAR(20) DEFAULT NULL, total_net NUMERIC(14, 2) NOT NULL, paid_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id INTEGER NOT NULL, CONSTRAINT FK_E9F48FAD9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E9F48FAD2DA68207 ON outgoing_invoice (invoice_number)');
        $this->addSql('CREATE INDEX IDX_E9F48FAD9395C3F3 ON outgoing_invoice (customer_id)');
        $this->addSql('CREATE TABLE outgoing_invoice_line (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, position INTEGER NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, quantity NUMERIC(12, 4) NOT NULL, unit VARCHAR(10) NOT NULL, unit_price NUMERIC(12, 4) NOT NULL, line_net NUMERIC(14, 2) NOT NULL, invoice_id INTEGER DEFAULT NULL, CONSTRAINT FK_8CCDAA0E2989F1FD FOREIGN KEY (invoice_id) REFERENCES outgoing_invoice (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8CCDAA0E2989F1FD ON outgoing_invoice_line (invoice_id)');
        $this->addSql('CREATE TABLE settings (id INTEGER NOT NULL, company_name VARCHAR(255) NOT NULL, contact_name VARCHAR(255) DEFAULT NULL, street VARCHAR(255) NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(255) NOT NULL, country VARCHAR(2) NOT NULL, tax_number VARCHAR(50) DEFAULT NULL, vat_id VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, iban VARCHAR(50) DEFAULT NULL, bic VARCHAR(20) DEFAULT NULL, bank_name VARCHAR(255) DEFAULT NULL, kleinunternehmer BOOLEAN NOT NULL, kleinunternehmer_note CLOB NOT NULL, invoice_number_prefix VARCHAR(10) NOT NULL, next_invoice_number INTEGER NOT NULL, default_payment_term_days INTEGER NOT NULL, logo_filename VARCHAR(20) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE incoming_invoice');
        $this->addSql('DROP TABLE outgoing_invoice');
        $this->addSql('DROP TABLE outgoing_invoice_line');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
