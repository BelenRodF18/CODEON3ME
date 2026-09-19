<?php
/**
 * Pruebas de la gestión de usuarios contra MySQL: alta, edición, baja, borrado y validaciones.
 */

use App\Models\UsuarioModel;

/** Nombre de usuario aleatorio para una cuenta temporal. */
function usuarioDePrueba(): string {
    return 'test.admin.' . bin2hex(random_bytes(5));
}

test('Los roles base son exactamente cinco', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $nombres = array_column((new UsuarioModel($db))->roles(), 'nombre');
    sort($nombres);

    afirmarIgual(
        ['Administrador', 'Entrenador', 'Personal', 'Recepcion', 'Socio'],
        $nombres,
        'El sistema debe tener solo los cinco roles base'
    );
});

test('Las cinco cuentas base existen y están activas', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $esperadas = [
        'admin' => 'Administrador',
        'entrenador' => 'Entrenador',
        'socio' => 'Socio',
        'personal' => 'Personal',
        'recepcion' => 'Recepcion',
    ];

    $stmt = $db->prepare(
        'SELECT u.Activo, t.Nombre AS rol FROM Usuarios u
         INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo WHERE u.Usuario = :usuario'
    );

    foreach ($esperadas as $usuario => $rol) {
        $stmt->execute(['usuario' => $usuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        afirmar($fila !== false, 'Falta la cuenta base ' . $usuario);
        afirmarIgual($rol, $fila['rol'], 'Rol incorrecto para ' . $usuario);
        afirmarIgual(1, (int) $fila['Activo'], $usuario . ' debería estar activo');
    }
});

test('Las contraseñas se guardan hasheadas, nunca en texto plano', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $id = $usuarios->crear('Hash De Prueba', usuarioDePrueba(), 'fitpower123', 3);

    try {
        $stmt = $db->prepare('SELECT Password FROM Usuarios WHERE ID_usuario = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        afirmar($hash !== 'fitpower123', 'La contraseña no puede quedar en texto plano');
        afirmar(password_verify('fitpower123', $hash), 'El hash debe validar la contraseña original');
    } finally {
        $usuarios->eliminar($id);
    }
});

test('Alta, edición y persistencia del usuario', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $id = $usuarios->crear('Nombre Original', usuarioDePrueba(), 'fitpower123', 3);

    try {
        $creado = $usuarios->buscar($id);
        afirmarIgual('Nombre Original', $creado['nombre'], 'Debe guardar el nombre');
        afirmarIgual(true, $creado['activo'], 'Un usuario nuevo nace activo');

        $nuevoUsuario = usuarioDePrueba();
        $usuarios->actualizar($id, 'Nombre Editado', $nuevoUsuario, 2, null);

        $editado = $usuarios->buscar($id);
        afirmarIgual('Nombre Editado', $editado['nombre'], 'El nombre debe persistir');
        afirmarIgual($nuevoUsuario, $editado['nombre_usuario'], 'El nombre de usuario debe persistir');
        afirmarIgual(2, $editado['rol_id'], 'El rol debe persistir');

        $stmt = $db->prepare('SELECT Usuario FROM Usuarios WHERE ID_usuario = :id');
        $stmt->execute(['id' => $id]);
        afirmarIgual($nuevoUsuario, $stmt->fetchColumn(), 'El login debe usar el nuevo nombre de usuario');
    } finally {
        $usuarios->eliminar($id);
    }
});

test('Dar de baja no borra al usuario y habilitar lo devuelve', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $id = $usuarios->crear('Baja Y Alta', usuarioDePrueba(), 'fitpower123', 3);

    try {
        $usuarios->cambiarEstado($id, false);

        $dadoDeBaja = $usuarios->buscar($id);
        afirmar($dadoDeBaja !== null, 'Dar de baja NO debe eliminar la fila');
        afirmarIgual(false, $dadoDeBaja['activo'], 'El usuario debe quedar inactivo');

        $usuarios->cambiarEstado($id, true);
        afirmarIgual(true, $usuarios->buscar($id)['activo'], 'Habilitar debe reactivarlo');
    } finally {
        $usuarios->eliminar($id);
    }
});

test('El nombre de usuario duplicado se detecta antes de insertar', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $nombreUsuario = usuarioDePrueba();
    $id = $usuarios->crear('Primero', $nombreUsuario, 'fitpower123', 3);

    try {
        afirmar($usuarios->usuarioEnUso($nombreUsuario), 'El usuario tomado debe detectarse');
        afirmar(!$usuarios->usuarioEnUso($nombreUsuario, $id), 'La propia cuenta no cuenta como duplicado');
        afirmar(!$usuarios->usuarioEnUso(usuarioDePrueba()), 'Un usuario libre no debe marcarse como usado');
    } finally {
        $usuarios->eliminar($id);
    }
});

