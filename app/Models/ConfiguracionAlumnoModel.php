<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfiguracionAlumnoModel extends Model
{
    protected $table         = 'configuracion_alumno';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['alumno_id', 'frecuencia_test', 'hora_inicio', 'hora_fin'];

    /** Configuración del alumno. Si todavía no tiene, se crea con los valores por defecto. */
    public function de(int $alumnoId): array
    {
        $fila = $this->filaDe($alumnoId);

        return [
            'frecuencia_test' => $fila['frecuencia_test'],
            'hora_inicio'     => substr($fila['hora_inicio'], 0, 5),
            'hora_fin'        => substr($fila['hora_fin'], 0, 5),
        ];
    }

    public function guardar(int $alumnoId, string $frecuencia, string $inicio, string $fin): void
    {
        $fila = $this->filaDe($alumnoId);

        $this->update($fila['id'], [
            'frecuencia_test' => $frecuencia,
            'hora_inicio'     => $inicio,
            'hora_fin'        => $fin,
        ]);
    }

    private function filaDe(int $alumnoId): array
    {
        $fila = $this->where('alumno_id', $alumnoId)->first();

        if (! $fila) {
            $this->insert(['alumno_id' => $alumnoId]);
            $fila = $this->where('alumno_id', $alumnoId)->first();
        }

        return $fila;
    }
}