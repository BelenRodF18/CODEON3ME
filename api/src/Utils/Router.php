<?php
namespace App\Utils;

/** Router: según el tipo de pedido y la dirección, decide qué controlador y qué función ejecutar. */
class Router {
    private $routes = [];
    private $basePath;
    private $db;

    /** Si está activo, la respuesta incluye cuánto tardó en resolverse (sirve para medir velocidad). */
    private function serverTimingEnabled(): bool {
        $v = getenv('API_SERVER_TIMING');
        if ($v === false || $v === '') {
            return false;
        }
        $v = strtolower(trim((string) $v));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    /** Recibe el comienzo común de las direcciones (/api) y la conexión a la base que usarán los controladores. */
    public function __construct($basePath, $db) {
        $this->basePath = $basePath;
        $this->db = $db;
    }

    /** Registra una ruta GET. */
    public function get($path, $handler) { $this->addRoute('GET', $path, $handler); }

    /** Registra una ruta POST. */
    public function post($path, $handler) { $this->addRoute('POST', $path, $handler); }

    /** Registra una ruta PUT. */
    public function put($path, $handler) { $this->addRoute('PUT', $path, $handler); }

    /** Registra una ruta DELETE. */
    public function delete($path, $handler) { $this->addRoute('DELETE', $path, $handler); }

    /** Guarda la ruta convirtiendo las partes variables (:id) en un patrón que acepta cualquier valor. */
    private function addRoute($method, $path, $handler) {
        $pattern = preg_replace('/:[a-zA-Z0-9]+/', '([a-zA-Z0-9_-]+)', $path);

        $this->routes[] = [
            'method' => $method,
            'pattern' => "#^" . $this->basePath . $pattern . "/?$#",
            'handler' => $handler
        ];
    }

    /** Busca la ruta que coincide con el pedido y la ejecuta; si no existe, responde 404 (no encontrado). */
    public function run() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        $uri = str_replace('/index.php', '', $uri);

        if (empty($uri)) {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // Se descarta el texto completo y quedan solo los valores de la dirección
                if ($this->serverTimingEnabled()) {
                    ob_start();
                    $t0 = microtime(true);
                    try {
                        $this->resolve($route['handler'], $matches);
                    } finally {
                        $durMs = (microtime(true) - $t0) * 1000.0;
                        header('Server-Timing: app;dur=' . round($durMs, 2));
                        echo ob_get_clean();
                    }
                    return;
                }
                return $this->resolve($route['handler'], $matches);
            }
        }

        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Ruta no encontrada en la API",
            "debug_uri" => $uri,        // Muestra qué dirección recibió el servidor (ayuda a encontrar errores)
            "debug_base" => $this->basePath
        ]);
    }

    /** Crea el controlador indicado y ejecuta su función con los valores que venían en la dirección. */
    private function resolve($handler, $params) {
        list($controllerName, $method) = explode('@', $handler);
        $controllerClass = "App\\Controllers\\" . $controllerName;

        if (class_exists($controllerClass)) {
            $controller = new $controllerClass($this->db);

            if (method_exists($controller, $method)) {
                return call_user_func_array([$controller, $method], $params);
            }
        }

        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error interno: Controlador ($controllerName) o método ($method) no válido"
        ]);
    }
}
