<?php
namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private array $routeGroups = [];

    /**
     * Adds a new route to the routing table.
     *
     * @param string $method The HTTP method (e.g., 'GET', 'POST').
     * @param string $path The URL path for the route (e.g., '/', '/users/{id}').
     * @param array $handler An array containing the FQCN of the controller and the method name (e.g., [App\Controllers\UserController::class, 'show']).
     */
    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->formatPath($path),
            'handler' => $handler,
            'middlewares' => [], // Initialize with no specific middlewares
        ];
    }

    public function get(string $path, array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, array $handler): void { $this->add('PUT', $path, $handler); }
    public function delete(string $path, array $handler): void { $this->add('DELETE', $path, $handler); }

    /**
     * Defines a group of routes with a common prefix and/or middlewares.
     *
     * @param string $prefix The URL prefix for routes in this group.
     * @param callable $callback A callable function where routes for this group are defined.
     * @param array $middlewares An array of middleware FQCNs (strings) to apply to all routes in this group.
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        // Store current routing state to correctly apply group settings.
        $this->routeGroups[] = [
            'prefix' => $prefix,
            'middlewares' => $middlewares, // Array of FQCN strings (e.g., ['App\Middlewares\GuestMiddleware'])
            'startIndex' => count($this->routes), // Mark where routes in this group begin.
        ];

        call_user_func($callback, $this);

        // Apply group-specific settings to routes that were just added.
        $groupData = array_pop($this->routeGroups);
        
        // Convert group middlewares (which are FQCN strings) into the consistent associative array format
        $convertedGroupMiddlewares = [];
        foreach ($groupData['middlewares'] as $middlewareClass) {
            $convertedGroupMiddlewares[] = [
                'class' => $middlewareClass,
                'path' => '', // For group middlewares, path is implicitly the group's routes
                'methods' => ['GET', 'POST', 'PUT', 'DELETE'] // Apply to all methods by default for group
            ];
        }

        for ($i = $groupData['startIndex']; $i < count($this->routes); $i++) {
            $this->routes[$i]['path'] = $this->formatPath($groupData['prefix'] . $this->routes[$i]['path']);
            // Merge the converted group middlewares with any route-specific middlewares already present.
            // Both arrays should now contain associative arrays of the same structure.
            $this->routes[$i]['middlewares'] = array_merge($convertedGroupMiddlewares, $this->routes[$i]['middlewares']);
        }
    }

    /**
     * Adds a global or path-specific middleware to be applied during route resolution.
     *
     * @param string $middlewareClass The FQCN of the middleware class.
     * @param string $path (Optional) The base path to which this middleware applies. Empty means global.
     * @param array $methods (Optional) HTTP methods to which this middleware applies.
     */
    public function middleware(string $middlewareClass, string $path = '', array $methods = ['GET', 'POST', 'PUT', 'DELETE']): void
    {
        // This method already stores middlewares in the correct associative array format.
        $this->middlewares[] = [
            'class' => $middlewareClass,
            'path' => $this->formatPath($path),
            'methods' => array_map('strtoupper', $methods)
        ];
    }

    /**
     * Formats a given path to ensure consistency (starts with '/', no trailing slash unless it's just '/').
     *
     * @param string $path
     * @return string
     */
    private function formatPath(string $path): string
    {
        // Trim leading/trailing slashes, then prepend a single slash
        $path = '/' . trim($path, '/');
        // Ensure that a path consisting of only '/' after trimming (e.g., '//' or just '/') remains just '/'
        return ($path === '//') ? '/' : $path;
    }

    /**
     * Resolves the current HTTP request against the defined routes.
     * This is the core dispatching logic of the router.
     */
    public function resolve(): void
    {
        // Get the full request URI path from the server.
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        
        // Remove the base path of the application (e.g., /Lugxwebsite) from the request URI.
        // This is crucial when the app is installed in a subdirectory.
        // BASE_URL_PATH is defined in constants.php and should be '/Lugxwebsite' or '/'
        if (defined('BASE_URL_PATH') && BASE_URL_PATH !== '/') {
            if (str_starts_with($requestUri, BASE_URL_PATH)) {
                $requestUri = substr($requestUri, strlen(BASE_URL_PATH));
            }
        }

        // Also remove the 'public' segment if it's still present.
        // This handles cases where the actual web-accessible directory is 'public'
        // and routes are defined relative to that (e.g., /public/api/cart -> /api/cart).
        if (str_starts_with($requestUri, '/public')) {
            $requestUri = substr($requestUri, strlen('/public'));
        }

        // Format the final request URI for consistent matching with defined routes.
        $requestUri = $this->formatPath($requestUri);

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET'; // Default to GET if not set

        // --- DEBUGGING LOGS ---
        error_log("DEBUG: Router processing URI: " . $requestUri . " with method: " . $requestMethod);
        // --- END DEBUGGING LOGS ---

        foreach ($this->routes as $route) {
            // Convert route path to a regex pattern, handling placeholders like {id}
            $pattern = '#^' . str_replace('/', '\/', preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_.-]+)', $route['path'])) . '$#';

            // --- DEBUGGING LOGS ---
            error_log("DEBUG: Matching against pattern: " . $pattern . " for route: " . $route['path']);
            // --- END DEBUGGING LOGS ---

            if (preg_match($pattern, $requestUri, $matches) && $route['method'] === $requestMethod) {
                array_shift($matches); // Remove the full match (index 0)
                $params = $matches;

                // Merge global and route-specific/group-specific middlewares
                $allMiddlewares = array_merge($this->middlewares, $route['middlewares']);
                
                error_log("DEBUG: Router - Matched route: " . $route['path'] . " for handler: " . $route['handler'][0] . "::" . $route['handler'][1]);
                error_log("DEBUG: Router - Applying " . count($allMiddlewares) . " middlewares.");

                foreach ($allMiddlewares as $middleware) {
                    $applies = false;
                    // Global middleware check (if path is defined)
                    if (!empty($middleware['path'])) {
                        // Check if the request URI starts with the middleware's defined path AND method matches
                        if (str_starts_with($requestUri, $this->formatPath($middleware['path'])) && in_array($requestMethod, $middleware['methods'])) {
                            $applies = true;
                        }
                    } else {    
                        // General middlewares (no specific path, applies if method matches)
                        if (in_array($requestMethod, $middleware['methods'])) {
                            $applies = true;
                        }
                    }

                    if ($applies) {
                        error_log("DEBUG: Router - Attempting to run middleware: " . $middleware['class']);
                        if (!class_exists($middleware['class'])) {
                            error_log("ERROR: Middleware class not found: " . $middleware['class']);
                            http_response_code(500);
                            require_once ROOT_PATH . '/views/errors/500.php';
                            exit(); // Critical: Terminate execution after error
                        }
                        $middlewareInstance = new $middleware['class']();
                        if (!method_exists($middlewareInstance, 'handle')) {
                             error_log("ERROR: Middleware class '{$middleware['class']}' does not have a 'handle' method.");
                             http_response_code(500);
                             require_once ROOT_PATH . '/views/errors/500.php';
                             exit(); // Critical: Terminate execution after error
                        }
                        if (!$middlewareInstance->handle()) {
                            error_log("DEBUG: Router - Middleware " . $middleware['class'] . " returned false, stopping request.");
                            return; // Stop further processing
                        }
                        error_log("DEBUG: Router - Middleware " . $middleware['class'] . " returned true, continuing.");
                    }
                }

                $controllerName = $route['handler'][0];
                $methodName = $route['handler'][1];
                
                error_log("DEBUG: Router - Attempting to call controller: " . $controllerName . "::" . $methodName);

                if (class_exists($controllerName)) {
                    $controller = new $controllerName();
                    if (method_exists($controller, $methodName)) {
                        error_log("DEBUG: Router - Calling controller method: " . $controllerName . "::" . $methodName . " with params: " . json_encode($params));
                        call_user_func_array([$controller, $methodName], $params);
                        error_log("DEBUG: Router - Controller method finished execution.");
                        return; // Route handled, stop
                    } else {
                        error_log("ERROR: Controller method not found: " . $controllerName . "::" . $methodName);
                        // Fall through to 404 handler or custom 500 if specific method missing
                    }
                } else {
                    error_log("ERROR: Controller class not found for route path: " . $route['path'] . " Handler: " . $controllerName);
                    // Fall through to 404 handler or custom 500 if class missing
                }
            }
        }

        // If no route was matched after iterating through all of them
        $this->handleNotFound();
    }

    private function handleNotFound(): void
    {
        error_log("DEBUG: Router - No route matched. Calling handleNotFound().");
        http_response_code(404);
        // Ensure ROOT_PATH is defined. It should be from index.php
        if (defined('ROOT_PATH') && file_exists(ROOT_PATH . '/views/errors/404.php')) {
            require_once ROOT_PATH . '/views/errors/404.php';
        } else {
            echo "<h1>404 Not Found</h1><p>The page you requested could not be found, and the custom 404 page is missing.</p>";
        }
        exit(); // Crucial: Terminate script execution after serving 404
    }
}
