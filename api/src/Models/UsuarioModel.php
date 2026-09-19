<?php
namespace App\Models;

/** Gestión de usuarios del panel de administración (tablas Usuarios y Persona). */
class UsuarioModel {
    /** Nombre de usuario: minúsculas y números, con . _ - entre medio (ej. juan.diaz). */
    public const FORMATO_USUARIO = '/^[a-z0-9]+([._-][a-z0-9]+)*$/';
    /** Largo mínimo del nombre de usuario. */
    public const LARGO_MIN_USUARIO = 3;
    /** Largo máximo del nombre de usuario. */
    public const LARGO_MAX_USUARIO = 30;

    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** Los cinco roles del sistema. */
    public function roles(): array {
        $sql = 'SELECT ID_tipo AS id, Nombre AS nombre, Descripcion AS descripcion
                FROM Tipo_usuario
                ORDER BY ID_tipo';

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Indica si el rol existe. */
    public function rolExiste(int $rolId): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM Tipo_usuario WHERE ID_tipo = :rol');
        $stmt->execute(['rol' => $rolId]);

        return (bool) $stmt->fetchColumn();
    }

    /** Todos los usuarios para la tabla del panel (activos primero). */
    public function listar(): array {
        $sql = 'SELECT u.ID_usuario AS id,
                       p.Nombre AS nombre,
                       u.Usuario AS nombre_usuario,
                       u.Activo AS activo,
                       u.ID_tipo AS rol_id,
                       t.Nombre AS rol
                FROM Usuarios u
                INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
                INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
                ORDER BY u.Activo DESC, p.Nombre';

        $filas = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizar'], $filas);
    }

    /** Un usuario por ID, o null si no existe. */
    public function buscar(int $id): ?array {
        $sql = 'SELECT u.ID_usuario AS id,
                       p.Nombre AS nombre,
                       u.Usuario AS nombre_usuario,
                       u.Activo AS activo,
                       u.ID_tipo AS rol_id,
                       t.Nombre AS rol
                FROM Usuarios u
                INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
                INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
                WHERE u.ID_usuario = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $fila ? $this->normalizar($fila) : null;
    }

    /** Convierte los tipos de una fila (IDs a número, activo a booleano). */
    private function normalizar(array $fila): array {
        $fila['id'] = (int) $fila['id'];
        $fila['rol_id'] = (int) $fila['rol_id'];
        $fila['activo'] = (int) $fila['activo'] === 1;

        return $fila;
    }

    /** Indica si otra cuenta ya usa ese nombre de usuario. */
    public function usuarioEnUso(string $nombreUsuario, ?int $exceptoId = null): bool {
        $sql = 'SELECT 1 FROM Usuarios u WHERE u.Usuario = :usuario';
        $parametros = ['usuario' => $nombreUsuario];

        if ($exceptoId !== null) {
            $sql .= ' AND u.ID_usuario <> :id';
            $parametros['id'] = $exceptoId;
        }

        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($parametros);

        return (bool) $stmt->fetchColumn();
    }

    /** Crea la cuenta y su persona en una transacción; guarda la contraseña con hash. */
    public function crear(string $nombre, string $nombreUsuario, string $password, int $rolId): int {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO Usuarios (Usuario, Password, ID_tipo, Activo) VALUES (:usuario, :password, :rol, 1)'
            );
            $stmt->execute([
                'usuario' => $nombreUsuario,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'rol' => $rolId,
            ]);

            $id = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare(
                'INSERT INTO Persona (Nombre, ID_usuario) VALUES (:nombre, :usuario)'
            );
            $stmt->execute(['nombre' => $nombre, 'usuario' => $id]);

            $this->db->commit();

            return $id;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** Edita la cuenta; la contraseña es opcional. Si deja de ser entrenador, sus programas quedan sin entrenador. */
    public function actualizar(int $id, string $nombre, string $nombreUsuario, int $rolId, ?string $password): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE Persona SET Nombre = :nombre WHERE ID_usuario = :usuario');
            $stmt->execute(['nombre' => $nombre, 'usuario' => $id]);

