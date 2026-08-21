<?php
// api/routes.php
// Acá se anota: "cuando pidan ESTA url, ejecutá ESTE controlador@método"

$router->get('/estado', 'EstadoController@ver');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/me', 'AuthController@me');
