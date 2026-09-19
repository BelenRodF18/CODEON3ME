<?php
namespace App\Controllers;

use App\Models\ProgresoModel;

/** Portal del socio: membresía, programas, rutinas, QR, rendimiento y reportes. */
class SocioController {
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

    /** Exige sesión de socio; devuelve su ID o null (ya respondió 401). */
    private function usuario() {
        if (empty($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'socio') {
            $this->json(['status' => 'error', 'message' => 'Se requiere una sesión de socio'], 401);
            return null;
        }
        return (int) $_SESSION['usuario_id'];
    }

    /** ID del socio conectado. */
    private function socioActual() {
        return $this->usuario();
    }

    /** GET /socio/resumen: membresía, programas, últimas rutinas y personas adentro. */
    public function resumen() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $stmt = $this->db->prepare(
            'SELECT m.Estado AS estado, m.Fecha_vencimiento AS fecha_vencimiento, p.Nombre AS plan, p.Precio AS precio, pe.Nombre AS entrenador
             FROM Membresias m
             INNER JOIN Plan p ON p.ID_plan = m.ID_plan
             LEFT JOIN Socio_Entrenador se ON se.ID_socio = m.ID_usuario
             LEFT JOIN Persona pe ON pe.ID_usuario = se.ID_entrenador
             WHERE m.ID_usuario = :usuario
             ORDER BY m.Fecha_vencimiento DESC LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuario]);
        $membresia = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        if ($membresia) {
            $estado = strtolower($membresia['estado']);
            $membresia['estado'] = str_contains($estado, 'al d')
                ? 'al_dia'
                : (str_contains($estado, 'venc') ? 'vencida' : (str_contains($estado, 'bloq') ? 'bloqueada' : $estado));
        }

        $rutinas = $this->rutinasData($usuario, 3);
        $aforo = (new \App\Models\AccesoModel($this->db))->personasAdentro();

        $programas = array_map(fn (array $programa) => [
            'id' => $programa['id'],
            'slug' => $programa['slug'],
            'nombre' => $programa['nombre'],
            'imagen' => $programa['imagen'],
            'entrenador' => $programa['entrenador'],
            'entrenador_foto' => $programa['entrenador_foto'],
        ], (new \App\Models\ProgramaModel($this->db))->inscripciones($usuario));

