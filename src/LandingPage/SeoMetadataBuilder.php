<?php

declare(strict_types=1);

namespace SenNoKuni\LandingPage;

final class SeoMetadataBuilder
{
    private LandingPageText $text;
    private LandingPageUrlBuilder $urlBuilder;

    public function __construct(string $siteBaseUrl, ?LandingPageText $text = null)
    {
        $this->text = $text ?? new LandingPageText();
        $this->urlBuilder = new LandingPageUrlBuilder($siteBaseUrl);
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $template
     * @return array{title: string, description: string, canonical: string, image: string, project_name: string, template_name: string}
     */
    public function build(array $agent, array $fields, array $template, string $currentUrl = '/'): array
    {
        $templateName = $this->text->plainText((string)($template['name'] ?? $agent['template_name'] ?? ''), 70);
        $projectName = $this->text->plainText((string)($template['project_name'] ?? ''), 70);
        $heroTitle = $this->text->plainText((string)($fields['hero_title']['value_text'] ?? ''), 70);
        $seoTitle = $this->text->plainText((string)($fields['seo_title']['value_text'] ?? ''), 70);

        $title = $seoTitle ?: ($heroTitle ?: ($templateName ?: ($projectName ?: 'LP')));
        if ($projectName !== '' && stripos($title, $projectName) === false) {
            $title .= ' | ' . $projectName;
        }

        $description = $this->text->plainText((string)($fields['seo_description']['value_text'] ?? ''), 160);
        if ($description === '') {
            $description = $this->text->plainText((string)($fields['hero_body']['value_text'] ?? ''), 160);
        }
        if ($description === '') {
            $description = $this->text->plainText((string)($template['description'] ?? $template['project_description'] ?? ''), 160);
        }
        if ($description === '') {
            $description = $title . ' information page. Please check the details and contact us from LINE or the inquiry form.';
        }

        $agentCode = (string)($agent['agent_code'] ?? '');
        $project = [];
        if (!empty($template['project_slug'])) {
            $project = ['slug' => $template['project_slug']];
        }
        $canonical = $agentCode !== '' && $agentCode !== 'preview'
            ? $this->urlBuilder->agentProjectUrl($agentCode, $project)
            : $this->urlBuilder->absoluteUrl($currentUrl);

        $image = $this->firstImage($fields, $template);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'project_name' => $projectName,
            'template_name' => $templateName,
        ];
    }

    /**
     * @param array{title: string, description: string, canonical: string, image: string, project_name: string, template_name: string} $seo
     */
    public function head(array $seo): string
    {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $seo['title'],
            'description' => $seo['description'],
            'url' => $seo['canonical'],
            'inLanguage' => 'ja',
            'about' => [
                '@type' => 'Service',
                'name' => $seo['project_name'] ?: $seo['template_name'] ?: $seo['title'],
                'description' => $seo['description'],
            ],
            'potentialAction' => [
                '@type' => 'ContactAction',
                'target' => $seo['canonical'],
            ],
        ];

        if ($seo['image'] !== '') {
            $jsonLd['image'] = $seo['image'];
            $jsonLd['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $seo['image'],
            ];
        }

        return "\n" .
            '<title>' . $this->escape($seo['title']) . "</title>\n" .
            '<meta name="description" content="' . $this->escape($seo['description']) . "\">\n" .
            '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">' . "\n" .
            '<link rel="canonical" href="' . $this->escape($seo['canonical']) . "\">\n" .
            '<meta property="og:type" content="website">' . "\n" .
            '<meta property="og:locale" content="ja_JP">' . "\n" .
            '<meta property="og:title" content="' . $this->escape($seo['title']) . "\">\n" .
            '<meta property="og:description" content="' . $this->escape($seo['description']) . "\">\n" .
            '<meta property="og:url" content="' . $this->escape($seo['canonical']) . "\">\n" .
            ($seo['image'] !== '' ? '<meta property="og:image" content="' . $this->escape($seo['image']) . "\">\n" : '') .
            '<meta name="twitter:card" content="' . ($seo['image'] !== '' ? 'summary_large_image' : 'summary') . "\">\n" .
            '<meta name="twitter:title" content="' . $this->escape($seo['title']) . "\">\n" .
            '<meta name="twitter:description" content="' . $this->escape($seo['description']) . "\">\n" .
            ($seo['image'] !== '' ? '<meta name="twitter:image" content="' . $this->escape($seo['image']) . "\">\n" : '') .
            '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $template
     */
    public function injectHead(string $html, array $agent, array $fields, array $template, string $currentUrl = '/'): string
    {
        $head = $this->head($this->build($agent, $fields, $template, $currentUrl));
        $patterns = [
            '/<title\b[^>]*>.*?<\/title>\s*/is',
            '/<meta\s+name=["\']description["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']robots["\'][^>]*>\s*/i',
            '/<link\s+rel=["\']canonical["\'][^>]*>\s*/i',
            '/<meta\s+property=["\']og:[^"\']+["\'][^>]*>\s*/i',
            '/<meta\s+name=["\']twitter:[^"\']+["\'][^>]*>\s*/i',
            '/<script\s+type=["\']application\/ld\+json["\'][^>]*>.*?<\/script>\s*/is',
        ];

        $html = preg_replace($patterns, '', $html) ?? $html;
        $html = preg_replace('/(<head\b[^>]*>)/i', '$1' . $head, $html, 1, $count) ?? $html;
        return $count ? $html : $head . $html;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $template
     */
    private function firstImage(array $fields, array $template): string
    {
        foreach (['og_image', 'hero_image_pc', 'hero_image', 'background_image', 'hero_image_sp'] as $key) {
            if (!empty($fields[$key]['value_file'])) {
                return $this->urlBuilder->absoluteUrl((string)$fields[$key]['value_file']);
            }
            if (!empty($fields[$key]['value_text'])) {
                return $this->urlBuilder->absoluteUrl((string)$fields[$key]['value_text']);
            }
        }

        if (!empty($template['thumbnail_url'])) {
            return $this->urlBuilder->absoluteUrl((string)$template['thumbnail_url']);
        }

        return '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
