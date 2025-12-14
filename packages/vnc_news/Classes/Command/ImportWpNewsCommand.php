<?php

declare(strict_types=1);

namespace Vancado\VncNews\Command;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use DOMDocument;
use SimpleXMLElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use \Vancado\VncNews\Configuration\CategoryConfig;

#[AsCommand(
    name: 'vnc:import-wp-news',
    description: 'Imports WordPress WXR (XML) to tx_news incl. categories & FAL attachments. Truncates news and clears MM & FAL refs first.'
)]
final class ImportWpNewsCommand extends Command
{

    private SiteFinder $site;
    private ConnectionPool $connectionPool;
    private ResourceFactory $resourceFactory;
    private array $categoryMap;
    private array $folderMap;

    public function __construct(
        ConnectionPool   $connectionPool,
        ?ResourceFactory $resourceFactory = null
    )
    {
        parent::__construct();
        $this->connectionPool = $connectionPool;
        // Fallback, falls DI für ResourceFactory nicht greift
        $this->resourceFactory = $resourceFactory ?? GeneralUtility::makeInstance(ResourceFactory::class);
        $this->site = $site ?? GeneralUtility::makeInstance(SiteFinder::class);

    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Absolute path to the WordPress XML (WXR) export file')
            ->addOption('wpdomain', null, InputOption::VALUE_REQUIRED, 'Domain name to substitute with current domain. eg my-blog.de')
            ->addOption('sourcefolder', null, InputOption::VALUE_REQUIRED, 'Source folder for images from WP')
            ->addOption('targetfolder', null, InputOption::VALUE_REQUIRED, 'Destination folder for images inside fileadmin')
            ->addOption('pid', null, InputOption::VALUE_OPTIONAL, 'Target storage PID for imported news (defaults to 0)', 0);
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = (string)($input->getOption('file') ?? '');
        $pid = (int)($input->getOption('pid') ?? 0);
        $oldHost = (string)($input->getOption('wpdomain') ?? '');

        // Dynamisches Mapping von WP-Kategorien zu sys_category.uid über title feld
        $this->categoryMap = \Vancado\VncNews\Configuration\CategoryConfig::getCategoryMap();

        // Quell- und Zielverzeichnisse
        $this->folderMap = [
            'wp-dir' => (string)($input->getOption('sourcefolder')),
            'typo3-dir' => (string)($input->getOption('targetfolder'))
        ];


        try {
            $currentHost = $this->site->getSiteByPageId($pid)->getBase()->getHost();
        } catch (SiteNotFoundException $e) {
        }

        if ($file === '' || !is_file($file) || !is_readable($file)) {
            $output->writeln('<error>ERROR: Please pass a readable file via --file="/absolute/path/to/export.xml"</error>');
            $output->writeln('');
            $output->writeln('<info>Usage:</info>');
            $output->writeln('   typo3 vnc:import-wp-news --file="var/export/beitraege.xml" --sourcefolder=wp-content/uploads --targetfolder=vnc_news [--pid=123] [--wp-domain=my-blog.com]');
            return Command::FAILURE;
        }
        if ($pid < 0) {
            $output->writeln('<error>ERROR: --pid must be >= 0.</error>');
            return Command::FAILURE;
        }

        // 1) XML laden (vor dem Löschen der Tabelle!)
        $output->writeln('<info>Loading & validating XML ...</info>');
        $xml = $this->loadWxr($file, $output);
        if (!$xml instanceof SimpleXMLElement) {
            return Command::FAILURE;
        }
        if (!isset($xml->channel) || !isset($xml->channel->item)) {
            $output->writeln('<comment>No <channel><item> nodes found – nothing to import.</comment>');
            return Command::SUCCESS;
        }

        // Namespaces
        $namespaces = $xml->getDocNamespaces(true);
        $wpNsPrefix = array_key_exists('wp', $namespaces) ? 'wp' : null;
        $contentNsPrefix = array_key_exists('content', $namespaces) ? 'content' : null;

        if (!$wpNsPrefix) {
            $output->writeln('<comment>Warning: No "wp:" namespace found; cannot filter by wp:post_type. Import skipped.</comment>');
            return Command::SUCCESS;
        }

        // 2) Default-Storage ermitteln
        $defaultStorage = $this->resourceFactory->getDefaultStorage();
        if (!$defaultStorage instanceof ResourceStorage) {
            $output->writeln('<error>No DEFAULT FAL storage configured. Please configure a default fileadmin storage.</error>');
            return Command::FAILURE;
        }
        if (!$defaultStorage->isOnline()) {
            $output->writeln('<error>DEFAULT FAL storage is not online. Please enable it.</error>');
            return Command::FAILURE;
        }

        // DB connections
        $newsConn = $this->connectionPool->getConnectionForTable('tx_news_domain_model_news');
        $refConn = $this->connectionPool->getConnectionForTable('sys_file_reference');
        $mmConn = $this->connectionPool->getConnectionForTable('sys_category_record_mm');

        // 3) Tabelle & alte Referenzen löschen (erst jetzt, nach erfolgreichem Parse)
        $output->writeln('<info>Clearing tx_news_domain_model_news ...</info>');
        try {
            $newsConn->executeStatement('TRUNCATE TABLE tx_news_domain_model_news');
        } catch (\Throwable) {
            $output->writeln('<comment>TRUNCATE failed, fallback to DELETE FROM ...</comment>');
            $newsConn->executeStatement('DELETE FROM tx_news_domain_model_news');
        }

        $output->writeln('<info>Clearing existing sys_file_reference for tx_news_domain_model_news ...</info>');
        $refConn->delete('sys_file_reference', ['tablenames' => 'tx_news_domain_model_news']);

        $output->writeln('<info>Clearing existing sys_category_record_mm for tx_news_domain_model_news ...</info>');
        $mmConn->delete('sys_category_record_mm', [
            'tablenames' => 'tx_news_domain_model_news',
            'fieldname' => 'categories',
        ]);

        // 4) PASS 1: Posts importieren; Map wp:post_id → newsUid aufbauen
        $now = time();
        $insertedPosts = 0;
        $totalItems = 0;
        $skippedNonPost = 0;
        $wpPostIdToNewsUid = [];   // [wp_post_id => newsUid]
        $refSeen = [];   // [newsUid => [fileUid => true]]
        $sortingPerNews = [];   // [newsUid => int]
        $unknownCategoryTitles = [];

        foreach ($xml->channel->item as $item) {
            $totalItems++;

            $wp = $item->children($wpNsPrefix, true);
            $postType = trim((string)($wp->post_type ?? ''));
            if ($postType !== 'post') {
                $skippedNonPost++;
                continue;
            }

            $wpPostId = (int)($wp->post_id ?? 0);

            // Felder sammeln
            $title = trim((string)($item->title ?? ''));
            $link = trim((string)($item->link ?? '')); // optional
            $description = trim((string)($item->description ?? ''));

            // content:encoded
            $bodytext = '';
            if ($contentNsPrefix) {
                $c = $item->children($contentNsPrefix, true);
                $bodytext = isset($c->encoded) ? (string)$c->encoded : '';
            }
            if ($bodytext === '' && $description !== '') {
                $bodytext = $description;
            }

            // pubDate -> UNIX timestamp
            $pubDateRaw = trim((string)($item->pubDate ?? ''));
            $datetimeTs = $this->parseRfc822ToTimestamp($pubDateRaw) ?? 0;

            // Kategorien aus <category domain="category">Titel</category>
            [$categoryUids, $unknownTitles] = $this->resolveCategoryUidsFromItem($item);
            if (!empty($unknownTitles)) {
                foreach ($unknownTitles as $unk) {
                    $unknownCategoryTitles[$unk] = true; // de-dupe
                }
            }

            if ($title === '') {
                $output->writeln('<comment>Skipped post without title (wp:post_id ' . $wpPostId . ').</comment>');
                continue;
            }

            $row = [
                'pid' => $pid,
                'tstamp' => $now,
                'crdate' => $now,
                'hidden' => 0,
                'deleted' => 0,
                'title' => $title,
                'teaser' => $description,
                'bodytext' => $this->substituteBodytextHostnames($bodytext, $oldHost, $currentHost),
                'datetime' => $datetimeTs,
                'type' => 0,
                // 'externalurl' => $link,
                'categories' => count($categoryUids), // Anzahl der verknüpften Kategorien
            ];

            try {
                $newsConn->insert('tx_news_domain_model_news', $row);
                $newsUid = (int)$newsConn->lastInsertId();
                $insertedPosts++;

                if ($wpPostId > 0) {
                    $wpPostIdToNewsUid[$wpPostId] = $newsUid;
                }
                $refSeen[$newsUid] = [];
                $sortingPerNews[$newsUid] = 0;

                // MM-Links für Kategorien anlegen
                if (!empty($categoryUids)) {
                    $this->insertCategoryMMLinks($mmConn, $categoryUids, $newsUid);
                }
            } catch (\Throwable $e) {
                $output->writeln('<error>Insert failed for "' . htmlspecialchars($title) . '" (wp:post_id ' . $wpPostId . '): ' . $e->getMessage() . '</error>');
                continue;
            }

            // Falls im Post direkt attachment_url vorhanden → verknüpfen
            if (isset($wp->attachment_url)) {
                $urls = [];
                foreach ($wp->attachment_url as $au) {
                    $u = trim((string)$au);
                    if ($u !== '') {
                        $urls[] = $u;
                    }
                }
                foreach ($urls as $url) {
                    $this->attachFileUrlToNews($url, $defaultStorage, $refConn, $pid, $now, $newsUid, $refSeen, $sortingPerNews, $output);
                }
            }
        }

        // 5) PASS 2: Attachments an Posts hängen (wp:post_type=attachment)
        $linkedAttachments = 0;
        $skippedAttachments = 0;

        foreach ($xml->channel->item as $item) {
            $wp = $item->children($wpNsPrefix, true);
            $postType = trim((string)($wp->post_type ?? ''));
            if ($postType !== 'attachment') {
                continue;
            }

            // Parent-Post holen
            $parentId = (int)($wp->post_parent ?? 0);
            if ($parentId <= 0) {
                $maybeParent = (int)($wp->post_id ?? 0);
                if ($maybeParent > 0 && isset($wpPostIdToNewsUid[$maybeParent])) {
                    $parentId = $maybeParent;
                }
            }

            if ($parentId <= 0 || !isset($wpPostIdToNewsUid[$parentId])) {
                $skippedAttachments++;
                continue;
            }
            $newsUid = $wpPostIdToNewsUid[$parentId];

            if (!isset($refSeen[$newsUid])) {
                $refSeen[$newsUid] = [];
            }
            if (!isset($sortingPerNews[$newsUid])) {
                $sortingPerNews[$newsUid] = 0;
            }

            // Attachment-URLs
            $urls = [];
            if (isset($wp->attachment_url)) {
                foreach ($wp->attachment_url as $au) {
                    $u = trim((string)$au);
                    if ($u !== '') {
                        $urls[] = $u;
                    }
                }
            }
            if (empty($urls)) {
                $link = trim((string)($item->link ?? ''));
                if ($link !== '') {
                    $urls[] = $link;
                }
            }
            if (empty($urls)) {
                $skippedAttachments++;
                continue;
            }

            foreach ($urls as $url) {
                $ok = $this->attachFileUrlToNews($url, $defaultStorage, $refConn, $pid, $now, $newsUid, $refSeen, $sortingPerNews, $output);
                if ($ok) {
                    $linkedAttachments++;
                } else {
                    $skippedAttachments++;
                }
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Done. Items: %d | Posts inserted: %d | Non-post skipped: %d | Attachments linked: %d (skipped: %d)</info>',
            $totalItems, $insertedPosts, $skippedNonPost, $linkedAttachments, $skippedAttachments
        ));
        if (!empty($unknownCategoryTitles)) {
            $output->writeln('<comment>Unknown categories (no mapping): ' . implode(', ', array_keys($unknownCategoryTitles)) . '</comment>');
        }

        return Command::SUCCESS;
    }

