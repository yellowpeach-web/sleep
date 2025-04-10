<?php
namespace YPTheme;

class Content
{
    public static function init()
    {
        add_filter('excerpt_length', fn() => 35, 999);
        add_filter('excerpt_more', fn() => '...');

    }

}