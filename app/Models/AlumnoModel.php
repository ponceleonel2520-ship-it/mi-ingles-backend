<?php

namespace App\Models;

use CodeIgniter\Model;

class AlumnoModel extends Model
{
    protected $table         = 'alumnos';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre_completo', 'matricula', 'correo', 'carrera', 'contrasena', 'hash'];

    public function listaSegura(): array
    {
        return $this->select('id, nombre_completo, matricula, correo, carrera')
            ->orderBy('nombre_completo', 'ASC')
            ->findAll();
    }

    /**
     * Verifica matrícula y contraseña.
     * El hash de la contraseña se guarda en la columna `hash` (varchar 200).
     * La columna `contrasena` es varchar(50) y un hash de bcrypt mide 60, por eso no se usa.
     *
     * @return array<string, mixed>|false
     */
    public function verifica(string $matricula, string $password): array|false
    {
        if (! ctype_digit($matricula)) {
            return false;
        }

        $alumno = $this->where('matricula', $matricula)->first();

        if (! $alumno || ($alumno['hash'] ?? '') === '') {
            return false;
        }

        return password_verify($password, $alumno['hash']) ? $alumno : false;
    }
    

}
