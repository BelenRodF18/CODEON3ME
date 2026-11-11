<?php
namespace App\Controllers;

use App\Models\UsuarioModel;
use App\Models\Auth\AuthModel;

/** Perfil de quien tiene la sesión abierta. Lo usan los cinco roles. */
class PerfilController extends BaseController {
    private $usuarios;

    /** Prepara el modelo de usuarios. */
    public function __construct($db) {
        parent::__construct($db);
        $this->usuarios = new UsuarioModel($db);
    }

    /** Exige que haya sesión (cualquier rol); devuelve el ID del usuario o null. */
    private function autorizado(): ?int {
        return $this->exigirRol(['admin', 'entrenador', 'socio', 'personal', 'recepcion']);
    }

    /** GET /perfil: los datos de mi cuenta (nunca la contraseña). */
    public function ver() {
        $id = $this->autorizado();
        if (!$id) return;

        $usuario = $this->usuarios->buscar($id);
        if (!$usuario) {
            return $this->error('No se encontró el usuario', 404);
        }

        $this->exito(['usuario' => $usuario]);
    }

    /** PUT /perfil: cambia mi nombre y mi teléfono. La contraseña solo si mando la nueva. */
    public function modificar() {
        $id = $this->autorizado();
        if (!$id) return;

        $actual = $this->usuarios->buscar($id);
        if (!$actual) {
            return $this->error('No se encontró el usuario', 404);
        }

        $datos = $this->body();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $telefono = trim((string) ($datos['telefono'] ?? ''));
        $passwordActual = (string) ($datos['password_actual'] ?? '');
        $passwordNueva = (string) ($datos['password_nueva'] ?? '');

        if ($nombre === '') {
            return $this->error('El nombre es obligatorio', 422, ['campo' => 'nombre']);
        }
        if (strlen($nombre) > 100) {
            return $this->error('El nombre no puede pasar de 100 caracteres', 422, ['campo' => 'nombre']);
        }
        if ($telefono !== '' && !preg_match('/^[0-9+() -]{6,30}$/', $telefono)) {
            return $this->error('El teléfono solo admite números, espacios, +, - y paréntesis (6 a 30 caracteres)', 422, ['campo' => 'telefono']);
        }

        // Para cambiar la contraseña hay que saber la actual.
        if ($passwordNueva !== '') {
            if (strlen($passwordNueva) < 6) {
                return $this->error('La contraseña nueva debe tener al menos 6 caracteres', 422, ['campo' => 'password_nueva']);
            }

            $cuenta = (new AuthModel($this->db))->buscarPorUsuario($actual['nombre_usuario']);
            if (!$cuenta || !password_verify($passwordActual, $cuenta['password_hash'])) {
                return $this->error('La contraseña actual no es correcta', 422, ['campo' => 'password_actual']);
            }
        }

        try {
            // El usuario y el rol quedan igual: acá solo se cambian los datos personales.
            $this->usuarios->actualizar($id, $nombre, $actual['nombre_usuario'], $actual['rol_id'], $passwordNueva !== '' ? $passwordNueva : null);
            $this->usuarios->guardarTelefono($id, $telefono);
        } catch (\PDOException $error) {
            return $this->error('No se pudo guardar el perfil', 409);
        }

        $_SESSION['usuario_nombre'] = $nombre;

        $this->exito([
            'message' => 'Perfil actualizado correctamente',
            'usuario' => $this->usuarios->buscar($id),
        ]);
    }
}
