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

    /**
     * POST /api/categorias (solo docente)
     * Recibe nombre y nivel (A1 a C1). El orden es opcional: si no viene,
     * la categoría se agrega al final.
     */
    public function create()
    {
        try {
            $datos = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            $datos = null;
        }

        if (! is_array($datos)) {
            $datos = $this->request->getPost() ?: [];
        }

        $reglas = [
            'nombre' => 'required|max_length[100]',
            'nivel'  => 'required|in_list[A1,A2,B1,B2,C1]',
            'orden'  => 'permit_empty|is_natural_no_zero',
        ];

        $mensajes = [
            'nombre' => [
                'required'   => 'Escribe el nombre de la categoría.',
                'max_length' => 'El nombre no puede superar 100 caracteres.',
            ],
            'nivel' => [
                'required' => 'Selecciona un nivel.',
                'in_list'  => 'El nivel debe ser A1, A2, B1, B2 o C1.',
            ],
            'orden' => [
                'is_natural_no_zero' => 'El orden debe ser un número mayor que cero.',
            ],
        ];

        if (! $this->validateData($datos, $reglas, $mensajes)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 400);
        }

        $modelo = model(CategoriaModel::class);
        $orden  = $datos['orden'] ?? '';

        if ($orden === '' || $orden === null) {
            $maximo = $modelo->selectMax('orden')->first();
            $orden  = (int) ($maximo['orden'] ?? 0) + 1;
        }

        $id = $modelo->insert([
            'nombre' => trim((string) $datos['nombre']),
            'nivel'  => $datos['nivel'],
            'orden'  => (int) $orden,
            'estado' => 1,
        ]);

        if (! $id) {
            return $this->failServerError('No se pudo guardar la categoría.');
        }

        return $this->respondCreated([
            'status'  => 201,
            'id'      => (int) $id,
            'message' => 'Categoría creada correctamente.',
        ]);
    }
}