<?php

if (!function_exists('array_get')) {
    // get data from array using dot notation
    function array_get($array, $key, $default = null) {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('getRepoPath')) {
    function getRepoPath()
    {
        $remoteOrigin = trim(exec('git config --get remote.origin.url'));
        preg_match('/.*:(.+?)\.git/', $remoteOrigin, $matches);
        return $matches[1];
    }
}

if (!function_exists('e')) {
    function e($data, $color = 'white', $prefix = '', $end = "\033[0m".PHP_EOL)
    {
        $colors = [
            'nocolor' => "\033[0m",
            'red' => "\033[0;31m",
            'green' => "\033[0;32m",
            'yellow' => "\033[0;33m",
            'blue' => "\033[0;34m",
            'magenta' => "\033[0;35m",
            'cyan' => "\033[0;36m",
            'white' => "\033[0;37m",
        ];

        echo $colors[$color].$prefix;

        echo is_array($data) ?
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ) :
            $data;

        echo $end;
    }
}

if (!function_exists('getUserInput')) {
    function getUserInput($question, $default = null) {
        if (is_null($default)) {
            $default = '';
        }

        $input = readline($question.' '.$default);

        if ($input === false) {
            return $default;
        }

        return $input;
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        $configFile = getenv('HOME').'/.bitbucket-rest-cli-config.json';

        if (!file_exists($configFile)) {
            return $default;
        }

        $config = json_decode(file_get_contents($configFile), true);

        return array_get($config, $key, $default);
    }
}
