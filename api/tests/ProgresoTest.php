<?php
/**
 * Pruebas de los récords personales: se recalculan al registrar, editar y borrar series.
 */

use App\Models\ProgresoModel;
use App\Models\UsuarioModel;

test('Los récords personales se recalculan al editar y borrar series', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $socio = $usuarios->crear('Socio Records', 'test.records.' . bin2hex(random_bytes(4)), 'fitpower123', 3);
    $ejercicio = (int) $db->query("SELECT ID_ejercicio FROM Ejercicios WHERE Nombre = 'Press banca'")->fetchColumn();
    $progreso = new ProgresoModel($db);

    $serie = function (float $carga, int $diasAtras) use ($db, $socio, $ejercicio): int {
        $db->prepare(
            'INSERT INTO Registro_Progreso (Fecha, Carga_utilizada, Repeticiones_realizadas, ID_usuario, ID_ejercicio)
             VALUES (NOW() - INTERVAL :dias DAY, :carga, 8, :socio, :ejercicio)'
        )->execute(['dias' => $diasAtras, 'carga' => $carga, 'socio' => $socio, 'ejercicio' => $ejercicio]);

        return (int) $db->lastInsertId();
    };

    try {
        $a = $serie(40, 3);
        $b = $serie(35, 2);
        $c = $serie(45, 1);
        $progreso->recalcularRecords($socio, $ejercicio);

        afirmar($progreso->esRecord($a), 'La primera serie es récord');
        afirmar(!$progreso->esRecord($b), 'Una carga menor no es récord');
        afirmar($progreso->esRecord($c), 'Superar la mejor carga es récord');
        afirmarIgual(45.0, $progreso->records($socio)[0]['peso'], 'El récord del ejercicio es 45 kg');

        $db->prepare('UPDATE Registro_Progreso SET Carga_utilizada = 50 WHERE ID_registro = :id')->execute(['id' => $b]);
        $progreso->recalcularRecords($socio, $ejercicio);
        afirmar($progreso->esRecord($b), 'La serie corregida a 50 kg es récord');
        afirmar(!$progreso->esRecord($c), '45 kg ya no supera a 50 kg');

        $db->prepare('DELETE FROM Registro_Progreso WHERE ID_registro = :id')->execute(['id' => $b]);
        $progreso->recalcularRecords($socio, $ejercicio);
        afirmar($progreso->esRecord($c), '45 kg vuelve a ser récord');
        afirmarIgual(45.0, $progreso->records($socio)[0]['peso'], 'La mejor marca vuelve a 45 kg');
        afirmarIgual(null, $progreso->ejercicioDeSerie($a, 1), 'Otro usuario no puede tocar la serie');
    } finally {
        $usuarios->eliminar($socio);
    }
});
