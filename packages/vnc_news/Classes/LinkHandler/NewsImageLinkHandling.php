<?php

namespace Vancado\VncNews\LinkHandler;

use TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;

class NewsImageLinkHandling implements LinkHandlerInterface

{
    protected string $baseUrn = 't3://file';

    public function asString(array $parameters): string
    {
        $imgfile = (int)$parameters['file'];
        return $this->baseUrn . '?uid=' . $imgfile;
    }

    public function resolveHandlerData(array $data): array
    {
        return [
            'file' => (int)$data['file'],
        ];
    }
}