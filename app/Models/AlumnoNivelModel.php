<?php

namespace App\Models;

use CodeIgniter\Model;

class AlumnoNivelModel extends Model
{
    protected $table         = 'alumno_nivel';
    protected $primaryKey    = 'id_alumno_nivel';
    protected $returnType    = 'array';
    protected $allowedFields = ['alumno_id', 'matricula', 'nivel'];

    /** Nivel actual del alumno (A1 si todavía no tiene registro). */
    public function nivelDe(int $alumnoId): string
    {
        $fila = $this->where('alumno_id', $alumnoId)->first();

        return $fila['nivel'] ?? 'A1';
    }
}