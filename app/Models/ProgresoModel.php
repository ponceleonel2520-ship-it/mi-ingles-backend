<?php

namespace App\Models;

use CodeIgniter\Model;

class ProgresoModel extends Model
{
    protected $table         = 'progreso_usuario';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['alumno_id', 'categoria_id', 'test_aprobado'];
    protected $useTimestamps = true;

    /** Ids de las categorías cuyo test ya aprobó el alumno. */
    public function aprobadasDe(int $alumnoId): array
    {
        $filas = $this->select('categoria_id')
            ->where('alumno_id', $alumnoId)
            ->where('test_aprobado', 1)
            ->findAll();

        return array_map('intval', array_column($filas, 'categoria_id'));
    }

    /** Guarda el avance. Una aprobación ya lograda no se quita. */
    public function registrar(int $alumnoId, int $categoriaId, bool $aprobado): void
    {
        $fila = $this->where('alumno_id', $alumnoId)
            ->where('categoria_id', $categoriaId)
            ->first();

        if ($fila) {
            if ($aprobado && ! (int) $fila['test_aprobado']) {
                $this->update($fila['id'], ['test_aprobado' => 1]);
            }
            return;
        }

        $this->insert([
            'alumno_id'     => $alumnoId,
            'categoria_id'  => $categoriaId,
            'test_aprobado' => $aprobado ? 1 : 0,
        ]);
    }
}