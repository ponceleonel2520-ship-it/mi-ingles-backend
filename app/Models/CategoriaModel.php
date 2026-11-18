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
     * Categorías ordenadas con su estado de bloqueo.
     * - Alumno: solo las activas de su nivel. Quedan bloqueadas hasta que el
     *   docente se las habilite.
     * - Docente ($alumnoId = null): todas, sin bloqueo.
     */
    public function conBloqueo(?int $alumnoId): array
    {
        $this->select('id, nombre, grado_dificultad, nivel, orden, estado')
            ->orderBy('orden', 'ASC')
            ->orderBy('id', 'ASC');

        $habilitadas = [];
        $aprobadas   = [];

        if ($alumnoId !== null) {
            $nivel = model(AlumnoNivelModel::class)->nivelDe($alumnoId);

            $this->where('estado', 1)->where('nivel', $nivel);

            $habilitadas = model(AlumnoCategoriaModel::class)->habilitadasDe($alumnoId);
            $aprobadas   = model(ProgresoModel::class)->aprobadasDe($alumnoId);
        }

        $categorias = $this->findAll();

        foreach ($categorias as &$c) {
            $c['id']               = (int) $c['id'];
            $c['grado_dificultad'] = (int) $c['grado_dificultad'];
            $c['orden']            = (int) $c['orden'];
            $c['estado']           = (int) $c['estado'];
            $c['aprobada']         = in_array($c['id'], $aprobadas, true);

            $c['bloqueada_por_defecto'] = $alumnoId !== null
                && ! in_array($c['id'], $habilitadas, true);
        }
        unset($c);

        return $categorias;
    }
}