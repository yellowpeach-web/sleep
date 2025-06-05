<?php
namespace YPTheme;

class LayoutOptions
{
    private static $contentSections = [
        'appBanner' => true,
        'appButtons' => true,
        'breadcrumbNav' => true,
        'cookieBanner' => true,
        'footer' => true,
        'header' => true,
        'sidebar' => true,
        'storeBanner' => true,
        'subNav' => true
    ];

    private static $queryParam = 'layoutOptions';

    public static function init()
    {
        // Run on init or early theme setup hook
        add_action('init', [self::class, 'prepare']);
    }

    public static function isVisible(string $section): bool
    {
        return self::$contentSections[$section] ?? true;
    }

    private static function togglePartnerWebview(): void
    {
        $hiddenSections = [
            'appBanner',
            'appButtons',
            'subNav',
            'breadcrumbNav',
            'cookieBanner',
            'footer',
            'header',
            'sidebar',
            'storeBanner'
        ];

        foreach ($hiddenSections as $section) {
            self::$contentSections[$section] = false;
        }
    }

    public static function prepare(): void
    {
        if (!isset($_GET[self::$queryParam])) {
            return;
        }

        $value = base64_decode($_GET[self::$queryParam]);
        $options = explode(',', $value);

        $params = [];
        foreach ($options as $opt) {
            $p = explode('=', $opt);
            if (count($p) === 2) {
                $params[$p[0]] = $p[1];
            }
        }

        foreach ($params as $key => $value) {
            if (array_key_exists($key, self::$contentSections) && $value === 'off') {
                self::$contentSections[$key] = false;
            }
        }

        if (isset($params['partnerWebview']) && $params['partnerWebview'] === 'on') {
            self::togglePartnerWebview();
        }
    }
}
