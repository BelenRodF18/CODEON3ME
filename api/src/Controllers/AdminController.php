<?php
namespace App\Controllers;

use App\Models\ChatModel;
use App\Models\ProgramaModel;
use App\Models\UsuarioModel;

/** Panel de administración: usuarios, socios, membresías, programas, ejercicios y conversaciones. */
class AdminController extends BaseController {
    private $usuarios;
    private $programas;

    /** Prepara los modelos de usuarios y de programas. */
    public function __construct($db) {
        parent::__construct($db);
        $this->usuarios = new UsuarioModel($db);
        $this->programas = new ProgramaModel($db);
    }

    /** Exige sesión de administrador; devuelve su ID o null. */
    private function autorizado(): ?int {
        return $this->exigirRol(['admin']);
    }

    /** GET /admin/conversaciones: todos los reportes y sugerencias. */
    public function conversaciones() {
        if (!$this->autorizado()) return;

        $sql = 'SELECT c.ID_chat AS id,
                       LOWER(c.Tipo_chat) AS tipo,
                       c.Titulo AS etiqueta,
                       c.Descripcion AS descripcion,
                       LOWER(REPLACE(c.Estado, " ", "_")) AS estado,
                       c.Foto AS foto_path,
                       DATE_FORMAT(c.Fecha_hora, "%d/%m/%Y %H:%i") AS creado,
                       p.Nombre AS socio,
                       u.Usuario AS nombre_usuario
                FROM Chat c
                INNER JOIN Persona p ON p.ID_usuario = c.ID_usuario
                INNER JOIN Usuarios u ON u.ID_usuario = c.ID_usuario
                ORDER BY c.Fecha_hora DESC';

        $this->exito(['conversaciones' => $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** GET /admin/conversaciones/:id/mensajes: mensajes de una conversación. */
    public function mensajes($id) {
        if (!$this->autorizado()) return;

        $sql = 'SELECT m.ID_mensaje AS id,
                       m.Mensaje AS mensaje,
                       m.Fecha_hora AS creado_en,
                       p.Nombre AS nombre
                FROM Mensajes_chat m
                INNER JOIN Persona p ON p.ID_usuario = m.ID_usuario
                WHERE m.ID_chat = :chat
                ORDER BY m.Fecha_hora';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['chat' => (int) $id]);

        $this->exito(['mensajes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** POST /admin/conversaciones/:id/mensajes: el administrador responde. */
    public function responder($id) {
        $admin = $this->autorizado();
        if (!$admin) return;

        $mensaje = trim((string) ($this->body()['mensaje'] ?? ''));
        if ($mensaje === '') {
            return $this->error('El mensaje es obligatorio');
        }

        $stmt = $this->db->prepare('SELECT ID_chat FROM Chat WHERE ID_chat = :chat');
        $stmt->execute(['chat' => (int) $id]);
        if (!$stmt->fetchColumn()) {
            return $this->error('Conversación no encontrada', 404);
        }

        $stmt = $this->db->prepare('INSERT INTO Mensajes_chat (ID_chat, ID_usuario, Mensaje) VALUES (:chat, :usuario, :mensaje)');
        $stmt->execute(['chat' => (int) $id, 'usuario' => $admin, 'mensaje' => $mensaje]);

        $this->exito(['message' => 'Respuesta enviada']);
    }

    /** PUT /admin/conversaciones/:id/estado: cambia el estado y avisa al socio. */
    public function cambiarEstado($id) {
        $admin = $this->autorizado();
        if (!$admin) return;

        $estado = ChatModel::ESTADOS[trim((string) ($this->body()['estado'] ?? ''))] ?? null;

        if (!$estado) {
            return $this->error('Estado inválido');
        }

        if (!(new ChatModel($this->db))->cambiarEstado((int) $id, $estado, $admin, false)) {
            return $this->error('Conversación no encontrada', 404);
        }

        $this->exito(['message' => 'Estado actualizado']);
    }

    /** GET /admin/resumen: todo lo que necesita el panel en una sola llamada. */
    public function resumen() {
        if (!$this->autorizado()) return;

        $socios = $this->db->query(
            "SELECT u.ID_usuario AS id,
                    p.Nombre AS nombre,
                    u.Usuario AS nombre_usuario,
                    u.Activo AS activo,
                    COALESCE((SELECT m.Estado FROM Membresias m WHERE m.ID_usuario = u.ID_usuario ORDER BY m.Fecha_vencimiento DESC LIMIT 1), 'Sin membresía') AS membresia,
                    (SELECT m.Fecha_vencimiento FROM Membresias m WHERE m.ID_usuario = u.ID_usuario ORDER BY m.Fecha_vencimiento DESC LIMIT 1) AS vencimiento,
                    (SELECT m.ID_plan FROM Membresias m WHERE m.ID_usuario = u.ID_usuario ORDER BY m.Fecha_vencimiento DESC LIMIT 1) AS plan_id,
                    se.ID_entrenador AS entrenador_id,
                    (SELECT GROUP_CONCAT(sp.ID_programa ORDER BY sp.ID_programa) FROM Socio_Programa sp WHERE sp.ID_socio = u.ID_usuario) AS programas
             FROM Usuarios u
             INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
             LEFT JOIN Socio_Entrenador se ON se.ID_socio = u.ID_usuario
             INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
             WHERE t.Nombre = 'Socio'
             ORDER BY p.Nombre"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $entrenadores = $this->db->query(
            "SELECT u.ID_usuario AS id, p.Nombre AS nombre
             FROM Usuarios u
             INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
             INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
             WHERE t.Nombre = 'Entrenador' AND u.Activo = 1
             ORDER BY p.Nombre"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $ejercicios = $this->db->query(
            'SELECT ID_ejercicio AS id, Nombre AS nombre, Grupo_muscular AS grupo_muscular, Activo AS activo
             FROM Ejercicios ORDER BY Nombre'
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($socios as &$socio) {
            $socio['programas'] = $socio['programas'] === null ? [] : array_map('intval', explode(',', $socio['programas']));
        }
        unset($socio);

        $this->exito([
            'socios' => $socios,
            'usuarios' => $this->usuarios->listar(),
            'roles' => $this->usuarios->roles(),
            'entrenadores' => $entrenadores,
            'ejercicios' => $ejercicios,
            'planes' => $this->programas->planes(),
            'programas' => $this->programas->programas(),
            'metricas' => $this->metricas(),
        ]);
    }

    /** Contadores del panel: usuarios, activos, socios e ingresos de hoy. */
    private function metricas(): array {
        $total = (int) $this->db->query('SELECT COUNT(*) FROM Usuarios')->fetchColumn();
        $activos = (int) $this->db->query('SELECT COUNT(*) FROM Usuarios WHERE Activo = 1')->fetchColumn();
        $socios = (int) $this->db->query(
            "SELECT COUNT(*) FROM Usuarios u INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo WHERE t.Nombre = 'Socio'"
        )->fetchColumn();
        $accesosHoy = (int) $this->db->query(
            "SELECT COUNT(*) FROM Registro_Accesos WHERE Estado_acceso = 'Permitido' AND DATE(Fecha_hora_ingreso) = CURRENT_DATE"
        )->fetchColumn();

        return [
            'usuarios' => $total,
            'activos' => $activos,
            'inactivos' => $total - $activos,
            'socios' => $socios,
            'accesos_hoy' => $accesosHoy,
        ];
    }


    /** Indica si el usuario existe y tiene rol Socio. */
    private function esSocio(int $id): bool {
        $usuario = $this->usuarios->buscar($id);

        return $usuario !== null && strcasecmp($usuario['rol'], 'Socio') === 0;
    }

    /** PUT /admin/socios/:id/entrenador: asigna o quita el entrenador personal (solo si el plan lo incluye). */
    public function asignarEntrenador($id) {
        if (!$this->autorizado()) return;

        $id = (int) $id;
        if (!$this->esSocio($id)) {
            return $this->error('Socio no encontrado', 404);
        }

        $entrenador = (int) ($this->body()['entrenador_id'] ?? 0);

        if ($entrenador) {
            if (!$this->programas->esEntrenadorActivo($entrenador)) {
                return $this->error('El entrenador elegido no existe o está inactivo', 422);
            }
            $plan = $this->programas->planDelSocio($id);
            if (!$plan || !$plan['entrenador_personal']) {
                return $this->error(
                    ($plan ? 'El ' . $plan['nombre'] : 'Un socio sin plan') . ' no incluye entrenador personal',
                    422
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO Socio_Entrenador (ID_socio, ID_entrenador) VALUES (:socio, :entrenador)
                 ON DUPLICATE KEY UPDATE ID_entrenador = VALUES(ID_entrenador), Fecha_asignacion = NOW()'
            );
            $stmt->execute(['socio' => (int) $id, 'entrenador' => $entrenador]);
        } else {
            $stmt = $this->db->prepare('DELETE FROM Socio_Entrenador WHERE ID_socio = :socio');
            $stmt->execute(['socio' => (int) $id]);
        }

        $this->exito(['message' => 'Entrenador actualizado']);
    }

    /** PUT /admin/socios/:id/membresia: plan, estado y vencimiento; ajusta programas y entrenador al plan. */
    public function actualizarMembresia($id) {
        if (!$this->autorizado()) return;

        $datos = $this->body();
        $plan = (int) ($datos['plan_id'] ?? 0);
        $estado = $datos['estado'] ?? 'Al dia';
        $vencimiento = trim((string) ($datos['vencimiento'] ?? ''));

        if ($vencimiento === '') {
            $vencimiento = date('Y-m-t');
        }

        $permitidos = ['Al dia', 'Vencida', 'Bloqueada'];
        if (!$plan || !in_array($estado, $permitidos, true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimiento)) {
            return $this->error('Datos de membresía inválidos');
        }

        $stmt = $this->db->prepare('SELECT Precio FROM Plan WHERE ID_plan = :plan');
        $stmt->execute(['plan' => $plan]);
        $precio = $stmt->fetchColumn();

        if ($precio === false) {
            return $this->error('Plan inexistente');
        }

        $stmt = $this->db->prepare('SELECT ID_membresia FROM Membresias WHERE ID_usuario = :usuario ORDER BY Fecha_vencimiento DESC LIMIT 1');
        $stmt->execute(['usuario' => (int) $id]);
        $membresia = $stmt->fetchColumn();

        if ($membresia) {
            $stmt = $this->db->prepare(
                'UPDATE Membresias SET ID_plan = :plan, Fecha_vencimiento = :vencimiento, Estado = :estado,
                        Fecha_ultimo_pago = NOW(), Monto_ultimo_pago = :precio
                 WHERE ID_membresia = :id'
            );
            $stmt->execute([
                'plan' => $plan,
                'vencimiento' => $vencimiento,
                'estado' => $estado,
                'precio' => $precio,
                'id' => $membresia,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO Membresias (ID_usuario, ID_plan, Fecha_inicio, Fecha_vencimiento, Estado, Fecha_ultimo_pago, Monto_ultimo_pago)
                 VALUES (:usuario, :plan, CURRENT_DATE, :vencimiento, :estado, NOW(), :precio)'
            );
            $stmt->execute([
                'usuario' => (int) $id,
                'plan' => $plan,
                'vencimiento' => $vencimiento,
                'estado' => $estado,
                'precio' => $precio,
            ]);
        }

        $this->programas->ajustarAlPlan((int) $id);

        $this->exito(['message' => 'Membresía actualizada']);
    }

    /** PUT /admin/socios/:id/programas: reemplaza los programas del socio (dentro de su plan). */
    public function inscribirProgramas($id) {
        if (!$this->autorizado()) return;

        $id = (int) $id;
        if (!$this->esSocio($id)) {
            return $this->error('Socio no encontrado', 404);
        }

        $programas = $this->body()['programas'] ?? [];
        if (!is_array($programas)) {
            return $this->error('La lista de programas no es válida', 422);
        }

        $motivo = $this->programas->inscribir($id, $programas);
        if ($motivo !== null) {
            return $this->error($motivo, 422);
        }

        $this->exito(['message' => 'Programas actualizados']);
    }


    /** PUT /admin/programas/:id/entrenador: cambia el entrenador a cargo del programa. */
    public function asignarEntrenadorPrograma($id) {
        if (!$this->autorizado()) return;

        $id = (int) $id;
        if (!$this->programas->existe($id)) {
            return $this->error('Programa no encontrado', 404);
        }

        $entrenador = (int) ($this->body()['entrenador_id'] ?? 0);
        if ($entrenador && !$this->programas->esEntrenadorActivo($entrenador)) {
            return $this->error('El entrenador elegido no existe o está inactivo', 422);
        }

        $this->programas->asignarEntrenador($id, $entrenador ?: null);

        $this->exito(['message' => 'Entrenador del programa actualizado']);
    }

    /** POST /admin/ejercicios: agrega un ejercicio al catálogo. */
    public function crearEjercicio() {
        if (!$this->autorizado()) return;

        $datos = $this->body();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $grupo = trim((string) ($datos['grupo_muscular'] ?? ''));

        if ($nombre === '' || $grupo === '') {
            return $this->error('Nombre y grupo muscular son obligatorios');
        }

        try {
            $stmt = $this->db->prepare('INSERT INTO Ejercicios (Nombre, Grupo_muscular, Descripcion) VALUES (:nombre, :grupo, :descripcion)');
            $stmt->execute([
                'nombre' => $nombre,
                'grupo' => $grupo,
                'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
            ]);

            $this->exito(['message' => 'Ejercicio agregado'], 201);
        } catch (\PDOException $error) {
            $this->error('El ejercicio ya existe', 409);
        }
    }

    /** GET /admin/usuarios: usuarios, roles y métricas para refrescar la tabla. */
    public function listarUsuarios() {
        if (!$this->autorizado()) return;

        $this->exito([
            'usuarios' => $this->usuarios->listar(),
            'roles' => $this->usuarios->roles(),
            'metricas' => $this->metricas(),
        ]);
    }

    /** Valida el formato del nombre de usuario; devuelve el mensaje de error o null. */
    private function errorNombreUsuario(string $nombreUsuario): ?string {
        $largo = strlen($nombreUsuario);
        if ($largo < UsuarioModel::LARGO_MIN_USUARIO || $largo > UsuarioModel::LARGO_MAX_USUARIO) {
            return sprintf(
                'El usuario debe tener entre %d y %d caracteres',
                UsuarioModel::LARGO_MIN_USUARIO,
                UsuarioModel::LARGO_MAX_USUARIO
            );
        }
        if (!preg_match(UsuarioModel::FORMATO_USUARIO, $nombreUsuario)) {
            return 'El usuario solo admite minúsculas, números, punto, guion y guion bajo (ej. juan.diaz)';
        }

        return null;
    }

    /** POST /admin/usuarios: alta de un usuario de cualquier rol. */
    public function crearUsuario() {
        if (!$this->autorizado()) return;

        $datos = $this->body();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $nombreUsuario = strtolower(trim((string) ($datos['nombre_usuario'] ?? '')));
        $password = (string) ($datos['password'] ?? '');
        $rol = (int) ($datos['rol_id'] ?? 0);

        if ($nombre === '') {
            return $this->error('El nombre es obligatorio', 422, ['campo' => 'nombre']);
        }
        if ($motivo = $this->errorNombreUsuario($nombreUsuario)) {
            return $this->error($motivo, 422, ['campo' => 'nombre_usuario']);
        }
        if (strlen($password) < 6) {
            return $this->error('La contraseña debe tener al menos 6 caracteres', 422, ['campo' => 'password']);
        }
        if (!$this->usuarios->rolExiste($rol)) {
            return $this->error('El rol seleccionado no existe', 422, ['campo' => 'rol_id']);
        }
        if ($this->usuarios->usuarioEnUso($nombreUsuario)) {
            return $this->error('Ya existe una cuenta con ese usuario', 409, ['campo' => 'nombre_usuario']);
        }

        try {
            $id = $this->usuarios->crear($nombre, $nombreUsuario, $password, $rol);
        } catch (\PDOException $error) {
            return $this->error('No se pudo crear el usuario', 409);
        }

        $this->exito([
            'message' => 'Usuario creado correctamente',
            'usuario' => $this->usuarios->buscar($id),
        ], 201);
    }

    /** PUT /admin/usuarios/:id: edita nombre, usuario, rol y (opcional) contraseña. */
    public function actualizarUsuario($id) {
        if (!$this->autorizado()) return;

        $id = (int) $id;
        $actual = $this->usuarios->buscar($id);
        if (!$actual) {
            return $this->error('Usuario no encontrado', 404);
        }

        $datos = $this->body();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $nombreUsuario = strtolower(trim((string) ($datos['nombre_usuario'] ?? '')));
        $rol = (int) ($datos['rol_id'] ?? $actual['rol_id']);
        $password = (string) ($datos['password'] ?? '');

        if ($nombre === '') {
            return $this->error('El nombre es obligatorio', 422, ['campo' => 'nombre']);
        }
        if ($motivo = $this->errorNombreUsuario($nombreUsuario)) {
            return $this->error($motivo, 422, ['campo' => 'nombre_usuario']);
        }
        if ($password !== '' && strlen($password) < 6) {
            return $this->error('La contraseña debe tener al menos 6 caracteres', 422, ['campo' => 'password']);
        }
        if (!$this->usuarios->rolExiste($rol)) {
            return $this->error('El rol seleccionado no existe', 422, ['campo' => 'rol_id']);
        }
        if ($this->usuarios->usuarioEnUso($nombreUsuario, $id)) {
            return $this->error('Ya existe otra cuenta con ese usuario', 409, ['campo' => 'nombre_usuario']);
        }

        $esAdmin = strcasecmp($actual['rol'], 'Administrador') === 0;
        if ($esAdmin && $rol !== $actual['rol_id'] && $this->usuarios->otrosAdminsActivos($id) === 0) {
            return $this->error('Es el único administrador activo: no se puede cambiar su rol', 409);
        }

        try {
            $this->usuarios->actualizar($id, $nombre, $nombreUsuario, $rol, $password !== '' ? $password : null);
        } catch (\PDOException $error) {
            return $this->error('No se pudo actualizar el usuario', 409);
        }

        $actualizado = $this->usuarios->buscar($id);

        if ($id === (int) $_SESSION['usuario_id']) {
            $_SESSION['usuario_nombre'] = $actualizado['nombre'];
            $_SESSION['usuario_login'] = $actualizado['nombre_usuario'];
        }

        $this->exito([
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $actualizado,
        ]);
    }

    /** PUT /admin/usuarios/:id/estado: baja lógica o habilitación. */
    public function cambiarEstadoUsuario($id) {
        $admin = $this->autorizado();
        if (!$admin) return;

        $id = (int) $id;
        $usuario = $this->usuarios->buscar($id);
        if (!$usuario) {
            return $this->error('Usuario no encontrado', 404);
        }

        $datos = $this->body();
        if (!array_key_exists('activo', $datos)) {
            return $this->error('Falta indicar el nuevo estado');
        }
        $activo = filter_var($datos['activo'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($activo === null) {
            return $this->error('El estado debe ser activo o inactivo');
        }

        if (!$activo && $id === $admin) {
            return $this->error('No podés darte de baja a vos mismo', 409);
        }
        if (!$activo && strcasecmp($usuario['rol'], 'Administrador') === 0 && $this->usuarios->otrosAdminsActivos($id) === 0) {
            return $this->error('Es el único administrador activo: no se puede dar de baja', 409);
        }

        $this->usuarios->cambiarEstado($id, $activo);

        $this->exito([
            'message' => $activo ? 'Usuario habilitado correctamente' : 'Usuario dado de baja correctamente',
            'usuario' => $this->usuarios->buscar($id),
        ]);
    }

    /** GET /admin/usuarios/:id/dependencias: qué se borraría con el usuario. */
    public function dependenciasUsuario($id) {
        if (!$this->autorizado()) return;

        $id = (int) $id;
        $usuario = $this->usuarios->buscar($id);
        if (!$usuario) {
            return $this->error('Usuario no encontrado', 404);
        }

        $dependencias = $this->usuarios->dependencias($id);

        $this->exito([
            'usuario' => $usuario,
            'dependencias' => $dependencias,
            'requiere_confirmacion_extra' => $this->usuarios->tieneDependenciasBloqueantes($dependencias),
        ]);
    }

    /** DELETE /admin/usuarios/:id: borrado definitivo (pide confirmación si hay registros relacionados). */
    public function eliminarUsuario($id) {
        $admin = $this->autorizado();
        if (!$admin) return;

        $id = (int) $id;
        $usuario = $this->usuarios->buscar($id);
        if (!$usuario) {
            return $this->error('Usuario no encontrado', 404);
        }

        if ($id === $admin) {
            return $this->error('No podés eliminar tu propia cuenta', 409);
        }
        if (strcasecmp($usuario['rol'], 'Administrador') === 0 && $this->usuarios->otrosAdminsActivos($id) === 0) {
            return $this->error('Es el único administrador activo: no se puede eliminar', 409);
        }

        $dependencias = $this->usuarios->dependencias($id);
        $forzar = filter_var($_GET['forzar'] ?? ($this->body()['forzar'] ?? false), FILTER_VALIDATE_BOOLEAN);

        if ($this->usuarios->tieneDependenciasBloqueantes($dependencias) && !$forzar) {
            return $this->error(
                'El usuario tiene registros asociados. Confirmá el borrado en cascada para continuar.',
                409,
                ['dependencias' => $dependencias, 'requiere_confirmacion_extra' => true]
            );
        }

        try {
            $this->usuarios->eliminar($id);
        } catch (\PDOException $error) {
            return $this->error('No se pudo eliminar el usuario: tiene registros relacionados', 409);
        }

        $this->exito([
            'message' => 'Usuario eliminado permanentemente',
            'id' => $id,
        ]);
    }
}
