<?php
namespace YPTheme;

class Security
{
    public static function init()
    {
        add_filter('rest_endpoints', [self::class, 'remove_rest_api_user_data']);
    }


    public static function remove_rest_api_user_data($rest_endpoints)
    {
        unset($rest_endpoints['/wp/v2/users'], $rest_endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        return $rest_endpoints;
    }

}