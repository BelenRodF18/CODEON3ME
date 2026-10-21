<?php
namespace App\Controllers;

/** Base de los controladores: arma las respuestas, lee los datos enviados y revisa que haya sesión con el rol correcto. */
abstract class BaseController {
    protected $db;

    /** Guarda la conexión y abre la sesión. */
    public function __construct($db) {
        $this->db = $db;

        \App\Utils\Sesion::iniciar();
    }

    /** Responde en formato JSON con el código de estado indicado (200 = bien, 400/401/403 = error). */
    protected function json(array $datos, int $estado = 200): void {
        http_response_code($estado);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }

    /** Responde un error: {status: error, message, ...extra}. */
    protected function error(string $mensaje, int $estado = 400, array $extra = []): void {
        $this->json(array_merge(['status' => 'error', 'message' => $mensaje], $extra), $estado);
    }

    /** Responde un éxito: {status: success, ...datos}. */
    protected function exito(array $datos = [], int $estado = 200): void {
        $this->json(array_merge(['status' => 'success'], $datos), $estado);
    }

    /** Devuelve los datos que mandó el navegador en una lista (vacía si no mandó nada). */
    protected function body(): array {
        $crudo = file_get_contents('php://input');
        $datos = json_decode((string) $crudo, true);

        return is_array($datos) ? $datos : [];
    }

    /** Exige que haya sesión con alguno de los roles; devuelve el ID del usuario o null (en ese caso ya respondió el error). */
    protected function exigirRol(array $roles): ?int {
        if (empty($_SESSION['usuario_id'])) {
            $this->error('Se requiere iniciar sesión', 401);

            return null;
        }

        $rol = (string) ($_SESSION['usuario_rol'] ?? '');
        if (!in_array($rol, $roles, true)) {
            $this->error('No tenés permisos para esta operación', 403);

            return null;
        }

        return (int) $_SESSION['usuario_id'];
    }
}
