<?php
namespace App\Controllers;

/** Chequeo de salud de la API y de la base. */
class EstadoController {
    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** GET /estado: indica si la API responde y si la base tiene sus tablas. */
    public function ver() {
        $respuesta = [
            'status' => 'ok',
            'message' => 'La API responde',
            'base_datos' => 'conectada'
        ];

        try {
            $tablas = $this->db->query("SHOW TABLES LIKE 'Usuarios'")->fetchColumn();
            $respuesta['tablas_fitpower'] = $tablas !== false ? 'ok' : 'incompletas';
        } catch (\Throwable $e) {
            http_response_code(503);
            $respuesta['status'] = 'error';
            $respuesta['base_datos'] = 'no disponible';
        }

        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    }
}