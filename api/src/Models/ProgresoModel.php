<?php
namespace App\Models;

/** Récords personales del socio: una serie es récord si supera la carga de todas las anteriores del ejercicio. */
class ProgresoModel {
    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** Ejercicio de una serie del socio, o null si no es suya. */
    public function ejercicioDeSerie(int $serieId, int $socioId): ?int {
        $stmt = $this->db->prepare('SELECT ID_ejercicio FROM Registro_Progreso WHERE ID_registro = :id AND ID_usuario = :socio');
        $stmt->execute(['id' => $serieId, 'socio' => $socioId]);
        $ejercicio = $stmt->fetchColumn();

        return $ejercicio === false ? null : (int) $ejercicio;
    }

    /** Vuelve a marcar qué series son récord en ese ejercicio (tras registrar, editar o borrar). */
    public function recalcularRecords(int $socioId, int $ejercicioId): void {
        $stmt = $this->db->prepare(
            'SELECT ID_registro, Carga_utilizada, Record_personal FROM Registro_Progreso
             WHERE ID_usuario = :socio AND ID_ejercicio = :ejercicio
             ORDER BY Fecha, ID_registro'
        );
        $stmt->execute(['socio' => $socioId, 'ejercicio' => $ejercicioId]);

        $actualizar = $this->db->prepare('UPDATE Registro_Progreso SET Record_personal = :record WHERE ID_registro = :id');
        $maximo = null;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $serie) {
            $carga = (float) $serie['Carga_utilizada'];
            $record = $maximo === null || $carga > $maximo;
            if ($record) {
                $maximo = $carga;
            }
            if ((int) $serie['Record_personal'] !== (int) $record) {
                $actualizar->execute(['record' => (int) $record, 'id' => (int) $serie['ID_registro']]);
            }
        }
    }

    /** Indica si la serie quedó marcada como récord. */
    public function esRecord(int $serieId): bool {
        $stmt = $this->db->prepare('SELECT Record_personal FROM Registro_Progreso WHERE ID_registro = :id');
        $stmt->execute(['id' => $serieId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    /** Mejor marca de cada ejercicio del socio. */
    public function records(int $socioId): array {
        $stmt = $this->db->prepare(
            'SELECT ejercicio, peso, repeticiones, fecha, series
             FROM (
                 SELECT e.Nombre AS ejercicio, rp.Carga_utilizada AS peso, rp.Repeticiones_realizadas AS repeticiones,
                        DATE_FORMAT(rp.Fecha, "%d/%m/%Y") AS fecha,
                        COUNT(*) OVER (PARTITION BY rp.ID_ejercicio) AS series,
                        ROW_NUMBER() OVER (PARTITION BY rp.ID_ejercicio ORDER BY rp.Carga_utilizada DESC, rp.Fecha, rp.ID_registro) AS puesto
                 FROM Registro_Progreso rp
                 INNER JOIN Ejercicios e ON e.ID_ejercicio = rp.ID_ejercicio
                 WHERE rp.ID_usuario = :socio
             ) mejores
             WHERE puesto = 1
             ORDER BY ejercicio'
        );
        $stmt->execute(['socio' => $socioId]);

        return array_map(function (array $fila): array {
            $fila['peso'] = (float) $fila['peso'];
            $fila['repeticiones'] = (int) $fila['repeticiones'];
            $fila['series'] = (int) $fila['series'];

            return $fila;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
