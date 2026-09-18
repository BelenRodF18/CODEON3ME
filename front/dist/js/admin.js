/* ==========================================================================
   FitPower · Panel de administración
   UI → API → MySQL → API → UI. Sin alert(), sin datos hardcodeados.
   ========================================================================== */

(function () {
    'use strict';

    const { api, escapar, exito, error: errorToast, modal, confirmar, badge, vacio, cargando, conCarga } = window.FP;

    const $ = (id) => document.getElementById(id);

    const estado = {
        usuario: null,
        usuarios: [],
        roles: [],
        socios: [],
        entrenadores: [],
        planes: [],
        programas: [],
        ejercicios: [],
        filtroTexto: '',
        filtroEstado: 'todos'
    };

    // ------------------------------------------------------------ Navegación

    const irA = (vista) => {
        document.querySelectorAll('.socio-view').forEach((seccion) => {
            seccion.classList.toggle('active', seccion.id === 'view-' + vista);
        });
        document.querySelectorAll('.socio-nav button').forEach((boton) => {
            boton.classList.toggle('active', boton.dataset.view === vista);
        });
        if (vista === 'conversaciones') cargarConversaciones();
    };

    // ------------------------------------------------------- Mensajes de API

    const mostrarFallo = (fallo, titulo) => {
        errorToast(titulo || 'No se pudo completar la operación', fallo.message);
    };

    // ---------------------------------------------------------- Tabla de usuarios

    const claseEstado = (activo) => (activo ? 'activo' : 'inactivo');

    // Usuarios que pasan el buscador y el filtro de estado.
    const usuariosVisibles = () => {
        const texto = estado.filtroTexto.trim().toLowerCase();

        return estado.usuarios.filter((usuario) => {
            if (estado.filtroEstado === 'activos' && !usuario.activo) return false;
            if (estado.filtroEstado === 'inactivos' && usuario.activo) return false;
            if (!texto) return true;

            return (usuario.nombre + ' ' + usuario.nombre_usuario + ' ' + usuario.rol).toLowerCase().includes(texto);
        });
    };

    // Fila de la tabla de usuarios con sus acciones.
    const filaUsuario = (usuario) => {
        const esYo = estado.usuario && usuario.id === estado.usuario.id;
        const accionEstado = usuario.activo
            ? '<button class="fp-btn fp-btn--ghost fp-btn--sm" data-accion="baja" data-id="' + usuario.id + '"' +
              (esYo ? ' disabled title="No podés darte de baja a vos mismo"' : '') + '>Dar de baja</button>'
            : '<button class="fp-btn fp-btn--suave fp-btn--sm" data-accion="alta" data-id="' + usuario.id + '">Habilitar</button>';

        return '<tr data-id="' + usuario.id + '" data-inactivo="' + (usuario.activo ? '0' : '1') + '">' +
            '<td><span class="fp-principal">' + escapar(usuario.nombre) +
            (esYo ? ' <span class="fp-badge fp-badge--info">vos</span>' : '') + '</span>' +
            '<span class="fp-secundario">' + escapar(usuario.nombre_usuario) + '</span></td>' +
            '<td>' + badge(usuario.rol, 'rol') + '</td>' +
            '<td>' + badge(usuario.activo ? 'Activo' : 'Inactivo', claseEstado(usuario.activo)) + '</td>' +
            '<td class="fp-acciones">' +
            '<button class="fp-btn fp-btn--ghost fp-btn--sm" data-accion="editar" data-id="' + usuario.id + '">Editar</button>' +
            accionEstado +
            '<button class="fp-btn fp-btn--peligro-ghost fp-btn--sm" data-accion="borrar" data-id="' + usuario.id + '"' +
            (esYo ? ' disabled title="No podés eliminar tu propia cuenta"' : '') + '>Borrar</button>' +
            '</td></tr>';
    };

    // Dibuja la tabla de usuarios (y resalta los recién cambiados).
    const pintarUsuarios = (idsResaltados = []) => {
        const contenedor = $('tabla-usuarios');
        const visibles = usuariosVisibles();

        if (!estado.usuarios.length) {
            contenedor.innerHTML = vacio(
                'Todavía no hay usuarios',
                'Creá el primer usuario del sistema con el botón "Nuevo usuario".',
                '+'
            );
            return;
        }

        if (!visibles.length) {
            contenedor.innerHTML = vacio(
                'Sin resultados',
                'Ningún usuario coincide con la búsqueda o el filtro aplicado.',
                '⌕'
            );
            return;
        }

        contenedor.innerHTML =
            '<div class="fp-tabla-wrap"><table class="fp-tabla">' +
            '<thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th class="fp-acciones">Acciones</th></tr></thead>' +
            '<tbody>' + visibles.map(filaUsuario).join('') + '</tbody></table></div>';

        idsResaltados.forEach((id) => {
            const fila = contenedor.querySelector('tr[data-id="' + id + '"]');
            if (fila) fila.classList.add('fp-resaltada');
        });
    };

    // Actualiza los contadores de arriba del panel.
    const pintarMetricas = (metricas) => {
        if (!metricas) return;
        $('kpi-usuarios').textContent = metricas.usuarios;
        $('kpi-activos').textContent = metricas.activos;
        $('kpi-inactivos').textContent = metricas.inactivos;
        $('kpi-socios').textContent = metricas.socios;
        $('kpi-accesos').textContent = metricas.accesos_hoy;
    };

    // ------------------------------------------------- Formulario de usuario

    const opcionesRol = (seleccionado) => estado.roles
        .map((rol) => '<option value="' + rol.id + '"' + (Number(rol.id) === Number(seleccionado) ? ' selected' : '') +
            '>' + escapar(rol.nombre) + '</option>')
        .join('');

    // Campo de texto del formulario con su etiqueta, ayuda y lugar para el error.
    const campoTexto = (nombre, etiqueta, valor, extra, ayuda) =>
        '<div class="fp-campo fp-campo--full" data-campo="' + nombre + '">' +
        '<label for="fp-' + nombre + '">' + etiqueta + '</label>' +
        '<input id="fp-' + nombre + '" name="' + nombre + '" value="' + escapar(valor || '') + '" ' + (extra || '') + '>' +
        (ayuda ? '<small>' + ayuda + '</small>' : '') +
        '<p class="fp-campo-error" data-error-de="' + nombre + '"></p></div>';

    // Formulario de alta o edición de un usuario.
    const formularioUsuario = (usuario) =>
        '<div class="fp-form">' +
        campoTexto('nombre', 'Nombre completo', usuario ? usuario.nombre : '', 'required maxlength="100" autocomplete="off"') +
        campoTexto('nombre_usuario', 'Usuario', usuario ? usuario.nombre_usuario : '',
            'required minlength="3" maxlength="30" autocomplete="off" autocapitalize="none" spellcheck="false"',
            'Con esto inicia sesión. Minúsculas, números, punto, guion o guion bajo (ej. juan.diaz).') +
        '<div class="fp-campo" data-campo="rol_id"><label for="fp-rol">Rol</label>' +
        '<select id="fp-rol" name="rol_id">' + opcionesRol(usuario ? usuario.rol_id : 3) + '</select>' +
        '<p class="fp-campo-error" data-error-de="rol_id"></p></div>' +
        '<div class="fp-campo" data-campo="password"><label for="fp-password">Contraseña</label>' +
        '<input id="fp-password" name="password" type="password" minlength="6" autocomplete="new-password"' +
        (usuario ? '' : ' required') + '>' +
        '<small>' + (usuario ? 'Dejalo vacío para no cambiarla.' : 'Mínimo 6 caracteres.') + '</small>' +
        '<p class="fp-campo-error" data-error-de="password"></p></div>' +
        '</div>';

    /** Muestra el error del backend junto al campo que lo causó. */
    const marcarError = (formulario, fallo) => {
        formulario.querySelectorAll('[data-campo]').forEach((campo) => delete campo.dataset.error);
        formulario.querySelectorAll('.fp-campo-error').forEach((nodo) => { nodo.textContent = ''; });

        const campo = fallo.datos && fallo.datos.campo;
        if (campo) {
            const contenedor = formulario.querySelector('[data-campo="' + campo + '"]');
            const mensaje = formulario.querySelector('[data-error-de="' + campo + '"]');
            if (contenedor) contenedor.dataset.error = '1';
            if (mensaje) mensaje.textContent = fallo.message;
            const entrada = contenedor && contenedor.querySelector('input, select');
            if (entrada) entrada.focus();
            return;
        }

        errorToast('No se pudo guardar', fallo.message);
    };

    // Lee el formulario y normaliza los datos antes de enviarlos.
    const datosDelFormulario = (formulario) => {
        const datos = Object.fromEntries(new FormData(formulario));
        datos.rol_id = Number(datos.rol_id);
        datos.nombre_usuario = String(datos.nombre_usuario || '').trim().toLowerCase();
        return datos;
    };

    // Misma regla que UsuarioModel::FORMATO_USUARIO. El backend es quien manda:
    // esto solo da feedback inmediato.
    const FORMATO_USUARIO = /^[a-z0-9]+([._-][a-z0-9]+)*$/;

    // Valida el nombre de usuario y muestra el error junto al campo.
    const usuarioValido = (formulario, datos) => {
        const largo = datos.nombre_usuario.length;
        const mensaje = largo < 3 || largo > 30
            ? 'El usuario debe tener entre 3 y 30 caracteres'
            : (FORMATO_USUARIO.test(datos.nombre_usuario)
                ? null
                : 'El usuario solo admite minúsculas, números, punto, guion y guion bajo (ej. juan.diaz)');

        if (mensaje) marcarError(formulario, { message: mensaje, datos: { campo: 'nombre_usuario' } });
        return !mensaje;
    };

    // Modal para crear un usuario nuevo.
    const abrirCrearUsuario = () => modal({
        titulo: 'Nuevo usuario',
        descripcion: 'Se guarda en MySQL y queda disponible de inmediato.',
        cuerpo: formularioUsuario(null),
        aceptar: 'Crear usuario',
        ancho: true,
        alAceptar: async (formulario) => {
            const datos = datosDelFormulario(formulario);
            if (!usuarioValido(formulario, datos)) return false;

            try {
                const respuesta = await api('/admin/usuarios', { method: 'POST', body: datos });
                await recargarUsuarios([respuesta.usuario.id]);
                exito('Usuario creado correctamente', respuesta.usuario.nombre + ' ya está disponible en el sistema.');
                return true;
            } catch (fallo) {
                marcarError(formulario, fallo);
                return false;
            }
        }
    });

    // Modal para editar un usuario existente.
    const abrirEditarUsuario = (usuario) => modal({
        titulo: 'Editar usuario',
        descripcion: 'Los cambios se guardan en MySQL y se reflejan al instante.',
        cuerpo: formularioUsuario(usuario),
        aceptar: 'Guardar cambios',
        ancho: true,
        alAceptar: async (formulario) => {
            const datos = datosDelFormulario(formulario);
            if (!usuarioValido(formulario, datos)) return false;
            if (!datos.password) delete datos.password;

            try {
                const respuesta = await api('/admin/usuarios/' + usuario.id, { method: 'PUT', body: datos });
                await recargarUsuarios([usuario.id]);
                exito('Usuario actualizado', 'Se guardaron los datos de ' + respuesta.usuario.nombre + '.');
                return true;
            } catch (fallo) {
                marcarError(formulario, fallo);
                return false;
            }
        }
    });

    // --------------------------------------------------------- Baja y alta

    const cambiarEstadoUsuario = async (usuario, activar) => {
        const confirmado = await confirmar({
            titulo: activar ? 'Habilitar usuario' : 'Dar de baja al usuario',
            descripcion: activar
                ? usuario.nombre + ' vuelve a estar activo y podrá iniciar sesión otra vez.'
                : usuario.nombre + ' quedará inactivo y no podrá iniciar sesión ni ingresar al gimnasio. No se borra ningún dato: podés habilitarlo cuando quieras.',
            aceptar: activar ? 'Habilitar' : 'Dar de baja',
            peligro: !activar
        });

        if (!confirmado) return;

        try {
            const respuesta = await api('/admin/usuarios/' + usuario.id + '/estado', {
                method: 'PUT',
                body: { activo: activar }
            });
            await recargarUsuarios([usuario.id]);
            exito(
                activar ? 'Usuario habilitado' : 'Usuario dado de baja',
                respuesta.usuario.nombre + ' figura ahora como ' + (activar ? 'activo' : 'inactivo') + '.'
            );
        } catch (fallo) {
            mostrarFallo(fallo, activar ? 'No se pudo habilitar' : 'No se pudo dar de baja');
        }
    };

    // -------------------------------------------------------------- Borrado

    // [singular, plural] para que no quede "1 socios".
    const ETIQUETAS_DEPENDENCIA = {
        socios_asignados: [
            'socio que tiene asignado como entrenador',
            'socios que tiene asignados como entrenador'
        ],
        rutinas_creadas: [
            'rutina creada por este entrenador (se elimina junto con sus ejercicios)',
            'rutinas creadas por este entrenador (se eliminan junto con sus ejercicios)'
        ],
        rutinas_asignadas: ['rutina asignada', 'rutinas asignadas'],
        membresias: ['membresía', 'membresías'],
        conversaciones: ['conversación y sus mensajes', 'conversaciones y sus mensajes'],
        series: ['serie de rendimiento registrada', 'series de rendimiento registradas'],
        accesos: ['ingreso registrado', 'ingresos registrados'],
        programas_inscriptos: ['inscripción a un programa', 'inscripciones a programas'],
        programas_a_cargo: [
            'programa que dirige (queda sin entrenador asignado)',
            'programas que dirige (quedan sin entrenador asignado)'
        ]
    };

    // Lista de lo que se borra junto con el usuario.
    const listaDependencias = (dependencias) => {
        const items = Object.entries(dependencias)
            .filter(([, cantidad]) => cantidad > 0)
            .map(([clave, cantidad]) =>
                '<li>' + cantidad + ' ' + ETIQUETAS_DEPENDENCIA[clave][cantidad === 1 ? 0 : 1] + '</li>');

        if (!items.length) return '';

        return '<div class="fp-aviso fp-aviso--aviso"><div><strong>También se eliminarán:</strong>' +
            '<ul>' + items.join('') + '</ul></div></div>';
    };

    // Pide confirmación (con las dependencias) y borra el usuario.
    const borrarUsuario = async (usuario) => {
        let dependencias = {};
        let requiereExtra = false;

        try {
            const respuesta = await api('/admin/usuarios/' + usuario.id + '/dependencias');
            dependencias = respuesta.dependencias;
            requiereExtra = respuesta.requiere_confirmacion_extra;
        } catch (fallo) {
            mostrarFallo(fallo, 'No se pudo consultar el usuario');
            return;
        }

        const cuerpo =
            '<div class="fp-aviso fp-aviso--peligro"><div>Esta acción es <strong>permanente</strong>. ' +
            escapar(usuario.nombre) + ' será eliminado de la base de datos y no se podrá recuperar.<br>' +
            'Si solo querés bloquearle el acceso, usá <strong>Dar de baja</strong>.</div></div>' +
            listaDependencias(dependencias) +
            (requiereExtra
                ? '<div class="fp-campo fp-campo--full"><label style="display:flex;gap:9px;align-items:flex-start;font-weight:400">' +
                  '<input type="checkbox" name="forzar" style="width:auto;margin-top:2px" required> ' +
                  'Entiendo que también se eliminarán los registros relacionados que se listan arriba.</label></div>'
                : '');

        const confirmado = await modal({
            titulo: 'Eliminar usuario',
            cuerpo,
            aceptar: 'Eliminar definitivamente',
            cancelar: 'Cancelar',
            peligro: true,
            alAceptar: async (formulario) => {
                const forzar = formulario.querySelector('[name="forzar"]');
                if (forzar && !forzar.checked) return false;

                try {
                    await api('/admin/usuarios/' + usuario.id + (forzar ? '?forzar=1' : ''), { method: 'DELETE' });
                    return true;
                } catch (fallo) {
                    errorToast('No se pudo eliminar', fallo.message);
                    return false;
                }
            }
        });

        if (confirmado !== true) return;

        await recargarUsuarios();
        exito('Usuario eliminado', usuario.nombre + ' fue borrado del sistema.');
    };

    // ------------------------------------------------------- Carga de datos

    // Un alta, edición o baja también cambia la lista de socios y de
    // entrenadores de la pestaña Socios: se recarga el resumen completo.
    const recargarUsuarios = async (idsResaltados = []) => {
        await cargarTodo();
        pintarUsuarios(idsResaltados);
    };

    // Carga el resumen completo del panel y dibuja todas las secciones.
    const cargarTodo = async () => {
        $('tabla-usuarios').innerHTML = cargando(5);
        $('socios-admin').innerHTML = cargando(3);
        $('programas-admin').innerHTML = cargando(3);

        const datos = await api('/admin/resumen');
        estado.usuarios = datos.usuarios;
        estado.roles = datos.roles;
        estado.socios = datos.socios;
        estado.entrenadores = datos.entrenadores;
        estado.planes = datos.planes;
        estado.programas = datos.programas;
        estado.ejercicios = datos.ejercicios;

        pintarUsuarios();
        pintarMetricas(datos.metricas);
        pintarSocios();
        pintarProgramas();
        pintarEjercicios();
    };

    // ------------------------------------------------------ Socios y planes

    const planPorId = (id) => estado.planes.find((plan) => String(plan.id) === String(id)) || null;

    /** Checkboxes con los programas que incluye el plan, cada uno con su entrenador. */
    const casillasProgramas = (socioId, plan, inscriptos) => {
        if (!plan) return '<p class="socio-meta">Asigná un plan para inscribirlo en programas.</p>';

        const incluidos = estado.programas.filter((programa) => plan.programas.includes(programa.id));
        if (!incluidos.length) return '<p class="socio-meta">El ' + escapar(plan.nombre) + ' no incluye programas.</p>';

        return incluidos.map((programa) =>
            '<label style="display:flex;gap:9px;align-items:center;font-weight:400;margin:4px 0">' +
            '<input type="checkbox" class="admin-programa" data-id="' + socioId + '" value="' + programa.id + '"' +
            (inscriptos.includes(programa.id) ? ' checked' : '') + ' style="width:auto">' +
            escapar(programa.nombre) + ' <span class="socio-meta">· ' +
            escapar(programa.entrenador || 'sin entrenador asignado') + '</span></label>'
        ).join('');
    };

    // Opciones del selector de entrenadores.
    const opcionesEntrenadores = (seleccionado, textoVacio) =>
        '<option value="0">' + textoVacio + '</option>' + estado.entrenadores
            .map((entrenador) => '<option value="' + entrenador.id + '"' +
                (String(entrenador.id) === String(seleccionado) ? ' selected' : '') + '>' +
                escapar(entrenador.nombre) + '</option>')
            .join('');

    /** Entrenador personal y programas dependen del plan elegido en la tarjeta. */
    const pintarBloquePlan = (socioId) => {
        const socio = estado.socios.find((item) => String(item.id) === String(socioId));
        const plan = planPorId(document.querySelector('.admin-plan[data-id="' + socioId + '"]').value);
        const selector = document.querySelector('.admin-trainer[data-id="' + socioId + '"]');
        const permitePersonal = Boolean(plan && plan.entrenador_personal);

        selector.disabled = !permitePersonal;
        if (!permitePersonal) selector.value = '0';
        document.querySelector('.admin-trainer-ayuda[data-id="' + socioId + '"]').textContent = permitePersonal
            ? 'Incluido en el ' + plan.nombre + '.'
            : (plan ? 'El ' + plan.nombre + ' no incluye entrenador personal.' : 'Primero asigná un plan.');

        const marcados = [...document.querySelectorAll('.admin-programa[data-id="' + socioId + '"]:checked')]
            .map((casilla) => Number(casilla.value));
        const inscriptos = document.querySelector('.admin-programas[data-id="' + socioId + '"]').dataset.pintado
            ? marcados
            : socio.programas;

        const bloque = document.querySelector('.admin-programas[data-id="' + socioId + '"]');
        bloque.innerHTML = casillasProgramas(socioId, plan, inscriptos);
        bloque.dataset.pintado = '1';
    };

    // Tarjetas de los socios con plan, entrenador, membresía y programas.
    const pintarSocios = () => {
        const contenedor = $('socios-admin');

        if (!estado.socios.length) {
            contenedor.innerHTML = vacio('Sin socios registrados', 'Cuando crees un usuario con rol Socio aparecerá acá.', '○');
            return;
        }

        // Opciones del selector de plan (con precio).
        const opcionesPlan = (socio) => (socio.plan_id ? '' : '<option value="" selected disabled>Elegí un plan</option>') +
            estado.planes
                .map((plan) => '<option value="' + plan.id + '"' +
                    (String(plan.id) === String(socio.plan_id) ? ' selected' : '') + '>' +
                    escapar(plan.nombre) + ' · $' + Number(plan.precio).toLocaleString('es-UY') + '</option>')
                .join('');

        // Opciones del estado de la membresía.
        const estadoMembresia = (socio) => ['Al dia', 'Vencida', 'Bloqueada']
            .map((opcion) => '<option' + (socio.membresia === opcion ? ' selected' : '') + '>' + opcion + '</option>')
            .join('');

        contenedor.innerHTML = estado.socios.map((socio) =>
            '<div class="routine-item" style="cursor:default">' +
            '<div class="routine-head"><strong>' + escapar(socio.nombre) + '</strong>' +
            badge(socio.membresia, socio.membresia === 'Al dia' ? 'activo' : 'peligro') + '</div>' +
            '<p class="socio-meta">' + escapar(socio.nombre_usuario) + '</p>' +
            '<div class="fp-form" style="margin-top:14px">' +
            '<div class="fp-campo"><label>Plan</label><select class="admin-plan" data-id="' + socio.id + '">' +
            opcionesPlan(socio) + '</select></div>' +
            '<div class="fp-campo"><label>Entrenador personal</label><select class="admin-trainer" data-id="' + socio.id + '">' +
            opcionesEntrenadores(socio.entrenador_id, 'Sin entrenador personal') + '</select>' +
            '<small class="admin-trainer-ayuda" data-id="' + socio.id + '"></small></div>' +
            '<div class="fp-campo"><label>Vencimiento</label><input class="admin-date" type="date" data-id="' + socio.id +
            '" value="' + (socio.vencimiento || '') + '"></div>' +
            '<div class="fp-campo"><label>Estado de membresía</label><select class="admin-status" data-id="' + socio.id + '">' +
            estadoMembresia(socio) + '</select></div>' +
            '<div class="fp-campo fp-campo--full"><label>Programas inscriptos</label>' +
            '<div class="admin-programas" data-id="' + socio.id + '"></div></div>' +
            '</div>' +
            '<button class="fp-btn fp-btn--sm admin-save" data-id="' + socio.id + '" style="margin-top:13px">Guardar cambios</button>' +
            '</div>'
        ).join('');

        estado.socios.forEach((socio) => pintarBloquePlan(socio.id));
    };

    // Lista de programas con el selector de su entrenador a cargo.
    const pintarProgramas = () => {
        const contenedor = $('programas-admin');

        contenedor.innerHTML = estado.programas.length
            ? estado.programas.map((programa) =>
                '<div class="exercise-row" style="align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)">' +
                '<span><strong>' + escapar(programa.nombre) + '</strong>' +
                '<small class="socio-meta" style="display:block">' + escapar(estado.planes
                    .filter((plan) => plan.programas.includes(programa.id))
                    .map((plan) => plan.nombre).join(' · ') || 'No lo incluye ningún plan') + '</small></span>' +
                '<span style="display:flex;gap:8px;align-items:center">' +
                '<select class="admin-programa-entrenador" data-id="' + programa.id + '" aria-label="Entrenador de ' +
                escapar(programa.nombre) + '">' + opcionesEntrenadores(programa.entrenador_id, 'Sin entrenador') + '</select>' +
                '<button class="fp-btn fp-btn--sm fp-btn--ghost admin-programa-guardar" data-id="' + programa.id + '">Guardar</button>' +
                '</span></div>').join('')
            : vacio('Sin programas', 'Los programas se cargan desde init.sql.', '○');
    };

    // Catálogo de ejercicios.
    const pintarEjercicios = () => {
        const contenedor = $('ejercicios-admin');

        contenedor.innerHTML = estado.ejercicios.length
            ? estado.ejercicios.map((ejercicio) =>
                '<div class="exercise-row"><span>' + escapar(ejercicio.nombre) + '</span>' +
                '<span>' + escapar(ejercicio.grupo_muscular) + '</span></div>').join('')
            : vacio('Catálogo vacío', 'Agregá el primer ejercicio con el formulario de arriba.', '+');
    };

    // Guarda plan, entrenador personal y programas del socio (en ese orden).
    const guardarSocio = async (boton) => {
        const id = boton.dataset.id;
        // Valor del control de esta tarjeta.
        const valor = (selector) => document.querySelector(selector + '[data-id="' + id + '"]').value;
        const programas = [...document.querySelectorAll('.admin-programa[data-id="' + id + '"]:checked')]
            .map((casilla) => Number(casilla.value));

        if (!valor('.admin-plan')) {
            errorToast('Falta el plan', 'Elegí un plan antes de guardar.');
            return;
        }

        await conCarga(boton, async () => {
            try {
                // El plan va primero: define qué entrenador y qué programas se permiten.
                await api('/admin/socios/' + id + '/membresia', {
                    method: 'PUT',
                    body: {
                        plan_id: valor('.admin-plan'),
                        vencimiento: valor('.admin-date'),
                        estado: valor('.admin-status')
                    }
                });
                await api('/admin/socios/' + id + '/entrenador', {
                    method: 'PUT',
                    body: { entrenador_id: valor('.admin-trainer') }
                });
                await api('/admin/socios/' + id + '/programas', {
                    method: 'PUT',
                    body: { programas }
                });
                await cargarTodo();
                exito('Socio actualizado', 'Se guardaron el plan, el entrenador y los programas.');
            } catch (fallo) {
                mostrarFallo(fallo, 'No se pudo guardar el socio');
            }
        });
    };

    // Guarda el entrenador a cargo de un programa.
    const guardarEntrenadorPrograma = async (boton) => {
        const id = boton.dataset.id;
        const entrenador = document.querySelector('.admin-programa-entrenador[data-id="' + id + '"]').value;

        await conCarga(boton, async () => {
            try {
                await api('/admin/programas/' + id + '/entrenador', {
                    method: 'PUT',
                    body: { entrenador_id: entrenador }
                });
                await cargarTodo();
                exito('Programa actualizado', 'El entrenador a cargo quedó guardado.');
            } catch (fallo) {
                mostrarFallo(fallo, 'No se pudo cambiar el entrenador');
            }
        });
    };

    // ------------------------------------------------------- Conversaciones

    const ESTADOS_CONVERSACION = {
        abierto: 'Abierto', en_revision: 'En revisión', en_reparacion: 'En reparación', solucionado: 'Solucionado'
    };

    // Bandeja de reportes y sugerencias con sus chats.
    const cargarConversaciones = async () => {
        const contenedor = $('conversaciones-admin');
        contenedor.innerHTML = cargando(3);

        try {
            const datos = await api('/admin/conversaciones');

            if (!datos.conversaciones.length) {
                contenedor.innerHTML = vacio('Bandeja vacía', 'Cuando un socio envíe una sugerencia o un reporte aparecerá acá.', '✉');
                return;
            }

            contenedor.innerHTML = datos.conversaciones.map((conversacion) =>
                '<article class="report-item"><header>' +
                '<strong>' + (conversacion.tipo === 'sugerencia' ? 'Sugerencia' : 'Mantenimiento') + ' · ' +
                escapar(conversacion.etiqueta) + '</strong>' +
                badge(ESTADOS_CONVERSACION[conversacion.estado] || conversacion.estado, conversacion.estado === 'solucionado' ? 'activo' : 'info') +
                '</header><p>' + escapar(conversacion.descripcion) + '</p>' +
                '<small class="socio-meta">' + escapar(conversacion.socio) + ' · ' + escapar(conversacion.creado) + '</small>' +
                '<div id="admin-chat-' + conversacion.id + '" class="chat-messages">' + cargando(1) + '</div>' +
                '<form class="admin-reply" data-id="' + conversacion.id + '" style="display:flex;gap:8px;margin-top:8px">' +
                '<input class="form-control" required placeholder="Escribir respuesta..." ' +
                'style="flex:1;border:1px solid #344138;border-radius:9px;background:#0b0f0c;color:#f4f7f2;padding:10px 12px">' +
                '<button class="fp-btn fp-btn--sm" type="submit">Enviar</button></form>' +
                '<div style="display:flex;gap:8px;margin-top:9px">' +
                '<button class="fp-btn fp-btn--ghost fp-btn--sm" data-state="en_reparacion" data-id="' + conversacion.id + '">En reparación</button>' +
                '<button class="fp-btn fp-btn--sm" data-state="solucionado" data-id="' + conversacion.id + '">Solucionado</button>' +
                '</div></article>'
            ).join('');

            datos.conversaciones.forEach(async (conversacion) => {
                try {
                    const mensajes = await api('/admin/conversaciones/' + conversacion.id + '/mensajes');
                    const caja = $('admin-chat-' + conversacion.id);
                    if (!caja) return;
                    caja.innerHTML = mensajes.mensajes
                        .map((mensaje) => '<div class="chat-message"><strong>' + escapar(mensaje.nombre) + ':</strong> ' +
                            escapar(mensaje.mensaje) + '</div>').join('');
                    caja.scrollTop = caja.scrollHeight;
                } catch (fallo) { /* el mensaje de error ya se muestra al operar */ }
            });
        } catch (fallo) {
            contenedor.innerHTML = vacio('No se pudo cargar la bandeja', fallo.message, '!');
        }
    };

    // ------------------------------------------------------------- Eventos

    const enlazarEventos = () => {
        document.querySelectorAll('[data-view]').forEach((boton) => {
            boton.addEventListener('click', () => irA(boton.dataset.view));
        });

        $('btn-nuevo-usuario').addEventListener('click', abrirCrearUsuario);

        $('buscar-usuario').addEventListener('input', (evento) => {
            estado.filtroTexto = evento.target.value;
            pintarUsuarios();
        });

        document.querySelectorAll('.fp-filtro').forEach((boton) => {
            boton.addEventListener('click', () => {
                estado.filtroEstado = boton.dataset.filtro;
                document.querySelectorAll('.fp-filtro').forEach((otro) => {
                    otro.setAttribute('aria-pressed', String(otro === boton));
                });
                pintarUsuarios();
            });
        });

        // Delegación: la tabla se vuelve a dibujar en cada cambio.
        $('tabla-usuarios').addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-accion]');
            if (!boton) return;

            const usuario = estado.usuarios.find((item) => String(item.id) === boton.dataset.id);
            if (!usuario) return;

            if (boton.dataset.accion === 'editar') abrirEditarUsuario(usuario);
            if (boton.dataset.accion === 'baja') cambiarEstadoUsuario(usuario, false);
            if (boton.dataset.accion === 'alta') cambiarEstadoUsuario(usuario, true);
            if (boton.dataset.accion === 'borrar') borrarUsuario(usuario);
        });

        $('socios-admin').addEventListener('click', (evento) => {
            const boton = evento.target.closest('.admin-save');
            if (boton) guardarSocio(boton);
        });

        $('socios-admin').addEventListener('change', (evento) => {
            const plan = evento.target.closest('.admin-plan');
            if (plan) pintarBloquePlan(plan.dataset.id);
        });

        $('programas-admin').addEventListener('click', (evento) => {
            const boton = evento.target.closest('.admin-programa-guardar');
            if (boton) guardarEntrenadorPrograma(boton);
        });

        $('ejercicio-form').addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const formulario = evento.currentTarget;

            await conCarga(formulario.querySelector('button[type="submit"]'), async () => {
                try {
                    await api('/admin/ejercicios', {
                        method: 'POST',
                        body: Object.fromEntries(new FormData(formulario))
                    });
                    formulario.reset();
                    await cargarTodo();
                    exito('Ejercicio agregado', 'Ya está disponible para armar rutinas.');
                } catch (fallo) {
                    mostrarFallo(fallo, 'No se pudo agregar el ejercicio');
                }
            });
        });

        $('conversaciones-admin').addEventListener('submit', async (evento) => {
            const formulario = evento.target.closest('.admin-reply');
            if (!formulario) return;
            evento.preventDefault();

            const entrada = formulario.querySelector('input');
            try {
                await api('/admin/conversaciones/' + formulario.dataset.id + '/mensajes', {
                    method: 'POST',
                    body: { mensaje: entrada.value }
                });
                entrada.value = '';
                await cargarConversaciones();
                exito('Respuesta enviada', 'El socio ya puede verla en su portal.');
            } catch (fallo) {
                mostrarFallo(fallo, 'No se pudo enviar la respuesta');
            }
        });

        $('conversaciones-admin').addEventListener('click', async (evento) => {
            const boton = evento.target.closest('[data-state]');
            if (!boton) return;

            try {
                await api('/admin/conversaciones/' + boton.dataset.id + '/estado', {
                    method: 'PUT',
                    body: { estado: boton.dataset.state }
                });
                await cargarConversaciones();
                exito('Estado actualizado', 'La conversación pasó a "' + ESTADOS_CONVERSACION[boton.dataset.state] + '".');
            } catch (fallo) {
                mostrarFallo(fallo, 'No se pudo cambiar el estado');
            }
        });

        $('actualizar-conversaciones').addEventListener('click', cargarConversaciones);

        $('cerrar-sesion').addEventListener('click', async () => {
            const confirmado = await confirmar({
                titulo: 'Cerrar sesión',
                descripcion: '¿Querés salir del panel de administración?',
                aceptar: 'Cerrar sesión'
            });
            if (!confirmado) return;

            window.FP.cerrarSesion();
        });
    };

    // ---------------------------------------------------------------- Inicio

    (async () => {
        estado.usuario = await window.FP.exigirSesion('admin');

        $('admin-nombre').textContent = estado.usuario.nombre;
        $('admin-avatar').textContent = estado.usuario.nombre
            .split(' ').map((parte) => parte[0]).slice(0, 2).join('').toUpperCase();

        enlazarEventos();

        try {
            await cargarTodo();
        } catch (fallo) {
            mostrarFallo(fallo, 'No se pudieron cargar los datos');
        }
    })();
})();
