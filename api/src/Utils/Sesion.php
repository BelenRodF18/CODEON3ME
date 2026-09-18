<?php
namespace App\Utils;

/**
 * Configuración única de la sesión de PHP para toda la API.
 * Todos los controladores la abren con Sesion::iniciar().
 */
class Sesion {
    /** Tiempo sin actividad (en segundos) antes de que la sesión venza: 8 horas. */
    public const DURACION_INACTIVIDAD = 28800;

    /** Nombre de la cookie de sesión. */
    public const NOMBRE = 'FITPOWERSESSID';

    /** Abre la sesión si todavía no está abierta, con cookie segura y 8 h de vida. */
    public static function iniciar(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Por defecto PHP borra las sesiones inactivas a los 24 minutos.
        ini_set('session.gc_maxlifetime', (string) self::DURACION_INACTIVIDAD);
        session_name(self::NOMBRE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}
