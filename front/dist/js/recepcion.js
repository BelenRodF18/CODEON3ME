/* ==========================================================================
   FitPower · Panel de Recepción
   Lee el QR del socio con la cámara, lo manda al backend y muestra el
   resultado del ingreso. El token queda registrado en MySQL recién acá.
   ========================================================================== */

(function () {
    'use strict';

    const { api, escapar, exito, error: errorToast, aviso, confirmar, badge, vacio } = window.FP;

    const $ = (id) => document.getElementById(id);

    const estado = {
        operador: null,
        lector: null,
        escaneando: false,
        procesando: false,
        ultimoToken: '',
        ultimoMomento: 0
    };

    /** Evita releer el mismo QR mientras la cámara sigue apuntando al código. */
    const ES_REPETIDO = (token) => token === estado.ultimoToken && Date.now() - estado.ultimoMomento < 3000;

    // ------------------------------------------------------- Estado visual

    const fijarEstado = (texto, tono) => {
        const nodo = $('escaner-estado');
        nodo.textContent = texto;
        nodo.dataset.tono = tono || 'neutro';
    };

    // Minutos a texto: "1 h 15 min".
    const duracion = (minutos) => {
        const total = Number(minutos) || 0;
        return total >= 60 ? Math.floor(total / 60) + ' h ' + (total % 60) + ' min' : total + ' min';
    };

    // Muestra el resultado del escaneo: ingreso, salida o rechazo.
    const mostrarResultado = (respuesta) => {
        const caja = $('resultado');
        caja.hidden = false;
        caja.dataset.tipo = respuesta.permitido ? 'ok' : 'no';

        if (respuesta.permitido && respuesta.movimiento === 'salida') {
            const socio = respuesta.socio || {};
            const acceso = respuesta.acceso || {};

            caja.innerHTML =
                '<div class="rec-resultado-marca" aria-hidden="true">→</div>' +
                '<h2>SALIDA REGISTRADA</h2>' +
                '<p class="rec-socio">' + escapar(socio.nombre || '') + '</p>' +
                '<p class="rec-detalle">Ingresó a las ' + escapar(acceso.hora_ingreso || '—') +
                ' · se retira a las <strong>' + escapar(acceso.hora || '') + '</strong><br>' +
                'Estadía: ' + escapar(duracion(acceso.minutos)) + '</p>';
        } else if (respuesta.permitido) {
            const socio = respuesta.socio || {};
            const acceso = respuesta.acceso || {};

            caja.innerHTML =
                '<div class="rec-resultado-marca" aria-hidden="true">✓</div>' +
                '<h2>INGRESO AUTORIZADO</h2>' +
                '<p class="rec-socio">' + escapar(socio.nombre || '') + '</p>' +
                '<p class="rec-rol">' + escapar(socio.rol || 'Socio') +
                (socio.plan ? ' · ' + escapar(socio.plan) : '') + '</p>' +
                '<p class="rec-detalle">Ingreso registrado<br><strong>' +
                escapar(acceso.fecha || '') + ' — ' + escapar(acceso.hora || '') + '</strong></p>';
        } else {
            const socio = respuesta.socio;

            caja.innerHTML =
                '<div class="rec-resultado-marca" aria-hidden="true">✕</div>' +
                '<h2>INGRESO RECHAZADO</h2>' +
                '<p class="rec-motivo">' + escapar(respuesta.message || 'QR inválido') + '</p>' +
                (socio ? '<p class="rec-detalle">' + escapar(socio.nombre) + '<br>' + escapar(socio.nombre_usuario) + '</p>' : '');
        }

        // Aviso sonoro corto: en un mostrador no siempre se mira la pantalla.
        pitar(respuesta.permitido);
        if (navigator.vibrate) navigator.vibrate(respuesta.permitido ? 120 : [90, 70, 90]);
    };

    /** Tono generado con WebAudio: no hace falta ningún archivo de sonido. */
    const pitar = (correcto) => {
        try {
            const Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) return;
            const contexto = new Audio();
            const oscilador = contexto.createOscillator();
            const volumen = contexto.createGain();

            oscilador.frequency.value = correcto ? 880 : 220;
            volumen.gain.value = 0.05;
            oscilador.connect(volumen);
            volumen.connect(contexto.destination);
            oscilador.start();
            oscilador.stop(contexto.currentTime + (correcto ? 0.14 : 0.32));
            setTimeout(() => contexto.close(), 600);
        } catch (fallo) { /* sin audio disponible: no es crítico */ }
    };

    // --------------------------------------------------------- Canje del QR

    const canjear = async (token, origen) => {
        if (estado.procesando) return;
        estado.procesando = true;
        estado.ultimoToken = token;
        estado.ultimoMomento = Date.now();

        fijarEstado('Validando código...', 'proceso');

        try {
            // Un ingreso rechazado llega con 200 (permitido = false); solo un
            // problema de sesión o un token vacío lanza error.
            const datos = await api('/recepcion/accesos/canjear', { method: 'POST', body: { token } });

            mostrarResultado(datos);

            if (datos.permitido && datos.movimiento === 'salida') {
                exito('Salida registrada', (datos.socio ? datos.socio.nombre : 'Socio') + ' se retiró del gimnasio.');
                fijarEstado('Listo para el siguiente socio', 'ok');
            } else if (datos.permitido) {
                exito('Ingreso autorizado', (datos.socio ? datos.socio.nombre : 'Socio') + ' ingresó correctamente.');
                fijarEstado('Listo para el siguiente socio', 'ok');
            } else {
                errorToast('Ingreso rechazado', datos.message || 'QR inválido');
                fijarEstado(datos.message || 'QR inválido', 'no');
            }

            await cargarHistorial();
        } catch (fallo) {
            errorToast('No se pudo validar el código', fallo.message);
            fijarEstado('Error de conexión', 'no');
        } finally {
            estado.procesando = false;
            if (origen === 'manual') $('token-manual').value = '';
        }
    };

    // -------------------------------------------------------- Lector de QR

    const contenedorLector = () => $('lector');

    // Enciende la cámara y empieza a leer códigos QR.
    const iniciarEscaner = async () => {
        if (estado.escaneando) return;

        if (typeof Html5Qrcode === 'undefined') {
            aviso('Lector no disponible', 'No se pudo cargar la librería del escáner. Usá el ingreso manual.');
            return;
        }

        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            aviso('Cámara bloqueada', 'El navegador solo permite la cámara en HTTPS o en localhost. Usá el ingreso manual.');
            return;
        }

        $('escaner-placeholder').hidden = true;
        contenedorLector().hidden = false;
        fijarEstado('Pidiendo permiso de cámara...', 'proceso');

        try {
            estado.lector = estado.lector || new Html5Qrcode('lector', { verbose: false });

            await estado.lector.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 }, aspectRatio: 1 },
                (texto) => {
                    if (ES_REPETIDO(texto) || estado.procesando) return;
                    canjear(texto.trim(), 'camara');
                },
                () => { /* cada frame sin QR entra acá: se ignora a propósito */ }
            );

            estado.escaneando = true;
            $('visor').classList.add('rec-visor--activo');
            $('btn-escanear').hidden = true;
            $('btn-detener').hidden = false;
            fijarEstado('Esperando código...', 'proceso');
        } catch (fallo) {
            contenedorLector().hidden = true;
            $('escaner-placeholder').hidden = false;
            fijarEstado('Cámara no disponible', 'no');

            const mensaje = String(fallo && fallo.message ? fallo.message : fallo);
            const permisoDenegado = /permission|denied|NotAllowed/i.test(mensaje);

            errorToast(
                permisoDenegado ? 'Permiso de cámara denegado' : 'No se pudo abrir la cámara',
                permisoDenegado
                    ? 'Habilitá la cámara para este sitio o usá el ingreso manual.'
                    : 'Revisá que haya una cámara conectada. Mientras tanto podés usar el ingreso manual.'
            );
        }
    };

    // Apaga la cámara.
    const detenerEscaner = async () => {
        if (!estado.lector || !estado.escaneando) return;

        try {
            await estado.lector.stop();
            estado.lector.clear();
        } catch (fallo) { /* ya estaba detenido */ }

        estado.escaneando = false;
        contenedorLector().hidden = true;
        $('visor').classList.remove('rec-visor--activo');
        $('escaner-placeholder').hidden = false;
        $('btn-escanear').hidden = false;
        $('btn-detener').hidden = true;
        fijarEstado('Lector detenido', 'neutro');
    };

    // ---------------------------------------------------------- Historial

    const filaAcceso = (acceso) => {
        const permitido = acceso.estado === 'Permitido';

        return '<tr>' +
            // El usuario va en el title: en una columna angosta el nombre alcanza.
            '<td title="' + escapar(acceso.nombre_usuario) + '"><span class="fp-principal">' +
            escapar(acceso.socio) + '</span></td>' +
            '<td>' + escapar(acceso.fecha || '—') + '<span class="fp-secundario">' + (permitido ? 'Ingreso ' : '') + escapar(acceso.hora || '') +
            (acceso.salida ? ' · Salida ' + escapar(acceso.salida) : '') + '</span>' +
            (acceso.adentro
                ? '<button class="fp-btn fp-btn--ghost fp-btn--sm rec-salida" type="button" data-id="' + acceso.id +
                  '" style="margin-top:6px">Registrar salida</button>'
                : '') + '</td>' +
            '<td>' + badge(permitido ? 'Aceptado' : 'Rechazado', permitido ? 'activo' : 'peligro') +
            (acceso.motivo ? '<span class="fp-secundario">' + escapar(acceso.motivo) + '</span>' : '') + '</td>' +
            '<td>' + escapar(acceso.recepcion || '—') + '</td>' +
            '</tr>';
    };

    // Contadores del día e historial de ingresos y salidas.
    const cargarHistorial = async () => {
        try {
            const datos = await api('/recepcion/resumen');
            $('kpi-permitidos').textContent = datos.hoy.permitidos;
            $('kpi-denegados').textContent = datos.hoy.denegados;
            $('kpi-adentro').textContent = datos.hoy.adentro;

            const contenedor = $('historial');
            contenedor.innerHTML = datos.accesos.length
                ? '<div class="fp-tabla-wrap"><table class="fp-tabla">' +
                  '<thead><tr><th>Socio</th><th>Fecha y hora</th><th>Estado</th><th>Recepción</th></tr></thead>' +
                  '<tbody>' + datos.accesos.map(filaAcceso).join('') + '</tbody></table></div>'
                : vacio('Sin ingresos todavía', 'Cuando escanees el primer QR del día va a aparecer acá.', '○');
        } catch (fallo) {
            $('historial').innerHTML = vacio('No se pudo cargar el historial', fallo.message, '!');
        }
    };

    // ------------------------------------------------------------- Eventos

    const enlazarEventos = () => {
        $('btn-escanear').addEventListener('click', iniciarEscaner);
        $('btn-detener').addEventListener('click', detenerEscaner);
        $('btn-actualizar').addEventListener('click', cargarHistorial);

        $('historial').addEventListener('click', async (evento) => {
            const boton = evento.target.closest('.rec-salida');
            if (!boton) return;

            try {
                const datos = await api('/recepcion/accesos/' + boton.dataset.id + '/salida', { method: 'POST' });
                exito('Salida registrada', datos.socio.nombre + ' se retiró a las ' + datos.acceso.hora + '.');
                await cargarHistorial();
            } catch (fallo) {
                errorToast('No se pudo registrar la salida', fallo.message);
            }
        });

        $('form-manual').addEventListener('submit', (evento) => {
            evento.preventDefault();
            const token = $('token-manual').value.trim();
            if (!token) return;
            canjear(token, 'manual');
        });

        $('cerrar-sesion').addEventListener('click', async () => {
            const confirmado = await confirmar({
                titulo: 'Cerrar sesión',
                descripcion: '¿Querés salir del panel de Recepción?',
                aceptar: 'Cerrar sesión'
            });
            if (!confirmado) return;

            await detenerEscaner();
            window.FP.cerrarSesion();
        });

        // Si se cierra la pestaña, liberar la cámara.
        window.addEventListener('pagehide', () => { detenerEscaner(); });
    };

    // -------------------------------------------------------------- Inicio

    (async () => {
        estado.operador = await window.FP.exigirSesion(['recepcion', 'admin']);
        $('operador').textContent = estado.operador.nombre_usuario;

        enlazarEventos();
        fijarEstado('Esperando código...', 'neutro');
        await cargarHistorial();
    })();
})();
