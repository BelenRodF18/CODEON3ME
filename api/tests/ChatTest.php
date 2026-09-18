<?php
/**
 * Pruebas de los reportes de mantenimiento: el socio se entera de los cambios de estado.
 */

use App\Models\ChatModel;
use App\Models\UsuarioModel;

test('Cambiar el estado de un reporte avisa al socio en el chat', function () {
    $db = conexionPruebas();
    if (!$db) saltear('MySQL no disponible');

    $usuarios = new UsuarioModel($db);
    $socio = $usuarios->crear('Socio Chat', 'test.chat.' . bin2hex(random_bytes(4)), 'fitpower123', 3);
    $personal = (int) $db->query("SELECT ID_usuario FROM Usuarios WHERE Usuario = 'personal'")->fetchColumn();

    try {
        $db->prepare("INSERT INTO Chat (Titulo, Descripcion, ID_usuario) VALUES ('Polea rota', 'Prueba', :socio)")
            ->execute(['socio' => $socio]);
        $chat = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO Mensajes_chat (ID_chat, ID_usuario, Mensaje) VALUES (:chat, :socio, 'Reporte recibido')")
            ->execute(['chat' => $chat, 'socio' => $socio]);

        $modelo = new ChatModel($db);
        afirmarIgual([], $modelo->avisosDelSocio($socio), 'Sin cambios no hay avisos');

        afirmar($modelo->cambiarEstado($chat, 'En reparacion', $personal, true, $personal), 'El reporte existe');
        $avisos = $modelo->avisosDelSocio($socio);
        afirmarIgual(1, count($avisos), 'Debe haber un aviso nuevo');
        afirmarIgual('Estado actualizado: En reparación.', $avisos[0]['mensaje'], 'Texto del aviso');

        $modelo->cambiarEstado($chat, 'En reparacion', $personal, true, $personal);
        afirmarIgual(1, count($modelo->avisosDelSocio($socio)), 'Sin cambio real no hay aviso nuevo');

        $modelo->marcarLeidos($socio);
        afirmarIgual([], $modelo->avisosDelSocio($socio), 'Al abrir sus conversaciones quedan leídos');

        afirmar(!$modelo->cambiarEstado(999999999, 'Solucionado', $personal, true, $personal), 'Un reporte inexistente se rechaza');
    } finally {
        $usuarios->eliminar($socio);
    }
});
