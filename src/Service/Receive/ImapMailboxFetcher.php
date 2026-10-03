<?php

declare(strict_types=1);

namespace App\Service\Receive;

use App\Entity\IncomingInvoice;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Webklex\PHPIMAP\ClientManager;

/**
 * Fetches unread messages from an IMAP mailbox, extracts XRechnung-XML and
 * ZUGFeRD-PDF attachments, hands them to the importer, and marks the messages
 * as seen. Thin wrapper around webklex/php-imap so we don't require the
 * deprecated `imap` PHP extension.
 */
final class ImapMailboxFetcher
{
    public function __construct(
        private readonly IncomingInvoiceImporter $importer,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param array{host:string,port:int,encryption:?string,validate_cert:bool,username:string,password:string,folder:string,mark_seen:bool} $config
     * @return array{imported:int, duplicates:int, skipped:int, messages:int}
     */
    public function fetch(array $config): array
    {
        $cm = new ClientManager();
        $client = $cm->make([
            'host' => $config['host'],
            'port' => $config['port'],
            'encryption' => $config['encryption'] ?: false,
            'validate_cert' => $config['validate_cert'],
            'username' => $config['username'],
            'password' => $config['password'],
            'protocol' => 'imap',
            'authentication' => null,
        ]);
        $client->connect();

        $folder = $client->getFolder($config['folder'] ?: 'INBOX');
        if ($folder === null) {
            throw new \RuntimeException(sprintf('IMAP folder "%s" not found.', $config['folder']));
        }

        $messages = $folder->query()->unseen()->leaveUnread()->get();
        $imported = $duplicates = $skipped = 0;

        foreach ($messages as $message) {
            $subject = $message->getSubject() ?: '(ohne Betreff)';
            $from = $message->getFrom()[0] ?? null;
            $fromAddress = $from ? $from->mail : '(unknown)';
            $this->logger->info('IMAP: processing message', ['subject' => $subject, 'from' => $fromAddress]);

            $foundAttachment = false;
            foreach ($message->getAttachments() as $attachment) {
                $name = (string) $attachment->getName();
                $content = (string) $attachment->getContent();
                if ($content === '') {
                    continue;
                }
                if (!$this->looksRelevant($name, $content)) {
                    continue;
                }
                $foundAttachment = true;
                $result = $this->importer->importBytes($content, $name, IncomingInvoice::SOURCE_EMAIL);
                if ($result->duplicate) {
                    $duplicates++;
                } else {
                    $imported++;
                }
            }

            if (!$foundAttachment) {
                $skipped++;
            }

            if ($config['mark_seen'] ?? true) {
                try {
                    $message->setFlag('Seen');
                } catch (\Throwable $e) {
                    $this->logger->warning('Could not mark IMAP message as seen', ['exception' => $e]);
                }
            }
        }

        $client->disconnect();

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'messages' => $messages->count(),
        ];
    }

    private function looksRelevant(string $name, string $content): bool
    {
        $lower = strtolower($name);
        if (str_ends_with($lower, '.xml') || str_ends_with($lower, '.pdf')) {
            return true;
        }
        return str_starts_with($content, '%PDF-') || str_starts_with(ltrim($content), '<?xml');
    }
}
