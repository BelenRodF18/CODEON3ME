<?php
/**
 * Pruebas de programas, planes, entrenadores e inscripciones, y de la socia de ejemplo.
 */

use App\Models\ProgramaModel;
use App\Models\UsuarioModel;

/** ID de una cuenta por su nombre de usuario. */
function idDeUsuario(PDO $db, string $usuario): int {
    $stmt = $db->prepare('SELECT ID_usuario FROM Usuarios WHERE Usuario = :usuario');
    $stmt->execute(['usuario' => $usuario]);

    return (int) $stmt->fetchColumn();
}

test('Cada programa activo tiene un entrenador activo a cargo', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $sinEntrenador = $db->query(
        "SELECT pr.Nombre FROM Programas pr
         LEFT JOIN Usuarios u ON u.ID_usuario = pr.ID_entrenador AND u.Activo = 1
         LEFT JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo AND t.Nombre = 'Entrenador'
         WHERE pr.Activo = 1 AND t.ID_tipo IS NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    afirmarIgual([], $sinEntrenador, 'Programas sin entrenador válido');
});

test('Ningún socio está inscripto en programas fuera de su plan', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $fuera = (int) $db->query(
        'SELECT COUNT(*) FROM Socio_Programa sp
         WHERE NOT EXISTS (
             SELECT 1 FROM Membresias m
             INNER JOIN Plan_Programa pp ON pp.ID_plan = m.ID_plan AND pp.ID_programa = sp.ID_programa
             WHERE m.ID_usuario = sp.ID_socio
         )'
    )->fetchColumn();

    afirmarIgual(0, $fuera, 'Hay inscripciones fuera del plan');
});

test('Las rutinas de un programa las arma el entrenador de ese programa', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $ajenas = $db->query(
        'SELECT r.Nombre_rutina FROM Rutinas r
         INNER JOIN Programas pr ON pr.ID_programa = r.ID_programa
         WHERE pr.ID_entrenador <> r.ID_entrenador'
    )->fetchAll(PDO::FETCH_COLUMN);

    afirmarIgual([], $ajenas, 'Rutinas creadas por quien no dirige el programa');
});

test('La socia de ejemplo tiene plan pagado, entrenador, programas y rutinas', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $martina = idDeUsuario($db, 'martina.lopez');
    afirmar($martina > 0, 'Falta la cuenta martina.lopez');

    $programas = new ProgramaModel($db);
    $plan = $programas->planDelSocio($martina);
    afirmarIgual('Plan Pro', $plan['nombre'] ?? null, 'Debe tener el Plan Pro');

    $pagos = $db->prepare('SELECT COUNT(*) FROM Historial_membresias WHERE ID_usuario = :id');
    $pagos->execute(['id' => $martina]);
    afirmar((int) $pagos->fetchColumn() >= 1, 'Debe tener pagos registrados');

    $personal = $db->prepare('SELECT ID_entrenador FROM Socio_Entrenador WHERE ID_socio = :id');
    $personal->execute(['id' => $martina]);
    afirmarIgual(idDeUsuario($db, 'diego.torres'), (int) $personal->fetchColumn(), 'Su entrenador personal es Diego Torres');

    $inscripciones = array_column($programas->inscripciones($martina), 'entrenador', 'slug');
    ksort($inscripciones);
    afirmarIgual(
        ['boxeo' => 'Juan Díaz', 'musculacion' => 'Diego Torres', 'spinning' => 'Sofía Rey'],
        $inscripciones,
        'Programas inscriptos y sus entrenadores'
    );

    $rutinas = $db->prepare('SELECT COUNT(*) FROM Usuario_Rutina WHERE ID_usuario = :id');
    $rutinas->execute(['id' => $martina]);
    afirmar((int) $rutinas->fetchColumn() >= 3, 'Debe tener rutinas asignadas');
});

test('Inscribir fuera del plan se rechaza y bajar de plan poda las inscripciones', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $programas = new ProgramaModel($db);
    $socio = $usuarios->crear('Socio Programas', 'test.programas.' . bin2hex(random_bytes(4)), 'fitpower123', 3);

    try {
        $planId = fn (string $slug) => (int) $db->query('SELECT ID_plan FROM Plan WHERE Slug = ' . $db->quote($slug))->fetchColumn();
        $programaId = fn (string $slug) => (int) $db->query('SELECT ID_programa FROM Programas WHERE Slug = ' . $db->quote($slug))->fetchColumn();

        afirmar($programas->inscribir($socio, [$programaId('yoga')]) !== null, 'Sin plan no se puede inscribir');

        $db->prepare('INSERT INTO Membresias (ID_usuario, ID_plan, Fecha_inicio, Fecha_vencimiento) VALUES (:id, :plan, CURRENT_DATE, LAST_DAY(CURRENT_DATE))')
            ->execute(['id' => $socio, 'plan' => $planId('basico')]);

        afirmar($programas->inscribir($socio, [$programaId('boxeo')]) !== null, 'Boxeo no está en el Plan Básico');
        afirmarIgual(null, $programas->inscribir($socio, [$programaId('yoga')]), 'Yoga sí está en el Plan Básico');

        $db->prepare('UPDATE Membresias SET ID_plan = :plan WHERE ID_usuario = :id')
            ->execute(['plan' => $planId('pro'), 'id' => $socio]);
        afirmarIgual(null, $programas->inscribir($socio, [$programaId('yoga'), $programaId('boxeo')]), 'El Plan Pro incluye ambos');
        $db->prepare('INSERT INTO Socio_Entrenador (ID_socio, ID_entrenador) VALUES (:socio, :entrenador)')
            ->execute(['socio' => $socio, 'entrenador' => idDeUsuario($db, 'juan.diaz')]);

        $db->prepare('UPDATE Membresias SET ID_plan = :plan WHERE ID_usuario = :id')
            ->execute(['plan' => $planId('basico'), 'id' => $socio]);
        $programas->ajustarAlPlan($socio);

        afirmarIgual(['yoga'], array_column($programas->inscripciones($socio), 'slug'), 'Solo queda lo que incluye el Básico');
        $personal = $db->prepare('SELECT COUNT(*) FROM Socio_Entrenador WHERE ID_socio = :id');
        $personal->execute(['id' => $socio]);
        afirmarIgual(0, (int) $personal->fetchColumn(), 'El Básico no incluye entrenador personal');
    } finally {
        $usuarios->eliminar($socio);
    }
});
