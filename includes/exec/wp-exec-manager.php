<?php

class ExecManager
{
    const PHP_PATH = 'php';
//    const PHP_PATH = '/usr/bin/php7.1';

    /**
     * @param string $cmd
     * @param string $path
     * @param bool   $inBackgroud
     * @param string $cmdParameters
     *
     * @return bool
     */
    public static function exec(string $cmd, string $path = '', bool $inBackgroud = true, string $cmdParameters = '')
    {
        $path = '' !== $path ? plugin_dir_path( __FILE__ ) . $path : '';

        $cmd = $cmd . ' ' . $cmdParameters . ' ' . $path;

        if (true === $inBackgroud) {
            self::execInBackground($cmd);
        } else {
            exec($cmd . ' 2>&1', $output);

            return $output;
        }

        return true;
    }

    protected static function execInBackground($cmd) {
        if (substr(php_uname(), 0, 7) == "Windows"){
            pclose(popen("start /B ". $cmd, "r"));
        }
        else {
            exec($cmd . " > /dev/null &");
        }
    }
}