<?php
namespace YPTheme;

class Security
{
    public static function init()
    {
        add_filter( 'rest_pre_dispatch', [self::class, 'remove_rest_api_user_data'], 10, 3);
    }

    public static function remove_rest_api_user_data($response, $server, $request)
    {

        $route = $request->get_route();

        if (
            preg_match( '#^/wp/v2/users(?:/.*)?$#', $route )
            && ! is_user_logged_in()
        ) {
            return new \WP_Error(
                'rest_forbidden',
                'Not allowed.',
                array( 'status' => 403 )
            );
        }

        return $response;

    }

}