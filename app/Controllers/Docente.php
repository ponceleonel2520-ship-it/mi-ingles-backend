<?php

namespace App\Controllers;

use App\Models\DocenteModel;
use CodeIgniter\RESTful\ResourceController;

class Docente extends ResourceController
{
    protected $modelName = DocenteModel::class;
    protected $format    = 'json';

    public function index()
    {
        return $this->respond([
            'message' => 'Ruta de docente lista para usarse.',
        ]);
    }

    /**
     * POST /api/docente  (registro público: web y app Android)
     * Recibe JSON o formulario: nombre, clave, contrasena y, opcionalmente, codigo.
     *
     * Si en el .env defines DOCENTE_CODIGO=algo, el registro exige ese código;
     * así no cualquiera puede darse de alta como docente.
     */
    public function create()
    {
        $datos = $this->datosEntrada();

        $reglas = [
            'nombre'     => 'required|max_length[80]',
            'clave'      => 'required|alpha_numeric|exact_length[9]|is_unique[docente.clave_docente]',
            'contrasena' => 'required|min_length[6]|max_length[72]',
        ];

        $mensajes = [
            'nombre'     => ['required' => 'Escribe tu nombre completo.', 'max_length' => 'El nombre no puede superar 80 caracteres.'],
            'clave'      => [
                'required'      => 'Escribe tu clave de docente.',
                'alpha_numeric' => 'La clave solo puede tener letras y números.',
                'exact_length'  => 'La clave debe tener 9 caracteres.',
                'is_unique'     => 'Esa clave de docente ya está registrada.',
            ],
            'contrasena' => [
                'required'   => 'Escribe una contraseña.',
                'min_length' => 'La contraseña debe tener al menos 6 caracteres.',
                'max_length' => 'La contraseña no puede superar 72 caracteres.',
            ],
        ];

        if (! $this->validateData($datos, $reglas, $mensajes)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 400);
        }

        $codigoEsperado = (string) env('DOCENTE_CODIGO', '');
        if ($codigoEsperado !== '' && ! hash_equals($codigoEsperado, (string) ($datos['codigo'] ?? ''))) {
            return $this->failForbidden('El código de registro de docente es incorrecto.');
        }

        try {
            $this->model->insert([
                'clave_docente'   => $datos['clave'],
                'nombre_completo' => $datos['nombre'],
                'password'        => '', // columna varchar(12): no cabe un hash de bcrypt (60)
                'hash'            => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
            ]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') !== false) {
                return $this->failResourceExists('Esa clave de docente ya está registrada.');
            }
            log_message('error', 'Registro de docente: ' . $e->getMessage());

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
