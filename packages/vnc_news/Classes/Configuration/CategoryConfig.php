<?php

declare(strict_types=1);

namespace Vancado\VncNews\Configuration;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CategoryConfig
{
    private static array $categoryMap = [];

    /**
     * Liefert ein Mapping: Kategoriename → UID
     */
    public static function getCategoryMap(): array
    {
        if (empty(self::$categoryMap)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_category');

            $rows = $queryBuilder
                ->select('uid', 'title')
                ->from('sys_category')
                ->where(
                    $queryBuilder->expr()->eq('deleted', 0),
                    $queryBuilder->expr()->eq('hidden', 0)
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $title = trim((string)$row['title']);
                if ($title !== '') {
                    self::$categoryMap[$title] = (int)$row['uid'];
                }
            }
        }

        return self::$categoryMap;
    }

    /**
     * Gibt die UID einer Kategorie anhand des Titels zurück.
     */
    public static function getCategoryId(string $title): ?int
    {
        $map = self::getCategoryMap();
        return $map[$title] ?? null;
    }
}
