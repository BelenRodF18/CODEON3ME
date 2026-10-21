<?php
namespace App\Utils;

/** Lee el archivo .env (configuración secreta) y deja sus valores disponibles para el resto del código. */
class EnvLoader {
    /** Carga cada línea CLAVE=valor del archivo, sin reemplazar las que ya estaban definidas. */
    public static function load($path) {
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Se saltean los comentarios y las líneas vacías
            if ($line === '' || strpos($line, '#') === 0) continue;

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) continue;

            $name = trim($parts[0]);
            $value = trim($parts[1]);
            // Algunos editores agregan un carácter invisible al inicio del archivo: se quita
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            if ($name === '') continue;

            $current = '';
            if (array_key_exists($name, $_ENV)) {
                $current = (string) $_ENV[$name];
            } elseif (array_key_exists($name, $_SERVER)) {
                $current = (string) $_SERVER[$name];
            } elseif (getenv($name) !== false) {
                $current = (string) getenv($name);
            }

            if ($current !== '') {
                continue;
            }

            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
