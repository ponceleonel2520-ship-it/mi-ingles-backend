<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\AlumnoModel;
use App\Models\DocenteModel;
use CodeIgniter\RESTful\ResourceController;

class Auth extends ResourceController
{
    protected $format = 'json';

    /**
     * POST /auth/login
     * Recibe (JSON o formulario): username, password, tipo ('alumno' | 'docente').
     * username = matrícula para alumno, clave_docente para docente.
     */
    public function login()
    {
        $username = trim((string) $this->request->getVar('username'));
        $password = (string) $this->request->getVar('password');
        $tipo     = (string) $this->request->getVar('tipo');

        if ($username === '' || $password === '' || $tipo === '') {
            return $this->fail('Faltan credenciales o el tipo de usuario', 400);
        }

        if ($tipo === 'docente') {
            $usuario = model(DocenteModel::class)->verifica($username, $password);
            $id      = $usuario ? (int) $usuario['id_docente'] : 0;
        } elseif ($tipo === 'alumno') {
            $usuario = model(AlumnoModel::class)->verifica($username, $password);
            $id      = $usuario ? (int) $usuario['id'] : 0;
        } else {
            return $this->fail('Rol no válido', 400);
        }

        if (! $usuario) {
            return $this->failUnauthorized('Credenciales inválidas. Verifica tu usuario y contraseña.');
        }

        // sub = id real en la base de datos, rol = alumno | docente
        $token = (new JwtService())->crear(['sub' => $id, 'rol' => $tipo]);

        return $this->respond([
            'status'  => 200,
            'message' => 'Login exitoso',
            'token'   => $token,
            'rol'     => $tipo,
            'id'      => $id,
            'nombre'  => $usuario['nombre_completo'],
        ]);
    }
}
