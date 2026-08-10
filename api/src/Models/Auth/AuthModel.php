<?php
namespace App\Models\Auth;

class AuthModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function buscarPorEmail($email) {
        $sql = 'SELECT u.ID_usuario AS id, p.Nombre AS nombre, u.Usuario AS email,
                   u.Password AS password_hash, u.Activo AS activo,
                   u.ID_tipo AS rol_id, t.Nombre AS rol
            FROM Usuarios u
            INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
            INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
            WHERE u.Usuario = :email
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function crearUsuario($nombre, $email, $password, $rolId) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = 'INSERT INTO Usuarios (Usuario, Password, ID_tipo, Activo)
            VALUES (:email, :password_hash, :rol_id, 1)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':email', $email, \PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $hash, \PDO::PARAM_STR);
        $stmt->bindParam(':rol_id', $rolId, \PDO::PARAM_INT);

        return $stmt->execute();
    }
}
