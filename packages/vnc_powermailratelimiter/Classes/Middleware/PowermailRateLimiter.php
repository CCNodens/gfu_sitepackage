<?php
namespace Vancado\VncPowermailratelimiter\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\HtmlResponse;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Log\LogManager;

class PowermailRateLimiter implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    /**
     * Zeit in Sekunden, die zwischen zwei Versendungen von derselben IP liegen muss.
     * @var int
     */
    private const SUBMISSION_INTERVAL = 15;

    private ResponseFactoryInterface $responseFactory;
    private CacheManager $cacheManager;

    public function __construct(ResponseFactoryInterface $responseFactory, CacheManager $cacheManager)
    {
        $this->responseFactory = $responseFactory;
        $this->cacheManager = $cacheManager;
        // Logger initialisieren
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->logger->info('PowermailRateLimiter triggered', [
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
        ]);

        $parsedBody = $request->getParsedBody();
 \TYPO3\CMS\Core\Utility\DebugUtility::debug($request);
        // Nur bei Powermail POST-Requests aktiv werden
        if ($request->getMethod() === 'POST'
            && isset($parsedBody['tx_powermail_pi1']['action'])
            && $parsedBody['tx_powermail_pi1']['action'] === 'create'
        ) {
            $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1';
            $cache = $this->cacheManager->getCache('vnc_ratelimit_cache');
            $cacheIdentifier = 'powermail-ip-' . md5($clientIp);

            if ($cache->has($cacheIdentifier)) {
                // IP ist bekannt, Verarbeitung blockieren
                $errorMessage = '<h1>Error 429: Too Many Requests</h1><p>Please wait a moment before submitting the form again.</p>';
                return (new HtmlResponse($errorMessage, 429));
            }

            // IP für das definierte Intervall sperren
            $cache->set($cacheIdentifier, 'blocked', [], self::SUBMISSION_INTERVAL);
        }

        // Request an die nächste Middleware weitergeben
        return $handler->handle($request);
    }
}