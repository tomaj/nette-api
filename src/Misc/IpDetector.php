<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

use Nette\Http\IRequest;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * The address of the client from Nette\Http\IRequest::getRemoteAddress(). Nette trusts the forwarded headers
 * (Forwarded, X-Forwarded-For) only from the proxies listed in http: proxy: of the Nette configuration,
 * so a client cannot forge its address. Without the request, the address of the TCP connection.
 *
 * With $warnUntrustedForwarding (the DI extension sets it in debug mode when http: proxy: is empty), the first request
 * with a forwarded header logs a warning, because behind a reverse proxy every client would have its address.
 */
final class IpDetector implements IpDetectorInterface
{
    private static bool $warned = false;

    public function __construct(
        private readonly ?IRequest $request = null,
        private readonly bool $warnUntrustedForwarding = false,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getRequestIp(): string
    {
        $ip = $this->request?->getRemoteAddress() ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $ip = is_string($ip) && $ip !== '' ? $ip : 'cli';
        if ($this->warnUntrustedForwarding && !self::$warned && $this->request !== null) {
            $header = $this->request->getHeader('X-Forwarded-For') !== null ? 'X-Forwarded-For' : ($this->request->getHeader('Forwarded') !== null ? 'Forwarded' : null);
            if ($header !== null) {
                self::$warned = true; // once per process, it is the same on every request
                self::warn(sprintf(
                    'The request has a %s header, but http: proxy: is empty, so the IP of every client is the address %s. Behind a reverse proxy or a load balancer list it, for example http: proxy: 10.0.0.0/8, otherwise logs, rate limits and IP restrictions of tokens see the proxy. Without a proxy ignore this, the header came from the client.',
                    $header,
                    $ip,
                ));
            }
        }

        return $ip;
    }

    private static function warn(string $message): void
    {
        try {
            Debugger::log($message, ILogger::WARNING);
        } catch (\Throwable $logError) {
            // Tracy without a log directory
            error_log($message . ' (the logger failed: ' . $logError->getMessage() . ')');
        }
    }
}
