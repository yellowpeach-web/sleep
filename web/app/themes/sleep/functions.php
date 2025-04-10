<?php
namespace YPTheme;

use YPTheme\AcfBuilder\ThemeFieldBuilder;

// Load Bedrock's Composer Autoloader
require_once WP_CONTENT_DIR . '/../../vendor/autoload.php';

// Function to recursively include all PHP files in a directory
function include_all_php_files($directory)
{
    $directoryIterator = new \RecursiveDirectoryIterator($directory);
    $iterator = new \RecursiveIteratorIterator($directoryIterator);
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }
}

include_all_php_files(__DIR__ . '/post-types');
include_all_php_files(__DIR__ . '/option-pages');

AdminSettings::init();
AssetLoader::init();
Cleanup::init();
Content::init();
Debugging::init();
GutenbergBlocks::init();
HelperFunctions::init();
Images::init();
Security::init();
TimberSetup::init();
ThemeSetup::init();


class Theme
{
    private static $instance;

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->initialize_hooks();
    }

    private function initialize_hooks()
    {
        // Additional theme-wide hooks can be added here
    }
}
