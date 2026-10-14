/* Modales de los paneles. */

let modalAbierto = null;

// Cierra el modal abierto y devuelve el resultado.
function cerrarModal(resultado) {
    if (!modalAbierto) return;
    const { backdrop, resolver, foco } = modalAbierto;
    modalAbierto = null;

    backdrop.classList.add('fp-saliendo');
    setTimeout(() => backdrop.remove(), 150);
    document.removeEventListener('keydown', alPresionarTecla, true);
    if (foco && document.body.contains(foco)) foco.focus();

    resolver(resultado);
}

// Teclado: Escape cierra y Tab no sale del modal.
function alPresionarTecla(evento) {
    if (!modalAbierto) return;

    if (evento.key === 'Escape') {
        evento.preventDefault();
        cerrarModal(null);
        return;
    }

    // Atrapar el tabulador dentro del modal.
    if (evento.key === 'Tab') {
        const focales = modalAbierto.backdrop.querySelectorAll(
            'button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [href]'
        );
        if (!focales.length) return;
        const primero = focales[0];
        const ultimo = focales[focales.length - 1];

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    }
}

// Abre un modal. Devuelve null si se cancela.
function abrirModal(opciones) {
    const config = {
        titulo: '',
        descripcion: '',
        cuerpo: '',
        aceptar: 'Aceptar',
        cancelar: 'Cancelar',
        peligro: false,
        ancho: false,
        alAbrir: null,
        alAceptar: null,
        ...opciones
    };

    // Si ya había uno abierto, se cierra sin resultado.
    if (modalAbierto) cerrarModal(null);

    return new Promise((resolver) => {
        const backdrop = document.createElement('div');
        backdrop.className = 'fp-backdrop';

        backdrop.innerHTML =
            '<div class="fp-modal' + (config.ancho ? ' fp-modal--ancho' : '') +
            (config.peligro ? ' fp-modal--peligro' : '') + '" role="dialog" aria-modal="true">' +
            '<div class="fp-modal-head"><h2>' + escapar(config.titulo) + '</h2>' +
            (config.descripcion ? '<p>' + escapar(config.descripcion) + '</p>' : '') + '</div>' +
            '<form class="fp-modal-form"><div class="fp-modal-body">' + config.cuerpo + '</div>' +
            '<div class="fp-modal-foot">' +
            '<button type="button" class="fp-btn fp-btn--ghost" data-fp-cancelar>' + escapar(config.cancelar) + '</button>' +
            '<button type="submit" class="fp-btn' + (config.peligro ? ' fp-btn--peligro' : '') + '" data-fp-aceptar>' +
            escapar(config.aceptar) + '</button>' +
            '</div></form></div>';

        document.body.appendChild(backdrop);

        const formulario = backdrop.querySelector('.fp-modal-form');
        const botonAceptar = backdrop.querySelector('[data-fp-aceptar]');

        modalAbierto = { backdrop, resolver, foco: document.activeElement };
        // Teclado mientras el modal está abierto.
        document.addEventListener('keydown', alPresionarTecla, true);

        // Botón Cancelar.
        backdrop.querySelector('[data-fp-cancelar]').addEventListener('click', () => cerrarModal(null));
        // Clic afuera del modal lo cierra.
        backdrop.addEventListener('mousedown', (evento) => {
            if (evento.target === backdrop) cerrarModal(null);
        });

        // Aceptar: ejecuta alAceptar y cierra si salió bien.
        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();

            if (!config.alAceptar) {
                cerrarModal(true);
                return;
            }

            botonAceptar.dataset.cargando = '1';
            try {
                const resultado = await config.alAceptar(formulario, backdrop);
                if (resultado !== false) cerrarModal(resultado ?? true);
            } finally {
                delete botonAceptar.dataset.cargando;
            }
        });

        const primerCampo = backdrop.querySelector('input, select, textarea') || botonAceptar;
        primerCampo.focus();
        if (typeof config.alAbrir === 'function') config.alAbrir(formulario, backdrop);
    });
}

// Pide confirmación antes de una acción.
async function confirmar(opciones) {
    const resultado = await abrirModal({
        aceptar: 'Confirmar',
        cancelar: 'Cancelar',
        ...opciones,
        alAceptar: null
    });

    return resultado === true;
}
