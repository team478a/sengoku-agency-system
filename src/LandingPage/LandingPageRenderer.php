<?php

declare(strict_types=1);

namespace SenNoKuni\LandingPage;

use Closure;
use Throwable;

final class LandingPageRenderer
{
    public function __construct(
        private readonly string $baseDir,
        private readonly Closure $tokenRenderer
    ) {
    }

    public function templateFile(array $agent, string $fallbackRelativePath = 'samurai/samurai.php'): string
    {
        $slug = trim((string)($agent['template_slug'] ?? ''));
        $htmlFile = trim((string)($agent['html_file'] ?? ''));
        $fallback = $this->baseDir . '/templates/' . $fallbackRelativePath;

        if ($slug === '' || $htmlFile === '') {
            return $fallback;
        }

        $file = $this->baseDir . '/templates/' . $slug . '/' . $htmlFile;
        return is_file($file) ? $file : $fallback;
    }

    public function renderFile(string $templateFile, array $agent, string $csrfToken): string
    {
        if (!is_file($templateFile)) {
            return '';
        }

        ob_start();
        try {
            include $templateFile;
            $html = (string)ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ($this->tokenRenderer)($html, $agent);
    }

    public function injectPreviewBar(string $html, string $previewBar): string
    {
        $withPreviewBar = preg_replace('/(<body[^>]*>)/i', '$1' . $previewBar, $html, 1, $replaceCount);
        return $replaceCount > 0 ? (string)$withPreviewBar : $previewBar . $html;
    }
}
