<?php

namespace Vancado\VncNews\LinkHandler;

use TYPO3\CMS\Frontend\Typolink\LinkResult;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;

/**
 * Builds a imagelink
 */
class NewsImageLinkBuilder extends AbstractTypolinkBuilder
{
    private const TYPE_NEWSIMAGE = 'newsimage';

    public function build(
        array &$linkDetails,
        string $linkText,
        string $target,
        array $conf,
    ): LinkResultInterface {
        $issueId = (int)$linkDetails['issue'];
        if ($issueId < 1) {
            throw new UnableToLinkException(
                '"' . $issueId . '" is not a valid image.',
                // Use the Unix timestamp of the time of creation of this message
                1665304602,
                null,
                $linkText,
            );
        }
        $url = 'https://github.com/TYPO3-Documentation/TYPO3CMS-Reference-CoreApi/issues/' . $issueId;

        return (new LinkResult(self::TYPE_NEWSIMAGE, $url))
            ->withTarget($target)
            ->withLinkConfiguration($conf)
            ->withLinkText($linkText);
    }
}