<?php

declare(strict_types=1);

namespace SenNoKuni\LandingPage;

final class LandingPageText
{
    public function plainText(string $value, int $maxLength = 160): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($decoded)) ?: '');
        if ($maxLength > 0 && function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $maxLength) {
            return mb_substr($value, 0, max(0, $maxLength - 3), 'UTF-8') . '...';
        }
        if ($maxLength > 0 && !function_exists('mb_strlen') && strlen($value) > $maxLength) {
            return substr($value, 0, $maxLength - 3) . '...';
        }
        return $value;
    }
}
