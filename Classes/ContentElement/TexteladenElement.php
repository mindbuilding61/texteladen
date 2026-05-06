<?php

declare(strict_types=1);

namespace Undnu\Texteladen\ContentElement;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment as Typo3Environment;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TexteladenElement
{
    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function buildViewData(array $data, ?ServerRequestInterface $request): array
    {
        $devContext = Typo3Environment::getContext()->isDevelopment();
        $ceUid = (int)($data['uid'] ?? 0);
        $debug = $this->isDebugEnabled($request, $ceUid);

        try {
            if ($ceUid === 0 && ($debug || $devContext)) {
                return $this->emptyResult($this->renderDebugBox('Kein tt_content Datensatz im Rendering-Kontext.', []), $debug);
            }

            $folderIdentifier = $this->parseFolderIdentifier((string)($data['tx_texteladen_folder'] ?? ''));
            $tag = trim((string)($data['tx_texteladen_tag'] ?? ''));
            $wordsPerPage = (int)($data['tx_texteladen_words_per_page'] ?? 200);
            if ($wordsPerPage <= 0) {
                $wordsPerPage = 200;
            }

            $folder = $this->resolveFolder($folderIdentifier);
            if (!$folder) {
                $fallback = ($debug || $devContext) ? $this->renderDebugBox('Kein Verzeichnis gefunden/gesetzt.', [
                    'tx_texteladen_folder(raw)' => (string)($data['tx_texteladen_folder'] ?? ''),
                    'folderIdentifier(parsed)' => $folderIdentifier,
                ]) : '';
                return $this->emptyResult($fallback, $debug);
            }

            $tagNeedle = $this->normalizeTagNeedle($tag);
            [$combinedMarkdown, $stats] = $this->loadAndFilterFiles($folder, $tagNeedle);
            if ($combinedMarkdown === '') {
                $fallback = ($debug || $devContext) ? $this->renderDebugBox('Keine passenden Dateien gefunden (oder Dateien leer).', [
                    'folder' => $folder->getIdentifier(),
                    'tagNeedle' => $tagNeedle ?: '(leer)',
                    'totalFilesInFolder' => (string)($stats['totalFiles'] ?? 0),
                    'matchedFiles' => implode(', ', $stats['matchedFiles'] ?? []),
                ]) : '';
                return $this->emptyResult($fallback, $debug);
            }

            $currentPage = $this->resolveCurrentPage($request, $ceUid);

            [$pageMarkdown, $pagination] = $this->paginateByWords($combinedMarkdown, $wordsPerPage, $currentPage);

            $contentHtml = $this->renderMarkdownToHtml($pageMarkdown);

            return [
                'data' => $data,
                'contentHtml' => $contentHtml,
                'pagination' => $pagination,
                'debug' => $debug,
                'debugStats' => $stats,
                'debugTagNeedle' => $tagNeedle,
                'debugFolderIdentifier' => $folderIdentifier,
                'debugFallback' => '',
            ];
        } catch (\Throwable $e) {
            if ($debug || $devContext) {
                return $this->emptyResult($this->renderDebugBox('Texteladen Exception', [
                    'type' => $e::class,
                    'message' => $e->getMessage(),
                ]), $debug);
            }
            return $this->emptyResult('', $debug);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyResult(string $fallback, bool $debug): array
    {
        return [
            'contentHtml' => '',
            'pagination' => [
                'totalPages' => 1,
                'currentPage' => 1,
                'hasPrevious' => false,
                'hasNext' => false,
                'previousPage' => 1,
                'nextPage' => 1,
            ],
            'debug' => $debug,
            'debugStats' => ['totalFiles' => 0, 'matchedFiles' => []],
            'debugTagNeedle' => '',
            'debugFolderIdentifier' => '',
            'debugFallback' => $fallback,
        ];
    }

    private function isDebugEnabled(?ServerRequestInterface $request, int $ceUid): bool
    {
        $tx = $this->resolveTxParams($request);
        if (!is_array($tx)) {
            return false;
        }
        $targetCe = (int)($tx['ce'] ?? 0);
        if ($ceUid > 0 && $targetCe !== $ceUid) {
            return false;
        }
        return (int)($tx['debug'] ?? 0) === 1;
    }

    private function resolveCurrentPage(?ServerRequestInterface $request, int $ceUid): int
    {
        $page = 1;
        $tx = $this->resolveTxParams($request);
        if (!is_array($tx)) {
            return $page;
        }

        $targetCe = (int)($tx['ce'] ?? 0);
        if ($targetCe !== $ceUid) {
            return $page;
        }

        $page = max(1, (int)($tx['page'] ?? 1));
        return $page;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveTxParams(?ServerRequestInterface $request): ?array
    {
        if ($request !== null) {
            $queryParams = $request->getQueryParams();
            $tx = $queryParams['tx_texteladen'] ?? null;
            if (is_array($tx)) {
                return $tx;
            }
        }

        $txGet = $_GET['tx_texteladen'] ?? null;
        return is_array($txGet) ? $txGet : null;
    }

    private function resolveFolder(string $folderIdentifier): ?Folder
    {
        if ($folderIdentifier === '') {
            return null;
        }

        try {
            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            return $resourceFactory->getFolderObjectFromCombinedIdentifier($folderIdentifier);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTagNeedle(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }
        $tag = ltrim($tag, "# \t\n\r\0\x0B");
        if ($tag === '') {
            return '';
        }
        return '#' . $tag;
    }

    private function parseFolderIdentifier(string $rawValue): string
    {
        $rawValue = trim($rawValue);
        if ($rawValue === '') {
            return '';
        }

        // FAL kann CSV speichern (z.B. "1:/foo/,") oder eine JSON-Liste.
        if (str_starts_with($rawValue, '[')) {
            try {
                $decoded = json_decode($rawValue, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded) && isset($decoded[0]) && is_string($decoded[0])) {
                    return trim($decoded[0]);
                }
            } catch (\Throwable) {
                // Fallback auf CSV-Parsing unten
            }
        }

        return trim((string)explode(',', $rawValue)[0]);
    }

    /**
     * @return array{0:string,1:array{totalFiles:int,matchedFiles:string[]}}
     */
    private function loadAndFilterFiles(Folder $folder, string $tagNeedle): array
    {
        $files = $folder->getFiles();
        $totalFiles = count($files);

        // Stabil sortieren (A-Z)
        usort($files, static function ($a, $b): int {
            return strcmp($a->getName(), $b->getName());
        });

        $chunks = [];
        $matchedFiles = [];
        foreach ($files as $file) {
            $ext = strtolower((string)$file->getExtension());
            if (!in_array($ext, ['md', 'txt'], true)) {
                continue;
            }

            $raw = (string)$file->getContents();

            if ($tagNeedle !== '' && !str_contains($raw, $tagNeedle)) {
                continue;
            }

            if ($tagNeedle !== '') {
                $raw = $this->removeTagFromText($raw, $tagNeedle);
            }

            $raw = trim($raw);
            if ($raw !== '') {
                $chunks[] = $raw;
                $matchedFiles[] = $file->getName();
            }
        }

        // Markdown-Trenner zwischen Dateien
        return [trim(implode("\n\n---\n\n", $chunks)), [
            'totalFiles' => $totalFiles,
            'matchedFiles' => $matchedFiles,
        ]];
    }

    private function removeTagFromText(string $text, string $tagNeedle): string
    {
        $pattern = '/(?<![\\pL\\pN_\\-])' . preg_quote($tagNeedle, '/') . '(?![\\pL\\pN_\\-])/u';
        $text = (string)preg_replace($pattern, '', $text);
        // Mehrfach-Spaces nach Entfernen des Tags etwas aufräumen
        $text = (string)preg_replace('/[ \\t]{2,}/u', ' ', $text);
        return $text;
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function paginateByWords(string $markdown, int $wordsPerPage, int $currentPage): array
    {
        $matches = [];
        preg_match_all('/\\S+/u', $markdown, $matches, PREG_OFFSET_CAPTURE);
        $wordMatches = $matches[0] ?? [];

        $totalWords = count($wordMatches);
        if ($totalWords === 0) {
            return ['', [
                'currentPage' => 1,
                'totalPages' => 1,
                'hasPrevious' => false,
                'hasNext' => false,
                'previousPage' => 1,
                'nextPage' => 1,
                'wordsPerPage' => $wordsPerPage,
                'totalWords' => 0,
            ]];
        }

        $totalPages = max(1, (int)ceil($totalWords / $wordsPerPage));
        $currentPage = max(1, min($currentPage, $totalPages));

        $startIndex = ($currentPage - 1) * $wordsPerPage;
        $endIndex = min($startIndex + $wordsPerPage - 1, $totalWords - 1);

        $startPos = (int)$wordMatches[$startIndex][1];
        $endWord = (string)$wordMatches[$endIndex][0];
        $endPos = (int)$wordMatches[$endIndex][1] + strlen($endWord);

        $pageMarkdown = trim(substr($markdown, $startPos, $endPos - $startPos));

        return [$pageMarkdown, [
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'hasPrevious' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
            'previousPage' => max(1, $currentPage - 1),
            'nextPage' => min($totalPages, $currentPage + 1),
            'wordsPerPage' => $wordsPerPage,
            'totalWords' => $totalWords,
        ]];
    }

    private function renderMarkdownToHtml(string $markdown): string
    {
        $environment = new Environment([
            // Sicherheit: kein HTML aus Markdown übernehmen
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());

        $converter = new CommonMarkConverter([], $environment);
        return $converter->convert($markdown)->getContent();
    }

    /**
     * Debug-Output, nur wenn ?tx_texteladen[ce]=<uid>&tx_texteladen[debug]=1 gesetzt ist.
     *
     * @param array<string,string> $context
     */
    private function renderDebugBox(string $headline, array $context): string
    {
        $lines = [$headline];
        foreach ($context as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        $safe = htmlspecialchars(implode("\n", $lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<pre class="texteladen__debug" style="padding:12px;border:1px dashed #999;white-space:pre-wrap;">' . $safe . '</pre>';
    }
}
