<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\LandingPage\LandingPageText;
use SenNoKuni\LandingPage\LandingPageUrlBuilder;
use SenNoKuni\LandingPage\ResponsiveImageBuilder;
use SenNoKuni\Notification\TemplateVariableReplacer;

final class LandingPageNotificationFoundationTest extends TestCase
{
    public function testLandingPageUrlBuilderKeepsAgentProjectUrlContract(): void
    {
        $builder = new LandingPageUrlBuilder('https://sengoku-ai.com/');

        self::assertSame('https://sengoku-ai.com/a/agent001', $builder->agentProjectUrl('agent001'));
        self::assertSame(
            'https://sengoku-ai.com/a/agent001?project=ai-art-school',
            $builder->agentProjectUrl('agent001', ['slug' => 'ai-art-school'])
        );
    }

    public function testLandingPageUrlBuilderAppendsQueryParams(): void
    {
        $builder = new LandingPageUrlBuilder('https://sengoku-ai.com');

        self::assertSame('/a/abc?rt=token&rs=session', $builder->appendQueryParams('/a/abc', [
            'rt' => 'token',
            'rs' => 'session',
            'empty' => '',
        ]));
        self::assertSame('/a/abc?project=x&rt=token', $builder->appendQueryParams('/a/abc?project=x', [
            'rt' => 'token',
        ]));
    }

    public function testLandingPageTextStripsHtmlAndShortens(): void
    {
        $text = new LandingPageText();

        self::assertSame('Hello World', $text->plainText('<p>Hello   World</p>', 50));
        self::assertSame('abc...', $text->plainText('abcdefghi', 6));
    }

    public function testResponsiveImageBuilderKeepsPictureContract(): void
    {
        $builder = new ResponsiveImageBuilder();

        $html = $builder->picture('/pc.jpg', '/sp.jpg', 'Hero', 'hero-img');

        self::assertStringContainsString('<picture>', $html);
        self::assertStringContainsString('media="(max-width: 768px)"', $html);
        self::assertStringContainsString('src="/pc.jpg"', $html);
        self::assertStringContainsString('class="hero-img"', $html);
    }

    public function testTemplateVariableReplacerAcceptsBracedAndPlainKeys(): void
    {
        $replacer = new TemplateVariableReplacer();

        self::assertSame('Hello yamada / /a/yamada', $replacer->replace('Hello {name} / {lp_url}', [
            'name' => 'yamada',
            '{lp_url}' => '/a/yamada',
        ]));
    }
}
