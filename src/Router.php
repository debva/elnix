<?php

namespace Debva\Elnix;

abstract class Router extends Env
{
    const CONTROLLER_PATH = 'Controllers';

    const MIDDLEWARE_PATH = 'Middleware';

    private $basepath;

    private $baseurl;

    private $path;

    private $class;

    public function __construct()
    {
        parent::__construct(join(DIRECTORY_SEPARATOR, [getcwd(), '..', '.env']));

        if (getenv('APP_PATH') === false) {
            die('APP_PATH not defined');
        }

        $args = isset($_SERVER['argv']) ? $_SERVER['argv'] : [];
        $this->command = isset($args[1]) ? $args[1] : null;
        $this->argument = isset($args[2]) ? array_slice($args, 2) : [];
    }

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
        $requests = $_REQUEST;
        $keys = is_string($keys) ? [$keys] : flatten($keys);

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
            return trim(join('/', [$this->baseurl, implode('/', $this->path)]), '/');
        }

        $route = $this->generateRoute(join('.', [$route, implode('.', $data)]));

        return $route['url'];
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
        $this->header('Access-Control-Expose-Headers: x-data-total, x-data-page');

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

        return [
            'columns' => $columns,
            'data' => $query->get()
        ];
    }

    private function generateRoute($route)
    {
        $controllers = [];
        $route = explode('.', $route);
        $prefix = array_merge(explode(DIRECTORY_SEPARATOR, env('APP_PATH')), [self::CONTROLLER_PATH]);
        $controllerpath = join(DIRECTORY_SEPARATOR, [$this->basepath, self::CONTROLLER_PATH]);

        foreach (scan_storage($controllerpath) as $filepath) {
            $controllers[] = join("\\", [
                get_namespace($file = join(DIRECTORY_SEPARATOR, [$controllerpath, $filepath])),
                pathinfo($file, PATHINFO_FILENAME)
            ]);
        }

        foreach ($controllers  as $controller) {
            $cpath = preg_replace("/Controller$/", "", $controller);
            $path = implode('/', array_slice(array_map('strtolower', explode("\\", $cpath)), count($prefix)));

            if (starts_with($path, $requestpath = implode('/', $route))) {
                $class = new $controller;
                $action = 'index';
                $data = array_filter(explode('/', trim(substr($requestpath, strlen($path)), '/')));

                if (!empty($data) and method_exists($class, reset($data))) {
                    $action = reset($data);
                    array_shift($data);
                }

                if ($action !== 'index' or ($action === 'index' and reset($data) !== 'index')) {
                    $reflection = new \ReflectionMethod($class, $action);
                    $dataRequired = $reflection->getNumberOfParameters();

                    if (count($data) === $dataRequired) {
                        return [
                            'routename' => implode('.', $route),
                            'controller' => $controller,
                            'action' => $action,
                            'data' => $data,
                            'url' => join('/', [$this->baseurl, $requestpath])
                        ];
                    }
                }
            }
        }

        die('Route not found');
    }

    private function loadRouter()
    {
        if (!env('APP_PATH')) {
            die('APP_PATH not defined in .env');
        }

        $this->basepath = rtrim(join(DIRECTORY_SEPARATOR, [getcwd(), '..', env('APP_PATH')]), DIRECTORY_SEPARATOR);

        $scriptname = str_replace('/public/index.php', '', $_SERVER['SCRIPT_NAME']);

        $this->baseurl = 'http' . (($_SERVER['SERVER_PORT'] == 443) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $scriptname;
        $this->path = explode('/', trim(str_replace($scriptname, '', parse_url($_SERVER['REQUEST_URI'])['path']), '/'));

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
    }

    private function loadController()
    {
        $route = $this->generateRoute(implode('.', $this->path));

        $controller = new $route['controller'];

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

        return $this->response($controller->{$route['action']}(...array_map('urldecode', $route['data'])));
    }

    public function start()
    {
        $this->loadRouter();

        $this->loadController();
    }
}
