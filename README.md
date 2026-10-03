# E-Rechnung – Web-App für Kleinunternehmer (§ 19 UStG)

Eine schlanke Web-App zum **Empfangen und Erstellen von E-Rechnungen** nach deutschem Standard (**XRechnung / ZUGFeRD / Factur-X**, Datenmodell **EN 16931**). Entworfen für Ein-Personen-Betriebe mit § 19-Status – ein Nutzer, eine SQLite-Datei, keine Buchhaltungsexporte.

## Was die App kann

### Empfangen (seit 01.01.2025 Pflicht für B2B)

- Upload von **XRechnung-XML** (UBL **und** CII) und **ZUGFeRD/Factur-X-PDF/A-3** über die Weboberfläche.
- Optionaler automatischer Abruf aus einem **IMAP-Postfach** (`php bin/console app:fetch-mailbox`, z. B. per Cron).
- Erkennung von Format (UBL vs. CII) und Profil (XRechnung 2.x/3.x, ZUGFeRD BASIC/EN16931/EXTENDED, Factur-X).
- Extraktion aller EN‑16931-Kernfelder: Rechnungsnummer, Datum, Fälligkeit, Leistungszeitraum, Verkäufer inkl. USt-IdNr. + IBAN, Käufer, Beträge, Positionen.
- **GoBD-orientierte Ablage**: Original-Datei bleibt unverändert, SHA-256 wird mitgespeichert; Dubletten werden erkannt.
- Status-Workflow: *neu → geprüft → bezahlt / beanstandet*.

### Erstellen (für Kleinunternehmer **optional** – du *darfst* weiter PDFs schicken, Kunden freuen sich aber über eine echte E-Rechnung)

- Kunden- und Artikelstamm.
- Rechnungserfassung mit Positionen, Mengen, Einheiten (UN/ECE Rec. 20).
- **Kleinunternehmer-Modus** aktiv: Steuerkategorie `E` (Exempt), Satz 0 %, Exemption-Code **VATEX-EU-O**, Pflichthinweis nach § 19 UStG wird automatisch sowohl im XML als auch im Sicht-PDF ausgegeben.
- Export als:
  - reine **XRechnung** (CII, Profil XRechnung 3.0),
  - **ZUGFeRD-PDF/A-3** (hybrid: PDF mit eingebettetem XML),
  - reines Sicht-PDF.
- **Festschreiben** (vergibt die Rechnungsnummer, danach keine Änderung mehr – GoBD).
- Versand per **E-Mail (SMTP)** direkt aus der App.

## Technik-Stack

