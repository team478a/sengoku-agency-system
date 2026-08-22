<?php

declare(strict_types=1);

namespace SenNoKuni\LandingPage;

final class LandingPageUrlBuilder
{
    public function __construct(private readonly string $siteBaseUrl)
    {
    }

    /**
     * @param array<string, mixed>|null $project
     */
    public function agentProjectUrl(string $agentCode, ?array $project = null): string
    {
        $url = rtrim($this->siteBaseUrl, '/') . '/a/' . rawurlencode($agentCode);
        if (!empty($project['slug'])) {
            $url .= '?project=' . rawurlencode((string)$project['slug']);
        }
        return $url;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function appendQueryParams(string $url, array $params): string
    {
        $params = array_filter($params, static fn($value) => $value !== null && $value !== '');
        if (!$params) {
            return $url;
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }

    public function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if ($url[0] !== '/') {
            $url = '/' . $url;
        }
        return rtrim($this->siteBaseUrl, '/') . $url;
    }
}
