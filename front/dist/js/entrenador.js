/* Panel del entrenador: sus programas y socios, y la creación de rutinas. */

// Escapa el texto antes de insertarlo en el HTML.
const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
}[c]));

// Cambia de sección del panel.
const show = (name) => {
    if (name === 'evolucion') cargarEvolucion();
    document.querySelectorAll('.socio-view').forEach((view) => view.classList.toggle('active', view.id === 'view-' + name));
    document.querySelectorAll('.socio-nav button').forEach((button) => button.classList.toggle('active', button.dataset.view === name));
};
// Menú lateral: cada botón abre su sección.
document.querySelectorAll('[data-view]').forEach((button) => button.onclick = () => show(button.dataset.view));

// ------------------------------------------------ Filas de ejercicios (crear y editar rutinas)

// Fila de un ejercicio con sus botones para subirlo, bajarlo o quitarlo.
const crearFilaEjercicio = (exercises, datos = {}) => {
    const row = document.createElement('div');
    row.className = 'rutina-row';
    row.dataset.descanso = datos.descanso ?? 90;

    const opciones = exercises.map((exercise) =>
        `<option value="${exercise.id}"${String(exercise.id) === String(datos.id) ? ' selected' : ''}>${escapeHtml(exercise.nombre)} · ${escapeHtml(exercise.grupo)}</option>`);
    // Un ejercicio dado de baja ya no está en el catálogo activo, pero la rutina lo conserva.
    if (datos.id && !exercises.some((exercise) => String(exercise.id) === String(datos.id))) {
        opciones.unshift(`<option value="${datos.id}" selected>${escapeHtml(datos.nombre)} (de baja)</option>`);
    }

    row.innerHTML = `<div class="rutina-row-cab"><span class="rutina-orden"><span>Ejercicio</span> <b></b></span><span class="rutina-acciones">` +
        `<button type="button" class="fp-btn fp-btn--ghost fp-btn--sm" data-fila="subir" title="Subir" aria-label="Subir ejercicio">↑ Subir</button>` +
        `<button type="button" class="fp-btn fp-btn--ghost fp-btn--sm" data-fila="bajar" title="Bajar" aria-label="Bajar ejercicio">↓ Bajar</button>` +
        `<button type="button" class="fp-btn fp-btn--peligro-ghost fp-btn--sm" data-fila="quitar" title="Quitar" aria-label="Quitar ejercicio">Quitar</button></span></div>` +
        `<label class="rutina-ej">Ejercicio<select class="exercise-id">${opciones.join('')}</select></label>` +
        `<label>Series<input class="exercise-series" type="number" min="1" value="${escapeHtml(datos.series ?? 3)}"></label>` +
        `<label>Repeticiones<input class="exercise-reps" value="${escapeHtml(datos.repeticiones ?? '10')}"></label>` +
        `<label class="rutina-nota">Nota para el socio (opcional)<input class="exercise-notas" maxlength="255" placeholder="Ej. Bajá lento, 3 segundos" value="${escapeHtml(datos.notas ?? '')}"></label>`;
    return row;
};

// Numera las filas y apaga los botones que no se pueden usar (subir la primera, bajar la última, quitar la única).
const actualizarFilas = (contenedor) => {
    const filas = [...contenedor.querySelectorAll(':scope > .rutina-row')];
    filas.forEach((fila, indice) => {
        fila.querySelector('.rutina-orden b').textContent = indice + 1;
        fila.querySelector('[data-fila="subir"]').disabled = indice === 0;
        fila.querySelector('[data-fila="bajar"]').disabled = indice === filas.length - 1;
        fila.querySelector('[data-fila="quitar"]').disabled = filas.length === 1;
    });
};

// Agrega una fila de ejercicio al contenedor (al formulario de crear rutina si no se indica otro).
const addExercise = (exercises, datos = {}, contenedor = document.getElementById('rutina-ejercicios')) => {
    contenedor.appendChild(crearFilaEjercicio(exercises, datos));
    actualizarFilas(contenedor);
};

// Lee los ejercicios del contenedor en el orden en que están en pantalla.
const leerEjercicios = (contenedor) => [...contenedor.querySelectorAll(':scope > .rutina-row')].map((row) => ({
    id: row.querySelector('.exercise-id').value,
    series: row.querySelector('.exercise-series').value,
    repeticiones: row.querySelector('.exercise-reps').value,
    descanso: row.dataset.descanso,
    notas: row.querySelector('.exercise-notas').value
}));

