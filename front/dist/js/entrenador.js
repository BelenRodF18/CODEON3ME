/* Panel del entrenador: sus programas y socios, y la creación de rutinas.
   Usa la API /entrenador/... a través de window.FP.api (ui.js). */

// Misma llamada a la API que el resto de los paneles: si la sesión
// vence o cambia de cuenta, vuelve al login con el motivo.
const api = window.FP.api;

// Escapa el texto antes de insertarlo en el HTML.
const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
}[c]));

// Cambia de sección del panel.
const show = (name) => {
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
    ).join('')}</select></label><label>Series<input class="exercise-series" type="number" min="1" value="3"></label><label>Repeticiones<input class="exercise-reps" value="10"></label>`;
    document.getElementById('rutina-ejercicios').appendChild(row);
};

let resumen = { socios: [], programas: [] };

// Texto del vínculo con el socio: personal y/o programas.
const vinculo = (socio) => [socio.personal ? 'Entrenamiento personal' : null, socio.programas]
    .filter(Boolean).join(' · ');

// Solo se ofrecen los programas tuyos en los que el socio está inscripto,
// más el entrenamiento personal si sos su entrenador personal.
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
    const data = await api('/entrenador/resumen');
    resumen = data;

    document.getElementById('programas-entrenador').innerHTML = data.programas.map((programa) =>
        `<div class="routine-item"><div class="routine-head"><strong>${escapeHtml(programa.nombre)}</strong><small>${programa.inscriptos} ${programa.inscriptos === 1 ? 'inscripto' : 'inscriptos'}</small></div></div>`
    ).join('') || '<div class="empty">No tenés programas a cargo.</div>';

    document.getElementById('socios-entrenador').innerHTML = data.socios.map((socio) =>
        `<div class="routine-item"><div class="routine-head"><strong>${escapeHtml(socio.nombre)}</strong><small>${escapeHtml(socio.membresia)}</small></div><p class="socio-meta">${escapeHtml(socio.nombre_usuario)} · ${escapeHtml(vinculo(socio))}</p></div>`
    ).join('') || '<div class="empty">No hay socios asignados.</div>';

    // Se conserva el socio elegido: armar varias rutinas seguidas para
    // el mismo socio no obliga a volver a buscarlo.
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
        repeticiones: row.querySelector('.exercise-reps').value
    }));

    try {
        const result = await api('/entrenador/rutinas', 'POST', values);
        window.FP.exito('Rutina creada', result.message);
        form.querySelector('[name=nombre]').value = '';
        form.querySelector('[name=descripcion]').value = '';
        await load();
    } catch (error) {
        window.FP.error('No se pudo crear la rutina', error.message);
    }
};

// Cerrar sesión.
document.getElementById('logout').onclick = (event) => {
    event.preventDefault();
    window.FP.cerrarSesion();
};

// Inicio: exige sesión de entrenador y carga el panel.
(async () => {
    await window.FP.exigirSesion('entrenador');
    await load();
})().catch(() => {});
