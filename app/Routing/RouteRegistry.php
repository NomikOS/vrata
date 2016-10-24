<?php

namespace App\Routing;

use Illuminate\Support\Facades\Storage;
use Laravel\Lumen\Application;
use Webpatser\Uuid\Uuid;

/**
 * Class RouteRegistry
 * @package App\Routing
 */
class RouteRegistry
{
    /**
     * @var array
     */
    protected $routes = [];

    /**
     * RouteRegistry constructor.
     */
    public function __construct()
    {
        $this->parseConfigRoutes();
    }

    /**
     * @param RouteContract $route
     */
    public function addRoute(RouteContract $route)
    {
        $this->routes[] = $route;
    }

    /**
     * @return void
     */
    private function parseConfigRoutes()
    {
        $config = config('gateway');
        if (empty($config)) return;

        collect($config['routes'])->each(function ($route, $key) {
            $routeObject = new Route([
                'id' => (string)Uuid::generate(4),
                'method' => $route['method'],
                'path' => config('gateway.global.prefix', '/') . $route['path'],
                'alias' => $key
            ]);

            collect($route['actions'])->each(function ($action, $alias) use ($routeObject) {
                $routeObject->addAction(new Action([
                    'url' => $action['path'],
                    'service' => $action['service'],
                    'method' => $action['method'],
                    'sequence' => $action['sequence'] ?? 0,
                    'alias' => $alias,
                    'critical' => $action['critical'] ?? false
                ]));
            });

            $this->addRoute($routeObject);
        });
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->routes);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getRoutes()
    {
        return collect($this->routes);
    }

    /**
     * @param string $id
     * @return RouteContract
     */
    public function getRoute($id)
    {
        return collect($this->routes)->first(function ($route) use ($id) {
            return $route->getId() == $id;
        });
    }

    /**
     * @param Application $app
     */
    public function bind(Application $app)
    {
        $this->getRoutes()->each(function ($route) use ($app) {
            $method = strtolower($route->getMethod());

            $app->{$method}($route->getPath(), [
                'uses' => 'App\Http\Controllers\GatewayController@' . $method,
                'middleware' => [ 'auth', 'helper:' . $route->getId() ]
            ]);
        });
    }

    /**
     * @param string $filename
     * @return RouteRegistry
     */
    public static function initFromFile($filename = null)
    {
        $registry = new self;
        $filename = $filename ?: 'routes.json';

        if (! Storage::exists($filename)) return $registry;
        $routes = json_decode(Storage::get($filename), true);
        if ($routes === null) return $registry;

        collect($routes)->each(function ($routeDetails) use ($registry) {
            $route = new Route([
                'id' => $routeDetails['id'],
                'method' => $routeDetails['method'],
                'path' => $routeDetails['path']
            ]);

            $route->addAction(new Action([
                'method' => $routeDetails['method'],
                'url' => $routeDetails['service_url'],
                'service' => $routeDetails['service']
            ]));

            $registry->addRoute($route);
        });

        return $registry;
    }
}