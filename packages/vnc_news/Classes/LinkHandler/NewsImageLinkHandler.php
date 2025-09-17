<?php
declare(strict_types=1);

namespace Vancado\VncNews\LinkHandler;

use TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController;
use TYPO3\CMS\Filelist\LinkHandler\FileLinkHandler;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Throwable;

/**
 * NewsImageLinkHandler
 * erzeugt <img src="t3://file?uid=..."/> im RTE
 */
class NewsImageLinkHandler extends FileLinkHandler
{
    protected string $startingFolder = 'fileadmin/news/';

    public function initialize(
        AbstractLinkBrowserController $linkBrowser,
                                      $identifier,
        array $configuration
    ) {
        $result = parent::initialize($linkBrowser, $identifier, $configuration);

        // Startordner setzen
        try {
            if ($this->startingFolder !== '') {
                $folder = $this->resourceFactory->retrieveFileOrFolderObject($this->startingFolder);
                if ($folder instanceof Folder) {
                    $this->selectedFolder = $folder;
                    $this->expandFolder = $folder->getCombinedIdentifier();
                }
            }
        } catch (Throwable $e) {
            // ignore
        }

        return $result;
    }

    /**
     * Überschreibt die URL-Ausgabe -> liefert <img> statt <a>
     */
    public function formatCurrentUrl(): string
    {
        if (!empty($this->linkParts['file'])) {
            $uid = $this->linkParts['file']->getUid();
            return '<img src="t3://file?uid=' . $uid . '" alt="" />';
        }
        return '';
    }
}