// Botones Subir, Bajar y Quitar de cada ejercicio (sirven en el formulario y en la ventana de editar).
document.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-fila]');
    if (!boton || boton.disabled) return;

    const fila = boton.closest('.rutina-row');
    const contenedor = fila.parentElement;
    const accion = boton.dataset.fila;

    if (accion === 'subir' && fila.previousElementSibling) contenedor.insertBefore(fila, fila.previousElementSibling);
    if (accion === 'bajar' && fila.nextElementSibling) contenedor.insertBefore(fila.nextElementSibling, fila);
    if (accion === 'quitar' && contenedor.querySelectorAll(':scope > .rutina-row').length > 1) fila.remove();

    actualizarFilas(contenedor);
    // El foco sigue al ejercicio que se movió.
    if (accion !== 'quitar' && !boton.disabled) boton.focus();
});

let resumen = { socios: [], programas: [], ejercicios: [], rutinas: [] };

// Texto del vínculo con el socio: personal y/o programas.
const vinculo = (socio) => [socio.personal ? 'Entrenamiento personal' : null, socio.programas]
    .filter(Boolean).join(' · ');

// Programas disponibles para el socio elegido.
const updateProgramOptions = () => {
    const socio = resumen.socios.find((item) => String(item.id) === document.getElementById('rutina-socio').value);
    const opciones = socio
        ? (socio.personal ? ['<option value="">Entrenamiento personal</option>'] : []).concat(
            resumen.programas
                .filter((programa) => socio.programa_ids.includes(programa.id))
                .map((programa) => `<option value="${programa.id}">${escapeHtml(programa.nombre)}</option>`))
        : [];
    document.getElementById('rutina-programa').innerHTML = opciones.join('');
};

// Tarjeta de una rutina guardada: sus socios, sus ejercicios en orden y los botones para editarla o borrarla.
const tarjetaRutina = (routine) => {
    const ejercicios = routine.ejercicios.map((ejercicio) =>
        `<li><strong>${escapeHtml(ejercicio.nombre)}</strong> · ${escapeHtml(ejercicio.series)} × ${escapeHtml(ejercicio.repeticiones)}${ejercicio.notas ? ` <small class="socio-meta">— ${escapeHtml(ejercicio.notas)}</small>` : ''}</li>`
    ).join('');

    return `<div class="routine-item" style="cursor:default"><div class="routine-head"><strong>${escapeHtml(routine.nombre)}</strong><small>${routine.ejercicios.length} ${routine.ejercicios.length === 1 ? 'ejercicio' : 'ejercicios'}</small></div>` +
        `<p class="socio-meta">${escapeHtml(routine.programa || 'Entrenamiento personal')} · ${escapeHtml(routine.descripcion || 'Sin descripción')} · ${escapeHtml(routine.fecha)}</p>` +
        `<p class="socio-meta"><strong>Asignada a:</strong> ${escapeHtml(routine.socios || 'ningún socio')}</p>` +
        `<ol class="rutina-lista">${ejercicios}</ol>` +
        `<div class="fila-control"><button type="button" class="socio-button secondary" data-rutina-accion="editar" data-id="${routine.id}">Editar o reasignar</button>` +
        `<button type="button" class="socio-button secondary danger" data-rutina-accion="borrar" data-id="${routine.id}">Borrar</button></div></div>`;
};

