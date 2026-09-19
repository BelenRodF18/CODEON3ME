<?php
/**
 * Pruebas del QR en Recepción contra MySQL: ingreso, salida, rechazos y que un QR no se use dos veces.
 * Cada prueba crea su socio temporal y lo borra al terminar.
 */

use App\Models\AccesoModel;
use App\Models\UsuarioModel;
use App\Utils\QrToken;

/** Crea un socio temporal con membresía; devuelve [id, función para borrarlo]. */
function socioDePrueba(PDO $db, string $estadoMembresia = 'Al dia', bool $activo = true): array {
    $usuarios = new UsuarioModel($db);
    $nombreUsuario = 'test.socio.' . bin2hex(random_bytes(5));

    $rolSocio = $db->query("SELECT ID_tipo FROM Tipo_usuario WHERE Nombre = 'Socio'")->fetchColumn();
    $id = $usuarios->crear('Socio De Prueba', $nombreUsuario, 'fitpower123', (int) $rolSocio);

    if (!$activo) {
        $usuarios->cambiarEstado($id, false);
    }

    $plan = $db->query('SELECT ID_plan FROM Plan ORDER BY ID_plan LIMIT 1')->fetchColumn();
    $vencimiento = $estadoMembresia === 'Vencida' ? date('Y-m-d', strtotime('-5 days')) : date('Y-m-d', strtotime('+30 days'));

    $stmt = $db->prepare(
        'INSERT INTO Membresias (ID_usuario, ID_plan, Fecha_inicio, Fecha_vencimiento, Estado)
         VALUES (:usuario, :plan, CURRENT_DATE, :vencimiento, :estado)'
    );
    $stmt->execute([
        'usuario' => $id,
        'plan' => $plan,
        'vencimiento' => $vencimiento,
        'estado' => $estadoMembresia,
    ]);

    $limpiar = function () use ($db, $id) {
        $db->prepare('DELETE FROM Usuarios WHERE ID_usuario = :id')->execute(['id' => $id]);
    };

    return [$id, $limpiar];
}

test('Generar un QR no crea ninguna fila en Tokens_QR', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db);

    try {
        $accesos = new AccesoModel($db);
        $antes = (int) $db->query('SELECT COUNT(*) FROM Tokens_QR')->fetchColumn();

        for ($i = 0; $i < 10; $i++) {
            QrToken::generar($socio, $accesos->semilla($socio));
        }

        $despues = (int) $db->query('SELECT COUNT(*) FROM Tokens_QR')->fetchColumn();
        afirmarIgual($antes, $despues, 'Mostrar el QR no debe escribir tokens en MySQL');
    } finally {
        $limpiar();
    }
});

test('Recepción canjea el QR y queda registrado el ingreso', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db);
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $token = QrToken::generar($socio, $accesos->semilla($socio))['token'];

        $resultado = $accesos->canjear($token, $recepcion);

        afirmar($resultado['permitido'], 'El ingreso debería estar autorizado: ' . ($resultado['motivo'] ?? ''));
        afirmarIgual('ingreso_autorizado', $resultado['codigo'], 'Código de resultado inesperado');

        $stmt = $db->prepare('SELECT Usado, ID_recepcion FROM Tokens_QR WHERE Token_hash = :hash');
        $stmt->execute(['hash' => QrToken::huella($token)]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        afirmar($fila !== false, 'El canje debe dejar la fila del token en MySQL');
        afirmarIgual(1, (int) $fila['Usado'], 'El token debe quedar marcado como usado');
        afirmarIgual($recepcion, (int) $fila['ID_recepcion'], 'Debe guardarse quién escaneó');

        $stmt = $db->prepare(
            "SELECT Estado_acceso, ID_recepcion, ID_token FROM Registro_Accesos
             WHERE ID_usuario = :id ORDER BY ID_acceso DESC LIMIT 1"
        );
        $stmt->execute(['id' => $socio]);
        $acceso = $stmt->fetch(PDO::FETCH_ASSOC);

        afirmarIgual('Permitido', $acceso['Estado_acceso'], 'El acceso debe quedar como permitido');
        afirmarIgual($recepcion, (int) $acceso['ID_recepcion'], 'El acceso debe apuntar a la recepción');
        afirmar((int) $acceso['ID_token'] > 0, 'El acceso debe referenciar el token canjeado');

        $stmt = $db->prepare(
            'SELECT DATE_FORMAT(Fecha_hora_ingreso, "%d/%m/%Y") AS fecha, DATE_FORMAT(Fecha_hora_ingreso, "%H:%i") AS hora
             FROM Registro_Accesos WHERE ID_acceso = :id'
        );
        $stmt->execute(['id' => $resultado['acceso']['id']]);
        $guardado = $stmt->fetch(PDO::FETCH_ASSOC);

        afirmarIgual($guardado['fecha'], $resultado['acceso']['fecha'], 'La fecha mostrada debe ser la guardada');
        afirmarIgual($guardado['hora'], $resultado['acceso']['hora'], 'La hora mostrada debe ser la guardada');
    } finally {
        $limpiar();
    }
});

test('El mismo QR no se puede canjear dos veces', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db);
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $token = QrToken::generar($socio, $accesos->semilla($socio))['token'];

        $primero = $accesos->canjear($token, $recepcion);
        afirmar($primero['permitido'], 'El primer canje debe pasar');

        $segundo = $accesos->canjear($token, $recepcion);
        afirmar(!$segundo['permitido'], 'El segundo canje debe rechazarse');
        afirmarIgual('token_usado', $segundo['codigo'], 'El motivo debe ser "QR ya utilizado"');
        afirmarIgual('QR ya utilizado', $segundo['motivo'], 'Mensaje al operador incorrecto');
    } finally {
        $limpiar();
    }
});

