<?php

namespace App\Models;

use CodeIgniter\Model;

class DocenteModel extends Model
{
    protected $table         = 'docente';
    protected $primaryKey    = 'id_docente';
    protected $returnType    = 'array';
    protected $allowedFields = ['clave_docente', 'nombre_completo', 'password', 'hash'];

    /**
     * Verifica clave y contraseña.
     * El hash se guarda en `hash`; la columna `password` es varchar(12) y queda vacía.
     *
     * @return array<string, mixed>|false
     */
    public function verifica(string $clave, string $password): array|false
    {
        $docente = $this->where('clave_docente', $clave)->first();

        if (! $docente || ($docente['hash'] ?? '') === '') {
            return false;
        }

        return password_verify($password, $docente['hash']) ? $docente : false;
    }
}
