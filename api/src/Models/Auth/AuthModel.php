<?php
namespace App\Models\Auth;

/** Consulta de usuarios para el inicio de sesión. */
class AuthModel {
    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** Busca la cuenta por nombre de usuario: datos, hash de la contraseña, estado y rol. */
    public function buscarPorUsuario(string $nombreUsuario): ?array {
        $sql = 'SELECT u.ID_usuario AS id, p.Nombre AS nombre, u.Usuario AS nombre_usuario,
                       u.Password AS password_hash, u.Activo AS activo,
                       u.ID_tipo AS rol_id, t.Nombre AS rol
                FROM Usuarios u
                INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
                INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
                WHERE u.Usuario = :usuario
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario' => $nombreUsuario]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
