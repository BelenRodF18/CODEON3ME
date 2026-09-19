<?php
/**
 * Runner de pruebas sin dependencias: php api/tests/run.php
 * Carga todos los *Test.php, los ejecuta y muestra el resultado.
 * Las pruebas que necesitan MySQL se saltean solas si la base no responde.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}

require_once __DIR__ . '/../autoload.php';
\App\Utils\EnvLoader::load(__DIR__ . '/../.env');


$GLOBALS['fp_tests'] = [];
$GLOBALS['fp_resultados'] = ['ok' => 0, 'fallos' => [], 'salteados' => []];

/** Registra una prueba: el nombre dice qué comprueba. */
function test(string $nombre, callable $caso): void {
    $GLOBALS['fp_tests'][] = ['nombre' => $nombre, 'caso' => $caso];
}

/** Señal para saltear una prueba (por ejemplo, sin MySQL). */
class TestSalteado extends \Exception {}

/** Saltea la prueba actual indicando el motivo. */
function saltear(string $motivo): void {
    throw new TestSalteado($motivo);
}

/** Falla la prueba si la condición no se cumple. */
function afirmar($condicion, string $mensaje): void {
    if (!$condicion) {
        throw new \Exception($mensaje);
    }
}

/** Falla la prueba si los dos valores no son iguales. */
function afirmarIgual($esperado, $obtenido, string $mensaje): void {
    if ($esperado !== $obtenido) {
        throw new \Exception(sprintf(
            "%s\n     esperado: %s\n     obtenido: %s",
            $mensaje,
            var_export($esperado, true),
            var_export($obtenido, true)
        ));
    }
}

/** Conexión a MySQL para las pruebas, o null si la base no está disponible. */
function conexionPruebas(): ?PDO {
    static $pdo = false;

    if ($pdo !== false) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'fitpower'
    );

    try {
        $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        $pdo = null;
    }

    return $pdo;
}


/** Carga todas las pruebas de la carpeta. */
foreach (glob(__DIR__ . '/*Test.php') as $archivo) {
    require_once $archivo;
}

$inicio = microtime(true);

/** Ejecuta cada prueba y cuenta los resultados. */
foreach ($GLOBALS['fp_tests'] as $test) {
    try {
        $test['caso']();
        $GLOBALS['fp_resultados']['ok']++;
        echo "  \033[32mOK\033[0m   " . $test['nombre'] . PHP_EOL;
    } catch (TestSalteado $salto) {
        $GLOBALS['fp_resultados']['salteados'][] = $test['nombre'];
        echo "  \033[33mSKIP\033[0m " . $test['nombre'] . ' (' . $salto->getMessage() . ')' . PHP_EOL;
    } catch (\Throwable $error) {
        $GLOBALS['fp_resultados']['fallos'][] = [$test['nombre'], $error->getMessage()];
        echo "  \033[31mFALLA\033[0m " . $test['nombre'] . PHP_EOL;
        echo '     ' . str_replace("\n", "\n     ", $error->getMessage()) . PHP_EOL;
    }
}

$duracion = round((microtime(true) - $inicio) * 1000);
$resultados = $GLOBALS['fp_resultados'];

echo PHP_EOL;
echo sprintf(
    "%d ok, %d fallas, %d salteados  (%d ms)%s",
    $resultados['ok'],
    count($resultados['fallos']),
    count($resultados['salteados']),
    $duracion,
    PHP_EOL
);

exit(count($resultados['fallos']) > 0 ? 1 : 0);
