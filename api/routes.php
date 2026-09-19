<?php
/**
 * Rutas de la API: método HTTP + URL => Controlador@método.
 * Los parámetros como :id o :slug llegan como argumentos al método.
 */

$router->get('/estado', 'EstadoController@ver');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/me', 'AuthController@me');
$router->get('/sesion', 'AuthController@sesion');
$router->get('/programas', 'CatalogoController@programas');
$router->get('/programas/:slug', 'CatalogoController@programa');
$router->get('/planes', 'CatalogoController@planes');
$router->get('/planes/:slug', 'CatalogoController@plan');
$router->get('/socio/resumen', 'SocioController@resumen');
$router->get('/socio/rutinas', 'SocioController@rutinas');
$router->post('/socio/reportes', 'SocioController@crearReporte');
$router->get('/socio/reportes', 'SocioController@reportes');
$router->get('/socio/reportes/:id/mensajes', 'SocioController@mensajes');
$router->get('/socio/avisos', 'SocioController@avisos');
$router->post('/socio/avisos/leidos', 'SocioController@marcarAvisosLeidos');
$router->post('/socio/reportes/:id/mensajes', 'SocioController@enviarMensaje');
$router->get('/socio/qr', 'SocioController@generarQr');
$router->post('/accesos/validar', 'SocioController@validarQr');
$router->get('/socio/rendimiento', 'SocioController@rendimiento');
$router->post('/socio/series', 'SocioController@registrarSerie');
$router->put('/socio/series/:id', 'SocioController@actualizarSerie');
$router->delete('/socio/series/:id', 'SocioController@eliminarSerie');
$router->get('/admin/conversaciones', 'AdminController@conversaciones');
$router->get('/admin/conversaciones/:id/mensajes', 'AdminController@mensajes');
$router->post('/admin/conversaciones/:id/mensajes', 'AdminController@responder');
$router->put('/admin/conversaciones/:id/estado', 'AdminController@cambiarEstado');
$router->get('/admin/resumen', 'AdminController@resumen');
$router->put('/admin/socios/:id/entrenador', 'AdminController@asignarEntrenador');
$router->put('/admin/socios/:id/membresia', 'AdminController@actualizarMembresia');
$router->put('/admin/socios/:id/programas', 'AdminController@inscribirProgramas');
$router->put('/admin/programas/:id/entrenador', 'AdminController@asignarEntrenadorPrograma');
$router->post('/admin/ejercicios', 'AdminController@crearEjercicio');
$router->get('/admin/usuarios', 'AdminController@listarUsuarios');
$router->post('/admin/usuarios', 'AdminController@crearUsuario');
$router->put('/admin/usuarios/:id', 'AdminController@actualizarUsuario');
$router->put('/admin/usuarios/:id/estado', 'AdminController@cambiarEstadoUsuario');
$router->get('/admin/usuarios/:id/dependencias', 'AdminController@dependenciasUsuario');
$router->delete('/admin/usuarios/:id', 'AdminController@eliminarUsuario');
$router->get('/entrenador/resumen', 'EntrenadorController@resumen');
$router->post('/entrenador/rutinas', 'EntrenadorController@crearRutina');
$router->get('/personal/conversaciones', 'PersonalController@conversaciones');
$router->get('/personal/conversaciones/:id/mensajes', 'PersonalController@mensajes');
$router->post('/personal/conversaciones/:id/mensajes', 'PersonalController@responder');
$router->put('/personal/conversaciones/:id/estado', 'PersonalController@cambiarEstado');
$router->get('/recepcion/resumen', 'RecepcionController@resumen');
$router->get('/recepcion/accesos', 'RecepcionController@historial');
$router->post('/recepcion/accesos/canjear', 'RecepcionController@canjear');
$router->post('/recepcion/accesos/:id/salida', 'RecepcionController@registrarSalida');