// Carga programas, socios, ejercicios y rutinas del entrenador.
const load = async () => {
    const data = await API.request('/entrenador/resumen');
    if (data.status === 'error') throw new Error(data.message);
    resumen = data;

    document.getElementById('programas-entrenador').innerHTML = data.programas.map((programa) =>
        `<div class="routine-item"><div class="routine-head"><strong>${escapeHtml(programa.nombre)}</strong><small>${programa.inscriptos} ${programa.inscriptos === 1 ? 'inscripto' : 'inscriptos'}</small></div></div>`
    ).join('') || '<div class="empty">No tenés programas a cargo.</div>';

    document.getElementById('socios-entrenador').innerHTML = data.socios.map((socio) =>
        `<div class="routine-item"><div class="routine-head"><strong>${escapeHtml(socio.nombre)}</strong><small>${escapeHtml(socio.membresia)}</small></div><p class="socio-meta">${escapeHtml(socio.nombre_usuario)} · ${escapeHtml(vinculo(socio))}</p><button type="button" class="socio-button secondary" data-evolucion="${socio.id}" style="margin-top:10px">Ver evolución</button></div>`
    ).join('') || '<div class="empty">No hay socios asignados.</div>';

    const selectorEvolucion = document.getElementById('evolucion-socio');
    const evolucionElegido = selectorEvolucion.value;
    selectorEvolucion.innerHTML = data.socios.map((socio) =>
        `<option value="${socio.id}">${escapeHtml(socio.nombre)}</option>`
    ).join('');
    if (data.socios.some((socio) => String(socio.id) === evolucionElegido)) selectorEvolucion.value = evolucionElegido;

    const selectorSocio = document.getElementById('rutina-socio');
    const socioElegido = selectorSocio.value;
    selectorSocio.innerHTML = data.socios.map((socio) =>
        `<option value="${socio.id}">${escapeHtml(socio.nombre)}</option>`
    ).join('');
    if (data.socios.some((socio) => String(socio.id) === socioElegido)) selectorSocio.value = socioElegido;
    updateProgramOptions();

    document.getElementById('rutinas-entrenador').innerHTML = data.rutinas.map(tarjetaRutina).join('')
        || '<div class="empty">Aún no hay rutinas guardadas.</div>';

    document.getElementById('rutina-ejercicios').innerHTML = '';
    addExercise(data.ejercicios);
    // Botón "Agregar ejercicio".
    document.getElementById('agregar-ejercicio').onclick = () => addExercise(data.ejercicios);
};

// Al cambiar de socio se actualizan los programas disponibles.
document.getElementById('rutina-socio').onchange = updateProgramOptions;

// Crear la rutina y asignarla al socio.
document.getElementById('rutina-form').onsubmit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;

    const values = Object.fromEntries(new FormData(form));
    values.ejercicios = leerEjercicios(document.getElementById('rutina-ejercicios'));

    try {
        const result = await API.request('/entrenador/rutinas', 'POST', values);
        if (result.status === 'error') throw new Error(result.message);
        avisoExito('Rutina creada', result.message);
        form.querySelector('[name=nombre]').value = '';
        form.querySelector('[name=descripcion]').value = '';
        await load();
    } catch (error) {
        avisoError('No se pudo crear la rutina', error.message);
    }
};

// ------------------------------------------------ Editar, reasignar y borrar rutinas guardadas

// Socios que pueden tener una rutina del programa elegido (sin programa: los de entrenamiento personal).
const sociosCompatibles = (programaId) => resumen.socios.filter((socio) =>
    programaId ? socio.programa_ids.includes(Number(programaId)) : socio.personal);

