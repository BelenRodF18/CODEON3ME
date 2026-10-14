/* Fragmentos de HTML reutilizables en los paneles. */

// Escapa el texto antes de insertarlo en el HTML.
const escapar = (valor) => String(valor ?? '').replace(/[&<>'"]/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
}[caracter]));

// Etiqueta de color (activo, inactivo, info...).
const badge = (texto, variante) =>
    '<span class="fp-badge fp-badge--' + variante + '">' + escapar(texto) + '</span>';

// Mensaje de "no hay datos" con ícono.
const vacio = (titulo, texto, icono = '○') =>
    '<div class="fp-empty"><div class="fp-empty-icono" aria-hidden="true">' + icono + '</div>' +
    '<strong>' + escapar(titulo) + '</strong><p>' + escapar(texto) + '</p></div>';

// Filas grises mientras carga.
const cargando = (filas = 4) =>
    '<div class="fp-skeleton" aria-busy="true" aria-label="Cargando">' +
    '<div class="fp-skeleton-fila"></div>'.repeat(filas) + '</div>';

// Marca un botón como ocupado mientras se guarda.
async function conCarga(boton, tarea) {
    if (boton) boton.dataset.cargando = '1';
    try {
        return await tarea();
    } finally {
        if (boton) delete boton.dataset.cargando;
    }
}
