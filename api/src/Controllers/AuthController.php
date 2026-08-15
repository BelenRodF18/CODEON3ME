<?php
namespace App\Controllers;

use App\Models\Auth\AuthModel;

class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login() {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
            return;
        }

        $email = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Email y contraseña son requeridos']);
            return;
        }

        $model = new AuthModel($this->db);
        $usuario = $model->buscarPorEmail($email);

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
        $roles = ['Administrador' => 'admin', 'Entrenador' => 'entrenador', 'Socio' => 'socio', 'Personal' => 'personal'];
        $rol = $roles[$usuario['rol']] ?? strtolower($usuario['rol']);
        $_SESSION['usuario_id'] = (int)$usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_rol'] = $rol;

        echo json_encode([
            'status' => 'success',
            'message' => 'Login correcto',
            'usuario' => [
                'id' => (int)$usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'rol' => $rol
            ]
        ]);
    }

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
                'email' => $_SESSION['usuario_email'],
                'rol' => $_SESSION['usuario_rol']
            ]
        ]);
    }
}
