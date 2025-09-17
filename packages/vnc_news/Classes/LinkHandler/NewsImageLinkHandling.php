<?php

namespace Vancado\VncNews\LinkHandler;

use TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\View\ViewInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\LinkHandler\LinkHandlerInterface;


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


    /**
     * @return mixed
     */
    public function getLinkAttributes()
    {
        // TODO: Implement getLinkAttributes() method.
    }

    /**
     * @param array $fieldDefinitions
     * @return mixed
     */
    public function modifyLinkAttributes(array $fieldDefinitions)
    {
        // TODO: Implement modifyLinkAttributes() method.
    }

    /**
     * @param AbstractLinkBrowserController $linkBrowser
     * @param $identifier
     * @param array $configuration
     * @return mixed
     */
    public function initialize(AbstractLinkBrowserController $linkBrowser, $identifier, array $configuration)
    {
        // TODO: Implement initialize() method.
    }

    /**
     * @param array $linkParts
     * @return mixed
     */
    public function canHandleLink(array $linkParts)
    {
        // TODO: Implement canHandleLink() method.
    }

    /**
     * @return mixed
     */
    public function formatCurrentUrl()
    {
        // TODO: Implement formatCurrentUrl() method.
    }

    /**
     * @param ServerRequestInterface $request
     * @return mixed
     */
    public function render(ServerRequestInterface $request)
    {
        // TODO: Implement render() method.
    }

    /**
     * @return mixed
     */
    public function isUpdateSupported()
    {
        // TODO: Implement isUpdateSupported() method.
    }

    /**
     * @return mixed
     */
    public function getBodyTagAttributes()
    {
        // TODO: Implement getBodyTagAttributes() method.
    }
}