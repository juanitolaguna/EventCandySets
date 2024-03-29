<?php declare(strict_types=1);

namespace EventCandy\Sets;

class Utils
{
    const LOGGING = true;

    public static function log($message)
    {
        if (!self::LOGGING) {
            return;
        }

        $date = '[' . date("Y-m-d H:i:s") . '] : ';
        $class = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] . '->';
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] . ' | ';
        file_put_contents('/var/www/html/public/log.txt', $date . $class . $caller . $message . "\n", FILE_APPEND);
    }
}
