<?php
namespace App\Controllers;

use App\Models\AccesoModel;

/** Panel de Recepción: escaneo del QR, ingresos y salidas. */
class RecepcionController extends BaseController {
    private $accesos;

    /** Prepara el modelo de accesos. */
    public function __construct($db) {
        parent::__construct($db);
        $this->accesos = new AccesoModel($db);
    }

    /** Recepción y administración pueden operar el lector. */
    private function autorizado(): ?int {
        return $this->exigirRol(['recepcion', 'admin']);
    }

    /** GET /recepcion/resumen: operador, contadores del día e historial. */
    public function resumen() {
        $usuario = $this->autorizado();
        if (!$usuario) return;

        $this->exito([
            'operador' => [
                'nombre' => $_SESSION['usuario_nombre'] ?? '',
                'nombre_usuario' => $_SESSION['usuario_login'] ?? '',
            ],
            'hoy' => $this->accesos->resumenDelDia(),
            'accesos' => $this->accesos->historial(15),
        ]);
    }

    /** GET /recepcion/accesos: últimos ingresos y salidas. */
    public function historial() {
        $usuario = $this->autorizado();
        if (!$usuario) return;

        $limite = (int) ($_GET['limite'] ?? 25);

        $this->exito(['accesos' => $this->accesos->historial($limite)]);
    }

    /** POST /recepcion/accesos/canjear: valida el QR y registra el ingreso o la salida. */
    public function canjear() {
        $recepcion = $this->autorizado();
        if (!$recepcion) return;

        $token = trim((string) ($this->body()['token'] ?? ''));
        if ($token === '') {
            return $this->error('Escaneá un código o ingresá el token manualmente', 422);
        }

        try {
            $resultado = $this->accesos->canjear($token, $recepcion);
        } catch (\Throwable $error) {
            error_log('Error al canjear QR: ' . $error->getMessage());

            return $this->error('No se pudo procesar el ingreso. Intentá de nuevo.', 500);
        }

        $this->json([
            'status' => $resultado['permitido'] ? 'success' : 'error',
            'permitido' => $resultado['permitido'],
            'codigo' => $resultado['codigo'],
            'movimiento' => $resultado['movimiento'] ?? null,
            'message' => $resultado['permitido']
                ? ($resultado['movimiento'] === 'salida' ? 'Salida registrada' : 'Ingreso autorizado')
                : $resultado['motivo'],
            'socio' => $resultado['socio'],
            'acceso' => $resultado['acceso'] ?? null,
        ]);
    }

    /** POST /recepcion/accesos/:id/salida: salida cargada a mano (se fue sin escanear). */
    public function registrarSalida($id) {
        if (!$this->autorizado()) return;

        $resultado = $this->accesos->registrarSalidaManual((int) $id);
        if ($resultado === null) {
            return $this->error('Ese ingreso no existe o ya tiene la salida registrada', 404);
        }

        $this->exito([
            'message' => 'Salida registrada',
            'socio' => $resultado['socio'],
            'acceso' => $resultado['acceso'],
        ]);
    }
}
