<?php

declare(strict_types=1);

namespace SenNoKuni\Tests\Characterization;

use PHPUnit\Framework\TestCase;
use SenNoKuni\LandingPage\LandingPageRenderer;
use SenNoKuni\LandingPage\LandingPageText;
use SenNoKuni\LandingPage\LandingPageUrlBuilder;
use SenNoKuni\LandingPage\ResponsiveImageBuilder;
use SenNoKuni\LandingPage\SeoMetadataBuilder;
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

    public function testSeoMetadataBuilderKeepsLpHeadContract(): void
    {
        $builder = new SeoMetadataBuilder('https://sengoku-ai.com');
        $fields = [
            'hero_title' => ['value_text' => 'AIアート無料体験', 'value_file' => ''],
            'hero_body' => ['value_text' => '<p>はじめてでも楽しく学べます。</p>', 'value_file' => ''],
            'hero_image_pc' => ['value_text' => '/uploads/hero.jpg', 'value_file' => ''],
        ];
        $template = [
            'name' => 'AIアートLP',
            'project_name' => 'AIアート教室',
            'project_slug' => 'ai-art-school',
        ];

        $seo = $builder->build(['agent_code' => 'dir001'], $fields, $template, '/preview');
        $html = $builder->injectHead('<html><head><title>old</title></head><body></body></html>', ['agent_code' => 'dir001'], $fields, $template);

        self::assertSame('AIアート無料体験 | AIアート教室', $seo['title']);
        self::assertSame('https://sengoku-ai.com/a/dir001?project=ai-art-school', $seo['canonical']);
        self::assertSame('https://sengoku-ai.com/uploads/hero.jpg', $seo['image']);
        self::assertStringContainsString('<meta property="og:title" content="AIアート無料体験 | AIアート教室">', $html);
        self::assertStringContainsString('<script type="application/ld+json">', $html);
        self::assertStringNotContainsString('<title>old</title>', $html);
    }

    public function testLandingPageRendererKeepsTemplateRenderContract(): void
    {
        $baseDir = sys_get_temp_dir() . '/sengoku_lp_renderer_' . bin2hex(random_bytes(4));
        $templateDir = $baseDir . '/templates/demo';
        mkdir($templateDir, 0777, true);
        file_put_contents($templateDir . '/demo.php', '<html><body><?= $csrfToken ?> {{hero_title}}</body></html>');

        $renderer = new LandingPageRenderer(
            $baseDir,
            static fn(string $html, array $agent): string => strtr($html, [
                '{{hero_title}}' => (string)($agent['title'] ?? ''),
            ])
        );

        $templateFile = $renderer->templateFile([
            'template_slug' => 'demo',
            'html_file' => 'demo.php',
        ]);

        self::assertSame($templateDir . '/demo.php', $templateFile);
        self::assertSame(
            '<html><body>csrf123 Welcome</body></html>',
            $renderer->renderFile($templateFile, ['title' => 'Welcome'], 'csrf123')
        );
    }

    public function testLandingPageRendererInjectsPreviewBarIntoBody(): void
    {
        $renderer = new LandingPageRenderer(sys_get_temp_dir(), static fn(string $html, array $agent): string => $html);

        self::assertSame(
            '<html><body><div id="bar"></div><main>LP</main></body></html>',
            $renderer->injectPreviewBar('<html><body><main>LP</main></body></html>', '<div id="bar"></div>')
        );

        self::assertSame(
            '<div id="bar"></div><main>LP</main>',
            $renderer->injectPreviewBar('<main>LP</main>', '<div id="bar"></div>')
        );
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
