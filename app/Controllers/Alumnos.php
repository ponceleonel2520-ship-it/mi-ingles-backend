<?php

namespace App\Controllers;

use App\Models\AlumnoModel;
use CodeIgniter\RESTful\ResourceController;

class Alumnos extends ResourceController
{
    protected $format = 'json';

    /** GET /api/alumnos (solo docente). Nunca incluye contraseña ni hash. */
    public function index()
    {
        return $this->respond(model(AlumnoModel::class)->listaSegura());
    }

    /**
     * POST /api/alumno  (registro público: web y app Android)
     * Recibe JSON o formulario: nombre, matricula, correo, carrera, contrasena.
     */
    public function create()
    {
        $datos = $this->datosEntrada();

        $reglas = [
            'nombre'     => 'required|max_length[150]',
            'matricula'  => 'required|regex_match[/^[0-9]{9}$/]|is_unique[alumnos.matricula]',
            'correo'     => 'required|valid_email|max_length[100]|is_unique[alumnos.correo]',
            'carrera'    => 'required|max_length[100]',
            'contrasena' => 'required|min_length[6]|max_length[72]',
        ];

        $mensajes = [
            'nombre'     => ['required' => 'Escribe tu nombre completo.', 'max_length' => 'El nombre no puede superar 150 caracteres.'],
            'matricula'  => [
                'required'    => 'Escribe tu matrícula.',
                'regex_match' => 'La matrícula debe tener 9 dígitos.',
                'is_unique'   => 'Esa matrícula ya está registrada.',
            ],
            'correo'     => [
                'required'    => 'Escribe tu correo.',
                'valid_email' => 'El correo no es válido.',
                'max_length'  => 'El correo no puede superar 100 caracteres.',
                'is_unique'   => 'Ese correo ya está registrado.',
            ],
            'carrera'    => ['required' => 'Selecciona tu carrera.', 'max_length' => 'La carrera no puede superar 100 caracteres.'],
            'contrasena' => [
                'required'   => 'Escribe una contraseña.',
                'min_length' => 'La contraseña debe tener al menos 6 caracteres.',
                'max_length' => 'La contraseña no puede superar 72 caracteres.',
            ],
        ];

        if (! $this->validateData($datos, $reglas, $mensajes)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 400);
        }

        try {
            model(AlumnoModel::class)->insert([
                'nombre_completo' => $datos['nombre'],
                'matricula'       => $datos['matricula'],
                'correo'          => strtolower($datos['correo']),
                'carrera'         => $datos['carrera'],
                'contrasena'      => '', // columna varchar(50): no cabe un hash de bcrypt (60)
                'hash'            => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
            ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') !== false) {
                return $this->failResourceExists('La matrícula o el correo ya están registrados.');
            }
            log_message('error', 'Registro de alumno: ' . $e->getMessage());

            return $this->failServerError('No se pudo guardar el registro. Intenta de nuevo.');
        }

        return $this->respondCreated([
            'status'  => 201,
            'message' => 'Registro exitoso. Ya puedes iniciar sesión.',
        ]);
    }

    /** Lee JSON (app Android / React) o formulario. Recorta espacios salvo en la contraseña. */
    private function datosEntrada(): array
    {
        try {
            $crudo = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            $crudo = null;
        }

        if (! is_array($crudo)) {
            $crudo = $this->request->getPost() ?? [];
        }

        $limpio = [];
        foreach ($crudo as $clave => $valor) {
            $texto          = is_scalar($valor) ? (string) $valor : '';
            $limpio[$clave] = $clave === 'contrasena' ? $texto : trim($texto);
        }

        return $limpio;
    }
}
