<?php

namespace App\Controllers;

use App\Models\AlumnoCategoriaModel;
use App\Models\AlumnoModel;
use App\Models\AlumnoNivelModel;
use App\Models\CategoriaModel;
use App\Models\ProgresoModel;
use CodeIgniter\RESTful\ResourceController;

class GestionAlumnos extends ResourceController
{
    protected $format = 'json';

    private const NIVELES = ['A1', 'A2', 'B1', 'B2', 'C1'];

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

    /**
     * GET /api/alumnos/{id}/progreso (solo docente)
     * Devuelve el resumen por categoría + el historial cronológico de tests con fecha y hora.
     */
    public function progreso($alumnoId = null)
    {
        $alumnoId = (int) $alumnoId;

        if (! model(AlumnoModel::class)->find($alumnoId)) {
            return $this->failNotFound('El alumno no existe.');
        }

        $nivel       = model(AlumnoNivelModel::class)->nivelDe($alumnoId);
        $habilitadas = model(AlumnoCategoriaModel::class)->habilitadasDe($alumnoId);
        $progresos   = model(ProgresoModel::class)->deAlumno($alumnoId);

        $mapaProgreso = [];
        foreach ($progresos as $p) {
            $mapaProgreso[(int) $p['categoria_id']] = $p;
        }

        $categorias = model(CategoriaModel::class)
            ->select('id, nombre, nivel, orden')
            ->where('estado', 1)
            ->where('nivel', $nivel)
            ->orderBy('orden', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($categorias as &$c) {
            $catId = (int) $c['id'];
            $c['id']         = $catId;
            $c['orden']      = (int) $c['orden'];
            $c['habilitada'] = in_array($catId, $habilitadas, true);

            if (isset($mapaProgreso[$catId])) {
                $aprobado = (int) $mapaProgreso[$catId]['test_aprobado'] === 1;
                $c['calificacion_test']    = $mapaProgreso[$catId]['calificacion_test'];
                $c['calificacion_cerrada'] = $mapaProgreso[$catId]['calificacion_cerrada'];
                $c['test_aprobado']        = $aprobado ? 1 : 0;
                $c['estado_test']          = $mapaProgreso[$catId]['calificacion_test'] !== null
                    ? ($aprobado ? 'Aprobada' : 'Reprobada')
                    : 'Sin presentar';
            } else {
                $c['calificacion_test']    = null;
                $c['calificacion_cerrada'] = null;
                $c['test_aprobado']        = 0;
                $c['estado_test']          = 'Sin presentar';
            }
        }
        unset($c);

        // Historial cronológico de intentos con fecha, hora y categoría
        $db = db_connect();
        $historial = $db->table('calificaciones cal')
            ->select('cal.id, cal.nivel, cal.puntuacion, cal.tipo_prueba, cal.created_at, cat.nombre AS categoria_nombre')
            ->join('categorias cat', 'cat.id = cal.categoria_id', 'left')
            ->where('cal.alumno_id', $alumnoId)
            ->orderBy('cal.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status'    => 200,
            'nivel'     => $nivel,
            'data'      => $categorias,
            'historial' => $historial,
        ]);
    }

    /**
     * PUT /api/alumnos/{id}/calificacion-final (solo docente)
     * Recibe {"calificaciones": [{"categoria_id": 1, "calificacion": 8.5}, ...]}
     * y guarda la calificación cerrada por categoría.
     */
    public function asignarCalificacionFinal($alumnoId = null)
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

        if (! is_array($datos) || ! isset($datos['calificaciones']) || ! is_array($datos['calificaciones'])) {
            return $this->fail('Envía la lista de calificaciones por categoría.', 400);
        }

        $progresoModel = model(ProgresoModel::class);

        foreach ($datos['calificaciones'] as $item) {
            $catId = (int) ($item['categoria_id'] ?? 0);
            if ($catId <= 0) {
                continue;
            }

            $valor = $item['calificacion'] ?? null;
            if ($valor === '' || $valor === null) {
                $progresoModel->guardarCalificacionCerrada($alumnoId, $catId, null);
                continue;
            }

            if (! is_numeric($valor) || (float) $valor < 0 || (float) $valor > 10) {
                return $this->fail('Cada calificación cerrada debe estar entre 0 y 10.', 400);
            }

            $progresoModel->guardarCalificacionCerrada($alumnoId, $catId, round((float) $valor, 1));
        }

        return $this->respond([
            'status'  => 200,
            'message' => 'Calificaciones por categoría guardadas.',
        ]);
    }
}