    /** XML robust laden (BOM/Steuerzeichen entfernen, SimpleXML, DOM-Fallback) */
    private function loadWxr(string $file, OutputInterface $output): ?SimpleXMLElement
    {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            $output->writeln('<error>ERROR: Could not read file contents.</error>');
            return null;
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3); // BOM
        }
        // verbotene Control-Chars entfernen (Tab, CR, LF bleiben)
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? $raw;

        libxml_use_internal_errors(true);
        $flagsCommon = LIBXML_NOCDATA | LIBXML_PARSEHUGE | LIBXML_NOERROR | LIBXML_NOWARNING;

        // 1) SimpleXML
        $xml = simplexml_load_string($raw, SimpleXMLElement::class, $flagsCommon);
        if ($xml instanceof SimpleXMLElement) {
            libxml_clear_errors();
            return $xml;
        }
        $errors1 = libxml_get_errors();
        libxml_clear_errors();

        // 2) DOM mit optionalem RECOVER
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        $domFlags = $flagsCommon;
        if (defined('LIBXML_RECOVER')) {
            $domFlags |= (int)constant('LIBXML_RECOVER');
        }

        $ok = $dom->loadXML($raw, $domFlags);
        $errors2 = libxml_get_errors();
        libxml_clear_errors();

        if ($ok && $dom->documentElement) {
            $xml2 = simplexml_import_dom($dom);
            if ($xml2 instanceof SimpleXMLElement) {
                if (!empty($errors1)) {
                    $output->writeln('<comment>Warning: XML had issues, parsed via DOM recover. Import will proceed.</comment>');
                }
                return $xml2;
            }
        }

        $output->writeln('<error>ERROR: Failed to parse XML.</error>');
        $this->printXmlErrors($output, $errors1, 'SimpleXML errors');
        $this->printXmlErrors($output, $errors2, 'DOM errors');
        $output->writeln('<comment>Hint: Often "&" vs "&amp;", broken entities, or stray control chars cause this.</comment>');
        return null;
    }

    // ---------- Helpers ----------

    private function printXmlErrors(OutputInterface $output, array $errs, string $label): void
    {
        if (empty($errs)) {
            return;
        }
        $output->writeln("<comment>$label (up to 5):</comment>");
        $n = 0;
        foreach ($errs as $err) {
            $n++;
            $output->writeln(sprintf('  • line %d, col %d: %s', (int)($err->line ?? 0), (int)($err->column ?? 0), trim((string)($err->message ?? ''))));
            if ($n >= 5) {
                break;
            }
        }
    }

    private function parseRfc822ToTimestamp(string $pubDate): ?int
    {
        if ($pubDate === '') {
            return null;
        }
        $ts = strtotime($pubDate);
        if ($ts !== false) {
            return $ts;
        }
        try {
            $dt = new DateTimeImmutable($pubDate, new DateTimeZone('UTC'));
            return $dt->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Liest alle <category domain="category">Titel</category> und mappt via CATEGORY_MAP */
    private function resolveCategoryUidsFromItem(SimpleXMLElement $item): array
    {
        $uids = [];
        $unknown = [];

        if (isset($item->category)) {
            foreach ($item->category as $catNode) {
                $attrs = $catNode->attributes();
                $domain = $attrs['domain'] ?? null;
                if ((string)$domain !== 'category') {
                    continue;
                }
                $title = trim((string)$catNode); // Inhalt aus <![CDATA[...]]>
                if ($title === '') {
                    continue;
                }
                if (array_key_exists($title, $this->categoryMap)) {
                    $uids[] = (int)$this->categoryMap[$title];
                } else {
                    $unknown[] = $title;
                }
            }
        }

        // de-dupe & normalize
        $uids = array_values(array_unique(array_filter($uids, static fn($v) => $v > 0)));
        $unknown = array_values(array_unique(array_filter($unknown)));

        return [$uids, $unknown];
    }

    private function substituteBodytextHostnames(string $bodytext, string $oldHost = '', string $newHost = 'domain.de'): string
    {
        $newHost = trim($newHost);
        $oldHost = trim($oldHost);
        $_storage = '/fileadmin/'; //@ToDo: get storage from api
        if ($newHost !== '' || $oldHost !== '') {
            $bodytext = str_replace($oldHost . '/' . $this->folderMap['wp-dir'] . '/', $newHost . $_storage .$this->folderMap['typo3-dir']. '/', $bodytext);
        }
        return $bodytext;
    }

    /** Schreibt MM-Beziehungen sys_category_record_mm (uid_local=cat.uid, uid_foreign=news.uid) r */
    private function insertCategoryMMLinks(Connection $mmConn, array $catUids, int $newsUid): void
    {
        $sorting = 1;
        foreach ($catUids as $catUid) {
            $mmConn->insert('sys_category_record_mm', [
                'uid_local' => $catUid,
                'uid_foreign' => $newsUid,
                'tablenames' => 'tx_news_domain_model_news',
                'fieldname' => 'categories',
                'sorting' => $sorting++,
                'sorting_foreign' => 0,
            ]);
        }
    }

    /** Datei-URL an News hängen (fal_media), inkl. Dedupe & Sorting. */
    private function attachFileUrlToNews(
        string          $url,
        ResourceStorage $defaultStorage,
        Connection      $refConn,
        int             $pid,
        int             $now,
        int             $newsUid,
        array           &$refSeen,
        array           &$sortingPerNews,
        OutputInterface $output
    ): bool
    {
        $identifier = $this->mapWpUrlToFileadminIdentifier($url);
        if ($identifier === null) {
            $output->writeln('  <comment>Skip attachment: cannot map URL → identifier: ' . $url . '</comment>');
            return false;
        }

        $fileUid = $this->resolveFileUidViaDefaultStorage($defaultStorage, $identifier);
        if ($fileUid === null) {
            $output->writeln('  <comment>Skip attachment: file not found in DEFAULT storage: ' . $identifier . '</comment>');
            return false;
        }

        // Dedupe: pro News gleiche Datei nur einmal referenzieren
        if (isset($refSeen[$newsUid][$fileUid])) {
            return true;
        }

        try {
            $refConn->insert('sys_file_reference', [
                'pid' => $pid,
                'tstamp' => $now,
                'crdate' => $now,
                'hidden' => 0,
                'deleted' => 0,
                'uid_local' => $fileUid,
                'uid_foreign' => $newsUid,
                'tablenames' => 'tx_news_domain_model_news',
                'fieldname' => 'fal_media',
                'sorting_foreign' => $sortingPerNews[$newsUid] ?? 0,
                'showinpreview' => 1,
            ]);
            $sortingPerNews[$newsUid] = ($sortingPerNews[$newsUid] ?? 0) + 1;
            $refSeen[$newsUid][$fileUid] = true;

            $output->writeln('  <info>Attached file (default storage):</info> ' . $identifier);
            return true;
        } catch (\Throwable $e) {
            $output->writeln('  <error>Failed to create file reference: ' . $e->getMessage() . '</error>');
            return false;
        }
    }

    /** WP-URL → FAL-Identifier unter fileadmin/dingers.de/99_blog/... */
    private function mapWpUrlToFileadminIdentifier(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if ($path === '') {
            return null;
        }
        $pos = strpos($path, $this->folderMap['wp-dir']);
        if ($pos === false) {
            $basename = basename($path);
            return $basename !== '' ? '/' . $this->folderMap['typo3-dir'] . '/' . $basename : null;
        }
        $suffix = ltrim(substr($path, $pos + strlen($this->folderMap['wp-dir'])), '/');
        return '/' . $this->folderMap['typo3-dir'] . '/' . $suffix;
    }

    /** sys_file.uid für Identifier im DEFAULT Storage ermitteln */
    private function resolveFileUidViaDefaultStorage(ResourceStorage $storage, string $identifier): ?int
    {
        if ($identifier === '') {
            return null;
        }
        $candidate = '/' . ltrim($identifier, '/');

        try {
            if ($storage->hasFile($candidate)) {
                $file = $storage->getFile($candidate);
                return (int)$file->getUid();
            }
            // Fallback: ohne führenden Slash
            $alt = ltrim($candidate, '/');
            if ($alt !== $candidate && $storage->hasFile($alt)) {
                $file = $storage->getFile($alt);
                return (int)$file->getUid();
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }
}
