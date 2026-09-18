<?php
namespace App\Models;

/** Reportes de mantenimiento y sugerencias: estados y avisos al socio. */
class ChatModel {
    /** Estado que llega del frontend => estado guardado en MySQL. */
    public const ESTADOS = [
        'abierto' => 'Abierto',
        'en_revision' => 'En revision',
        'en_reparacion' => 'En reparacion',
        'solucionado' => 'Solucionado',
    ];

    /** Cómo se muestra cada estado en el aviso al socio. */
    private const ETIQUETAS = [
        'Abierto' => 'Abierto',
        'En revision' => 'En revisión',
        'En reparacion' => 'En reparación',
        'Solucionado' => 'Solucionado',
    ];

    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** Cambia el estado del reporte y, si cambió, deja un aviso en el chat. False si no existe. */
    public function cambiarEstado(int $chatId, string $estado, int $autorId, bool $soloMantenimiento, ?int $personalId = null): bool {
        $sql = 'SELECT Estado FROM Chat WHERE ID_chat = :chat' . ($soloMantenimiento ? " AND Tipo_chat = 'Mantenimiento'" : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['chat' => $chatId]);
        $anterior = $stmt->fetchColumn();
        if ($anterior === false) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            if ($personalId !== null) {
                $stmt = $this->db->prepare('UPDATE Chat SET Estado = :estado, ID_personal = :personal WHERE ID_chat = :chat');
                $stmt->execute(['estado' => $estado, 'personal' => $personalId, 'chat' => $chatId]);
            } else {
                $stmt = $this->db->prepare('UPDATE Chat SET Estado = :estado WHERE ID_chat = :chat');
                $stmt->execute(['estado' => $estado, 'chat' => $chatId]);
            }

            if ($anterior !== $estado) {
                $stmt = $this->db->prepare('INSERT INTO Mensajes_chat (ID_chat, ID_usuario, Mensaje) VALUES (:chat, :autor, :mensaje)');
                $stmt->execute([
                    'chat' => $chatId,
                    'autor' => $autorId,
                    'mensaje' => 'Estado actualizado: ' . (self::ETIQUETAS[$estado] ?? $estado) . '.',
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return true;
    }

    /** Mensajes del equipo que el socio todavía no vio. */
    public function avisosDelSocio(int $socioId): array {
        $stmt = $this->db->prepare(
            'SELECT m.ID_mensaje AS id, c.ID_chat AS reporte_id, c.Titulo AS etiqueta,
                    LOWER(REPLACE(c.Estado, " ", "_")) AS estado, m.Mensaje AS mensaje,
                    DATE_FORMAT(m.Fecha_hora, "%d/%m/%Y %H:%i") AS fecha
             FROM Mensajes_chat m
             INNER JOIN Chat c ON c.ID_chat = m.ID_chat
             WHERE c.ID_usuario = :socio
               AND m.ID_usuario <> c.ID_usuario
               AND m.ID_mensaje > COALESCE(c.Ultimo_leido_socio, 0)
             ORDER BY m.ID_mensaje DESC'
        );
        $stmt->execute(['socio' => $socioId]);

        return array_map(function (array $fila): array {
            $fila['id'] = (int) $fila['id'];
            $fila['reporte_id'] = (int) $fila['reporte_id'];

            return $fila;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** Marca como leído todo lo recibido hasta ahora en sus reportes. */
    public function marcarLeidos(int $socioId): void {
        $stmt = $this->db->prepare(
            'UPDATE Chat c
             SET c.Ultimo_leido_socio = (SELECT MAX(m.ID_mensaje) FROM Mensajes_chat m WHERE m.ID_chat = c.ID_chat)
             WHERE c.ID_usuario = :socio'
        );
        $stmt->execute(['socio' => $socioId]);
    }
}
