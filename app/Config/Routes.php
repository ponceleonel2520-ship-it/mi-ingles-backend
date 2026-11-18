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

// Registro público: solo alumnos. Los docentes los da de alta el administrador en la base de datos.
$routes->post('api/alumno', 'Alumnos::create');

// API protegida con JWT
$routes->get('api/materiales', 'Materiales::index', ['filter' => 'jwt']);
$routes->post('api/materiales', 'Materiales::create', ['filter' => 'jwt:docente']);
$routes->get('api/categorias', 'Categorias::index', ['filter' => 'jwt']);
$routes->get('api/flashcards', 'Flashcards::index', ['filter' => 'jwt']);
$routes->get('api/configuracion', 'Configuracion::obtener', ['filter' => 'jwt:alumno']);
$routes->put('api/configuracion', 'Configuracion::guardar', ['filter' => 'jwt:alumno']);
$routes->get('api/progreso', 'Progreso::obtener', ['filter' => 'jwt:alumno']);
$routes->post('api/progreso', 'Progreso::guardar', ['filter' => 'jwt:alumno']);
$routes->get('api/alumnos', 'Alumnos::index', ['filter' => 'jwt:docente']);
$routes->get('api/alumnos/(:num)/categorias', 'GestionAlumnos::categorias/$1', ['filter' => 'jwt:docente']);
$routes->put('api/alumnos/(:num)/categorias', 'GestionAlumnos::asignarCategorias/$1', ['filter' => 'jwt:docente']);
$routes->post('api/alumnos/(:num)/promover', 'GestionAlumnos::promover/$1', ['filter' => 'jwt:docente']);
$routes->get('api/calificaciones', 'Calificaciones::index', ['filter' => 'jwt']);


// todavía no existen en el repo; actívalas cuando los crees.
// $routes->group('api/movil', ['filter' => 'jwt'], static function ($routes) {
//     $routes->get('flashcards/diarias', 'Api\Flashcards::getDiarias');
//     $routes->post('evaluaciones/guardar', 'Api\Evaluaciones::guardarPuntuacion');
// });