test('Borrar un usuario no deja registros huérfanos', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $id = $usuarios->crear('Con Relaciones', usuarioDePrueba(), 'fitpower123', 3);

    $plan = $db->query('SELECT ID_plan FROM Plan ORDER BY ID_plan LIMIT 1')->fetchColumn();
    $db->prepare(
        'INSERT INTO Membresias (ID_usuario, ID_plan, Fecha_inicio, Fecha_vencimiento) VALUES (:id, :plan, CURRENT_DATE, CURRENT_DATE)'
    )->execute(['id' => $id, 'plan' => $plan]);

    $db->prepare(
        "INSERT INTO Registro_Accesos (Fecha_hora_ingreso, Estado_acceso, ID_usuario) VALUES (NOW(), 'Permitido', :id)"
    )->execute(['id' => $id]);

    $db->prepare(
        "INSERT INTO Chat (Descripcion, ID_usuario) VALUES ('Reporte de prueba', :id)"
    )->execute(['id' => $id]);

    $usuarios->eliminar($id);

    afirmarIgual(null, $usuarios->buscar($id), 'El usuario debe desaparecer de MySQL');

    foreach (['Persona', 'Membresias', 'Registro_Accesos', 'Chat'] as $tabla) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM {$tabla} WHERE ID_usuario = :id");
        $stmt->execute(['id' => $id]);
        afirmarIgual(0, (int) $stmt->fetchColumn(), 'Quedaron filas huérfanas en ' . $tabla);
    }
});

test('Las dependencias bloqueantes se detectan antes de borrar', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $rolEntrenador = (int) $db->query("SELECT ID_tipo FROM Tipo_usuario WHERE Nombre = 'Entrenador'")->fetchColumn();
    $entrenador = $usuarios->crear('Entrenador Prueba', usuarioDePrueba(), 'fitpower123', $rolEntrenador);
    $socio = $usuarios->crear('Socio Prueba', usuarioDePrueba(), 'fitpower123', 3);

    try {
        $db->prepare('INSERT INTO Socio_Entrenador (ID_socio, ID_entrenador) VALUES (:socio, :entrenador)')
            ->execute(['socio' => $socio, 'entrenador' => $entrenador]);
        $db->prepare('INSERT INTO Rutinas (Nombre_rutina, ID_entrenador) VALUES (:nombre, :entrenador)')
            ->execute(['nombre' => 'Rutina de prueba ' . bin2hex(random_bytes(3)), 'entrenador' => $entrenador]);

        $dependencias = $usuarios->dependencias($entrenador);

        afirmarIgual(1, $dependencias['socios_asignados'], 'Debe contar el socio asignado');
        afirmarIgual(1, $dependencias['rutinas_creadas'], 'Debe contar la rutina creada');
        afirmar($usuarios->tieneDependenciasBloqueantes($dependencias), 'Debe pedir confirmación extra');

        $usuarios->eliminar($entrenador);

        afirmarIgual(null, $usuarios->buscar($entrenador), 'El entrenador debe borrarse');
        afirmar($usuarios->buscar($socio) !== null, 'El socio no debe borrarse con su entrenador');

        $stmt = $db->prepare('SELECT COUNT(*) FROM Socio_Entrenador WHERE ID_socio = :id');
        $stmt->execute(['id' => $socio]);
        afirmarIgual(0, (int) $stmt->fetchColumn(), 'El socio debe quedar sin entrenador, no huérfano');
    } finally {
        foreach ([$entrenador, $socio] as $id) {
            if ($usuarios->buscar($id)) $usuarios->eliminar($id);
        }
    }
});

test('Siempre queda al menos un administrador activo', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $admin = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'admin'")->fetchColumn();

    $otros = $usuarios->otrosAdminsActivos($admin);
    afirmar($otros >= 0, 'El contador de administradores debe ser válido');

    $nuevoAdmin = $usuarios->crear('Admin Extra', usuarioDePrueba(), 'fitpower123', 1);
    try {
        afirmarIgual($otros + 1, $usuarios->otrosAdminsActivos($admin), 'Debe contar al nuevo administrador');

        $usuarios->cambiarEstado($nuevoAdmin, false);
        afirmarIgual($otros, $usuarios->otrosAdminsActivos($admin), 'Un admin inactivo no cuenta');
    } finally {
        $usuarios->eliminar($nuevoAdmin);
    }
});

test('El formato de usuario acepta nombres simples y rechaza emails', function () {
    foreach (['admin', 'juan.diaz', 'socio_2', 'm-lopez'] as $valido) {
        afirmar((bool) preg_match(UsuarioModel::FORMATO_USUARIO, $valido), $valido . ' debería ser válido');
    }
    foreach (['admin@fitpower.com', 'Juan', 'juan..diaz', '.admin', 'con espacio'] as $invalido) {
        afirmar(!preg_match(UsuarioModel::FORMATO_USUARIO, $invalido), $invalido . ' debería rechazarse');
    }
});
