<?php

namespace Debva\Elnix;

abstract class Router extends Env
{
    protected $appPath;

    private $host;

    private $requestPath;

    private $controllerPath;

    private $controller;

    private $data;

    private $class;

    abstract public function setAppPath($path);

    protected function header($header)
    {
        return header($header);
    }

    protected function method($method = null)
    {
        return is_null($method)
            ? $_SERVER['REQUEST_METHOD']
            : $_SERVER['REQUEST_METHOD'] === $method;
    }

    protected function request(...$keys)
    {
        $keys = is_string($keys) ? [$keys] : flatten($keys);

        $requests = $_REQUEST;

        if (!empty($body = file_get_contents("php://input"))) {
            $requests = array_merge($requests, json_decode($body, true));
        }

        if (!empty($keys)) {
            if (count($keys) > 1) {
                array_walk($keys, function ($key) use (&$result, $requests) {
                    if (isset($requests[$key])) {
                        $result[$key] = $requests[$key];
                    }
                });
                return $result;
            }

            return isset($requests[end($keys)]) ? $requests[end($keys)] : null;
        }

        return $requests;
    }

    protected function response($response = [])
    {
        $this->header('Content-Type: application/json');
        ini_set('zlib.output_compression', 'on');
        return print(json_encode($response));
    }

    protected function redirect($route)
    {
        $this->header("Location: {$route}");
        exit;
    }

    protected function route($route = null, $data = [])
    {
        if (!is_array($data)) {
            die('Route data must be array');
        }

        if (is_null($route)) {
            return trim(join('/', [$this->host, implode('/', $this->requestPath)]), '/');
        }

        $path = implode('/', array_merge(explode('.', $route), $data));

        $path = trim(str_replace([DIRECTORY_SEPARATOR, 'index'], ['/', ''], strtolower($path)), '/');

        return join('/', [$this->host, $path]);
    }

    protected function paginate($query, $columns)
    {
        $page = (int) $this->request('page') ?: 1;
        $limit = (int) $this->request('limit') ?: 10;

        $search = $this->request('search');
        $filters = $this->request('filters') ?: [];
        $sorting = $this->request('sorting') ?: [];

        $this->header("x-data-page: {$page}");
        $this->header("x-data-total: {$query->count()}");

        $query = $query->limit($limit)->offset(($page - 1) * $limit);

        if (!empty($search)) {
            foreach ($columns as $column) {
                if ($column['searchable']) {
                    $query->orWhere($column['key'], 'LIKE', "%{$search}%");
                }
            }
        }

        $filterables = array_merge(...array_map(function ($column) {
            return [$column['key'] => $column['filterable']];
        }, $columns));

        foreach ($filters as $column => $search) {
            if ($filterables[$column]) {
                $query->where($column, 'LIKE', "%{$search}%");
            }
        }

        $sortables = array_merge(...array_map(function ($column) {
            return [$column['key'] => $column['sortable']];
        }, $columns));

        foreach ($sorting as $column => $sortBy) {
            if ($sortables[$column]) {
                $query->orderBy($column, $sortBy);
            }
        }

        return $query->get();
    }

    private function generateRoute()
    {
        $directory = '';
        $appPath = join(DIRECTORY_SEPARATOR, [$this->appPath, 'controllers']);
        $scriptName = str_replace('/public/index.php', '', $_SERVER['SCRIPT_NAME']);

        $this->host = 'http' . (($_SERVER['SERVER_PORT'] == 443) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $scriptName;
        $this->requestPath = $requestPath = explode('/', trim(str_replace($scriptName, '', parse_url($_SERVER['REQUEST_URI'])['path']), '/'));

        foreach ($requestPath as $path) {
            $path = array_shift($requestPath);

            if (is_file($controllerPath = join(DIRECTORY_SEPARATOR, [$appPath, $directory, $controller = pascal("{$path}Controller.php")]))) {
                $this->controllerPath = $controllerPath;
                $this->controller = str_replace('.php', '', $controller);
                $this->data = $requestPath;
                break;
            }

            $directory = join(DIRECTORY_SEPARATOR, [$directory, $path]);
        }

        if (empty($this->controller) or reset($this->data) === 'index') {
            die('Route not found');
        }

        $this->class = new Anonymous;

        foreach ([
            'header' => function ($header) {
                return $this->header($header);
            },
            'method' => function ($method = null) {
                return $this->method($method);
            },
            'request' => function (...$keys) {
                return $this->request(...$keys);
            },
            'response' => function ($response = []) {
                return $this->response($response);
            },
            'redirect' => function ($route) {
                return $this->redirect($route);
            },
            'route' => function ($route = null, $data = []) {
                return $this->route($route, $data);
            },
            'paginate' => function ($query, $columns) {
                return $this->paginate($query, $columns);
            },
        ] as $name => $callable) {
            $this->class->macro($name, $callable);
        }
    }

    private function loadMiddleware($middlewares, $action)
    {
        $current = 0;
        $path = join(DIRECTORY_SEPARATOR, [$this->appPath, 'middleware']);

        $next = function ($router) use (&$middlewares, &$current, &$next, $path) {
            if (count($middlewares) >= ($current + 1)) {
                $middleware = pascal($middlewares[$current++]);

                if (file_exists($middlewarePath = join(DIRECTORY_SEPARATOR, [$path, "{$middleware}.php"]))) {
                    require_once($middlewarePath);
                    return (new $middleware)->handle($router,  $next);
                }
            }
            return true;
        };

        return $next($this->class);
    }

    private function loadController()
    {
        require_once($this->controllerPath);

        $controller = new $this->controller;

        $data = $this->data;

        $action = empty($data) ? 'index' : reset($data);

        if (method_exists($controller, $action)) {
            array_shift($data);

            $reflection = new \ReflectionMethod($controller, $action);
            $totalDataRequired = $reflection->getNumberOfParameters();

            if (count($data) !== $totalDataRequired) {
                die('The route data entered does not match ');
            }

            $this->loadMiddleware($controller->middleware, $action);

            $controller->app = $this->class;

            $controller->crypt = new Crypt;

            $controller->db = new Database(...env(
                'DB_HOST',
                'DB_PORT',
                'DB_DATABASE',
                'DB_USERNAME',
                'DB_PASSWORD',
                'DB_DRIVER'
            ));

            $controller->app->header('Access-Control-Allow-Origin: *');
            $controller->app->header('Access-Control-Allow-Headers: *');

            return $this->response($controller->$action(...array_map('urldecode', $data)));
        }
    }

    public function start()
    {
        $this->generateRoute();

        $this->loadController();
    }
}
