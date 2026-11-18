<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\CategoriaModel;
use CodeIgniter\RESTful\ResourceController;

class Categorias extends ResourceController
{
    protected $format = 'json';

    /**
     * GET /api/categorias
     * Alumno: categorías activas de su nivel, con su estado de bloqueo.
     * Docente: todas las categorías.
     * El id del alumno sale del token, no de la petición.
     */
    public function index()
    {
        $payload  = (new JwtService())->desdeRequest($this->request);
        $alumnoId = ($payload->rol ?? '') === 'alumno' ? (int) $payload->sub : null;

        return $this->respond([
            'status' => 200,
            'data'   => model(CategoriaModel::class)->conBloqueo($alumnoId),
        ]);
    }
}