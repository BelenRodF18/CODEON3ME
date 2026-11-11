<?php
namespace App\Controllers;

use App\Models\ProgramaModel;
use App\Models\ProgresoModel;

/** Panel del entrenador: sus programas, sus socios y la creación de rutinas. */
class EntrenadorController {
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

    /** Exige sesión de entrenador; devuelve su ID o false. */
    private function autorizado() {
        if (empty($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== 'entrenador') {
            $this->json(['status' => 'error', 'message' => 'Se requiere una sesión de entrenador'], 401);
            return false;
        }
        return (int) $_SESSION['usuario_id'];
    }

    /** Socios del entrenador: los que lo tienen de entrenador personal y los inscriptos en sus programas. */
    private function sociosDe(int $entrenador): array {
        $stmt = $this->db->prepare(
            "SELECT u.ID_usuario AS id, p.Nombre AS nombre, u.Usuario AS nombre_usuario,
                    COALESCE((SELECT m.Estado FROM Membresias m WHERE m.ID_usuario = u.ID_usuario ORDER BY m.Fecha_vencimiento DESC LIMIT 1), 'Sin membresía') AS membresia,
                    MAX(se.ID_socio IS NOT NULL) AS personal,
                    GROUP_CONCAT(DISTINCT pr.ID_programa ORDER BY pr.Nombre) AS programa_ids,
                    GROUP_CONCAT(DISTINCT pr.Nombre ORDER BY pr.Nombre SEPARATOR ', ') AS programas
             FROM Usuarios u
             INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
             LEFT JOIN Socio_Entrenador se ON se.ID_socio = u.ID_usuario AND se.ID_entrenador = :entrenador_personal
             LEFT JOIN Socio_Programa sp ON sp.ID_socio = u.ID_usuario
             LEFT JOIN Programas pr ON pr.ID_programa = sp.ID_programa AND pr.ID_entrenador = :entrenador_programa
             WHERE se.ID_socio IS NOT NULL OR pr.ID_programa IS NOT NULL
             GROUP BY u.ID_usuario, p.Nombre, u.Usuario
             ORDER BY p.Nombre"
        );
        $stmt->execute(['entrenador_personal' => $entrenador, 'entrenador_programa' => $entrenador]);

        return array_map(function (array $socio): array {
            $socio['id'] = (int) $socio['id'];
            $socio['personal'] = (int) $socio['personal'] === 1;
            $socio['programa_ids'] = $socio['programa_ids'] === null ? [] : array_map('intval', explode(',', $socio['programa_ids']));

            return $socio;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** GET /entrenador/resumen: programas, socios, ejercicios y rutinas del entrenador. */
    public function resumen() {
        $entrenador = $this->autorizado();
        if (!$entrenador) return;

        $ejercicios = $this->db->query(
            'SELECT ID_ejercicio AS id, Nombre AS nombre, Grupo_muscular AS grupo FROM Ejercicios WHERE Activo = 1 ORDER BY Nombre'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $rutinas = $this->db->prepare(
            'SELECT r.ID_rutina AS id, r.Nombre_rutina AS nombre, r.Descripcion AS descripcion, p.Nombre AS socio,
                    pr.Nombre AS programa, DATE_FORMAT(r.Fecha_creacion, "%d/%m/%Y") AS fecha
             FROM Rutinas r
             INNER JOIN Usuario_Rutina ur ON ur.ID_rutina = r.ID_rutina
             INNER JOIN Persona p ON p.ID_usuario = ur.ID_usuario
             LEFT JOIN Programas pr ON pr.ID_programa = r.ID_programa
             WHERE r.ID_entrenador = :entrenador AND r.Activa = 1
             ORDER BY r.Fecha_creacion DESC'
        );
        $rutinas->execute(['entrenador' => $entrenador]);

        $this->json([
            'status' => 'success',
            'programas' => (new ProgramaModel($this->db))->programasDeEntrenador($entrenador),
            'socios' => $this->sociosDe($entrenador),
            'ejercicios' => $ejercicios,
            'rutinas' => $rutinas->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    /** GET /entrenador/socios/:id/evolucion: récords, series recientes y constancia de un socio suyo. */
    public function evolucion($id) {
        $entrenador = $this->autorizado();
        if (!$entrenador) return;

        $socio = (int) $id;
        $vinculo = null;
        foreach ($this->sociosDe($entrenador) as $candidato) {
            if ($candidato['id'] === $socio) {
                $vinculo = $candidato;
                break;
            }
        }
        if (!$vinculo) {
            return $this->json(['status' => 'error', 'message' => 'El socio no está asignado a este entrenador ni a sus programas'], 403);
        }

        $progreso = new ProgresoModel($this->db);

        // Primera carga registrada de cada ejercicio, para medir cuánto mejoró.
        $primeras = $this->db->prepare(
            'SELECT e.Nombre AS ejercicio, rp.Carga_utilizada AS carga
             FROM Registro_Progreso rp
             INNER JOIN Ejercicios e ON e.ID_ejercicio = rp.ID_ejercicio
             WHERE rp.ID_usuario = :socio
               AND rp.ID_registro = (SELECT r2.ID_registro FROM Registro_Progreso r2
                                     WHERE r2.ID_usuario = rp.ID_usuario AND r2.ID_ejercicio = rp.ID_ejercicio
                                     ORDER BY r2.Fecha, r2.ID_registro LIMIT 1)'
        );
        $primeras->execute(['socio' => $socio]);
        $primera = [];
        foreach ($primeras->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
            $primera[$fila['ejercicio']] = (float) $fila['carga'];
        }

        $records = $progreso->records($socio);
        foreach ($records as &$record) {
            $inicial = $primera[$record['ejercicio']] ?? $record['peso'];
            $record['primera'] = $inicial;
            $record['mejora'] = round($record['peso'] - $inicial, 2);
        }
        unset($record);

        $recientes = $this->db->prepare(
            'SELECT e.Nombre AS ejercicio, DATE_FORMAT(rp.Fecha, "%d/%m/%Y") AS fecha, rp.Carga_utilizada AS peso,
                    rp.Repeticiones_realizadas AS repeticiones, rp.Record_personal AS record, rp.Sensaciones_entrenamiento AS sensacion
             FROM Registro_Progreso rp
             INNER JOIN Ejercicios e ON e.ID_ejercicio = rp.ID_ejercicio
             WHERE rp.ID_usuario = :socio
             ORDER BY rp.Fecha DESC, rp.ID_registro DESC LIMIT 12'
        );
        $recientes->execute(['socio' => $socio]);
        $series = array_map(function (array $fila): array {
            $fila['peso'] = (float) $fila['peso'];
            $fila['repeticiones'] = (int) $fila['repeticiones'];
            $fila['record'] = (int) $fila['record'] === 1;

            return $fila;
        }, $recientes->fetchAll(\PDO::FETCH_ASSOC));

        // Constancia: días con entrenamiento registrado en cada una de las últimas 8 semanas.
        $semanas = [];
        $lunes = new \DateTimeImmutable('monday this week');
        for ($i = 7; $i >= 0; $i--) {
            $inicio = $lunes->modify("-$i weeks");
            $semanas[$inicio->format('o-W')] = ['desde' => $inicio->format('d/m'), 'dias' => 0, 'series' => 0];
        }
        $porSemana = $this->db->prepare(
            'SELECT DATE_FORMAT(Fecha, "%x-%v") AS clave, COUNT(DISTINCT DATE(Fecha)) AS dias, COUNT(*) AS series
             FROM Registro_Progreso
             WHERE ID_usuario = :socio AND Fecha >= :desde
             GROUP BY clave'
        );
        $porSemana->execute(['socio' => $socio, 'desde' => $lunes->modify('-7 weeks')->format('Y-m-d')]);
        foreach ($porSemana->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
            if (isset($semanas[$fila['clave']])) {
                $semanas[$fila['clave']]['dias'] = (int) $fila['dias'];
                $semanas[$fila['clave']]['series'] = (int) $fila['series'];
            }
        }

        $totales = $this->db->prepare(
            'SELECT COUNT(*) AS series, COUNT(DISTINCT DATE(Fecha)) AS dias, DATE_FORMAT(MIN(Fecha), "%d/%m/%Y") AS desde,
                    DATE_FORMAT(MAX(Fecha), "%d/%m/%Y") AS ultima
             FROM Registro_Progreso WHERE ID_usuario = :socio'
        );
        $totales->execute(['socio' => $socio]);
        $resumen = $totales->fetch(\PDO::FETCH_ASSOC);

        $visitas = $this->db->prepare(
            "SELECT COUNT(DISTINCT DATE(Fecha_hora_ingreso)) FROM Registro_Accesos
             WHERE ID_usuario = :socio AND Estado_acceso = 'Permitido' AND Fecha_hora_ingreso >= (NOW() - INTERVAL 30 DAY)"
        );
        $visitas->execute(['socio' => $socio]);

        $this->json([
            'status' => 'success',
            'socio' => ['id' => $vinculo['id'], 'nombre' => $vinculo['nombre'], 'membresia' => $vinculo['membresia'], 'vinculo' => $vinculo['programas']],
            'resumen' => [
                'series' => (int) $resumen['series'],
                'dias_entrenados' => (int) $resumen['dias'],
                'desde' => $resumen['desde'],
                'ultima' => $resumen['ultima'],
                'records' => count($records),
                'visitas_30_dias' => (int) $visitas->fetchColumn(),
            ],
            'records' => $records,
            'series' => $series,
            'semanas' => array_values($semanas),
        ]);
    }

    /** POST /entrenador/rutinas: crea una rutina con sus ejercicios y la asigna al socio. */
    public function crearRutina() {
        $entrenador = $this->autorizado();
        if (!$entrenador) return;

        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $socio = (int) ($in['socio_id'] ?? 0);
        $programa = (int) ($in['programa_id'] ?? 0) ?: null;
        $nombre = trim((string) ($in['nombre'] ?? ''));
        $descripcion = trim((string) ($in['descripcion'] ?? ''));
        $ejercicios = $in['ejercicios'] ?? [];

        if (!$socio || $nombre === '' || !is_array($ejercicios) || !count($ejercicios)) {
            return $this->json(['status' => 'error', 'message' => 'Socio, nombre y al menos un ejercicio son obligatorios'], 400);
        }

        foreach ($ejercicios as $ejercicio) {
            if ((int) ($ejercicio['id'] ?? 0) < 1) {
                return $this->json(['status' => 'error', 'message' => 'Elegí ejercicios válidos del catálogo'], 400);
            }
        }

        $vinculo = null;
        foreach ($this->sociosDe($entrenador) as $candidato) {
            if ($candidato['id'] === $socio) {
                $vinculo = $candidato;
                break;
            }
        }
        if (!$vinculo) {
            return $this->json(['status' => 'error', 'message' => 'El socio no está asignado a este entrenador ni a sus programas'], 403);
        }
        if ($programa === null && !$vinculo['personal']) {
            return $this->json(['status' => 'error', 'message' => 'Elegí el programa: no sos el entrenador personal de este socio'], 400);
        }
        if ($programa !== null && !in_array($programa, $vinculo['programa_ids'], true)) {
            return $this->json(['status' => 'error', 'message' => 'El socio no está inscripto en ese programa o no lo dirigís vos'], 403);
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'INSERT INTO Rutinas (Nombre_rutina, Descripcion, ID_entrenador, ID_programa) VALUES (:nombre, :descripcion, :entrenador, :programa)'
            );
            $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion, 'entrenador' => $entrenador, 'programa' => $programa]);
            $rutina = (int) $this->db->lastInsertId();

            $asignar = $this->db->prepare('INSERT INTO Usuario_Rutina (ID_usuario, ID_rutina) VALUES (:socio, :rutina)');
            $asignar->execute(['socio' => $socio, 'rutina' => $rutina]);

            $item = $this->db->prepare(
                'INSERT INTO Rutina_Ejercicio (ID_rutina, ID_ejercicio, Series, Repeticiones, Orden, Tiempo_descanso, Tiempo_ejecucion, Notas) VALUES (:rutina, :ejercicio, :series, :reps, :orden, :descanso, :tiempo, :notas)'
            );
            foreach ($ejercicios as $orden => $ejercicio) {
                $item->execute([
                    'rutina' => $rutina,
                    'ejercicio' => (int) ($ejercicio['id'] ?? 0),
                    'series' => max(1, (int) ($ejercicio['series'] ?? 3)),
                    'reps' => trim((string) ($ejercicio['repeticiones'] ?? '10')),
                    'orden' => $orden + 1,
                    'descanso' => max(0, (int) ($ejercicio['descanso'] ?? 90)),
                    'tiempo' => !empty($ejercicio['tiempo']) ? (int) $ejercicio['tiempo'] : null,
                    'notas' => trim((string) ($ejercicio['notas'] ?? '')),
                ]);
            }

            $this->db->commit();
            $this->json(['status' => 'success', 'message' => 'Rutina creada y asignada']);
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->json(['status' => 'error', 'message' => 'No se pudo guardar la rutina'], 500);
        }
    }
}
