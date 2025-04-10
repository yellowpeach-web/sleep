<?php

namespace YPTheme;

use Exception;
use Symfony\Component\VarDumper\VarDumper;

class Debugging
{
    public static function init()
    {
        // Initialization code if needed
    }

    public static function theme_error_handling($error, $backtrace)
    {
        $called_from = $backtrace[0]['file'];
        $line = $backtrace[0]['line'];
        $message = "$error error in $called_from line $line";
        throw new Exception($message);
    }

    /**
     * Dump variable using Symfony's VarDumper.
     */
    public static function d(...$args)
    {
        echo '<pre>';
        foreach ($args as $arg) {
            VarDumper::dump($arg);
        }
        echo '</pre>';
    }

    public static function dd(...$args)
    {
        self::d(...$args);
        die();
    }
}