- PHP 8.3, Symfony 7.4 (Webapp-Pack, Doctrine ORM, Twig, Forms, Security, Mailer).
- SQLite als Datenbank (ein Mandant – keine Infrastruktur nötig).
- [horstoeko/zugferd](https://github.com/horstoeko/zugferd) für XRechnung/ZUGFeRD/Factur-X-Lesen und -Schreiben.
- [dompdf/dompdf](https://github.com/dompdf/dompdf) für die PDF/A-3-Erzeugung.
- [webklex/php-imap](https://github.com/Webklex/php-imap) für IMAP (ohne die veraltete PHP-`imap`-Extension).
- Bootstrap 5 für die UI.

## Schnellstart (Docker)

```bash
# 1. .env bearbeiten (SMTP, optional IMAP)
cp .env .env.local
# APP_SECRET, MAILER_DSN, ggf. IMAP_* eintragen

# 2. Container bauen und starten
docker compose up -d --build

# 3. App öffnen
open http://localhost:8080
```

Beim ersten Start läuft `doctrine:migrations:migrate` automatisch. Die SQLite-Datei und das Rechnungsarchiv liegen im Volume `erechnung_data` (Pfad im Container: `/var/www/app/var`).

### IMAP via Cron

```bash
# jede Stunde Postfach prüfen (im Host, docker exec)
0 * * * * docker exec erechnung php bin/console app:fetch-mailbox
```

## Lokale Entwicklung (ohne Docker)

Voraussetzungen: PHP 8.3 mit den Erweiterungen `pdo_sqlite`, `xml`, `zip`, `intl`, `mbstring`, `gd`, `bcmath` – plus Composer.

```bash
composer install
cp .env .env.local  # MAILER_DSN etc. anpassen
php bin/console doctrine:migrations:migrate --no-interaction
php -S 127.0.0.1:8787 -t public public/index.php
```

Dann [http://127.0.0.1:8787](http://127.0.0.1:8787) öffnen.

## Konfiguration (`.env.local`)

```dotenv
APP_ENV=prod
APP_SECRET=<64 Hexstellen, z. B. aus `php -r "echo bin2hex(random_bytes(32));"`>
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_prod.db"

# SMTP für den Rechnungsversand
MAILER_DSN=smtp://user:pass@smtp.beispiel.de:587

# Optional: Postfach-Abruf für Eingangsrechnungen
IMAP_HOST=imap.beispiel.de
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=rechnungen@beispiel.de
IMAP_PASSWORD=<app-passwort>
IMAP_FOLDER=INBOX
IMAP_MARK_SEEN=true
```

## Wichtige rechtliche Hinweise

- Die **E-Rechnungs-Empfangspflicht** gilt seit 01.01.2025 für alle inländischen B2B-Umsätze. Diese App deckt sie ab, indem sie jede XRechnung oder ZUGFeRD-PDF revisionssicher ablegt und maschinenlesbar auswertet.
- Als **Kleinunternehmer nach § 19 UStG** bist du vom **Ausstellen** einer E-Rechnung befreit (Wachstumschancengesetz / JStG 2024) – du *darfst* aber eine ausstellen, und viele Geschäftskunden werden sich darüber freuen. Dieser Pflichthinweis wird in jede ausgehende Rechnung automatisch eingefügt.
- Die App unterstützt die **GoBD** durch unveränderte Originalablage, SHA-256-Fingerprints und das Sperren festgeschriebener Ausgangsrechnungen gegen Änderungen.
- Für die **Schematron-Vollvalidierung** nach EN 16931 kann zusätzlich der [KoSIT-Validator](https://github.com/itplr-kosit/validator) (Java) eingebunden werden – aktuell wird nur die XSD-Konformität und die Lesbarkeit über horstoeko geprüft; das ist für den praktischen Betrieb ausreichend, formell aber kein Ersatz für die amtliche KoSIT-Prüfung.
- **Peppol-Versand** (BIS Billing 3.0 über einen Access Point) ist nicht enthalten. Für B2G über Peppol brauchst du einen zertifizierten Access-Point-Provider – die XML-Dateien dieser App sind dafür bereits korrekt strukturiert.

## Projektstruktur

```
src/
  Entity/              # Doctrine-Entitäten (Settings, Customer, Article, OutgoingInvoice, …)
  Repository/
  Controller/          # Dashboard, Settings, Customer, Article, Incoming/Outgoing invoice
  Form/
  Command/             # app:fetch-mailbox
  Service/
    InvoiceStorage.php                      # GoBD-Ablage
    Receive/
      InvoiceFormatDetector.php             # erkennt UBL / CII / PDF
      CiiInvoiceParser.php                  # ZUGFeRD/XRechnung CII (horstoeko)
      UblInvoiceParser.php                  # XRechnung UBL (DOMXPath)
      IncomingInvoiceImporter.php           # Orchestrierung + Dedup
      ImapMailboxFetcher.php                # IMAP-Abruf
    Create/
      XRechnungBuilder.php                  # UN/CEFACT CII (XRechnung 3.0)
      InvoicePdfRenderer.php                # Dompdf + Twig
      ZugferdPdfBuilder.php                 # hybrides PDF/A-3
    Send/
      InvoiceMailer.php                     # Symfony Mailer mit Anhängen
templates/             # Twig-Views, inkl. PDF-Template
tests/                 # PHPUnit-Smoke-Tests inkl. Roundtrip CII/UBL
docker/                # Dockerfile-Beiwerk (Apache/PHP/entrypoint)
```

## Tests

```bash
vendor/bin/phpunit
```

Enthalten:

- **CII-Parser-Roundtrip:** erzeugt per horstoeko eine XRechnung-3-CII, parst sie zurück und prüft Beträge/IBAN/Positionen.
- **UBL-Parser:** parst ein echtes XRechnung-UBL-Dokument inkl. Leitweg-ID und Positionen.
- **XRechnungBuilder (Kleinunternehmer):** erzeugt eine komplette § 19‑Rechnung und validiert, dass VATEX-EU-O, § 19‑Hinweis und 0 %-Steuer korrekt im XML landen.

## Lizenz

MIT – siehe [LICENSE](LICENSE).
