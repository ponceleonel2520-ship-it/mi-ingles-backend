<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\ConfiguracionAlumnoModel;
use CodeIgniter\RESTful\ResourceController;

class Configuracion extends ResourceController
{
    protected $format = 'json';

    /** GET /api/configuracion (solo alumno). El id sale del token. */
    public function obtener()
    {
        $alumnoId = $this->alumnoId();

        return $this->respond([
            'status' => 200,
            'data'   => model(ConfiguracionAlumnoModel::class)->de($alumnoId),
        ]);
    }

    /** PUT /api/configuracion (solo alumno). Recibe frecuencia_test, hora_inicio y hora_fin. */
    public function guardar()
    {
        $alumnoId = $this->alumnoId();

        try {
            $datos = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            $datos = null;
        }

        if (! is_array($datos)) {
            $datos = $this->request->getRawInput() ?: $this->request->getPost() ?: [];
        }

        $hora = 'required|regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]';

        $reglas = [
            'frecuencia_test' => 'required|in_list[semanal,mensual]',
            'hora_inicio'     => $hora,
            'hora_fin'        => $hora,
        ];

        $mensajes = [
            'frecuencia_test' => ['in_list' => 'La frecuencia debe ser semanal o mensual.'],
            'hora_inicio'     => ['regex_match' => 'La hora de inicio debe tener el formato HH:MM.'],
            'hora_fin'        => ['regex_match' => 'La hora de fin debe tener el formato HH:MM.'],
        ];

        if (! $this->validateData($datos, $reglas, $mensajes)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 400);
        }

        if ($datos['hora_inicio'] >= $datos['hora_fin']) {
            return $this->fail('La hora de inicio debe ser anterior a la hora de fin.', 400);
        }

        $modelo = model(ConfiguracionAlumnoModel::class);
        $modelo->guardar($alumnoId, $datos['frecuencia_test'], $datos['hora_inicio'], $datos['hora_fin']);

        return $this->respond([
            'status'  => 200,
            'message' => 'Configuración guardada.',
            'data'    => $modelo->de($alumnoId),
        ]);
    }

    private function alumnoId(): int
    {
        $payload = (new JwtService())->desdeRequest($this->request);

        return (int) ($payload->sub ?? 0);
    }
}