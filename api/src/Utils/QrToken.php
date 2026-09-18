<?php
namespace App\Utils;

/**
 * Códigos QR firmados (HMAC-SHA256) que se generan sin escribir en la base.
 * Formato: FP1.<id_usuario>.<expira_unix>.<nonce>.<firma>
 */
class QrToken {
    /** Versión del formato del token. */
    public const PREFIJO = 'FP1';

    /** Segundos que dura cada QR antes de renovarse. */
    public const VIGENCIA_SEGUNDOS = 60;

    /** Tolerancia de reloj entre el celular del socio y el servidor. */
    private const MARGEN_RELOJ = 10;

    /** Caracteres de la firma que se guardan en el token. */
    private const LARGO_FIRMA = 32;

    /** Secreto de la aplicación: QR_SECRET del .env o uno generado en api/config/.qr_secret. */
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

    /** Clave de firma: mezcla el secreto de la app con la semilla del socio. */
    private static function clave(int $idUsuario, ?string $semilla): string {
        return hash_hmac('sha256', 'qr|' . $idUsuario . '|' . (string) $semilla, self::secretoApp(), true);
    }

    /** Firma el cuerpo del token con la clave del socio. */
    private static function firmar(string $cuerpo, int $idUsuario, ?string $semilla): string {
        return substr(hash_hmac('sha256', $cuerpo, self::clave($idUsuario, $semilla)), 0, self::LARGO_FIRMA);
    }

    /** Genera un QR nuevo para el socio; devuelve el token y cuándo vence. */
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

    /** Separa el token en sus partes; null si el formato es inválido. */
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

    /** Comprueba que la firma corresponda al socio (token no alterado). */
    public static function firmaValida(array $partes, ?string $semilla): bool {
        $esperada = self::firmar($partes['cuerpo'], $partes['usuario_id'], $semilla);

        return hash_equals($esperada, $partes['firma']);
    }

    /** Indica si el token todavía no venció. */
    public static function vigente(array $partes, ?int $ahora = null): bool {
        $ahora = $ahora ?? time();

        return $partes['expira'] + self::MARGEN_RELOJ >= $ahora;
    }

    /** Hash del token que se guarda en Tokens_QR para impedir reutilizarlo. */
    public static function huella(string $token): string {
        return hash('sha256', trim($token));
    }
}
