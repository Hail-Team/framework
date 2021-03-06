<?php

namespace Hail\Http\Middleware;

use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface
};

class ClientIp implements MiddlewareInterface
{
    /**
     * @var bool
     */
    private $remote = false;

    /**
     * @var string The attribute name
     */
    private $attribute = 'client-ip';

    /**
     * @var array The trusted proxy headers
     */
    private $proxyHeaders = [];

    /**
     * @var array The trusted proxy ips
     */
    private $proxyIps = [];

    /**
     * Configure the proxy.
     *
     * @param array $ips
     * @param array $headers
     *
     * @return self
     */
    public function proxy(
        array $ips = [],
        array $headers = [
            'Forwarded',
            'Forwarded-For',
            'X-Forwarded',
            'X-Forwarded-For',
            'X-Cluster-Client-Ip',
            'Client-Ip',
        ]
    ) {
        $this->proxyIps = $ips;
        $this->proxyHeaders = $headers;

        return $this;
    }

    /**
     * To get the ip from a remote service.
     * Useful for testing purposes on localhost.
     *
     * @param bool $remote
     *
     * @return self
     */
    public function remote($remote = true)
    {
        $this->remote = $remote;

        return $this;
    }

    /**
     * Set the attribute name to store client's IP address.
     *
     * @param string $attribute
     *
     * @return self
     */
    public function attribute($attribute)
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $this->getIp($request);

        return $handler->handle(
            $request->withAttribute($this->attribute, $ip)
        );
    }

    /**
     * Detect and return the ip.
     *
     * @param ServerRequestInterface $request
     *
     * @return string|null
     */
    private function getIp(ServerRequestInterface $request)
    {
        if ($this->remote && ($remoteIp = $this->getRemoteIp()) !== null) {
            // Found IP address via remote service.
            return $remoteIp;
        }

        $localIp = $this->getLocalIp($request);

        if ($this->proxyIps && !\in_array($localIp, $this->proxyIps, true)) {
            // Local IP address does not point at a known proxy, do not attempt
            // to read proxied IP address.
            return $localIp;
        }

        $proxiedIp = $this->getProxiedIp($request);

        if ($proxiedIp !== null) {
            // Found IP address via proxy-defined headers.
            return $proxiedIp;
        }

        return $localIp;
    }

    /**
     * Returns the IP address from remote service.
     *
     * @return string|null
     */
    private function getRemoteIp(): ?string
    {
        $ip = \file_get_contents('http://ipecho.net/plain');
        if (self::isValid($ip)) {
            return $ip;
        }

        return null;
    }

    /**
     * Returns the first valid proxied IP found.
     *
     * @param ServerRequestInterface $request
     *
     * @return string|null
     */
    private function getProxiedIp(ServerRequestInterface $request): ?string
    {
        foreach ($this->proxyHeaders as $name) {
            if ($request->hasHeader($name)) {
                $ip = self::getHeaderIp($request->getHeaderLine($name));
                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Returns the remote address of the request, if valid.
     *
     * @param ServerRequestInterface $request
     *
     * @return string|null
     */
    private function getLocalIp(ServerRequestInterface $request): ?string
    {
        $server = $request->getServerParams();
        if (!empty($server['REMOTE_ADDR']) && self::isValid($server['REMOTE_ADDR'])) {
            return $server['REMOTE_ADDR'];
        }

        return null;
    }

    /**
     * Returns the first valid ip found in the header.
     *
     * @param string $header
     *
     * @return string|null
     */
    private static function getHeaderIp($header): ?string
    {
        foreach (\array_map('\trim', \explode(',', $header)) as $ip) {
            if (self::isValid($ip)) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Check that a given string is a valid IP address.
     *
     * @param string $ip
     *
     * @return bool
     */
    private static function isValid($ip)
    {
        return \filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }
}