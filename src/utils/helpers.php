<?php

if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null)
    {
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
        preg_match('#.*bitbucket\.org[:,/](.+?)\.git#', $remoteOrigin, $matches);

        if (!$matches) {
            throw new \Exception('Cannot get repository info. Are you sure this is a bitbucket repository?');
        }

        return $matches[1];
    }
}

if (!function_exists('o')) {
    function o($data, $color = 'white', $prefix = '', $end = "\033[0m".PHP_EOL)
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
            'gray' => "\033[0;90m",
        ];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    o($value, $color, $prefix, $end);
                } else {
                    if (!is_int($key)) {
                        o(ucfirst($key).': ', 'cyan', $prefix, $colors['nocolor']);
                        o($value, 'yellow', '');
                    } else {
                        o($value, $color, '');
                    }
                }
            }
        } else {
            echo $colors[$color].$prefix.$data;
            echo $end;
        }
    }
}

if (!function_exists('getUserInput')) {
    function getUserInput($question, $default = null)
    {
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
    function config($key, $default = null)
    {
        $appConfig = include __DIR__.'/../../config/app.php';

        return array_get($appConfig, $key, $default);
    }
}

if (!function_exists('userConfig')) {
    function userConfig($key, $default = null)
    {
        $userConfigFilePath = config('userConfigFilePath');
        $config = json_decode(file_get_contents($userConfigFilePath), true);

        if (is_null($key)) {
            return $config;
        }

        if (is_array($key)) {
            $arrayKey = key($key);
            $config[$arrayKey] = $key[$arrayKey];

            return file_put_contents(
                $userConfigFilePath,
                json_encode($config, JSON_PRETTY_PRINT)
            );
        }

        return array_get($config, $key, $default);
    }
}
