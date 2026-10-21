<?php
namespace App\Utils;

/**
 * Configuración de la sesión (el "estás conectado") que comparte toda la API.
 * Todos los controladores la abren con Sesion::iniciar().
 */
class Sesion {
    /** Segundos sin actividad antes de cerrar la sesión sola: 8 horas. */
    public const DURACION_INACTIVIDAD = 28800;

    /** Nombre con el que el navegador guarda la sesión. */
    public const NOMBRE = 'FITPOWERSESSID';

    /** Abre la sesión si todavía no está abierta; dura 8 horas y la cookie se guarda de forma segura. */
    public static function iniciar(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Normalmente PHP cierra las sesiones inactivas a los 24 minutos; acá se alarga.
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
