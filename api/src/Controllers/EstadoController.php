<?php
namespace App\Controllers;

class EstadoController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

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