<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

$routes->get('/', static function () {
    return response()->setJSON(['status' => 'API de English Cards funcionando correctamente']);
});

// Preflight de CORS (el filtro cors responde, pero la ruta tiene que existir)
$routes->options('(:any)', static function () {});

// Login unificado (alumno y docente)
$routes->post('auth/login', 'Auth::login');

// Registro público (web y app Android)
$routes->post('api/alumno', 'Alumnos::create');
$routes->post('api/docente', 'Docente::create');

// API protegida con JWT
$routes->get('api/materiales', 'Materiales::index', ['filter' => 'jwt']);
$routes->post('api/materiales', 'Materiales::create', ['filter' => 'jwt:docente']);
$routes->get('api/categorias', 'Categorias::index', ['filter' => 'jwt']);
$routes->get('api/alumnos', 'Alumnos::index', ['filter' => 'jwt:docente']);
$routes->get('api/calificaciones', 'Calificaciones::index', ['filter' => 'jwt']);

// Pendiente: rutas de la app móvil. Esos controladores (Api\Flashcards, Api\Evaluaciones)
// todavía no existen en el repo; actívalas cuando los crees.
// $routes->group('api/movil', ['filter' => 'jwt'], static function ($routes) {
//     $routes->get('flashcards/diarias', 'Api\Flashcards::getDiarias');
//     $routes->post('evaluaciones/guardar', 'Api\Evaluaciones::guardarPuntuacion');
// });
