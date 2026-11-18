<?php

namespace App\Models;

use CodeIgniter\Model;

class AlumnoCategoriaModel extends Model
{
    protected $table         = 'alumno_categoria';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['alumno_id', 'categoria_id'];

    /** Ids de las categorías que el docente habilitó a este alumno. */
    public function habilitadasDe(int $alumnoId): array
    {
        $filas = $this->select('categoria_id')
            ->where('alumno_id', $alumnoId)
            ->findAll();

        return array_map('intval', array_column($filas, 'categoria_id'));
    }

    public function estaHabilitada(int $alumnoId, int $categoriaId): bool
    {
        return $this->where('alumno_id', $alumnoId)
            ->where('categoria_id', $categoriaId)
            ->countAllResults() > 0;
    }
}