test('Un socio inactivo no puede ingresar', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db, 'Al dia', false);
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $token = QrToken::generar($socio, $accesos->semilla($socio))['token'];
        $resultado = $accesos->canjear($token, $recepcion);

        afirmar(!$resultado['permitido'], 'Un socio inactivo no debe ingresar');
        afirmarIgual('socio_inactivo', $resultado['codigo'], 'Motivo esperado: socio inactivo');

        $stmt = $db->prepare("SELECT Estado_acceso, Motivo FROM Registro_Accesos WHERE ID_usuario = :id ORDER BY ID_acceso DESC LIMIT 1");
        $stmt->execute(['id' => $socio]);
        $acceso = $stmt->fetch(PDO::FETCH_ASSOC);

        afirmarIgual('Denegado', $acceso['Estado_acceso'], 'El rechazo debe quedar en el historial');
        afirmarIgual('Socio inactivo', $acceso['Motivo'], 'El motivo debe guardarse');
    } finally {
        $limpiar();
    }
});

test('Una membresía vencida bloquea el ingreso', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db, 'Vencida');
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $token = QrToken::generar($socio, $accesos->semilla($socio))['token'];
        $resultado = $accesos->canjear($token, $recepcion);

        afirmar(!$resultado['permitido'], 'Con la membresía vencida no se entra');
        afirmarIgual('membresia_vencida', $resultado['codigo'], 'Motivo esperado: membresía vencida');
    } finally {
        $limpiar();
    }
});

test('Un token expirado se rechaza', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db);
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $token = QrToken::generar($socio, $accesos->semilla($socio), time() - 600)['token'];
        $resultado = $accesos->canjear($token, $recepcion);

        afirmar(!$resultado['permitido'], 'Un QR de hace 10 minutos no sirve');
        afirmarIgual('token_expirado', $resultado['codigo'], 'Motivo esperado: token expirado');
    } finally {
        $limpiar();
    }
});

test('Un token inventado no identifica a nadie', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $accesos = new AccesoModel($db);

    $basura = $accesos->canjear('no-es-un-token', null);
    afirmarIgual('formato_invalido', $basura['codigo'], 'Un texto cualquiera debe ser formato inválido');
    afirmarIgual('QR inválido', $basura['motivo'], 'Mensaje al operador incorrecto');

    $inexistente = $accesos->canjear('FP1.999999.' . (time() + 60) . '.aabbccddeeff.' . str_repeat('a', 32), null);
    afirmarIgual('socio_inexistente', $inexistente['codigo'], 'Debe avisar que el socio no existe');
});

test('El segundo QR del socio que está adentro registra su salida', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db);
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $adentroAntes = $accesos->personasAdentro();

        $ingreso = $accesos->canjear(QrToken::generar($socio, $accesos->semilla($socio))['token'], $recepcion);
        afirmarIgual('ingreso', $ingreso['movimiento'], 'El primer QR es el ingreso');
        afirmarIgual($adentroAntes + 1, $accesos->personasAdentro(), 'El socio debe contar como adentro');

        $duplicado = $accesos->canjear(QrToken::generar($socio, $accesos->semilla($socio))['token'], $recepcion);
        afirmarIgual('ingreso_duplicado', $duplicado['codigo'], 'Dos lecturas seguidas no deben registrar la salida');

        $db->prepare('UPDATE Registro_Accesos SET Fecha_hora_ingreso = NOW() - INTERVAL 1 HOUR WHERE ID_acceso = :id')
            ->execute(['id' => $ingreso['acceso']['id']]);
        $db->prepare("UPDATE Membresias SET Estado = 'Vencida' WHERE ID_usuario = :id")->execute(['id' => $socio]);

        $salida = $accesos->canjear(QrToken::generar($socio, $accesos->semilla($socio))['token'], $recepcion);
        afirmar($salida['permitido'], 'La salida no depende de la membresía: ' . ($salida['motivo'] ?? ''));
        afirmarIgual('salida', $salida['movimiento'], 'El segundo QR debe registrar la salida');
        afirmarIgual($ingreso['acceso']['id'], $salida['acceso']['id'], 'La salida cierra el mismo ingreso');
        afirmar($salida['acceso']['minutos'] >= 59, 'Debe informar el tiempo de estadía');
        afirmarIgual($adentroAntes, $accesos->personasAdentro(), 'Después de salir ya no cuenta como adentro');

        $stmt = $db->prepare('SELECT Fecha_hora_salida FROM Registro_Accesos WHERE ID_acceso = :id');
        $stmt->execute(['id' => $ingreso['acceso']['id']]);
        afirmar($stmt->fetchColumn() !== null, 'La hora de salida debe quedar guardada');
    } finally {
        $limpiar();
    }
});

test('Recepción puede registrar la salida a mano una sola vez', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    [$socio, $limpiar] = socioDePrueba($db);
    $recepcion = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'recepcion'")->fetchColumn();

    try {
        $accesos = new AccesoModel($db);
        $ingreso = $accesos->canjear(QrToken::generar($socio, $accesos->semilla($socio))['token'], $recepcion);

        $salida = $accesos->registrarSalidaManual((int) $ingreso['acceso']['id']);
        afirmar($salida !== null, 'La salida manual debe registrarse');
        afirmarIgual(null, $accesos->registrarSalidaManual((int) $ingreso['acceso']['id']), 'No se puede cerrar dos veces');
    } finally {
        $limpiar();
    }
});