// Ventana para editar una rutina: datos, programa, socios, y ejercicios con su orden.
const editarRutina = (rutina) => {
    const opcionesPrograma = ['<option value="">Entrenamiento personal</option>'].concat(
        resumen.programas.map((programa) =>
            `<option value="${programa.id}"${Number(programa.id) === rutina.programa_id ? ' selected' : ''}>${escapeHtml(programa.nombre)}</option>`)
    ).join('');

    return abrirModal({
        titulo: 'Editar rutina',
        descripcion: 'Cambiá los datos, reasigná la rutina a otros socios o reordená los ejercicios.',
        ancho: true,
        aceptar: 'Guardar cambios',
        cuerpo: `<div class="fp-form">` +
            `<div class="fp-campo fp-campo--full"><label for="er-nombre">Nombre</label><input id="er-nombre" name="nombre" required maxlength="100" value="${escapeHtml(rutina.nombre)}"></div>` +
            `<div class="fp-campo fp-campo--full"><label for="er-descripcion">Descripción</label><input id="er-descripcion" name="descripcion" maxlength="255" value="${escapeHtml(rutina.descripcion || '')}"></div>` +
            `<div class="fp-campo fp-campo--full"><label for="er-programa">Programa</label><select id="er-programa" name="programa_id">${opcionesPrograma}</select></div>` +
            `<div class="fp-campo fp-campo--full"><label>Asignada a</label><div id="er-socios" class="er-socios"></div><small>Solo aparecen los socios de ese programa (o de tu entrenamiento personal). Marcá a quién le llega la rutina.</small></div>` +
            `<div class="fp-campo fp-campo--full"><label>Ejercicios, en el orden en que se hacen</label><div id="er-ejercicios"></div>` +
            `<button type="button" class="fp-btn fp-btn--ghost fp-btn--sm" id="er-agregar" style="margin-top:8px">Agregar ejercicio</button></div></div>`,
        alAbrir: (formulario) => {
            const contenedor = formulario.querySelector('#er-ejercicios');
            const selectorPrograma = formulario.querySelector('#er-programa');
            const cajaSocios = formulario.querySelector('#er-socios');

            rutina.ejercicios.forEach((ejercicio) => addExercise(resumen.ejercicios, ejercicio, contenedor));
            if (!rutina.ejercicios.length) addExercise(resumen.ejercicios, {}, contenedor);

            // Lista de socios que corresponden al programa elegido; se conservan las casillas que siguen siendo válidas.
            const pintarSocios = (marcados) => {
                const lista = sociosCompatibles(selectorPrograma.value);
                cajaSocios.innerHTML = lista.map((socio) =>
                    `<label class="er-socio"><input type="checkbox" name="socio" value="${socio.id}"${marcados.includes(socio.id) ? ' checked' : ''}> ${escapeHtml(socio.nombre)} <small class="socio-meta">${escapeHtml(socio.membresia)}</small></label>`
                ).join('') || '<div class="empty">Ningún socio tuyo está en ese programa.</div>';
            };
            const marcadosAhora = () => [...cajaSocios.querySelectorAll('input:checked')].map((casilla) => Number(casilla.value));

            pintarSocios(rutina.socio_ids);
            selectorPrograma.onchange = () => pintarSocios(marcadosAhora());
            formulario.querySelector('#er-agregar').onclick = () => addExercise(resumen.ejercicios, {}, contenedor);
        },
        alAceptar: async (formulario) => {
            const socios = [...formulario.querySelectorAll('#er-socios input:checked')].map((casilla) => Number(casilla.value));
            if (!socios.length) {
                avisoError('Falta asignar la rutina', 'Marcá al menos un socio.');
                return false;
            }

            const resultado = await API.request('/entrenador/rutinas/' + rutina.id, 'PUT', {
                nombre: formulario.querySelector('#er-nombre').value,
                descripcion: formulario.querySelector('#er-descripcion').value,
                programa_id: formulario.querySelector('#er-programa').value,
                socio_ids: socios,
                ejercicios: leerEjercicios(formulario.querySelector('#er-ejercicios'))
            });
            if (resultado.status === 'error') {
                avisoError('No se pudo guardar la rutina', resultado.message);
                return false;
            }

            await load();
            avisoExito('Rutina actualizada', resultado.message);
            return true;
        }
    });
};

// Borra una rutina después de confirmar.
const borrarRutina = async (rutina) => {
    const confirmado = await confirmar({
        titulo: 'Borrar rutina',
        descripcion: `«${rutina.nombre}» se va a eliminar y ${rutina.socios ? 'dejará de verse en el portal de: ' + rutina.socios : 'no la tiene ningún socio'}. Las series que los socios ya registraron se conservan.`,
        aceptar: 'Borrar rutina',
        peligro: true
    });
    if (!confirmado) return;

    const resultado = await API.request('/entrenador/rutinas/' + rutina.id, 'DELETE');
    if (resultado.status === 'error') {
        avisoError('No se pudo borrar la rutina', resultado.message);
        return;
    }
    await load();
    avisoExito('Rutina eliminada', rutina.nombre);
};

// Botones "Editar o reasignar" y "Borrar" de cada rutina guardada.
document.getElementById('rutinas-entrenador').addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-rutina-accion]');
    if (!boton) return;
    const rutina = resumen.rutinas.find((item) => String(item.id) === boton.dataset.id);
    if (!rutina) return;
    if (boton.dataset.rutinaAccion === 'editar') editarRutina(rutina);
    else borrarRutina(rutina);
});

// Cerrar sesión.
document.getElementById('logout').onclick = async (event) => {
    event.preventDefault();
    await API.request('/logout', 'POST');
    window.location.href = '../../index.html';
};

// Inicio: comprueba la sesión y carga el panel.
(async () => {
    const sesion = await API.request('/me');
    if (sesion.status !== 'success' || sesion.usuario.rol !== 'entrenador') {
        window.location.href = 'login.html';
        return;
    }
    await load();
})().catch(() => {});

