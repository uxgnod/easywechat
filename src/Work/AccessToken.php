<?php

declare(strict_types=1);

namespace EasyWeChat\Work;

use EasyWeChat\Kernel\Contracts\RefreshableAccessToken;
use EasyWeChat\Kernel\Exceptions\HttpException;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use JetBrains\PhpStorm\ArrayShape;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AccessToken implements RefreshableAccessToken
{
    protected HttpClientInterface $httpClient;
    protected CacheInterface $cache;

    public function __construct(
        protected string $corpId,
        protected string $secret,
        protected ?string $key = null,
        ?CacheInterface $cache = null,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? new AccessTokenAwareClient();
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter(namespace: 'easywechat', defaultLifetime: 1500));
    }

    public function getKey(): string
    {
        return $this->key ?? $this->key = \sprintf('work.access_token.%s', $this->corpId);
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string
     * @throws \EasyWeChat\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getToken(): string
    {
        $token = $this->cache->get($this->getKey());

        if (!!$token && \is_string($token)) {
            return $token;
        }

        return $this->refresh();
    }


    /**
     * @return array<string, string>
     * @throws \EasyWeChat\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    #[ArrayShape(['access_token' => "string"])]
    public function toQuery(): array
    {
        return ['access_token' => $this->getToken()];
    }

    /**
     * @throws \EasyWeChat\Kernel\Exceptions\HttpException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function refresh(): string
    {
        $response = $this->httpClient->request('GET', '/cgi-bin/gettoken', [
                'query' => [
                    'corpid' => $this->corpId,
                    'corpsecret' => $this->secret,
                ],
            ])->toArray(false);

        if (empty($response['access_token'])) {
            throw new HttpException('Failed to get access_token: '.\json_encode($response, \JSON_UNESCAPED_UNICODE));
        }

        $this->cache->set($this->getKey(), $response['access_token'], \intval($response['expires_in']));

        return $response['access_token'];
    }
}
