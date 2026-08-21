<?php
namespace App\Models\Auth;

class AuthModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function buscarPorEmail($email) {
        $sql = 'SELECT u.id, u.nombre, u.email, u.password_hash, u.activo, u.rol_id, r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE u.email = :email
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function crearUsuario($nombre, $email, $password, $rolId) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo)
                VALUES (:nombre, :email, :password_hash, :rol_id, 1)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':nombre', $nombre, \PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, \PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $hash, \PDO::PARAM_STR);
        $stmt->bindParam(':rol_id', $rolId, \PDO::PARAM_INT);

        return $stmt->execute();
    }
}
