<?php

declare(strict_types=1);

namespace SenNoKuni\Notification;

final class TemplateVariableReplacer
{
    /**
     * @param array<string, mixed> $variables
     */
    public function replace(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $token = str_starts_with($key, '{') ? $key : '{' . $key . '}';
            $replacements[$token] = (string)$value;
        }
        return strtr($template, $replacements);
    }
}