// ---------------------------------------------------------------- Evolución del socio

// Carga y dibuja la evolución del socio elegido.
const cargarEvolucion = async () => {
    const contenedor = document.getElementById('evolucion-detalle');
    const socio = document.getElementById('evolucion-socio').value;
    if (!socio) {
        contenedor.innerHTML = '<div class="empty">Todavía no tenés socios asignados.</div>';
        return;
    }

    contenedor.innerHTML = '<div class="empty">Cargando...</div>';
    const datos = await API.request('/entrenador/socios/' + socio + '/evolucion');
    if (datos.status === 'error') {
        contenedor.innerHTML = '';
        avisoError('No se pudo cargar la evolución', datos.message);
        return;
    }

    const r = datos.resumen;
    const kpi = (valor, etiqueta) => `<div class="record-item"><strong>${escapeHtml(valor)}</strong><small>${escapeHtml(etiqueta)}</small></div>`;
    const maxDias = Math.max(1, ...datos.semanas.map((semana) => semana.dias));
    const barras = datos.semanas.map((semana) =>
        `<div class="evo-col" title="${semana.dias} días, ${semana.series} series"><span class="evo-num">${semana.dias}</span><div class="evo-bar" style="height:${Math.round((semana.dias / maxDias) * 100)}%"></div><small>${escapeHtml(semana.desde)}</small></div>`
    ).join('');

    const records = datos.records.map((rec) => {
        const mejora = rec.mejora > 0 ? `<small class="evo-sube">▲ +${escapeHtml(rec.mejora)} kg desde el inicio</small>` : '<small>Sin mejora todavía</small>';
        return `<div class="record-item"><span>${escapeHtml(rec.ejercicio)}</span><strong>${escapeHtml(rec.peso)} kg</strong><small>${escapeHtml(rec.repeticiones)} reps · ${escapeHtml(rec.fecha)}</small>${mejora}</div>`;
    }).join('') || '<div class="empty">Todavía no registró series.</div>';

    const series = datos.series.map((serie) =>
        `<div class="evo-serie"><span class="evo-serie-nombre">${escapeHtml(serie.ejercicio)}</span><span class="socio-meta">${escapeHtml(serie.fecha)}</span><strong>${escapeHtml(serie.peso)} kg</strong><span>${escapeHtml(serie.repeticiones)} reps${serie.record ? ' · 🏆 récord' : ''}</span><small class="socio-meta">${escapeHtml(serie.sensacion || '')}</small></div>`
    ).join('') || '<div class="empty">Sin series registradas.</div>';

    contenedor.innerHTML =
        `<div class="socio-card full"><h3>${escapeHtml(datos.socio.nombre)}</h3><p class="socio-meta">${escapeHtml(datos.socio.vinculo || 'Entrenamiento personal')} · Membresía: ${escapeHtml(datos.socio.membresia)}</p>` +
        `<div class="records-grid">${kpi(r.series, 'series registradas')}${kpi(r.dias_entrenados, 'días entrenados')}${kpi(r.records, 'récords personales')}${kpi(r.visitas_30_dias, 'visitas al gym (30 días)')}</div>` +
        `<p class="socio-meta" style="margin-top:10px">${r.series ? 'Primer registro: ' + escapeHtml(r.desde) + ' · Último: ' + escapeHtml(r.ultima) : 'Todavía no registró entrenamientos.'}</p></div>` +
        `<div class="socio-card full" style="margin-top:20px"><h3>Constancia: días entrenados por semana</h3><p class="socio-meta">Últimas 8 semanas (cada barra empieza el lunes indicado).</p><div class="evo-chart">${barras}</div></div>` +
        `<div class="socio-card full" style="margin-top:20px"><h3>Récords y mejora</h3><div class="records-grid">${records}</div></div>` +
        `<div class="socio-card full" style="margin-top:20px"><h3>Últimas series</h3>${series}</div>`;
};

document.getElementById('evolucion-socio').onchange = cargarEvolucion;
// Botón "Ver evolución" de cada socio.
document.getElementById('socios-entrenador').addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-evolucion]');
    if (!boton) return;
    document.getElementById('evolucion-socio').value = boton.dataset.evolucion;
    show('evolucion');
});
