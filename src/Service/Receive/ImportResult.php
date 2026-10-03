<?php

declare(strict_types=1);

namespace App\Service\Receive;

use App\Entity\IncomingInvoice;

final class ImportResult
{
    public function __construct(
        public readonly IncomingInvoice $invoice,
        public readonly bool $duplicate,
        /** @var string[] */
        public readonly array $errors,
    ) {
    }
}