            if ($password !== null && $password !== '') {
                $stmt = $this->db->prepare(
                    'UPDATE Usuarios SET Usuario = :nombre_usuario, ID_tipo = :rol, Password = :password, Token_semilla = NULL WHERE ID_usuario = :usuario'
                );
                $stmt->execute([
                    'nombre_usuario' => $nombreUsuario,
                    'rol' => $rolId,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'usuario' => $id,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE Usuarios SET Usuario = :nombre_usuario, ID_tipo = :rol WHERE ID_usuario = :usuario'
                );
                $stmt->execute(['nombre_usuario' => $nombreUsuario, 'rol' => $rolId, 'usuario' => $id]);
            }

            $stmt = $this->db->prepare(
                "UPDATE Programas SET ID_entrenador = NULL
                 WHERE ID_entrenador = :usuario
                   AND NOT EXISTS (SELECT 1 FROM Tipo_usuario t WHERE t.ID_tipo = :rol AND t.Nombre = 'Entrenador')"
            );
            $stmt->execute(['usuario' => $id, 'rol' => $rolId]);

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    /** Baja o alta lógica: nunca borra la fila. */
    public function cambiarEstado(int $id, bool $activo): void {
        $stmt = $this->db->prepare('UPDATE Usuarios SET Activo = :activo WHERE ID_usuario = :id');
        $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    /** Cuántos administradores activos hay además del indicado. */
    public function otrosAdminsActivos(int $exceptoId): int {
        $sql = "SELECT COUNT(*)
                FROM Usuarios u
                INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
                WHERE t.Nombre = 'Administrador' AND u.Activo = 1 AND u.ID_usuario <> :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $exceptoId]);

        return (int) $stmt->fetchColumn();
    }

    /** Registros relacionados que se verían afectados al borrar el usuario. */
    public function dependencias(int $id): array {
        $contar = function (string $sql) use ($id): int {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            return (int) $stmt->fetchColumn();
        };

        return [
            'socios_asignados' => $contar('SELECT COUNT(*) FROM Socio_Entrenador WHERE ID_entrenador = :id'),
            'rutinas_creadas' => $contar('SELECT COUNT(*) FROM Rutinas WHERE ID_entrenador = :id'),
            'rutinas_asignadas' => $contar('SELECT COUNT(*) FROM Usuario_Rutina WHERE ID_usuario = :id'),
            'membresias' => $contar('SELECT COUNT(*) FROM Membresias WHERE ID_usuario = :id'),
            'conversaciones' => $contar('SELECT COUNT(*) FROM Chat WHERE ID_usuario = :id'),
            'series' => $contar('SELECT COUNT(*) FROM Registro_Progreso WHERE ID_usuario = :id'),
            'accesos' => $contar('SELECT COUNT(*) FROM Registro_Accesos WHERE ID_usuario = :id'),
            'programas_inscriptos' => $contar('SELECT COUNT(*) FROM Socio_Programa WHERE ID_socio = :id'),
            'programas_a_cargo' => $contar('SELECT COUNT(*) FROM Programas WHERE ID_entrenador = :id'),
        ];
    }

    /** Indica si el borrado necesita confirmación extra (socios o rutinas a cargo). */
    public function tieneDependenciasBloqueantes(array $dependencias): bool {
        return $dependencias['socios_asignados'] > 0 || $dependencias['rutinas_creadas'] > 0;
    }

    /** Borrado definitivo: limpia lo que las claves foráneas no borran solas. */
    public function eliminar(int $id): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('DELETE FROM Socio_Entrenador WHERE ID_entrenador = :id');
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare('DELETE FROM Rutinas WHERE ID_entrenador = :id');
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare('DELETE FROM Usuarios WHERE ID_usuario = :id');
            $stmt->execute(['id' => $id]);

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
