<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\AlumnoCategoriaModel;
use App\Models\AlumnoNivelModel;
use App\Models\CalificacionModel;
use App\Models\CategoriaModel;
use App\Models\ProgresoModel;
use CodeIgniter\RESTful\ResourceController;

class Progreso extends ResourceController
{
    protected $format = 'json';

    /** Calificación mínima (de 0 a 100) para aprobar el test de una categoría. */
    private const PUNTAJE_APROBATORIO = 80;

    /** GET /api/progreso (solo alumno): su avance en cada categoría. */
    public function obtener()
    {
        return $this->respond([
            'status' => 200,
            'data'   => model(ProgresoModel::class)->deAlumno($this->alumnoId()),
        ]);
    }

    /**
     * POST /api/progreso (solo alumno)
     * Recibe categoria_id y calificacion (0 a 100). El servidor decide si aprobó
     * y el id del alumno sale del token.
     */
    public function guardar()
    {
        $alumnoId = $this->alumnoId();

        try {
            $datos = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            $datos = null;
        }

        if (! is_array($datos)) {
            $datos = $this->request->getPost() ?: [];
        }

        $reglas = [
            'categoria_id' => 'required|is_natural_no_zero',
            'calificacion' => 'required|numeric|greater_than_equal_to[0]|less_than_equal_to[100]',
        ];

        if (! $this->validateData($datos, $reglas)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 400);
        }

        $categoriaId = (int) $datos['categoria_id'];
        $categoria   = model(CategoriaModel::class)->find($categoriaId);

        if (! $categoria) {
            return $this->failNotFound('La categoría no existe.');
        }

        $nivel = model(AlumnoNivelModel::class)->nivelDe($alumnoId);

        if (
            (int) $categoria['estado'] !== 1
            || $categoria['nivel'] !== $nivel
            || ! model(AlumnoCategoriaModel::class)->estaHabilitada($alumnoId, $categoriaId)
        ) {
            return $this->failForbidden('No tienes habilitada esta categoría.');
        }

        $calificacion = round((float) $datos['calificacion'], 2);
        $aprobado     = $calificacion >= self::PUNTAJE_APROBATORIO;

        model(ProgresoModel::class)->registrar($alumnoId, $categoriaId, $aprobado, $calificacion);

        model(CalificacionModel::class)->insert([
            'alumno_id'   => $alumnoId,
            'nivel'       => $nivel,
            'puntuacion'  => $calificacion,
            'tipo_prueba' => 'test',
        ]);

        return $this->respondCreated([
            'status'  => 201,
            'message' => $aprobado ? 'Test aprobado.' : 'Test registrado. No alcanzaste el puntaje mínimo.',
            'data'    => [
                'test_aprobado' => $aprobado ? 1 : 0,
                'calificacion'  => $calificacion,
            ],
        ]);
    }

    private function alumnoId(): int
    {
        $payload = (new JwtService())->desdeRequest($this->request);

        return (int) ($payload->sub ?? 0);
    }
}