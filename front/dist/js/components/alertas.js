/* Alertas (toasts) de los paneles. */

const ICONOS = { exito: '✓', error: '✕', aviso: '!', info: 'i' };
let pilaToasts = null;

// Contenedor donde se apilan los avisos.
function contenedorToasts() {
    if (!pilaToasts || !document.body.contains(pilaToasts)) {
        pilaToasts = document.createElement('div');
        pilaToasts.className = 'fp-toasts';
        pilaToasts.setAttribute('role', 'status');
        pilaToasts.setAttribute('aria-live', 'polite');
        document.body.appendChild(pilaToasts);
    }
    return pilaToasts;
}

// Cierra un aviso con animación.
function cerrarToast(elemento) {
    if (!elemento || elemento.dataset.cerrando === '1') return;
    elemento.dataset.cerrando = '1';
    elemento.classList.add('fp-saliendo');
    setTimeout(() => elemento.remove(), 200);
}

// Muestra un aviso. tipo: 'exito', 'error', 'aviso' o 'info'.
function mostrarToast(opciones) {
    const { tipo = 'info', titulo = '', texto = '', duracion } = opciones || {};
    const elemento = document.createElement('div');
    elemento.className = 'fp-toast fp-toast--' + tipo;
    elemento.innerHTML =
        '<span class="fp-toast-icono" aria-hidden="true">' + ICONOS[tipo] + '</span>' +
        '<div><p class="fp-toast-titulo">' + escapar(titulo) + '</p>' +
        (texto ? '<p class="fp-toast-texto">' + escapar(texto) + '</p>' : '') + '</div>' +
        '<button type="button" class="fp-toast-cerrar" aria-label="Cerrar aviso">&times;</button>';

    // La cruz cierra el aviso.
    elemento.querySelector('.fp-toast-cerrar').addEventListener('click', () => cerrarToast(elemento));
    contenedorToasts().appendChild(elemento);

    const espera = duracion ?? (tipo === 'error' ? 6000 : 4000);
    const temporizador = setTimeout(() => cerrarToast(elemento), espera);
    elemento.addEventListener('mouseenter', () => clearTimeout(temporizador));

    return elemento;
}

// Aviso verde.
const avisoExito = (titulo, texto) => mostrarToast({ tipo: 'exito', titulo, texto });
// Aviso rojo.
const avisoError = (titulo, texto) => mostrarToast({ tipo: 'error', titulo, texto });
// Aviso amarillo.
const avisoAtencion = (titulo, texto) => mostrarToast({ tipo: 'aviso', titulo, texto });
// Aviso informativo.
const avisoInfo = (titulo, texto) => mostrarToast({ tipo: 'info', titulo, texto });
