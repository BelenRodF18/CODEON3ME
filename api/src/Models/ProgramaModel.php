<?php
namespace App\Models;

/**
 * Programas, planes, entrenadores a cargo e inscripciones de los socios.
 * Regla: un socio solo se inscribe en programas que incluye su plan.
 */
class ProgramaModel {
    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** Consulta base de un programa con su entrenador (solo si está activo). */
    private const SQL_PROGRAMA = 'SELECT pr.ID_programa AS id,
                                         pr.Slug AS slug,
                                         pr.Nombre AS nombre,
                                         pr.Imagen AS imagen,
                                         pr.Historia AS historia,
                                         pr.Tecnica AS tecnica,
                                         pr.Beneficios AS beneficios,
                                         pr.ID_entrenador AS entrenador_id,
                                         pe.Nombre AS entrenador,
                                         pe.Foto AS entrenador_foto
                                  FROM Programas pr
                                  LEFT JOIN Usuarios ue ON ue.ID_usuario = pr.ID_entrenador AND ue.Activo = 1
                                  LEFT JOIN Persona pe ON pe.ID_usuario = ue.ID_usuario';

    /** Convierte los tipos de una fila de programa. */
    private function normalizarPrograma(array $fila): array {
        $fila['id'] = (int) $fila['id'];
        $fila['entrenador_id'] = $fila['entrenador'] === null ? null : (int) $fila['entrenador_id'];

        return $fila;
    }

    /** Programas activos con su entrenador. */
    public function programas(): array {
        $filas = $this->db->query(self::SQL_PROGRAMA . ' WHERE pr.Activo = 1 ORDER BY pr.ID_programa')
            ->fetchAll(\PDO::FETCH_ASSOC);

        return array_map([$this, 'normalizarPrograma'], $filas);
    }

    /** Un programa por su slug, con los planes que lo incluyen. */
    public function programaPorSlug(string $slug): ?array {
        $stmt = $this->db->prepare(self::SQL_PROGRAMA . ' WHERE pr.Activo = 1 AND pr.Slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }

        $programa = $this->normalizarPrograma($fila);

        $stmt = $this->db->prepare(
            'SELECT pl.Slug AS slug, pl.Nombre AS nombre
             FROM Plan_Programa pp
             INNER JOIN Plan pl ON pl.ID_plan = pp.ID_plan
             WHERE pp.ID_programa = :programa AND pl.Activo = 1
             ORDER BY pl.Precio'
        );
        $stmt->execute(['programa' => $programa['id']]);
        $programa['planes'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $programa;
    }

    /** Planes activos con los IDs de sus programas. */
    public function planes(): array {
        $filas = $this->db->query(
            'SELECT pl.ID_plan AS id, pl.Slug AS slug, pl.Nombre AS nombre, pl.Precio AS precio,
                    pl.Descripcion AS descripcion, pl.Entrenador_personal AS entrenador_personal,
                    GROUP_CONCAT(pp.ID_programa ORDER BY pp.ID_programa) AS programas
             FROM Plan pl
             LEFT JOIN Plan_Programa pp ON pp.ID_plan = pl.ID_plan
             WHERE pl.Activo = 1
             GROUP BY pl.ID_plan
             ORDER BY pl.Precio'
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function (array $plan): array {
            $plan['id'] = (int) $plan['id'];
            $plan['precio'] = (float) $plan['precio'];
            $plan['entrenador_personal'] = (int) $plan['entrenador_personal'] === 1;
            $plan['programas'] = $plan['programas'] === null ? [] : array_map('intval', explode(',', $plan['programas']));

            return $plan;
        }, $filas);
    }

    /** Un plan por su slug, con el detalle de sus programas. */
    public function planPorSlug(string $slug): ?array {
        foreach ($this->planes() as $plan) {
            if ($plan['slug'] !== $slug) {
                continue;
            }

            $incluidos = array_flip($plan['programas']);
            $plan['programas'] = array_values(array_filter(
                $this->programas(),
                fn (array $programa) => isset($incluidos[$programa['id']])
            ));

            return $plan;
        }

        return null;
    }


    /** Indica si el usuario es un entrenador activo. */
    public function esEntrenadorActivo(int $usuarioId): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM Usuarios u
             INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
             WHERE u.ID_usuario = :id AND t.Nombre = 'Entrenador' AND u.Activo = 1"
        );
        $stmt->execute(['id' => $usuarioId]);

        return (bool) $stmt->fetchColumn();
    }

    /** Indica si el programa existe. */
    public function existe(int $programaId): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM Programas WHERE ID_programa = :id');
        $stmt->execute(['id' => $programaId]);

