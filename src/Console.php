<?php

namespace Debva\Elnix;

class Console extends Env
{
    private $command;

    private $argument = [];

    public function __construct()
    {
        parent::__construct();

        if (getenv('APP_PATH') === false) {
            die('APP_PATH not defined');
        }

        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];
        $this->command = isset($args[1]) ? $args[1] : null;
        $this->argument = isset($args[2]) ? array_slice($args, 2) : [];
    }

    private function write($text, $color = null, $background = null)
    {
        $palette = [
            'reset-color'   => 0,
            'text-black'    => 30,
            'text-red'      => 31,
            'text-green'    => 32,
            'text-yellow'   => 33,
            'text-blue'     => 34,
            'text-magenta'  => 35,
            'text-cyan'     => 36,
            'text-white'    => 37,
            'bg-black'      => 40,
            'bg-red'        => 41,
            'bg-green'      => 42,
            'bg-yellow'     => 43,
            'bg-blue'       => 44,
            'bg-magenta'    => 45,
            'bg-cyan'       => 46,
            'bg-white'      => 47,
        ];

        if (is_null($color) and is_null($background)) {
            return print("{$text}\033[{$palette['reset-color']}m");
        }

        if ((!is_null($color) xor !is_null($background)) and (isset($palette[$color]) or isset($palette[$background]))) {
            $color = isset($palette[$color]) ? $palette[$color] : $palette[$background];
            return print("\033[{$color}m{$text}\033[{$palette['reset-color']}m");
        }

        if ((!is_null($color) and !is_null($background)) and (isset($palette[$color]) and isset($palette[$background]))) {
            return print("\033[{$palette[$color]};{$palette[$background]}m{$text}\033[{$palette['reset-color']}m");
        }

        return print($text);
    }

    private function loadConsole()
    {
        if (is_null($this->command)) {
            $commands = [];
            foreach ($this->defaultCommand() as $command => $items) {
                $cmd = explode(':', $command);
                if (count($cmd) > 1) {
                    list($parent, $child) = $cmd;
                    $commands[$parent][$child] = isset($items['description']) ? $items['description'] : '';
                } else {
                    $commands[end($cmd)] = isset($items['description']) ? $items['description'] : '';
                }
            }

            $this->write("_____________________________\n");
            $this->write("\nElnix Framework ");
            $this->write(' ' . Application::VERSION . ' ', 'text-white', 'bg-red');
            $this->write("\n_____________________________\n");
            $this->write("\ncommands\n", 'text-yellow');

            foreach ($commands as $command => $items) {
                if (is_array($items)) {
                    $this->write("{$command}\n", 'text-yellow');
                    foreach ($items as $subcommand => $description) {
                        $this->write(' - ');
                        $this->write("{$command}:{$subcommand}", 'text-green');
                        $this->write("\n   {$description}\n\n");
                    }
                } else {
                    $this->write(' - ');
                    $this->write($command, 'text-green');
                    $this->write("\n   {$items}\n\n");
                }
            }

            exit;
        }

        if (in_array($this->command, array_keys($this->defaultCommand()))) {
            $command = $this->defaultCommand($this->command);
            list($description, $argument, $handle) = array_values($command);

            if (!empty($argument)) {
                preg_match_all('/{(?P<key>\w+)(?::(?P<description>\w+(?:\s+\w+)*))?}/', $argument, $matches);

                $argument = [];
                foreach ($matches['key'] as $index => $key) {
                    $argument[$key] = $matches['description'][$index];
                }

                if (count($this->argument) !== count($argument)) {
                    $this->error('Argument invalid');
                    exit;
                }

                foreach ($this->argument as $index => $arg) {
                    $this->argument[array_keys($argument)[$index]] = $arg;
                }
            }

            return $handle();
        }

        $this->warning('Command not found');
        exit;
    }

    private function defaultCommand($command = null)
    {
        $commands = [
            'env' => [
                'description' => 'Generate Env File',
                'argument' => null,
                'handle' => function () {
                    return;
                }
            ],
            'key:generate' => [
                'description' => 'Generate App Key',
                'argument' => null,
                'handle' => function () {
                    return;
                }
            ],
            'make:controller' => [
                'description' => 'Make Controller',
                'argument' => '{name:Controller Name}',
                'handle' => function () {
                    return $this->generateFile(
                        'Controller',
                        "{$this->argument('name')}Controller",
                        'controllers',
                        'controller.stub.php',
                        '{{name}}',
                        $this->argument('name')
                    );
                }
            ],
            'make:middleware' => [
                'description' => 'Make Middleware',
                'argument' => '{name:Middleware Name}',
                'handle' => function () {
                    return $this->generateFile(
                        'Middleware',
                        $this->argument('name'),
                        'middleware',
                        'middleware.stub.php',
                        '{{name}}',
                        $this->argument('name')
                    );
                }
            ]
        ];

        return is_null($command) ? $commands : $commands[$command];
    }

    protected function generateFile($title, $name, $folder, $stub, $search, $replace)
    {
        $stub = file_get_contents(join(DIRECTORY_SEPARATOR, [__DIR__, '..', 'stubs', $stub]));
        $stub = str_replace($search, $replace, $stub);

        if (!file_exists($path = join(DIRECTORY_SEPARATOR, [getcwd(), getenv('APP_PATH'), $folder, pathinfo($name, PATHINFO_DIRNAME)]))) {
            mkdir($path, 0755, true);
        }

        if (file_exists($filepath = join(DIRECTORY_SEPARATOR, [$path, basename($name) . '.php']))) {
            $this->error($title . basename($name) . ' exists');
            exit;
        }

        file_put_contents($filepath, $stub);
        $this->success("{$title} created successfully");
    }

    protected function success($text)
    {
        $this->write("\n Success ", 'text-black', 'bg-green');
        $this->write(" {$text}\n", 'text-white');
    }

    protected function warning($text)
    {
        $this->write("\n Warning ", 'text-black', 'bg-yellow');
        $this->write(" {$text}\n", 'text-white');
    }

    protected function error($text)
    {
        $this->write("\n Error ", 'text-white', 'bg-red');
        $this->write(" {$text}\n", 'text-white');
    }

    protected function info($text)
    {
        $this->write("\n Info ", 'text-black', 'bg-cyan');
        $this->write(" {$text}\n", 'text-white');
    }

    protected function input($text, $color = null, $background = null)
    {
        $this->write($text, $color, $background);
        $command = trim(fgets(STDIN));
        return explode(' ', $command);
    }

    protected function argument(...$keys)
    {
        $keys = is_string($keys) ? [$keys] : flatten($keys);

        if (count($keys) === 1) {
            return $this->argument[end($keys)];
        }

        if (count($keys) > 1) {
            return array_map(function ($key) {
                return $this->argument[$key];
            }, $keys);
        }

        return $this->argument;
    }

    public function start()
    {
        $this->loadConsole();
    }
}