        return $this->json([
            'status' => 'success',
            'membresia' => $membresia,
            'programas' => $programas,
            'rutinas' => $rutinas,
            'aforo' => $aforo,
        ]);
    }

    /** GET /socio/rutinas: todas las rutinas asignadas. */
    public function rutinas() {
        $usuario = $this->socioActual();
        if (!$usuario) return;
        $this->json(['status' => 'success', 'rutinas' => $this->rutinasData($usuario)]);
    }

    /** Rutinas del socio con su programa, entrenador y ejercicios. */
    private function rutinasData($usuario, $limit = null) {
        $sql = 'SELECT r.ID_rutina AS id, r.Nombre_rutina AS nombre, r.Descripcion AS descripcion, DATE_FORMAT(r.Fecha_creacion, "%d/%m/%Y") AS fecha,
                       p.Nombre AS entrenador, pr.Nombre AS programa
                FROM Rutinas r
                INNER JOIN Usuario_Rutina ur ON ur.ID_rutina = r.ID_rutina
                INNER JOIN Persona p ON p.ID_usuario = r.ID_entrenador
                LEFT JOIN Programas pr ON pr.ID_programa = r.ID_programa
                WHERE ur.ID_usuario = :usuario AND r.Activa = 1
                ORDER BY r.Fecha_creacion DESC';
        if ($limit) $sql .= ' LIMIT ' . (int) $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        $rutinas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ex = $this->db->prepare(
            'SELECT re.ID_rutina_ejercicio AS id, re.Series AS series, re.Repeticiones AS repeticiones, re.Tiempo_descanso AS descanso_segundos, re.Orden AS orden, e.Nombre AS nombre, e.Grupo_muscular AS grupo_muscular
             FROM Rutina_Ejercicio re
             INNER JOIN Ejercicios e ON e.ID_ejercicio = re.ID_ejercicio
             WHERE re.ID_rutina = :rutina
             ORDER BY re.Orden'
        );
        foreach ($rutinas as &$rutina) {
            $ex->execute(['rutina' => $rutina['id']]);
            $rutina['ejercicios'] = $ex->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $rutinas;
    }

    /** POST /socio/reportes: nuevo reporte (con foto opcional) o sugerencia; abre el chat. */
    public function crearReporte() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $tipo = trim((string) ($_POST['tipo'] ?? 'mantenimiento'));
        if (!in_array($tipo, ['mantenimiento', 'sugerencia'], true)) {
            return $this->json(['status' => 'error', 'message' => 'Tipo inválido'], 400);
        }

        $titulo = trim((string) ($_POST['etiqueta'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        if ($titulo === '' || $descripcion === '') {
            return $this->json(['status' => 'error', 'message' => 'Asunto y descripción son obligatorios'], 400);
        }

        $foto = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK || $_FILES['foto']['size'] > 5242880) {
                return $this->json(['status' => 'error', 'message' => 'La foto debe pesar menos de 5 MB'], 400);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($_FILES['foto']['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($allowed[$mime])) {
                return $this->json(['status' => 'error', 'message' => 'Formato no válido'], 400);
            }
            $dir = dirname(__DIR__, 2) . '/uploads/mantenimiento';
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            $file = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . '/' . $file)) {
                return $this->json(['status' => 'error', 'message' => 'No se pudo guardar la foto'], 500);
            }
            $foto = 'uploads/mantenimiento/' . $file;
        }

        $tipoDb = $tipo === 'sugerencia' ? 'Sugerencia' : 'Mantenimiento';
        $stmt = $this->db->prepare(
            'INSERT INTO Chat (Tipo_chat, Titulo, Descripcion, Foto, Fecha_hora, Estado, ID_usuario) VALUES (:tipo, :titulo, :descripcion, :foto, NOW(), \'Abierto\', :usuario)'
        );
        $stmt->execute(['tipo' => $tipoDb, 'titulo' => $titulo, 'descripcion' => $descripcion, 'foto' => $foto, 'usuario' => $usuario]);
        $chat = (int) $this->db->lastInsertId();

        $this->mensaje(
            $chat,
            $usuario,
            $tipo === 'sugerencia'
                ? 'Sugerencia recibida. El administrador la revisará pronto.'
                : 'Reporte recibido. El personal de mantenimiento revisará el caso.'
        );

        $this->json([
            'status' => 'success',
            'reporte_id' => $chat,
            'message' => $tipo === 'sugerencia' ? 'Sugerencia enviada' : 'Reporte creado',
        ]);
    }

    /** Guarda un mensaje en el chat de un reporte. */
    private function mensaje($chat, $usuario, $texto) {
        $stmt = $this->db->prepare('INSERT INTO Mensajes_chat (ID_chat, ID_usuario, Mensaje) VALUES (:chat, :usuario, :mensaje)');
        $stmt->execute(['chat' => $chat, 'usuario' => $usuario, 'mensaje' => $texto]);
    }

    /** GET /socio/reportes: sus reportes con la cantidad de novedades sin leer. */
    public function reportes() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $stmt = $this->db->prepare(
            'SELECT c.ID_chat AS id, LOWER(c.Tipo_chat) AS tipo, c.Titulo AS etiqueta, c.Descripcion AS descripcion, LOWER(REPLACE(c.Estado, \' \', \'_\')) AS estado, c.Foto AS foto_path, DATE_FORMAT(c.Fecha_hora, "%d/%m/%Y %H:%i") AS creado,
                    (SELECT COUNT(*) FROM Mensajes_chat m
                     WHERE m.ID_chat = c.ID_chat AND m.ID_usuario <> c.ID_usuario AND m.ID_mensaje > COALESCE(c.Ultimo_leido_socio, 0)) AS sin_leer
             FROM Chat c WHERE c.ID_usuario = :usuario ORDER BY c.Fecha_hora DESC'
        );
        $stmt->execute(['usuario' => $usuario]);
        $this->json(['status' => 'success', 'reportes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** GET /socio/avisos: novedades de sus reportes que todavía no vio. */
    public function avisos() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $avisos = (new \App\Models\ChatModel($this->db))->avisosDelSocio($usuario);
        $this->json(['status' => 'success', 'pendientes' => count($avisos), 'avisos' => $avisos]);
    }

    /** POST /socio/avisos/leidos: marca todas las novedades como vistas. */
    public function marcarAvisosLeidos() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        (new \App\Models\ChatModel($this->db))->marcarLeidos($usuario);
        $this->json(['status' => 'success', 'message' => 'Avisos marcados como leídos']);
    }

    /** GET /socio/reportes/:id/mensajes: chat de uno de sus reportes. */
    public function mensajes($id) {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $stmt = $this->db->prepare(
            'SELECT m.ID_mensaje AS id, m.Mensaje AS mensaje, m.Fecha_hora AS creado_en, p.Nombre AS nombre
             FROM Mensajes_chat m
             INNER JOIN Chat c ON c.ID_chat = m.ID_chat
             INNER JOIN Persona p ON p.ID_usuario = m.ID_usuario
             WHERE m.ID_chat = :chat AND c.ID_usuario = :usuario
             ORDER BY m.Fecha_hora'
        );
        $stmt->execute(['chat' => (int) $id, 'usuario' => $usuario]);
        $this->json(['status' => 'success', 'mensajes' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** POST /socio/reportes/:id/mensajes: escribe en el chat de su reporte. */
    public function enviarMensaje($id) {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $mensaje = trim((string) ($in['mensaje'] ?? ''));

        $stmt = $this->db->prepare('SELECT ID_chat FROM Chat WHERE ID_chat = :chat AND ID_usuario = :usuario');
        $stmt->execute(['chat' => (int) $id, 'usuario' => $usuario]);
        if (!$stmt->fetchColumn() || $mensaje === '') {
            return $this->json(['status' => 'error', 'message' => 'Conversación o mensaje inválido'], 400);
        }

        $this->mensaje((int) $id, $usuario, $mensaje);
        $this->json(['status' => 'success', 'message' => 'Mensaje enviado']);
    }

    /** GET /socio/qr: genera su QR de acceso (no escribe en la base). */
    public function generarQr() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $accesos = new \App\Models\AccesoModel($this->db);
        $qr = \App\Utils\QrToken::generar($usuario, $accesos->semilla($usuario));

        $this->json([
            'status' => 'success',
            'token' => $qr['token'],
            'vigencia_segundos' => $qr['vigencia'],
            'expira_en' => date(DATE_ATOM, $qr['expira_en']),
        ]);
    }

    /** POST /accesos/validar: canje del QR (ruta anterior, equivalente a la de Recepción). */
    public function validarQr() {
        if (empty($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'] ?? '', ['recepcion', 'admin'], true)) {
            return $this->json(['status' => 'error', 'message' => 'Se requiere una sesión de recepción'], 401);
        }

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $token = trim((string) ($in['token'] ?? ''));
        if ($token === '') return $this->json(['status' => 'error', 'message' => 'Token requerido'], 400);

        $accesos = new \App\Models\AccesoModel($this->db);
        $resultado = $accesos->canjear($token, (int) $_SESSION['usuario_id']);

        $this->json([
            'status' => $resultado['permitido'] ? 'success' : 'error',
            'permitido' => $resultado['permitido'],
            'codigo' => $resultado['codigo'],
            'movimiento' => $resultado['movimiento'] ?? null,
            'message' => $resultado['permitido']
                ? ($resultado['movimiento'] === 'salida' ? 'Salida registrada' : 'Acceso permitido')
                : $resultado['motivo'],
            'socio' => $resultado['socio'],
            'acceso' => $resultado['acceso'] ?? null,
        ], $resultado['permitido'] ? 200 : 403);
    }

    /** GET /socio/rendimiento: historial de series, récords y ejercicios disponibles. */
    public function rendimiento() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $stmt = $this->db->prepare(
            'SELECT rp.ID_registro AS id, e.Nombre AS ejercicio, rp.Carga_utilizada AS peso, rp.Repeticiones_realizadas AS repeticiones, rp.Sensaciones_entrenamiento AS sensacion, rp.Tiempo_descanso AS descanso_segundos, DATE_FORMAT(rp.Fecha, "%d/%m/%Y") AS fecha, rp.Record_personal AS record
             FROM Registro_Progreso rp
             INNER JOIN Ejercicios e ON e.ID_ejercicio = rp.ID_ejercicio
             WHERE rp.ID_usuario = :usuario
             ORDER BY rp.Fecha DESC'
        );
        $stmt->execute(['usuario' => $usuario]);
        $series = array_map(function (array $serie): array {
            $serie['record'] = (int) $serie['record'] === 1;

            return $serie;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));

        $ej = $this->db->query('SELECT ID_ejercicio AS id, Nombre AS nombre, Grupo_muscular AS grupo_muscular FROM Ejercicios WHERE Activo = 1 ORDER BY Nombre')
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'status' => 'success',
            'series' => $series,
            'records' => (new ProgresoModel($this->db))->records($usuario),
            'ejercicios' => $ej,
        ]);
    }

    /** POST /socio/series: registra una serie y avisa si es un récord nuevo. */
    public function registrarSerie() {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $ej = (int) ($in['ejercicio_id'] ?? 0);
        $peso = (float) ($in['peso'] ?? 0);
        $reps = (int) ($in['repeticiones'] ?? 0);
        $desc = max(10, min(600, (int) ($in['descanso_segundos'] ?? 90)));
        $sens = trim((string) ($in['sensacion'] ?? ''));

        if ($ej < 1 || $peso < 0 || $reps < 1) {
            return $this->json(['status' => 'error', 'message' => 'Completa ejercicio, peso y repeticiones'], 400);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO Registro_Progreso (Carga_utilizada, Repeticiones_realizadas, Sensaciones_entrenamiento, Tiempo_descanso, ID_usuario, ID_ejercicio) VALUES (:peso, :reps, :sens, :desc, :usuario, :ej)'
        );
        $stmt->execute(['peso' => $peso, 'reps' => $reps, 'sens' => $sens, 'desc' => $desc, 'usuario' => $usuario, 'ej' => $ej]);
        $serie = (int) $this->db->lastInsertId();

        $progreso = new ProgresoModel($this->db);
        $progreso->recalcularRecords($usuario, $ej);

        $this->json([
            'status' => 'success',
            'message' => 'Serie registrada',
            'descanso_segundos' => $desc,
            'record' => $progreso->esRecord($serie),
        ]);
    }

    /** PUT /socio/series/:id: corrige una serie y recalcula los récords. */
    public function actualizarSerie($id) {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $peso = (float) ($in['peso'] ?? -1);
        $reps = (int) ($in['repeticiones'] ?? 0);
        $desc = max(10, min(600, (int) ($in['descanso_segundos'] ?? 90)));
        $sens = trim((string) ($in['sensacion'] ?? ''));

        if ($peso < 0 || $reps < 1) {
            return $this->json(['status' => 'error', 'message' => 'Peso y repeticiones deben ser válidos'], 400);
        }

        $progreso = new ProgresoModel($this->db);
        $ejercicio = $progreso->ejercicioDeSerie((int) $id, $usuario);
        if ($ejercicio === null) {
            return $this->json(['status' => 'error', 'message' => 'Registro no encontrado'], 404);
        }

        $stmt = $this->db->prepare(
            'UPDATE Registro_Progreso SET Carga_utilizada = :peso, Repeticiones_realizadas = :reps, Tiempo_descanso = :desc, Sensaciones_entrenamiento = :sens WHERE ID_registro = :id AND ID_usuario = :usuario'
        );
        $stmt->execute(['peso' => $peso, 'reps' => $reps, 'desc' => $desc, 'sens' => $sens, 'id' => (int) $id, 'usuario' => $usuario]);
        $progreso->recalcularRecords($usuario, $ejercicio);

        $this->json(['status' => 'success', 'message' => 'Serie actualizada']);
    }

    /** DELETE /socio/series/:id: borra una serie y recalcula los récords. */
    public function eliminarSerie($id) {
        $usuario = $this->socioActual();
        if (!$usuario) return;

        $progreso = new ProgresoModel($this->db);
        $ejercicio = $progreso->ejercicioDeSerie((int) $id, $usuario);
        if ($ejercicio === null) {
            return $this->json(['status' => 'error', 'message' => 'Registro no encontrado'], 404);
        }

        $stmt = $this->db->prepare('DELETE FROM Registro_Progreso WHERE ID_registro = :id AND ID_usuario = :usuario');
        $stmt->execute(['id' => (int) $id, 'usuario' => $usuario]);
        $progreso->recalcularRecords($usuario, $ejercicio);

        $this->json(['status' => 'success', 'message' => 'Serie eliminada']);
    }
}
