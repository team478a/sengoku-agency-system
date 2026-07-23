<?php

declare(strict_types=1);

namespace SenNoKuni\LandingPage;

final class ResponsiveImageBuilder
{
    public function picture(string $pc, string $sp, string $alt = '', string $class = ''): string
    {
        if ($pc === '' && $sp === '') {
            return '';
        }

        $html = '<picture>';
        if ($sp !== '') {
            $html .= '<source media="(max-width: 768px)" srcset="' . $this->escape($sp) . '">';
        }
        $html .= '<img src="' . $this->escape($pc !== '' ? $pc : $sp) . '" alt="' . $this->escape($alt) . '"'
            . ($class !== '' ? ' class="' . $this->escape($class) . '"' : '') . '>';
        $html .= '</picture>';
        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
