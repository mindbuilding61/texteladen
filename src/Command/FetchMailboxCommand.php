<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Receive\ImapMailboxFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-mailbox',
    description: 'Holt neue E-Rechnungs-Anhänge (XML/PDF) aus dem per IMAP-Env konfigurierten Postfach.'
)]
final class FetchMailboxCommand extends Command
{
    public function __construct(
        private readonly ImapMailboxFetcher $fetcher,
        #[\SensitiveParameter]
        private readonly string $imapHost = '',
        private readonly int $imapPort = 993,
        private readonly string $imapEncryption = 'ssl',
        private readonly bool $imapValidateCert = true,
        #[\SensitiveParameter]
        private readonly string $imapUsername = '',
        #[\SensitiveParameter]
        private readonly string $imapPassword = '',
        private readonly string $imapFolder = 'INBOX',
        private readonly bool $imapMarkSeen = true,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->imapHost === '' || $this->imapUsername === '' || $this->imapPassword === '') {
            $io->error('IMAP ist nicht konfiguriert. Setze IMAP_HOST, IMAP_USERNAME und IMAP_PASSWORD in .env.local.');
            return Command::FAILURE;
        }

        try {
            $result = $this->fetcher->fetch([
                'host' => $this->imapHost,
                'port' => $this->imapPort,
                'encryption' => $this->imapEncryption,
                'validate_cert' => $this->imapValidateCert,
                'username' => $this->imapUsername,
                'password' => $this->imapPassword,
                'folder' => $this->imapFolder,
                'mark_seen' => $this->imapMarkSeen,
            ]);
        } catch (\Throwable $e) {
            $io->error('IMAP-Abruf fehlgeschlagen: '.$e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d Nachricht(en) geprüft · %d importiert · %d Duplikat(e) · %d ohne passenden Anhang.',
            $result['messages'],
            $result['imported'],
            $result['duplicates'],
            $result['skipped']
        ));

        return Command::SUCCESS;
    }
}
