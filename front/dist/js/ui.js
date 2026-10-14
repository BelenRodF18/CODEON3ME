/* ==========================================================================
   FitPower · Componentes de interfaz compartidos (FP)
   Reemplaza alert() / confirm() / prompt() del navegador por toasts y modales.
   Se usa desde los dashboards: window.FP
   ========================================================================== */

(function (global) {
    'use strict';

    // ------------------------------------------------------------- Utilidades

    const escapar = (valor) => String(valor ?? '').replace(/[&<>'"]/g, (caracter) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[caracter]));

    // Todos los dashboards viven en dist/pages/, junto al login.
    const URL_LOGIN = 'login.html';

    /** Roles que acepta la página abierta (los fija exigirSesion). */
    let rolesPagina = null;

    /** Vuelve al login explicando por qué (sesión vencida u otra cuenta). */
    function irAlLogin(motivo) {
        window.location.replace(URL_LOGIN + '?motivo=' + encodeURIComponent(motivo));
    }

    /**
     * La API respondió con error estando en un panel: si la sesión venció o en
     * este navegador se entró con otra cuenta, se vuelve al login.
     */
    async function revisarSesion() {
        if (!rolesPagina) return;
        const sesion = await API.request('/me');
        if (sesion.status === 'success') {
            if (!rolesPagina.includes(sesion.usuario.rol)) irAlLogin('otra_cuenta');
        } else if (sesion.message !== 'No se pudo conectar con la API') {
            irAlLogin('expirada');
        }
    }

    /**
     * Llamada a la API con API.request (api.js). Devuelve el JSON; si el
     * backend responde status 'error' lanza un Error con el mensaje y los
     * datos extra (campo, dependencias), así cada panel lo atrapa con try/catch.
     */
    async function api(endpoint, method = 'GET', data = null) {
        const datos = await API.request(endpoint, method, data);

        if (datos.status === 'error') {
            await revisarSesion();
            const fallo = new Error(datos.message || 'No se pudo completar la operación');
            fallo.datos = datos;
            throw fallo;
        }

        return datos;
    }

    // ---------------------------------------------------------------- Toasts

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

    /**
     * toast({ tipo, titulo, texto, duracion })
     * tipo: 'exito' | 'error' | 'aviso' | 'info'
     */
    function toast(opciones) {
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
        // Con el mouse encima, el aviso no se cierra solo.
        elemento.addEventListener('mouseenter', () => clearTimeout(temporizador));

        return elemento;
    }

    // Aviso verde.
    const exito = (titulo, texto) => toast({ tipo: 'exito', titulo, texto });
    // Aviso rojo.
    const error = (titulo, texto) => toast({ tipo: 'error', titulo, texto });
    // Aviso amarillo.
    const aviso = (titulo, texto) => toast({ tipo: 'aviso', titulo, texto });
    // Aviso informativo.
    const info = (titulo, texto) => toast({ tipo: 'info', titulo, texto });

    // ---------------------------------------------------------------- Modales

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

    // Escape cierra el modal; Tab queda dentro del modal.
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

    /**
     * modal({ titulo, descripcion, cuerpo, aceptar, cancelar, peligro, ancho, alAbrir, alAceptar })
     *
     * Resuelve con:
     *   null  -> cancelado
     *   true  -> aceptado (sin formulario)
     *   datos -> lo que devuelva alAceptar(form)
     */
    function modal(opciones) {
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
                    // alAceptar devuelve false para dejar el modal abierto (error de validación).
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

    /** Confirmación antes de una acción. Devuelve true/false, nunca bloquea el hilo. */
    async function confirmar(opciones) {
        const resultado = await modal({
            aceptar: 'Confirmar',
            cancelar: 'Cancelar',
            ...opciones,
            alAceptar: null
        });

        return resultado === true;
    }

    // ------------------------------------------------------------ Fragmentos

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

    /** Marca un botón como ocupado mientras dura una promesa. */
    async function conCarga(boton, tarea) {
        if (boton) boton.dataset.cargando = '1';
        try {
            return await tarea();
        } finally {
            if (boton) delete boton.dataset.cargando;
        }
    }

    /**
     * Sesión válida para el rol pedido; si no, vuelve al login. Desde ese
     * momento la página vigila que la sesión siga siendo la misma.
     */
    async function exigirSesion(rol, urlLogin = URL_LOGIN) {
        try {
            const datos = await api('/me');
            const rolesValidos = Array.isArray(rol) ? rol : [rol];
            if (!datos.usuario || !rolesValidos.includes(datos.usuario.rol)) {
                throw new Error('Sesión inválida');
            }
            rolesPagina = rolesValidos;
            return datos.usuario;
        } catch (fallo) {
            window.location.replace(urlLogin);
            // La página se detiene acá mientras el navegador va al login.
            return new Promise(() => {});
        }
    }

    /** Cierra la sesión en el servidor y vuelve a la página principal del sitio. */
    async function cerrarSesion() {
        rolesPagina = null;
        await API.request('/logout', 'POST');
        window.location.replace('../../index.html');
    }

    global.FP = {
        api, escapar,
        toast, exito, error, aviso, info,
        modal, confirmar,
        badge, vacio, cargando, conCarga,
        exigirSesion, cerrarSesion, revisarSesion
    };
})(window);
