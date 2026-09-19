<?php
namespace App\Controllers;

use App\Models\Auth\AuthModel;

/** Inicio y cierre de sesión, y consulta del usuario conectado. */
class AuthController {
    private $db;

    /** Guarda la conexión y abre la sesión. */
    public function __construct($db) {
        $this->db = $db;

        \App\Utils\Sesion::iniciar();
    }

    /** POST /login: valida usuario y contraseña, regenera la sesión y devuelve el rol. */
    public function login() {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
            return;
        }

        $nombreUsuario = strtolower(trim((string)($input['usuario'] ?? '')));
        $password = (string)($input['password'] ?? '');

        if ($nombreUsuario === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Usuario y contraseña son requeridos']);
            return;
        }

        $model = new AuthModel($this->db);
        $usuario = $model->buscarPorUsuario($nombreUsuario);

        if (!$usuario) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Credenciales inválidas']);
            return;
        }

        if ((int)$usuario['activo'] !== 1) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Usuario inactivo']);
            return;
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Credenciales inválidas']);
            return;
        }

        session_regenerate_id(true);
        $roles = ['Administrador' => 'admin', 'Entrenador' => 'entrenador', 'Socio' => 'socio', 'Personal' => 'personal', 'Recepcion' => 'recepcion'];
        $rol = $roles[$usuario['rol']] ?? strtolower($usuario['rol']);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_login'] = $usuario['nombre_usuario'];
        $_SESSION['usuario_rol'] = $rol;

        echo json_encode([
            'status' => 'success',
            'message' => 'Login correcto',
            'usuario' => [
                'id' => (int)$usuario['id'],
                'nombre' => $usuario['nombre'],
                'nombre_usuario' => $usuario['nombre_usuario'],
                'rol' => $rol
            ]
        ]);
    }

    /** POST /logout: borra la sesión y su cookie. */
    public function logout() {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();

        echo json_encode([
            'status' => 'success',
            'message' => 'Sesión cerrada'
        ]);
    }

    /** GET /me: devuelve el usuario conectado o 401 si no hay sesión. */
    public function me() {
        if (empty($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'usuario' => [
                'id' => $_SESSION['usuario_id'],
                'nombre' => $_SESSION['usuario_nombre'],
                'nombre_usuario' => $_SESSION['usuario_login'] ?? '',
                'rol' => $_SESSION['usuario_rol']
            ]
        ]);
    }

    /** GET /sesion: usuario conectado o null (siempre 200; lo usa la web pública). */
    public function sesion() {
        echo json_encode([
            'status' => 'success',
            'usuario' => empty($_SESSION['usuario_id']) ? null : [
                'id' => $_SESSION['usuario_id'],
                'nombre' => $_SESSION['usuario_nombre'],
                'nombre_usuario' => $_SESSION['usuario_login'] ?? '',
                'rol' => $_SESSION['usuario_rol'],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
}
