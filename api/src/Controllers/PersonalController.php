<?php
namespace App\Controllers;

use App\Models\ChatModel;

/** Panel del personal de mantenimiento: reportes y chat con el socio. */
class PersonalController {
    private $db;

    /** Guarda la conexión y abre la sesión. */
    public function __construct($db) {
        $this->db = $db;
        \App\Utils\Sesion::iniciar();
    }

    /** Responde con JSON y el código HTTP indicado. */
    private function json($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /** Exige sesión del personal. */
    private function autorizado() {
        if (empty($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'personal') {
            $this->json(['status' => 'error', 'message' => 'Se requiere una sesión de personal'], 401);
            return false;
        }
        return true;
    }

    /** GET /personal/conversaciones: reportes de mantenimiento ordenados por estado. */
    public function conversaciones() {
        if (!$this->autorizado()) return;

        $stmt = $this->db->query(
            "SELECT c.ID_chat AS id, c.Titulo AS etiqueta, c.Descripcion AS descripcion, LOWER(REPLACE(c.Estado, ' ', '_')) AS estado, DATE_FORMAT(c.Fecha_hora, '%d/%m/%Y %H:%i') AS creado, p.Nombre AS socio
             FROM Chat c
             INNER JOIN Persona p ON p.ID_usuario = c.ID_usuario
             WHERE c.Tipo_chat = 'Mantenimiento'
             ORDER BY FIELD(c.Estado, 'Abierto', 'En revision', 'En reparacion', 'Solucionado'), c.Fecha_hora DESC"
        );
        $this->json(['status' => 'success', 'conversaciones' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** GET /personal/conversaciones/:id/mensajes: chat de un reporte. */
    public function mensajes($id) {
        if (!$this->autorizado()) return;

        $stmt = $this->db->prepare(
            'SELECT m.Mensaje AS mensaje, m.Fecha_hora AS creado, p.Nombre AS nombre
             FROM Mensajes_chat m
             INNER JOIN Persona p ON p.ID_usuario = m.ID_usuario
             WHERE m.ID_chat = :chat
             ORDER BY m.Fecha_hora'
        );
        $stmt->execute(['chat' => (int) $id]);
        $this->json(['status' => 'success', 'mensajes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** POST /personal/conversaciones/:id/mensajes: responde al socio. */
    public function responder($id) {
        if (!$this->autorizado()) return;

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $mensaje = trim((string) ($in['mensaje'] ?? ''));
        if ($mensaje === '') {
            return $this->json(['status' => 'error', 'message' => 'El mensaje es obligatorio'], 400);
        }

        $stmt = $this->db->prepare('SELECT ID_chat FROM Chat WHERE ID_chat = :chat AND Tipo_chat="Mantenimiento"');
        $stmt->execute(['chat' => (int) $id]);
        if (!$stmt->fetchColumn()) {
            return $this->json(['status' => 'error', 'message' => 'Reporte no encontrado'], 404);
        }

        $stmt = $this->db->prepare('INSERT INTO Mensajes_chat (ID_chat, ID_usuario, Mensaje) VALUES (:chat, :usuario, :mensaje)');
        $stmt->execute(['chat' => (int) $id, 'usuario' => (int) $_SESSION['usuario_id'], 'mensaje' => $mensaje]);
        $this->json(['status' => 'success', 'message' => 'Respuesta enviada']);
    }

    /** PUT /personal/conversaciones/:id/estado: cambia el estado y avisa al socio. */
    public function cambiarEstado($id) {
        if (!$this->autorizado()) return;

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $estado = ChatModel::ESTADOS[$in['estado'] ?? ''] ?? null;
        if (!$estado) {
            return $this->json(['status' => 'error', 'message' => 'Estado inválido'], 400);
        }

        $personal = (int) $_SESSION['usuario_id'];
        if (!(new ChatModel($this->db))->cambiarEstado((int) $id, $estado, $personal, true, $personal)) {
            return $this->json(['status' => 'error', 'message' => 'Reporte no encontrado'], 404);
        }
        $this->json(['status' => 'success', 'message' => 'Estado actualizado']);
    }
}
