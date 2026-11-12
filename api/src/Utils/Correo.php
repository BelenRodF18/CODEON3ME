<?php
namespace App\Utils;

/**
 * Envío de correos por SMTP (pensado para Gmail).
 * La cuenta y la clave salen del .env: MAIL_USUARIO y MAIL_CLAVE.
 * Con Gmail, MAIL_CLAVE tiene que ser una "contraseña de aplicación", no la contraseña normal de la cuenta.
 */
class Correo {
    /** Segundos máximos que se espera la respuesta del servidor de correo. */
    private const ESPERA = 15;

    /** Manda un correo de texto. Si algo falla, lanza una excepción que explica el motivo. */
    public static function enviar(string $para, string $asunto, string $texto, ?string $responderA = null): void {
        $host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $puerto = (int) (getenv('MAIL_PUERTO') ?: 587);
        $usuario = (string) getenv('MAIL_USUARIO');
        $clave = (string) getenv('MAIL_CLAVE');
        $cifrado = (string) (getenv('MAIL_CIFRADO') ?: 'starttls'); // "ninguno" solo se usa en las pruebas

        if ($usuario === '' || ($cifrado !== 'ninguno' && $clave === '')) {
            throw new \RuntimeException('Falta configurar MAIL_USUARIO y MAIL_CLAVE en el .env');
        }

        // Nada de saltos de línea en los datos que van en los encabezados (así nadie puede meter encabezados de más).
        $para = self::limpiar($para);
        $asunto = self::limpiar($asunto);
        $responderA = $responderA !== null ? self::limpiar($responderA) : null;

        $conexion = @stream_socket_client("tcp://$host:$puerto", $codigo, $mensaje, self::ESPERA);
        if (!$conexion) {
            throw new \RuntimeException("No se pudo conectar con $host:$puerto ($mensaje)");
        }
        stream_set_timeout($conexion, self::ESPERA);

        try {
            self::esperar($conexion, '220');
            self::comando($conexion, 'EHLO fitpower', '250');

            if ($cifrado !== 'ninguno') {
                self::comando($conexion, 'STARTTLS', '220');
                $metodo = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                if (!stream_socket_enable_crypto($conexion, true, $metodo)) {
                    throw new \RuntimeException('No se pudo cifrar la conexión con el servidor de correo');
                }
                self::comando($conexion, 'EHLO fitpower', '250');

                self::comando($conexion, 'AUTH LOGIN', '334');
                self::comando($conexion, base64_encode($usuario), '334');
                self::comando($conexion, base64_encode($clave), '235');
            }

            self::comando($conexion, "MAIL FROM:<$usuario>", '250');
            self::comando($conexion, "RCPT TO:<$para>", '250');
            self::comando($conexion, 'DATA', '354');

            $encabezados = [
                'From: ' . self::textoCodificado('Web FitPower') . " <$usuario>",
                "To: <$para>",
                'Subject: ' . self::textoCodificado($asunto),
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@fitpower>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            if ($responderA !== null && filter_var($responderA, FILTER_VALIDATE_EMAIL)) {
                $encabezados[] = "Reply-To: <$responderA>";
            }

            $cuerpo = chunk_split(base64_encode($texto), 76, "\r\n");
            fwrite($conexion, implode("\r\n", $encabezados) . "\r\n\r\n" . $cuerpo . "\r\n.\r\n");
            self::esperar($conexion, '250');

            @self::comando($conexion, 'QUIT', '221');
        } finally {
            fclose($conexion);
        }
    }

    /** Saca los saltos de línea de un dato que va en un encabezado. */
    private static function limpiar(string $dato): string {
        return trim(str_replace(["\r", "\n"], ' ', $dato));
    }

    /** Pasa un texto con tildes a la forma que aceptan los encabezados de correo. */
    private static function textoCodificado(string $texto): string {
        return '=?UTF-8?B?' . base64_encode($texto) . '?=';
    }

    /** Manda una orden al servidor y revisa que conteste con el código esperado. */
    private static function comando($conexion, string $orden, string $esperado): string {
        fwrite($conexion, $orden . "\r\n");

        return self::esperar($conexion, $esperado);
    }

    /** Lee la respuesta del servidor (puede venir en varias líneas) y revisa el código. */
    private static function esperar($conexion, string $esperado): string {
        $respuesta = '';
        while (($linea = fgets($conexion, 512)) !== false) {
            $respuesta .= $linea;
            // La última línea de la respuesta tiene un espacio después del código (250 ...), las anteriores un guion (250-...).
            if (strlen($linea) < 4 || $linea[3] === ' ') {
                break;
            }
        }

        $respuesta = trim($respuesta);
        if (strncmp($respuesta, $esperado, 3) !== 0) {
            $primera = strtok($respuesta, "\r\n") ?: 'sin respuesta';
            throw new \RuntimeException("El servidor de correo respondió: $primera");
        }

        return $respuesta;
    }
}
