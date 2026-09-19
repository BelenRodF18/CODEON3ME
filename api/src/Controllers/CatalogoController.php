<?php
namespace App\Controllers;

use App\Models\ProgramaModel;

/** Programas y planes para la web pública (no requiere sesión). */
class CatalogoController extends BaseController {
    private $catalogo;

    /** Prepara el modelo de programas. */
    public function __construct($db) {
        parent::__construct($db);
        $this->catalogo = new ProgramaModel($db);
    }

    /** GET /programas: lista de programas con su entrenador. */
    public function programas() {
        $this->exito(['programas' => $this->catalogo->programas()]);
    }

    /** GET /programas/:slug: un programa con su coach y los planes que lo incluyen. */
    public function programa($slug) {
        $programa = $this->catalogo->programaPorSlug((string) $slug);
        if (!$programa) {
            return $this->error('Programa no encontrado', 404);
        }

        $this->exito(['programa' => $programa]);
    }

    /** GET /planes: planes activos con los programas que incluyen. */
    public function planes() {
        $this->exito(['planes' => $this->catalogo->planes()]);
    }

    /** GET /planes/:slug: un plan con el detalle de sus programas. */
    public function plan($slug) {
        $plan = $this->catalogo->planPorSlug((string) $slug);
        if (!$plan) {
            return $this->error('Plan no encontrado', 404);
        }

        $this->exito(['plan' => $plan]);
    }
}
