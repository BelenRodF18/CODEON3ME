<?php
/**
 * Router para el servidor embebido de PHP. Reemplaza a Apache/Docker.
 *
 *   php -S localhost:8000 server.php
 *
 * Reglas (las mismas que el .htaccess de producción):
 *   /api/...  -> api/index.php
 *   /         -> front/index.html
 *   resto     -> archivos de front/ y, si no existe, front/index.html
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rawurldecode($uri);
$raiz = __DIR__;

// --- API -------------------------------------------------------------------
if (strpos($uri, '/api') === 0) {
    $archivo = $raiz . $uri;

    // Archivos reales dentro de api/ (por ejemplo las fotos subidas).
    if ($uri !== '/api' && is_file($archivo) && substr($archivo, -4) !== '.php') {
        return false;
    }

    require $raiz . '/api/index.php';

    return true;
}

// --- Frontend --------------------------------------------------------------
if ($uri === '/' || $uri === '') {
    $uri = '/front/index.html';
}

// Permite escribir /front/... o directamente /dist/... y /index.html
$candidatos = [
    $raiz . $uri,
    $raiz . '/front' . $uri,
];

foreach ($candidatos as $archivo) {
    if (is_file($archivo)) {
        return false; // el servidor embebido lo sirve con su propio MIME
    }
}

http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><meta charset="utf-8"><title>404</title>';
echo '<p style="font-family:system-ui;padding:40px">No encontrado: ' . htmlspecialchars($uri, ENT_QUOTES) . '</p>';

return true;
