<?php

declare(strict_types=1);

namespace App\DTO\PrintGate;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Métadonnées techniques du job d'impression, telles que déclarées par
 * l'agent. Ne représente jamais le contenu du document (PrintGate ne
 * stocke ni ne vérifie le contenu réel imprimé, cf. résumé technique §14).
 */
final readonly class PrintJobPayload
{
    public function __construct(
        #[Assert\Positive]
        public int $jobId,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $printerName,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $documentName,

        #[Assert\PositiveOrZero]
        public int $pageCount = 0,

        #[Assert\Positive]
        public int $copies = 1,

        #[Assert\Length(max: 32)]
        public ?string $paperSize = null,

        #[Assert\Choice(choices: ['COLOR', 'MONOCHROME'])]
        public ?string $colorMode = null,

        #[Assert\Choice(choices: ['ONE_SIDED', 'TWO_SIDED'])]
        public ?string $duplexMode = null,
    ) {
    }
}
