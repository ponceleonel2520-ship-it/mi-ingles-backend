<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialModel extends Model
{
    protected $table         = 'materiales';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['categoria_id', 'nombre', 'traduccion', 'imagen', 'nivel'];
    protected $useTimestamps = true;

    /** Materiales junto con el nombre de su categoría (para la web). */
    public function conCategoria(): array
    {
        $filas = $this->select('materiales.id, materiales.nombre, materiales.traduccion, materiales.imagen, '
                . 'materiales.nivel, materiales.categoria_id, categorias.nombre AS categoria')
            ->join('categorias', 'categorias.id = materiales.categoria_id')
            ->orderBy('materiales.id', 'DESC')
            ->findAll();

        foreach ($filas as &$f) {
            $f['imagen'] = $this->urlPublica($f['imagen']);
        }
        unset($f);

        return $filas;
    }

    /** Flashcards de una categoría con los nombres de campo que espera Android. */
    public function flashcardsPorCategoria(int $categoriaId): array
    {
        $filas = $this->select('id, categoria_id, nombre AS palabra_ingles, traduccion, imagen AS imagen_url')
            ->where('categoria_id', $categoriaId)
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($filas as &$f) {
            $f['id']           = (int) $f['id'];
            $f['categoria_id'] = (int) $f['categoria_id'];
            $f['imagen_url']   = $this->urlPublica($f['imagen_url']);
        }
        unset($f);

        return $filas;
    }

    /** La base guarda la ruta relativa; aquí se arma la URL completa. */
    private function urlPublica(?string $ruta): string
    {
        $ruta = (string) $ruta;

        if ($ruta === '' || preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }

        return base_url($ruta);
    }
}