<?php

namespace App\Controllers;

use App\Models\AlumnoCategoriaModel;
use App\Models\AlumnoModel;
use App\Models\AlumnoNivelModel;
use App\Models\CategoriaModel;
use CodeIgniter\RESTful\ResourceController;

class GestionAlumnos extends ResourceController
{
    protected $format = 'json';

    private const NIVELES = ['A1', 'A2', 'B1', 'B2', 'C1'];

    /**
     * GET /api/alumnos/{id}/categorias (solo docente)
     * Categorías activas del nivel del alumno, indicando cuáles tiene habilitadas.
     */
    public function categorias($alumnoId = null)
    {
        $alumnoId = (int) $alumnoId;

        if (! model(AlumnoModel::class)->find($alumnoId)) {
            return $this->failNotFound('El alumno no existe.');
        }

        $nivel       = model(AlumnoNivelModel::class)->nivelDe($alumnoId);
        $habilitadas = model(AlumnoCategoriaModel::class)->habilitadasDe($alumnoId);

        $categorias = model(CategoriaModel::class)
            ->select('id, nombre, nivel, orden')
            ->where('estado', 1)
            ->where('nivel', $nivel)
            ->orderBy('orden', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($categorias as &$c) {
            $c['id']         = (int) $c['id'];
            $c['orden']      = (int) $c['orden'];
            $c['habilitada'] = in_array($c['id'], $habilitadas, true);
        }
        unset($c);

        return $this->respond([
            'status' => 200,
            'nivel'  => $nivel,
            'data'   => $categorias,
        ]);
    }

    /**
     * PUT /api/alumnos/{id}/categorias (solo docente)
     * Recibe {"categorias": [1, 2]} y deja habilitadas exactamente esas.
     */
    public function asignarCategorias($alumnoId = null)
    {
        $alumnoId = (int) $alumnoId;

        if (! model(AlumnoModel::class)->find($alumnoId)) {
            return $this->failNotFound('El alumno no existe.');
        }

        try {
            $datos = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            $datos = null;
        }

        if (! is_array($datos) || ! isset($datos['categorias']) || ! is_array($datos['categorias'])) {
            return $this->fail('Envía la lista de categorías en el campo "categorias".', 400);
        }

        $ids   = array_values(array_unique(array_map('intval', $datos['categorias'])));
        $nivel = model(AlumnoNivelModel::class)->nivelDe($alumnoId);

        // Solo se pueden habilitar categorías activas del nivel del alumno
        if ($ids !== []) {
            $validas = model(CategoriaModel::class)
                ->select('id')
                ->whereIn('id', $ids)
                ->where('estado', 1)
                ->where('nivel', $nivel)
                ->findAll();

            if (count($validas) !== count($ids)) {
                return $this->fail('Alguna categoría no existe, está inactiva o no es del nivel del alumno.', 400);
            }
        }

        $db = db_connect();
        $db->transStart();
        $db->table('alumno_categoria')->where('alumno_id', $alumnoId)->delete();

        foreach ($ids as $id) {
            $db->table('alumno_categoria')->insert([
                'alumno_id'    => $alumnoId,
                'categoria_id' => $id,
            ]);
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->failServerError('No se pudieron guardar las categorías.');
        }

        return $this->respond([
            'status'  => 200,
            'message' => 'Categorías actualizadas.',
        ]);
    }

    /**
     * POST /api/alumnos/{id}/promover (solo docente)
     * Sube al alumno un nivel (A1 -> A2 -> B1 -> B2 -> C1). Nunca es automático.
     */
    public function promover($alumnoId = null)
    {
        $alumnoId = (int) $alumnoId;
        $alumno   = model(AlumnoModel::class)->find($alumnoId);

        if (! $alumno) {
            return $this->failNotFound('El alumno no existe.');
        }

        $niveles = model(AlumnoNivelModel::class);
        $actual  = $niveles->nivelDe($alumnoId);
        $pos     = array_search($actual, self::NIVELES, true);

        if ($pos === false || $pos >= count(self::NIVELES) - 1) {
            return $this->fail('El alumno ya está en el nivel más alto (C1).', 400);
        }

        $nuevo = self::NIVELES[$pos + 1];
        $fila  = $niveles->where('alumno_id', $alumnoId)->first();

        if ($fila) {
            $niveles->update($fila['id_alumno_nivel'], ['nivel' => $nuevo]);
        } else {
            $niveles->insert([
                'alumno_id' => $alumnoId,
                'matricula' => $alumno['matricula'],
                'nivel'     => $nuevo,
            ]);
        }

        return $this->respond([
            'status'  => 200,
            'message' => 'Alumno promovido a ' . $nuevo . '.',
            'data'    => ['nivel' => $nuevo],
        ]);
    }
}