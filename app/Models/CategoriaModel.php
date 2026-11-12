<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoriaModel extends Model
{
    protected $table         = 'categorias';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre', 'grado_dificultad', 'orden', 'estado'];
    protected $useTimestamps = true;

    /**
     * Categorías ordenadas con su estado de bloqueo.
     * - Alumno: solo las activas; la primera está libre y cada una se libera
     *   cuando aprobó el test de la anterior.
     * - Docente ($alumnoId = null): todas, sin bloqueo.
     */
    public function conBloqueo(?int $alumnoId): array
    {
        $this->select('id, nombre, grado_dificultad, orden, estado')
            ->orderBy('orden', 'ASC')
            ->orderBy('id', 'ASC');

        if ($alumnoId !== null) {
            $this->where('estado', 1);
        }

        $categorias = $this->findAll();
        $aprobadas  = $alumnoId !== null ? model(ProgresoModel::class)->aprobadasDe($alumnoId) : [];

        $liberada = true; // la primera categoría siempre está libre
        foreach ($categorias as &$c) {
            $c['id']               = (int) $c['id'];
            $c['grado_dificultad'] = (int) $c['grado_dificultad'];
            $c['orden']            = (int) $c['orden'];
            $c['estado']           = (int) $c['estado'];
            $c['aprobada']         = in_array($c['id'], $aprobadas, true);

            $c['bloqueada_por_defecto'] = $alumnoId !== null && ! $liberada;
            $liberada                   = $c['aprobada'];
        }
        unset($c);

        return $categorias;
    }
}