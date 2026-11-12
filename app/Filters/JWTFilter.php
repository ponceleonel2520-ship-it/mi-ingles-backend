<?php

namespace App\Filters;

use App\Libraries\JwtService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Uso en Routes.php:
 *   ['filter' => 'jwt']          -> cualquier usuario con token válido
 *   ['filter' => 'jwt:docente']  -> solo el rol indicado (se pueden separar varios con coma)
 */
class JWTFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $payload = $this->payloadDe($request);

        // Sin token, token inválido o expirado -> 401 (el frontend cierra la sesión)
        if ($payload === null) {
            return $this->error(401, 'Token no proporcionado, inválido o expirado');
        }

        // Si la ruta pide un rol (jwt:docente), el rol del token debe coincidir -> 403
        if (! empty($arguments) && ! in_array((string) ($payload->rol ?? ''), $arguments, true)) {
            return $this->error(403, 'No tienes permiso para realizar esta acción');
        }

        $GLOBALS['userData'] = $payload;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No hace falta nada después de la petición
    }

    private function payloadDe(RequestInterface $request): ?object
    {
        $jwt     = new JwtService();
        $payload = $jwt->desdeRequest($request);

        if ($payload !== null) {
            return $payload;
        }

        // Respaldo: algunos Apache entregan el encabezado como variable de servidor
        $header = (string) $request->getServer('HTTP_AUTHORIZATION');
        if ($header === '') {
            return null;
        }

        try {
            return $jwt->validar(trim((string) preg_replace('/^Bearer\s+/i', '', $header)));
        } catch (\Throwable) {
            return null;
        }
    }

    private function error(int $codigo, string $mensaje): ResponseInterface
    {
        $respuesta = service('response')
            ->setStatusCode($codigo)
            ->setJSON([
                'status'   => $codigo,
                'error'    => $codigo,
                'messages' => ['error' => $mensaje],
            ]);

        // Cuando un filtro responde antes del controlador, CORS se agrega a mano
        $respuesta->setHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $respuesta->setHeader('Access-Control-Allow-Headers', 'X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization');
        $respuesta->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE');

        return $respuesta;
    }
}
