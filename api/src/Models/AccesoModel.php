<?php
namespace App\Models;

use App\Utils\QrToken;

/**
 * Canje de QR en Recepción: registra ingresos y salidas.
 * Mostrar un QR no escribe en MySQL; el token se guarda recién al escanearlo.
 * Si el socio ya está adentro, el siguiente QR registra su salida.
 */
class AccesoModel {
    /** Dos lecturas del mismo socio en menos de esto se toman como duplicado. */
    private const SEGUNDOS_ANTI_DUPLICADO = 45;

    /** Un ingreso sin salida más viejo que esto ya no cuenta como "adentro". */
    public const HORAS_ESTADIA_MAXIMA = 12;

    private $db;

    /** Guarda la conexión. */
    public function __construct($db) {
        $this->db = $db;
    }

    /** Semilla con la que se firman los QR del socio (la crea la primera vez). */
    public function semilla(int $idUsuario): ?string {
        $stmt = $this->db->prepare('SELECT Token_semilla FROM Usuarios WHERE ID_usuario = :id');
        $stmt->execute(['id' => $idUsuario]);
        $semilla = $stmt->fetchColumn();

        if ($semilla === false) {
            return null;
        }

        if (is_string($semilla) && $semilla !== '') {
            return $semilla;
        }

        $nueva = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare('UPDATE Usuarios SET Token_semilla = :semilla WHERE ID_usuario = :id');
        $stmt->execute(['semilla' => $nueva, 'id' => $idUsuario]);

        return $nueva;
    }

    /** Datos del usuario dueño del token. */
    private function socioDelToken(int $idUsuario): ?array {
        $sql = 'SELECT u.ID_usuario AS id,
                       u.Activo AS activo,
                       u.Token_semilla AS semilla,
                       t.Nombre AS rol,
                       p.Nombre AS nombre,
                       u.Usuario AS nombre_usuario
                FROM Usuarios u
                INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
                INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
                WHERE u.ID_usuario = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idUsuario]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Membresía más reciente del socio. */
    private function membresia(int $idUsuario): ?array {
        $sql = 'SELECT m.Estado AS estado,
                       m.Fecha_vencimiento AS vencimiento,
                       p.Nombre AS plan
                FROM Membresias m
                INNER JOIN Plan p ON p.ID_plan = m.ID_plan
                WHERE m.ID_usuario = :id
                ORDER BY m.Fecha_vencimiento DESC
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idUsuario]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Indica si ese QR ya se usó. */
    private function tokenYaCanjeado(string $huella): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM Tokens_QR WHERE Token_hash = :hash LIMIT 1');
        $stmt->execute(['hash' => $huella]);

