<?php

if (!function_exists('pascal')) {
    function pascal($string)
    {
        $string = explode(' ', str_replace(['-', '_'], ' ', $string));
        $string = array_map('ucfirst', $string);
        return implode($string);
    }
}

if (!function_exists('camel')) {
    function camel($string)
    {
        $string = explode(' ', str_replace(['-', '_'], ' ', strtolower($string)));
        $firstString = array_shift($string);
        $string = array_merge([$firstString], array_map('ucfirst', $string));
        return implode($string);
    }
}

if (!function_exists('flatten')) {
    function flatten($array)
    {
        $result = [];
        foreach ($array as $element) {
            if (is_array($element)) {
                $result = array_merge($result, flatten($element));
            } else {
                $result[] = $element;
            }
        }
        return $result;
    }
}

if (!function_exists('sanitize_string')) {
    function sanitize_string(...$string)
    {
        $string = flatten($string);

        if (count($string) === 1) {
            return htmlspecialchars(end($string));
        }

        return $string;
    }
}

if (!function_exists('generate_string')) {
    function generate_string($length = 10)
    {
        $string = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        for ($i = 0; $i < $length; $i++) {
            $randomIndex = rand(0, strlen($characters) - 1);
            $string .= $characters[$randomIndex];
        }

        return $string;
    }
}

if (!function_exists('env')) {
    function env(...$env)
    {
        $env = flatten($env);

        if (count($env) === 1) {
            return getenv(end($env));
        }

        return array_map(function ($e) {
            return getenv($e);
        }, $env);
    }
}

if (!function_exists('scan_storage')) {
    function scan_storage($dir, $dirpath = null)
    {
        $result = [];
        $files = scandir($dir);

        foreach ($files as $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);

            if (!is_dir($path)) {
                $result[] = join(DIRECTORY_SEPARATOR, [$dirpath, $value]);
            } else if ($value != "." && $value != "..") {
                $result = array_merge(
                    $result,
                    scan_storage(
                        $path,
                        (is_null($dirpath)
                            ? $value
                            : join(DIRECTORY_SEPARATOR, [$dirpath, $value]))
                    )
                );
            }
        }

        return $result;
    }
}

if (!function_exists('get_namespace')) {
    function get_namespace($filepath)
    {
        $namespace = '';
        $tokens = token_get_all(file_get_contents($filepath));

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                for ($i = 1; $i < count($tokens); $i++) {
                    if ($tokens[$i] === '{' || $tokens[$i] === ';') break;
                    elseif (is_array($tokens[$i]) and $tokens[$i][1] !== 'namespace') {
                        $namespace .= trim($tokens[$i][1]);
                    } else continue;
                }
                break;
            }
        }

        return $namespace;
    }
}

if (!function_exists('equal_start_with')) {
    function equal_start_with($string, $search)
    {
        return $string === substr($search, 0, strlen($string));
    }
}
