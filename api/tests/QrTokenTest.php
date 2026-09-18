<?php
/**
 * Pruebas del formato y la firma de los tokens QR (no usan la base).
 */

use App\Utils\QrToken;

test('El token generado se puede volver a parsear', function () {
    $qr = QrToken::generar(7, 'semilla-de-prueba');
    $partes = QrToken::partes($qr['token']);

    afirmar($partes !== null, 'El token generado debería ser parseable');
    afirmarIgual(7, $partes['usuario_id'], 'El token debe llevar el id del socio');
    afirmarIgual($qr['expira_en'], $partes['expira'], 'La expiración debe coincidir');
});

test('La firma es válida solo con la semilla correcta', function () {
    $qr = QrToken::generar(7, 'semilla-de-prueba');
    $partes = QrToken::partes($qr['token']);

    afirmar(QrToken::firmaValida($partes, 'semilla-de-prueba'), 'La firma propia debe validar');
    afirmar(!QrToken::firmaValida($partes, 'otra-semilla'), 'Cambiar la semilla debe invalidar el QR');
});

test('Un token alterado no pasa la validación', function () {
    $qr = QrToken::generar(7, 'semilla-de-prueba');
    $partes = explode('.', $qr['token']);

    $partes[1] = '8';
    $falsificado = QrToken::partes(implode('.', $partes));

    afirmar($falsificado !== null, 'El formato sigue siendo válido');
    afirmar(!QrToken::firmaValida($falsificado, 'semilla-de-prueba'), 'Un token manipulado no debe validar');
});

test('Formatos inválidos se rechazan antes de tocar la base', function () {
    $invalidos = [
        '',
        'texto-cualquiera',
        'FP1.7.123',
        'FP0.7.9999999999.aabbccddeeff.' . str_repeat('a', 32),
        'FP1.abc.9999999999.aabbccddeeff.' . str_repeat('a', 32),
        'FP1.7.9999999999.zzzz.' . str_repeat('a', 32),
        'FP1.7.9999999999.aabbccddeeff.corta',
    ];

    foreach ($invalidos as $token) {
        afirmarIgual(null, QrToken::partes($token), 'Debería rechazar el token: ' . var_export($token, true));
    }
});

test('Un token vencido deja de estar vigente', function () {
    $viejo = QrToken::generar(7, 'semilla-de-prueba', time() - 600);
    $partes = QrToken::partes($viejo['token']);

    afirmar(!QrToken::vigente($partes), 'Un token de hace 10 minutos debe estar expirado');

    $nuevo = QrToken::partes(QrToken::generar(7, 'semilla-de-prueba')['token']);
    afirmar(QrToken::vigente($nuevo), 'Un token recién creado debe estar vigente');
});

test('Cada token es único aunque se generen en el mismo segundo', function () {
    $ahora = time();
    $primero = QrToken::generar(7, 'semilla-de-prueba', $ahora)['token'];
    $segundo = QrToken::generar(7, 'semilla-de-prueba', $ahora)['token'];

    afirmar($primero !== $segundo, 'El nonce debe hacer único a cada token');
    afirmar(
        QrToken::huella($primero) !== QrToken::huella($segundo),
        'Dos tokens distintos no pueden compartir huella'
    );
});
