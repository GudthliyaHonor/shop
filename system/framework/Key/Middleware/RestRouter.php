<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Middleware;


use Key\Exception\AppException;
use Key\Exception\NotFoundException;
use Key\Route;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Key\Abstracts\Middleware;
use Key\Routing\Routes;
use Psr\Http\Message\UriInterface;

/**
 * Middleware RestRouter
 * @package Key\Middleware
 */
class RestRouter extends Router
{

    public function getMethod()
    {

    }

    /**
     * @param UriInterface $uri
     * @return Route
     * @throws NotFoundException
     */
    protected function lookupRoute(UriInterface $uri)
    {
        /** @var \Key\Http\Request $request */
        $request = $this->container['request'];

        $routesObj = new Routes($this->container, $request, $this->container['config']['global.apiPrefix']);
        $route = $routesObj->lookup();

//         if (method_exists($uri, 'getBasePath')) {
//             $bathPath = $uri->getBasePath();
//         } else {
//             $bathPath = $uri->getPath();
//         }

//         $originalPath = '/' . trim($bathPath.'/'.$uri->getPath(), '/');
//         $this->originalPath = $originalPath;

//         $outputContentType = $this->determineContentType($originalPath);
//         $this->outputContentType = $outputContentType;

//         $path = $this->normalizePath($originalPath);
//         $matched = null;
//         $request_method = strtoupper($request->getMethod());

//         $fullMatched = false;
//         foreach ($this->routes as $key => $setting) {
//             $str = $request_method . ' ' . $path;
//             if ($str == $key) {
//                 $setting['method'] = $request_method;
//                 $this->loadClasses($setting);
//                 $route = new Route($uri, $setting, [], $key);
//                 $fullMatched = true;
//             }
//         }

//         if (!$fullMatched) {
//             $str = $request_method . ' ' . $path;

//             $routes = $this->routes;

//             error_log('path: ' . $path);
//             $pathPieces = array_values(array_filter(explode('/', $path)));
//             error_log(var_export($pathPieces, true));
//             $pathLead = $pathPieces[0];
//             error_log('path lead: ' . $pathLead);
//             if ($pathLead && extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
//                 $res = apcu_fetch('YDZS_ROUTES_LEAD');
//                 if ($res) {
//                     $leadRoutes = json_decode($res, true);
//                     error_log(var_export(array_rand($leadRoutes, 1), true));
//                     $method = strtoupper($this->request->getMethod());
//                     if (isset($leadRoutes[$method . ' ' . $pathLead])) {
//                         error_log('#### found in path lead: ' . $method . ' ' . $pathLead);
//                         $routes = $leadRoutes[$method . ' ' . $pathLead];
//                     }
//                     else {
//                         error_log('not found array key: ' . $method . ' ' . $pathLead);
//                     }
//                 }
//                 else {
//                     error_log('not found apcu key: YDZS_ROUTES_LEAD');
//                 }
//             }
//             error_log('checking routes: ' . count($routes));
//             foreach ($routes as $key => $setting) {
//                 if (empty($key)) {
//                     error_log('Route key must not empty!');
//                     continue;
//                 }
// //                $str = $request_method . ' ' . $path;

//                 $pattern = '/'.str_replace('/', '\/', $key).'$/';
//                 $pattern = $this->paramMatch($pattern);

//                 if (preg_match($pattern, $str, $matches) > 0) {
//                     array_shift($matches);
//                     $setting['method'] = $request_method;

//                     //$this->container['logger']->info(sprintf('Route matched: %s %s - %s', $request_method, $originalPath, $key));
//                     //$this->container['logger']->info('Route query: ' . var_export($matches, true));
//                     $this->loadClasses($setting);
//                     $route = new Route($uri, $setting, $matches, $key);
//                     break;
//                 }
//             }
//         }

        if ($route && $route->isEnvMatch()) {
            if ($route->isDisabled()) {
                throw new AppException('Route is diabled');
            }
            return $route;
        }
        else {
            $this->container['logger']->error('Route not found or env not matched');
        }

        // $this->response = $this->response->withHeader('Content-Type', $outputContentType);
        $this->container['logger']->error(sprintf('Route Not Found: %s %s', $request->getMethod(), $request->getUri()));
        $this->container['logger']->error(sprintf('Request uri: %s', $uri));
        // $this->container['logger']->error(sprintf('Request path: %s', $originalPath));
        $this->container['logger']->error(sprintf('Request ip: %s', $this->getRequestIp()));
        throw new NotFoundException($this->request, $this->response);
    }

    protected function paramMatch($str)
    {
        return preg_replace_callback('/\(\:([0-9a-zA-Z_]+)\)/', function ($matches) {
            return '(?<' . $matches[1] . '>[^\/]+)';
        }, $str);
    }

    protected static function startsWith($haystack, $needle)
    {
        return substr_compare ( $haystack , $needle , 0 , strlen ( $needle ) ) === 0 ;
    }

    protected function getRequestIp()
    {
        if(getenv('HTTP_CLIENT_IP')){
            $onlineip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR')){
            $onlineip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR')){
            $onlineip = getenv('REMOTE_ADDR');
        } else{
            $onlineip = null;
        }
        return $onlineip;
    }
}