        return (bool) $stmt->fetchColumn();
    }

    /** Cambia (o quita) el entrenador a cargo del programa. */
    public function asignarEntrenador(int $programaId, ?int $entrenadorId): void {
        $stmt = $this->db->prepare('UPDATE Programas SET ID_entrenador = :entrenador WHERE ID_programa = :id');
        $stmt->execute(['entrenador' => $entrenadorId, 'id' => $programaId]);
    }

    /** Programas que dirige el entrenador, con la cantidad de inscriptos. */
    public function programasDeEntrenador(int $entrenadorId): array {
        $stmt = $this->db->prepare(
            'SELECT pr.ID_programa AS id, pr.Slug AS slug, pr.Nombre AS nombre,
                    COUNT(sp.ID_socio) AS inscriptos
             FROM Programas pr
             LEFT JOIN Socio_Programa sp ON sp.ID_programa = pr.ID_programa
             WHERE pr.ID_entrenador = :entrenador AND pr.Activo = 1
             GROUP BY pr.ID_programa
             ORDER BY pr.Nombre'
        );
        $stmt->execute(['entrenador' => $entrenadorId]);

        return array_map(function (array $fila): array {
            $fila['id'] = (int) $fila['id'];
            $fila['inscriptos'] = (int) $fila['inscriptos'];

            return $fila;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** Plan de la membresía más reciente del socio. */
    public function planDelSocio(int $socioId): ?array {
        $stmt = $this->db->prepare(
            'SELECT pl.ID_plan AS id, pl.Nombre AS nombre, pl.Entrenador_personal AS entrenador_personal
             FROM Membresias m
             INNER JOIN Plan pl ON pl.ID_plan = m.ID_plan
             WHERE m.ID_usuario = :socio
             ORDER BY m.Fecha_vencimiento DESC
             LIMIT 1'
        );
        $stmt->execute(['socio' => $socioId]);
        $plan = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$plan) {
            return null;
        }

        $plan['id'] = (int) $plan['id'];
        $plan['entrenador_personal'] = (int) $plan['entrenador_personal'] === 1;

        return $plan;
    }

    /** IDs de los programas que incluye el plan. */
    public function programasDelPlan(int $planId): array {
        $stmt = $this->db->prepare('SELECT ID_programa FROM Plan_Programa WHERE ID_plan = :plan');
        $stmt->execute(['plan' => $planId]);

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Programas en los que está inscripto el socio, con su entrenador. */
    public function inscripciones(int $socioId): array {
        $stmt = $this->db->prepare(
            self::SQL_PROGRAMA . '
             INNER JOIN Socio_Programa sp ON sp.ID_programa = pr.ID_programa
             WHERE sp.ID_socio = :socio
             ORDER BY pr.Nombre'
        );
        $stmt->execute(['socio' => $socioId]);

        return array_map([$this, 'normalizarPrograma'], $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** Reemplaza las inscripciones del socio; devuelve el motivo si se rechaza o null si salió bien. */
    public function inscribir(int $socioId, array $programaIds): ?string {
        $programaIds = array_values(array_unique(array_map('intval', $programaIds)));

        if ($programaIds) {
            $plan = $this->planDelSocio($socioId);
            if (!$plan) {
                return 'El socio no tiene un plan asignado';
            }
            if (array_diff($programaIds, $this->programasDelPlan($plan['id']))) {
                return 'Hay programas que no incluye el ' . $plan['nombre'];
            }
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('DELETE FROM Socio_Programa WHERE ID_socio = :socio');
            $stmt->execute(['socio' => $socioId]);

            $stmt = $this->db->prepare('INSERT INTO Socio_Programa (ID_socio, ID_programa) VALUES (:socio, :programa)');
            foreach ($programaIds as $programaId) {
                $stmt->execute(['socio' => $socioId, 'programa' => $programaId]);
            }

            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return null;
    }

    /** Tras un cambio de plan: quita programas y entrenador personal que el plan ya no incluye. */
    public function ajustarAlPlan(int $socioId): void {
        $plan = $this->planDelSocio($socioId);
        if (!$plan) {
            return;
        }

        $stmt = $this->db->prepare(
            'DELETE sp FROM Socio_Programa sp
             LEFT JOIN Plan_Programa pp ON pp.ID_programa = sp.ID_programa AND pp.ID_plan = :plan
             WHERE sp.ID_socio = :socio AND pp.ID_plan IS NULL'
        );
        $stmt->execute(['plan' => $plan['id'], 'socio' => $socioId]);

        if (!$plan['entrenador_personal']) {
            $stmt = $this->db->prepare('DELETE FROM Socio_Entrenador WHERE ID_socio = :socio');
            $stmt->execute(['socio' => $socioId]);
        }
    }
}
