<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriaModel extends Model
{
    protected $table         = 'categorias';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre', 'grado_dificultad', 'nivel', 'orden', 'estado'];
    protected $useTimestamps = true;

    /**
     * Categorías ordenadas con su estado de bloqueo y calificación cerrada por categoría.
     */
    public function conBloqueo(?int $alumnoId): array
    {
        $this->select('id, nombre, grado_dificultad, nivel, orden, estado')
            ->orderBy('orden', 'ASC')
            ->orderBy('id', 'ASC');

        $habilitadas = [];
        $aprobadas   = [];
        $cerradas    = [];

        if ($alumnoId !== null) {
            $nivel = model(AlumnoNivelModel::class)->nivelDe($alumnoId);

            $this->where('estado', 1)->where('nivel', $nivel);

            $habilitadas = model(AlumnoCategoriaModel::class)->habilitadasDe($alumnoId);
            $progreso    = model(ProgresoModel::class);
            $aprobadas   = $progreso->aprobadasDe($alumnoId);
            $cerradas    = $progreso->calificacionesCerradasDe($alumnoId);
        }

        $categorias = $this->findAll();

        foreach ($categorias as &$c) {
            $id = (int) $c['id'];
            $c['id']                   = $id;
            $c['grado_dificultad']     = (int) $c['grado_dificultad'];
            $c['orden']                = (int) $c['orden'];
            $c['estado']               = (int) $c['estado'];
            $c['aprobada']             = in_array($id, $aprobadas, true);
            $c['calificacion_cerrada'] = $cerradas[$id] ?? null;

            $c['bloqueada_por_defecto'] = $alumnoId !== null
                && ! in_array($id, $habilitadas, true);
        }
        unset($c);

        return $categorias;
    }
}