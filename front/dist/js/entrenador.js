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

// Agrega una fila de ejercicio al formulario de rutina.
const addExercise = (exercises) => {
    const row = document.createElement('div');
    row.className = 'rutina-row';
    row.innerHTML = `<label>Ejercicio<select class="exercise-id">${exercises.map((exercise) =>
        `<option value="${exercise.id}">${escapeHtml(exercise.nombre)} · ${escapeHtml(exercise.grupo)}</option>`
    ).join('')}</select></label><label>Series<input class="exercise-series" type="number" min="1" value="3"></label><label>Repeticiones<input class="exercise-reps" value="10"></label><label class="rutina-nota">Nota para el socio (opcional)<input class="exercise-notas" maxlength="255" placeholder="Ej. Bajá lento, 3 segundos"></label>`;
    document.getElementById('rutina-ejercicios').appendChild(row);
};

let resumen = { socios: [], programas: [] };

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

    document.getElementById('rutinas-entrenador').innerHTML = data.rutinas.map((routine) =>
        `<div class="routine-item"><div class="routine-head"><strong>${escapeHtml(routine.nombre)}</strong><small>${escapeHtml(routine.socio)}</small></div><p class="socio-meta">${escapeHtml(routine.programa || 'Entrenamiento personal')} · ${escapeHtml(routine.descripcion || 'Sin descripción')} · ${routine.fecha}</p></div>`
    ).join('') || '<div class="empty">Aún no hay rutinas guardadas.</div>';

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
    values.ejercicios = [...document.querySelectorAll('.rutina-row')].map((row) => ({
        id: row.querySelector('.exercise-id').value,
        series: row.querySelector('.exercise-series').value,
        repeticiones: row.querySelector('.exercise-reps').value,
        notas: row.querySelector('.exercise-notas').value
    }));

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
        `<div class="socio-card full" style="margin-top:18px"><h3>Constancia: días entrenados por semana</h3><p class="socio-meta">Últimas 8 semanas (cada barra empieza el lunes indicado).</p><div class="evo-chart">${barras}</div></div>` +
        `<div class="socio-card full" style="margin-top:18px"><h3>Récords y mejora</h3><div class="records-grid">${records}</div></div>` +
        `<div class="socio-card full" style="margin-top:18px"><h3>Últimas series</h3>${series}</div>`;
};

document.getElementById('evolucion-socio').onchange = cargarEvolucion;
// Botón "Ver evolución" de cada socio.
document.getElementById('socios-entrenador').addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-evolucion]');
    if (!boton) return;
    document.getElementById('evolucion-socio').value = boton.dataset.evolucion;
    show('evolucion');
});
