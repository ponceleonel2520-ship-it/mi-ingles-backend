<?php

namespace App\Models;

use CodeIgniter\Model;

class ProgresoModel extends Model
{
    protected $table         = 'progreso_usuario';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['alumno_id', 'categoria_id', 'test_aprobado', 'calificacion_test'];
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

    /** Avance del alumno en cada categoría (la app lo usa para sincronizarse). */
    public function deAlumno(int $alumnoId): array
    {
        $filas = $this->select('categoria_id, test_aprobado, calificacion_test')
            ->where('alumno_id', $alumnoId)
            ->findAll();

        foreach ($filas as &$f) {
            $f['categoria_id']      = (int) $f['categoria_id'];
            $f['test_aprobado']     = (int) $f['test_aprobado'];
            $f['calificacion_test'] = $f['calificacion_test'] !== null ? (float) $f['calificacion_test'] : null;
        }
        unset($f);

        return $filas;
    }

    /**
     * Guarda el resultado de un test. Una aprobación ya lograda no se quita
     * y la calificación guardada es la más alta.
     */
    public function registrar(int $alumnoId, int $categoriaId, bool $aprobado, float $calificacion): void
    {
        $fila = $this->where('alumno_id', $alumnoId)
            ->where('categoria_id', $categoriaId)
            ->first();

        if ($fila) {
            $cambios = [];

            if ($aprobado && ! (int) $fila['test_aprobado']) {
                $cambios['test_aprobado'] = 1;
            }

            if ($fila['calificacion_test'] === null || $calificacion > (float) $fila['calificacion_test']) {
                $cambios['calificacion_test'] = $calificacion;
            }

            if ($cambios !== []) {
                $this->update($fila['id'], $cambios);
            }

            return;
        }

        $this->insert([
            'alumno_id'         => $alumnoId,
            'categoria_id'      => $categoriaId,
            'test_aprobado'     => $aprobado ? 1 : 0,
            'calificacion_test' => $calificacion,
        ]);
    }
}