<?php

declare(strict_types=1);

namespace Undnu\Texteladen\DataProcessing;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use Undnu\Texteladen\ContentElement\TexteladenElement;

final class TexteladenProcessor implements DataProcessorInterface
{
    /**
     * @param array<string,mixed> $contentObjectConfiguration
     * @param array<string,mixed> $processorConfiguration
     * @param array<string,mixed> $processedData
     * @return array<string,mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        $data = is_array($processedData['data'] ?? null) ? $processedData['data'] : [];
        $request = method_exists($cObj, 'getRequest') ? $cObj->getRequest() : null;
        if (!$request instanceof ServerRequestInterface) {
            $globalRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
            $request = $globalRequest instanceof ServerRequestInterface ? $globalRequest : null;
        }

        $viewData = (new TexteladenElement())->buildViewData($data, $request);
        return array_merge($processedData, $viewData);
    }
}
