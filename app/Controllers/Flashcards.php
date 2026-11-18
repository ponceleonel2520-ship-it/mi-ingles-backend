<?php

namespace App\Controllers;

use App\Libraries\JwtService;
use App\Models\AlumnoCategoriaModel;
use App\Models\AlumnoNivelModel;
use App\Models\CategoriaModel;
use App\Models\MaterialModel;
use CodeIgniter\RESTful\ResourceController;

class Flashcards extends ResourceController
{
    protected $format = 'json';

    /**
     * GET /api/flashcards?categoria_id=X
     * Alumno: solo si la categoría es de su nivel y el docente se la habilitó.
     * Docente: cualquier categoría.
     */
    public function index()
    {
        $categoriaId = (int) $this->request->getGet('categoria_id');

        if ($categoriaId <= 0) {
            return $this->failValidationErrors('El parámetro categoria_id es obligatorio');
        }

        $categoria = model(CategoriaModel::class)->find($categoriaId);

        if (! $categoria) {
            return $this->failNotFound('La categoría no existe.');
        }

        $payload = (new JwtService())->desdeRequest($this->request);

        if (($payload->rol ?? '') === 'alumno') {
            $alumnoId = (int) $payload->sub;
            $nivel    = model(AlumnoNivelModel::class)->nivelDe($alumnoId);

            if ((int) $categoria['estado'] !== 1 || $categoria['nivel'] !== $nivel) {
                return $this->failForbidden('Esta categoría no es de tu nivel.');
            }

            if (! model(AlumnoCategoriaModel::class)->estaHabilitada($alumnoId, $categoriaId)) {
                return $this->failForbidden('Tu docente aún no habilita esta categoría.');
            }
        }

        return $this->respond([
            'status' => 200,
            'data'   => model(MaterialModel::class)->flashcardsPorCategoria($categoriaId),
        ]);
    }
}