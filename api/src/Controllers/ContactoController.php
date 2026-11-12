<?php
namespace App\Controllers;

use App\Utils\Correo;

/** Formulario de contacto de la web: manda el mensaje por correo al gimnasio. No hace falta iniciar sesión. */
class ContactoController extends BaseController {
    /** POST /contacto: valida lo que escribió la persona y lo manda por correo. */
    public function enviar() {
        $datos = $this->body();

        // Campo trampa: una persona real lo deja vacío, los programas que rellenan todo lo completan.
        if (trim((string) ($datos['sitio_web'] ?? '')) !== '') {
            return $this->exito(['message' => 'Mensaje enviado']);
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $apellido = trim((string) ($datos['apellido'] ?? ''));
        $contacto = trim((string) ($datos['contacto'] ?? ''));
        $mensaje = trim((string) ($datos['mensaje'] ?? ''));

        if ($nombre === '' || $apellido === '' || mb_strlen($nombre) > 60 || mb_strlen($apellido) > 60) {
            return $this->error('Completá tu nombre y apellido', 422, ['campo' => 'nombre']);
        }

        $esEmail = filter_var($contacto, FILTER_VALIDATE_EMAIL) !== false;
        $esTelefono = (bool) preg_match('/^[+\d][\d\s().-]{6,}$/', $contacto);
        if ((!$esEmail && !$esTelefono) || mb_strlen($contacto) > 150) {
            return $this->error('Ingresá un correo o un teléfono válido', 422, ['campo' => 'contacto']);
        }

        if (mb_strlen($mensaje) < 5 || mb_strlen($mensaje) > 2000) {
            return $this->error('Escribí tu mensaje (hasta 2000 caracteres)', 422, ['campo' => 'mensaje']);
        }

        // Un mensaje cada 30 segundos por visita, para que nadie llene el correo.
        $ahora = time();
        if (!empty($_SESSION['contacto_ultimo']) && $ahora - (int) $_SESSION['contacto_ultimo'] < 30) {
            return $this->error('Esperá unos segundos antes de mandar otro mensaje', 429);
        }

        $destino = (string) (getenv('MAIL_DESTINO') ?: getenv('MAIL_USUARIO'));
        $texto = "Nuevo mensaje desde la web de FitPower\n\n"
            . "Nombre: $nombre $apellido\n"
            . "Contacto: $contacto\n\n"
            . "Mensaje:\n$mensaje\n";

        try {
            Correo::enviar($destino, "Contacto web FitPower: $nombre $apellido", $texto, $esEmail ? $contacto : null);
        } catch (\Throwable $error) {
            // El motivo real queda en el log del servidor; a la persona se le muestra un aviso simple.
            error_log('Contacto: no se pudo enviar el correo: ' . $error->getMessage());

            return $this->error('No pudimos enviar tu mensaje. Probá de nuevo más tarde.', 502);
        }

        $_SESSION['contacto_ultimo'] = $ahora;
        $this->exito(['message' => 'Mensaje enviado']);
    }
}