        return (bool) $stmt->fetchColumn();
    }

    /** Indica si el socio ingresó hace menos de 45 segundos (doble escaneo). */
    private function ingresoReciente(int $idUsuario): bool {
        $sql = "SELECT 1
                FROM Registro_Accesos
                WHERE ID_usuario = :id
                  AND Estado_acceso = 'Permitido'
                  AND Fecha_hora_ingreso >= DATE_SUB(NOW(), INTERVAL " . self::SEGUNDOS_ANTI_DUPLICADO . " SECOND)
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $idUsuario, \PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /** Ingreso aceptado del socio que todavía no tiene salida. */
    private function ingresoAbierto(int $idUsuario): ?array {
        $sql = "SELECT ID_acceso AS id, Fecha_hora_ingreso AS ingreso
                FROM Registro_Accesos
                WHERE ID_usuario = :id
                  AND Estado_acceso = 'Permitido'
                  AND Fecha_hora_salida IS NULL
                  AND Fecha_hora_ingreso >= DATE_SUB(NOW(), INTERVAL " . self::HORAS_ESTADIA_MAXIMA . " HOUR)
                ORDER BY Fecha_hora_ingreso DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $idUsuario, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Guarda el intento de ingreso, aceptado o rechazado; devuelve su ID. */
    private function registrarAcceso(int $idUsuario, string $estado, ?string $motivo, ?int $idRecepcion, ?int $idToken): int {
        $sql = 'INSERT INTO Registro_Accesos (Fecha_hora_ingreso, Estado_acceso, Motivo, ID_usuario, ID_recepcion, ID_token)
                VALUES (NOW(), :estado, :motivo, :usuario, :recepcion, :token)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'estado' => $estado,
            'motivo' => $motivo,
            'usuario' => $idUsuario,
            'recepcion' => $idRecepcion,
            'token' => $idToken,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** Fecha y hora guardadas del ingreso o de la salida, y minutos de estadía. */
    private function momentoDelAcceso(int $idAcceso, string $movimiento = 'ingreso'): array {
        $columna = $movimiento === 'salida' ? 'Fecha_hora_salida' : 'Fecha_hora_ingreso';
        $sql = "SELECT DATE_FORMAT($columna, '%d/%m/%Y') AS fecha,
                       DATE_FORMAT($columna, '%H:%i') AS hora,
                       $columna AS registrado_en,
                       DATE_FORMAT(Fecha_hora_ingreso, '%H:%i') AS hora_ingreso,
                       TIMESTAMPDIFF(MINUTE, Fecha_hora_ingreso, COALESCE(Fecha_hora_salida, NOW())) AS minutos
                FROM Registro_Accesos
                WHERE ID_acceso = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idAcceso]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'fecha' => date('d/m/Y'),
            'hora' => date('H:i'),
            'registrado_en' => date('Y-m-d H:i:s'),
            'hora_ingreso' => null,
            'minutos' => 0,
        ];
    }

    /** Arma la respuesta de rechazo y la registra si se sabe de qué socio es. */
    private function rechazo(string $motivo, string $codigo, ?array $socio = null, ?int $idRecepcion = null): array {
        if ($socio !== null) {
            $this->registrarAcceso((int) $socio['id'], 'Denegado', $motivo, $idRecepcion, null);
        }

        return [
            'permitido' => false,
            'codigo' => $codigo,
            'motivo' => $motivo,
            'socio' => $socio ? $this->socioPublico($socio) : null,
        ];
    }

    /** Datos del socio que se pueden mostrar en Recepción. */
    private function socioPublico(array $socio): array {
        return [
            'id' => (int) $socio['id'],
            'nombre' => $socio['nombre'],
            'nombre_usuario' => $socio['nombre_usuario'],
            'rol' => $socio['rol'],
        ];
    }

    /** Valida el QR (firma, vencimiento, uso, socio, membresía) y registra el ingreso o la salida. */
    public function canjear(string $token, ?int $idRecepcion): array {
        $partes = QrToken::partes($token);
        if ($partes === null) {
            return $this->rechazo('QR inválido', 'formato_invalido');
        }

        $socio = $this->socioDelToken($partes['usuario_id']);
        if ($socio === null) {
            return $this->rechazo('Socio no encontrado', 'socio_inexistente');
        }

        if (!QrToken::firmaValida($partes, $socio['semilla'])) {
            return $this->rechazo('QR inválido', 'firma_invalida', $socio, $idRecepcion);
        }

        if (strcasecmp((string) $socio['rol'], 'Socio') !== 0) {
            return $this->rechazo('El usuario no es un socio', 'rol_invalido', $socio, $idRecepcion);
        }

        if ((int) $socio['activo'] !== 1) {
            return $this->rechazo('Socio inactivo', 'socio_inactivo', $socio, $idRecepcion);
        }

        if (!QrToken::vigente($partes)) {
            return $this->rechazo('Token expirado', 'token_expirado', $socio, $idRecepcion);
        }

        $huella = QrToken::huella($token);
        if ($this->tokenYaCanjeado($huella)) {
            return $this->rechazo('QR ya utilizado', 'token_usado', $socio, $idRecepcion);
        }

        if ($this->ingresoReciente((int) $socio['id'])) {
            return $this->rechazo('Ingreso duplicado', 'ingreso_duplicado', $socio, $idRecepcion);
        }

        $abierto = $this->ingresoAbierto((int) $socio['id']);
        if ($abierto !== null) {
            return $this->registrarSalida($socio, $partes, $huella, (int) $abierto['id'], $idRecepcion);
        }

        $membresia = $this->membresia((int) $socio['id']);
        if ($membresia === null) {
            return $this->rechazo('Sin membresía registrada', 'sin_membresia', $socio, $idRecepcion);
        }

        $estado = strtolower((string) $membresia['estado']);
        if (strpos($estado, 'bloq') !== false) {
            return $this->rechazo('Membresía bloqueada', 'membresia_bloqueada', $socio, $idRecepcion);
        }
        if (strpos($estado, 'venc') !== false || $membresia['vencimiento'] < date('Y-m-d')) {
            return $this->rechazo('Membresía vencida', 'membresia_vencida', $socio, $idRecepcion);
        }

        $this->db->beginTransaction();

        try {
            $sql = 'INSERT INTO Tokens_QR (Token_hash, ID_usuario, Fecha_expiracion, Usado, Fecha_uso, ID_recepcion)
                    VALUES (:hash, :usuario, FROM_UNIXTIME(:expira), 1, NOW(), :recepcion)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'hash' => $huella,
                'usuario' => (int) $socio['id'],
                'expira' => $partes['expira'],
                'recepcion' => $idRecepcion,
            ]);

            $idToken = (int) $this->db->lastInsertId();
            $idAcceso = $this->registrarAcceso((int) $socio['id'], 'Permitido', null, $idRecepcion, $idToken);

            $this->db->commit();

            $momento = $this->momentoDelAcceso($idAcceso);
        } catch (\PDOException $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if ($error->getCode() === '23000') {
                return $this->rechazo('QR ya utilizado', 'token_usado', $socio, $idRecepcion);
            }

            throw $error;
        }

        return [
            'permitido' => true,
            'codigo' => 'ingreso_autorizado',
            'movimiento' => 'ingreso',
            'motivo' => null,
            'socio' => $this->socioPublico($socio) + [
                'plan' => $membresia['plan'],
                'membresia' => $membresia['estado'],
                'vencimiento' => $membresia['vencimiento'],
            ],
            'acceso' => [
                'id' => $idAcceso,
                'token_id' => $idToken,
                'fecha' => $momento['fecha'],
                'hora' => $momento['hora'],
                'registrado_en' => $momento['registrado_en'],
            ],
        ];
    }

    /** Guarda el token usado y cierra el ingreso abierto con la hora de salida. */
    private function registrarSalida(array $socio, array $partes, string $huella, int $idAcceso, ?int $idRecepcion): array {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO Tokens_QR (Token_hash, ID_usuario, Fecha_expiracion, Usado, Fecha_uso, ID_recepcion)
                 VALUES (:hash, :usuario, FROM_UNIXTIME(:expira), 1, NOW(), :recepcion)'
            );
            $stmt->execute([
                'hash' => $huella,
                'usuario' => (int) $socio['id'],
                'expira' => $partes['expira'],
                'recepcion' => $idRecepcion,
            ]);

            $this->cerrarIngreso($idAcceso);
            $this->db->commit();
        } catch (\PDOException $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($error->getCode() === '23000') {
                return $this->rechazo('QR ya utilizado', 'token_usado', $socio, $idRecepcion);
            }
            throw $error;
        }

        return $this->resultadoSalida($socio, $idAcceso);
    }

    /** Pone la hora de salida a un ingreso abierto. */
    private function cerrarIngreso(int $idAcceso): void {
        $stmt = $this->db->prepare(
            'UPDATE Registro_Accesos SET Fecha_hora_salida = NOW() WHERE ID_acceso = :id AND Fecha_hora_salida IS NULL'
        );
        $stmt->execute(['id' => $idAcceso]);
    }

    /** Respuesta de una salida: horas de ingreso y salida, y estadía. */
    private function resultadoSalida(array $socio, int $idAcceso): array {
        $momento = $this->momentoDelAcceso($idAcceso, 'salida');

        return [
            'permitido' => true,
            'codigo' => 'salida_registrada',
            'movimiento' => 'salida',
            'motivo' => null,
            'socio' => $this->socioPublico($socio),
            'acceso' => [
                'id' => $idAcceso,
                'fecha' => $momento['fecha'],
                'hora' => $momento['hora'],
                'hora_ingreso' => $momento['hora_ingreso'],
                'minutos' => (int) $momento['minutos'],
                'registrado_en' => $momento['registrado_en'],
            ],
        ];
    }

    /** Salida cargada a mano por Recepción; null si el ingreso no existe o ya estaba cerrado. */
    public function registrarSalidaManual(int $idAcceso): ?array {
        $stmt = $this->db->prepare(
            "SELECT ra.ID_acceso, u.ID_usuario AS id, u.Activo AS activo, u.Token_semilla AS semilla,
                    t.Nombre AS rol, p.Nombre AS nombre, u.Usuario AS nombre_usuario
             FROM Registro_Accesos ra
             INNER JOIN Usuarios u ON u.ID_usuario = ra.ID_usuario
             INNER JOIN Tipo_usuario t ON t.ID_tipo = u.ID_tipo
             INNER JOIN Persona p ON p.ID_usuario = u.ID_usuario
             WHERE ra.ID_acceso = :id AND ra.Estado_acceso = 'Permitido' AND ra.Fecha_hora_salida IS NULL"
        );
        $stmt->execute(['id' => $idAcceso]);
        $socio = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$socio) {
            return null;
        }

        $this->cerrarIngreso($idAcceso);

        return $this->resultadoSalida($socio, $idAcceso);
    }

    /** Últimos ingresos y salidas para el panel de Recepción. */
    public function historial(int $limite = 25): array {
        $sql = 'SELECT ra.ID_acceso AS id,
                       ra.Estado_acceso AS estado,
                       ra.Motivo AS motivo,
                       DATE_FORMAT(ra.Fecha_hora_ingreso, "%d/%m/%Y") AS fecha,
                       DATE_FORMAT(ra.Fecha_hora_ingreso, "%H:%i") AS hora,
                       DATE_FORMAT(ra.Fecha_hora_salida, "%H:%i") AS salida,
                       (ra.Estado_acceso = "Permitido" AND ra.Fecha_hora_salida IS NULL
                        AND ra.Fecha_hora_ingreso >= DATE_SUB(NOW(), INTERVAL ' . self::HORAS_ESTADIA_MAXIMA . ' HOUR)) AS adentro,
                       p.Nombre AS socio,
                       us.Usuario AS nombre_usuario,
                       ur.Usuario AS recepcion
                FROM Registro_Accesos ra
                INNER JOIN Persona p ON p.ID_usuario = ra.ID_usuario
                INNER JOIN Usuarios us ON us.ID_usuario = ra.ID_usuario
                LEFT JOIN Usuarios ur ON ur.ID_usuario = ra.ID_recepcion
                ORDER BY ra.Fecha_hora_ingreso DESC, ra.ID_acceso DESC
                LIMIT :limite';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limite', max(1, min(200, $limite)), \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function (array $fila): array {
            $fila['adentro'] = (int) $fila['adentro'] === 1;

            return $fila;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** Cantidad de socios dentro del gimnasio ahora. */
    public function personasAdentro(): int {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM Registro_Accesos
             WHERE Estado_acceso = 'Permitido' AND Fecha_hora_salida IS NULL
               AND Fecha_hora_ingreso >= DATE_SUB(NOW(), INTERVAL " . self::HORAS_ESTADIA_MAXIMA . " HOUR)"
        )->fetchColumn();
    }

    /** Contadores del día: aceptados, rechazados y personas adentro. */
    public function resumenDelDia(): array {
        $sql = "SELECT
                    SUM(Estado_acceso = 'Permitido') AS permitidos,
                    SUM(Estado_acceso = 'Denegado') AS denegados
                FROM Registro_Accesos
                WHERE DATE(Fecha_hora_ingreso) = CURRENT_DATE";

        $fila = $this->db->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'permitidos' => (int) ($fila['permitidos'] ?? 0),
            'denegados' => (int) ($fila['denegados'] ?? 0),
            'adentro' => $this->personasAdentro(),
        ];
    }
}
