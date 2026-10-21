<?php
namespace App\Utils;

/**
 * Códigos QR con firma de seguridad: nadie puede inventarlos ni modificarlos, y no se guardan en la base al crearlos.
 * Formato: FP1.<id_usuario>.<vencimiento>.<número_al_azar>.<firma>
 */
class QrToken {
    /** Versión del formato del código. */
    public const PREFIJO = 'FP1';

    /** Segundos que dura cada QR antes de cambiar por uno nuevo. */
    public const VIGENCIA_SEGUNDOS = 60;

    /** Margen en segundos por si el reloj del celular y el del servidor no están clavados. */
    private const MARGEN_RELOJ = 10;

    /** Cantidad de caracteres de la firma que se incluyen en el código. */
    private const LARGO_FIRMA = 32;

    /** Clave secreta de la aplicación: viene de QR_SECRET en el .env o se crea sola en api/config/.qr_secret. */
    public static function secretoApp(): string {
        $secreto = (string) (getenv('QR_SECRET') ?: '');
        if (strlen($secreto) >= 32) {
            return $secreto;
        }

        $archivo = dirname(__DIR__, 2) . '/config/.qr_secret';
        if (is_readable($archivo)) {
            $guardado = trim((string) file_get_contents($archivo));
            if (strlen($guardado) >= 32) {
                return $guardado;
            }
        }

        $nuevo = bin2hex(random_bytes(32));
        @file_put_contents($archivo, $nuevo, LOCK_EX);
        @chmod($archivo, 0600);

        return $nuevo;
    }

    /** Clave para firmar: combina la clave secreta de la app con la semilla propia de cada socio. */
    private static function clave(int $idUsuario, ?string $semilla): string {
        return hash_hmac('sha256', 'qr|' . $idUsuario . '|' . (string) $semilla, self::secretoApp(), true);
    }

    /** Calcula la firma del código con la clave del socio. */
    private static function firmar(string $cuerpo, int $idUsuario, ?string $semilla): string {
        return substr(hash_hmac('sha256', $cuerpo, self::clave($idUsuario, $semilla)), 0, self::LARGO_FIRMA);
    }

    /** Crea un QR nuevo para el socio; devuelve el código y cuándo vence. */
    public static function generar(int $idUsuario, ?string $semilla, ?int $ahora = null): array {
        $ahora = $ahora ?? time();
        $expira = $ahora + self::VIGENCIA_SEGUNDOS;
        $nonce = bin2hex(random_bytes(6));

        $cuerpo = self::PREFIJO . '.' . $idUsuario . '.' . $expira . '.' . $nonce;
        $token = $cuerpo . '.' . self::firmar($cuerpo, $idUsuario, $semilla);

        return [
            'token' => $token,
            'expira_en' => $expira,
            'vigencia' => self::VIGENCIA_SEGUNDOS,
        ];
    }

    /** Separa el código en sus partes; null si el formato no es válido. */
    public static function partes(string $token): ?array {
        $token = trim($token);
        if ($token === '' || strlen($token) > 200) {
            return null;
        }

        $partes = explode('.', $token);
        if (count($partes) !== 5 || $partes[0] !== self::PREFIJO) {
            return null;
        }

        [$prefijo, $usuario, $expira, $nonce, $firma] = $partes;

        if (!ctype_digit($usuario) || !ctype_digit($expira)) {
            return null;
        }
        if (!ctype_xdigit($nonce) || strlen($nonce) !== 12) {
            return null;
        }
        if (!ctype_xdigit($firma) || strlen($firma) !== self::LARGO_FIRMA) {
            return null;
        }

        return [
            'usuario_id' => (int) $usuario,
            'expira' => (int) $expira,
            'nonce' => $nonce,
            'firma' => $firma,
            'cuerpo' => $prefijo . '.' . $usuario . '.' . $expira . '.' . $nonce,
        ];
    }

    /** Comprueba que la firma sea del socio (el código no fue modificado). */
    public static function firmaValida(array $partes, ?string $semilla): bool {
        $esperada = self::firmar($partes['cuerpo'], $partes['usuario_id'], $semilla);

        return hash_equals($esperada, $partes['firma']);
    }

    /** Indica si el código todavía no venció. */
    public static function vigente(array $partes, ?int $ahora = null): bool {
        $ahora = $ahora ?? time();

        return $partes['expira'] + self::MARGEN_RELOJ >= $ahora;
    }

    /** Versión cifrada del código; se guarda en Tokens_QR para que no se pueda usar dos veces. */
    public static function huella(string $token): string {
        return hash('sha256', trim($token));
    }
}
