<?php
// api/routes.php
// Acá se anota: "cuando pidan ESTA url, ejecutá ESTE controlador@método"

$router->get('/estado', 'EstadoController@ver');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/me', 'AuthController@me');
$router->get('/socio/resumen', 'SocioController@resumen');
$router->get('/socio/rutinas', 'SocioController@rutinas');
$router->post('/socio/reportes', 'SocioController@crearReporte');
$router->get('/socio/reportes', 'SocioController@reportes');
$router->get('/socio/reportes/:id/mensajes', 'SocioController@mensajes');
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
$router->post('/admin/ejercicios', 'AdminController@crearEjercicio');
$router->post('/admin/usuarios', 'AdminController@crearUsuario');
$router->put('/admin/usuarios/:id', 'AdminController@actualizarUsuario');
$router->delete('/admin/usuarios/:id', 'AdminController@desactivarUsuario');
$router->get('/entrenador/resumen', 'EntrenadorController@resumen');
$router->post('/entrenador/rutinas', 'EntrenadorController@crearRutina');
$router->get('/personal/conversaciones', 'PersonalController@conversaciones');
$router->get('/personal/conversaciones/:id/mensajes', 'PersonalController@mensajes');
$router->post('/personal/conversaciones/:id/mensajes', 'PersonalController@responder');
$router->put('/personal/conversaciones/:id/estado', 'PersonalController@cambiarEstado');
