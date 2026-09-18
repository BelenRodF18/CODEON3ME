<?php
/**
 * Instalador de la base sin Docker: php api/config/setup.php [--reset-passwords]
 * Ejecuta init.sql (idempotente) y verifica que existan las cuentas sembradas.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta desde la línea de comandos.\n");
}

require_once __DIR__ . '/../autoload.php';

\App\Utils\EnvLoader::load(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'fitpower';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS');
$pass = $pass === false ? '' : $pass;

$resetPasswords = in_array('--reset-passwords', $argv, true);

/** Imprime una línea en la consola. */
function salida(string $mensaje): void {
    echo $mensaje . PHP_EOL;
}

/** Parte el script SQL en sentencias, respetando comillas y comentarios. */
function dividirSql(string $sql): array {
    $sentencias = [];
    $actual = '';
    $comilla = null;
    $largo = strlen($sql);

    for ($i = 0; $i < $largo; $i++) {
        $caracter = $sql[$i];

        if ($comilla !== null) {
            $actual .= $caracter;
            if ($caracter === "\\" && $i + 1 < $largo) {
                $actual .= $sql[++$i];
                continue;
            }
            if ($caracter === $comilla) {
                $comilla = null;
            }
            continue;
        }

        if ($caracter === "'" || $caracter === '"' || $caracter === '`') {
            $comilla = $caracter;
            $actual .= $caracter;
            continue;
        }

        if (($caracter === '-' && substr($sql, $i, 2) === '--') || $caracter === '#') {
            $fin = strpos($sql, "\n", $i);
            $i = $fin === false ? $largo : $fin;
            continue;
        }

        if ($caracter === ';') {
            $sentencia = trim($actual);
            if ($sentencia !== '') {
                $sentencias[] = $sentencia;
            }
            $actual = '';
            continue;
        }

        $actual .= $caracter;
    }

    $sentencia = trim($actual);
    if ($sentencia !== '') {
        $sentencias[] = $sentencia;
    }

    return $sentencias;
}

try {
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    salida('No se pudo conectar a MySQL en ' . $host . ':' . $port);
    salida('Detalle: ' . $e->getMessage());
    salida('Revisá api/.env (DB_HOST, DB_PORT, DB_USER, DB_PASS).');
    exit(1);
}

salida('Conectado a MySQL en ' . $host . ':' . $port);

$script = file_get_contents(__DIR__ . '/init.sql');
if ($script === false) {
    salida('No se pudo leer init.sql');
    exit(1);
}

if ($name !== 'fitpower') {
    $script = preg_replace('/\bfitpower\b/', $name, $script, 2);
}

$sentencias = dividirSql($script);
$ejecutadas = 0;

foreach ($sentencias as $sentencia) {
    try {
        $pdo->exec($sentencia);
        $ejecutadas++;
    } catch (PDOException $e) {
        salida('Error ejecutando: ' . substr(preg_replace('/\s+/', ' ', $sentencia), 0, 120) . '...');
        salida('Detalle: ' . $e->getMessage());
        exit(1);
    }
}

salida('Esquema aplicado (' . $ejecutadas . ' sentencias).');

$pdo->exec('USE `' . str_replace('`', '', $name) . '`');

$roles = $pdo->query('SELECT ID_tipo, Nombre FROM Tipo_usuario ORDER BY ID_tipo')->fetchAll(PDO::FETCH_KEY_PAIR);
salida('Roles: ' . implode(', ', $roles));

$cuentas = [
    'admin' => 'Administrador',
    'entrenador' => 'Entrenador',
    'socio' => 'Socio',
    'personal' => 'Personal',
    'recepcion' => 'Recepcion',
    'juan.diaz' => 'Entrenador',
    'diego.torres' => 'Entrenador',
    'lucas.gomez' => 'Entrenador',
    'sofia.rey' => 'Entrenador',
    'elena.paz' => 'Entrenador',
    'bruno.soler' => 'Entrenador',
    'matias.rossi' => 'Entrenador',
    'martina.lopez' => 'Socio',
];

if ($resetPasswords) {
    $hash = password_hash('fitpower123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE Usuarios SET Password = :hash, Activo = 1 WHERE Usuario = :usuario');
    foreach (array_keys($cuentas) as $usuario) {
        $stmt->execute(['hash' => $hash, 'usuario' => $usuario]);
    }
    salida('Contraseñas de las cuentas base restablecidas a "fitpower123".');
}

$stmt = $pdo->prepare('SELECT u.Activo, t.Nombre FROM Usuarios u INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo WHERE u.Usuario = :usuario');
$faltantes = [];
foreach ($cuentas as $usuario => $rolEsperado) {
    $stmt->execute(['usuario' => $usuario]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fila) {
        $faltantes[] = $usuario;
        continue;
    }
    salida(sprintf('  %-16s %-14s %s', $usuario, $fila['Nombre'], (int) $fila['Activo'] === 1 ? 'activo' : 'inactivo'));
}

if ($faltantes) {
    salida('Faltan cuentas base: ' . implode(', ', $faltantes));
    exit(1);
}

\App\Utils\QrToken::secretoApp();
salida('Secreto de firma QR listo.');

salida('');
salida('Listo. Contraseña de las cuentas base: fitpower123');
