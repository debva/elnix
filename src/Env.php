<?php

namespace Debva\Elnix;

abstract class Env
{
    public function __construct()
    {
        if (file_exists($envpath = join(DIRECTORY_SEPARATOR, [str_replace(DIRECTORY_SEPARATOR . 'public', '', getcwd()), '.env']))) {
            $env = file_get_contents($envpath);
            $lines = explode(PHP_EOL, $env);

            foreach ($lines as $line) {
                $line = trim($line);

                if (!$line || strpos($line, '#') === 0) continue;
                list($name, $value) = explode('=', $line, 2);

                $name = trim($name);
                $value = trim($value, "\"");

                if (!array_key_exists($name, $_ENV) && !array_key_exists($name, $_SERVER)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }

            return;
        }

        die('Env file not exists');
    }
}
