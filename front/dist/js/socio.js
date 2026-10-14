/* Portal del socio */

const state = { me: null, timer: null, qrTimer: null, qrSeconds: 0, workoutTimer: null, workoutRemaining: 0, workoutRunning: false, notices: 0 };
const $ = (id) => document.getElementById(id);
// Escapa el texto antes de insertarlo en el HTML.
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));

// Aviso breve abajo a la derecha.
const toast = (message) => {
    $('toast').textContent = message;
    $('toast').classList.add('show');
    setTimeout(() => $('toast').classList.remove('show'), 3000);
};

// Cambia de sección y carga sus datos.
const go = (view) => {
    document.querySelectorAll('.socio-view').forEach((el) => el.classList.toggle('active', el.id === 'view-' + view));
    document.querySelectorAll('.socio-nav button').forEach((el) => el.classList.toggle('active', el.dataset.view === view));
    if (view === 'acceso') makeQr();
    if (view === 'mantenimiento') loadReports().then(markNoticesRead);
    if (view === 'rendimiento') loadPerformance();
};
// Menú lateral: cada botón abre su sección.
document.querySelectorAll('[data-view]').forEach((button) => button.addEventListener('click', () => go(button.dataset.view)));
// Botones que llevan a otra sección (ej. "Ver mi QR").
document.querySelectorAll('[data-go]').forEach((button) => button.addEventListener('click', () => go(button.dataset.go)));

// Fecha AAAA-MM-DD a "30 de septiembre".
const formatDate = (value) => value ? new Date(value + 'T00:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'long' }) : '--';

// Tarjeta de una rutina con sus ejercicios.
const renderRoutine = (routine) => `<div class="routine-item"><div class="routine-head"><strong>${routine.nombre}</strong><small>${routine.programa || 'Entrenamiento personal'} · ${routine.entrenador || 'Equipo Fit Power'}</small></div><p class="socio-meta">${routine.descripcion || 'Rutina personalizada para ti.'}</p><div class="exercise-list">${(routine.ejercicios || []).map((exercise) =>
    `<div class="exercise-row"><span>${exercise.nombre}</span><span>${exercise.series} x ${exercise.repeticiones} · ${exercise.descanso_segundos}s</span></div>`
).join('') || '<div class="empty">Tu entrenador aún está cargando los ejercicios.</div>'}</div></div>`;

// Tarjeta de un programa con su coach.
const renderProgram = (program) => `<div class="routine-item" style="cursor:default"><div class="routine-head" style="align-items:center">${program.entrenador_foto ? `<img src="../assets/imagenes/${program.entrenador_foto}" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover;margin-right:12px">` : ''}<div style="flex:1"><strong>${program.nombre}</strong><p class="socio-meta" style="margin:2px 0 0">Coach: ${program.entrenador || 'por asignar'}</p></div><a class="socio-button secondary" href="programas.html?programa=${program.slug}">Ver programa</a></div></div>`;

// Abrir o cerrar una rutina al tocarla.
const bindRoutineToggle = () => document.querySelectorAll('.routine-item').forEach((item) => item.addEventListener('click', () => item.classList.toggle('open')));

// Novedades de los reportes: respuestas del equipo y cambios de estado.
const renderNotices = (data) => {
    const badge = $('avisos-badge');
    badge.hidden = !data.pendientes;
    badge.textContent = data.pendientes;
    $('avisos-card').hidden = !data.pendientes;
    $('avisos-list').innerHTML = data.avisos.slice(0, 3).map((notice) =>
        `<div class="report-item"><header><strong>${escapeHtml(notice.etiqueta || 'Reporte')}</strong><small class="socio-meta">${escapeHtml(notice.fecha)}</small></header><p style="margin:6px 0 0">${escapeHtml(notice.mensaje)}</p></div>`
    ).join('');
};

// Consulta las novedades; si llegó una nueva, la anuncia.
const checkNotices = async (announce = false) => {
    try {
        const data = await API.request('/socio/avisos');
        if (data.status === 'error') throw new Error(data.message);
        if (announce && data.pendientes > state.notices) {
            const latest = data.avisos[0];
            toast(`${latest.etiqueta || 'Tu reporte'}: ${latest.mensaje}`);
        }
        state.notices = data.pendientes;
        renderNotices(data);
    } catch (error) {}
};

// Marca las novedades como vistas al abrir las conversaciones.
const markNoticesRead = async () => {
    if (!state.notices) return;
    try {
        const resultado = await API.request('/socio/avisos/leidos', 'POST');
        if (resultado.status === 'error') throw new Error(resultado.message);
        state.notices = 0;
        renderNotices({ pendientes: 0, avisos: [] });
    } catch (error) {}
};

// Inicio: membresía, afluencia, programas y rutinas.
const loadDashboard = async () => {
    const data = await API.request('/socio/resumen');
    if (data.status === 'error') throw new Error(data.message);
    const user = state.me.usuario;
    $('saludo').textContent = 'Hola, ' + user.nombre.split(' ')[0];
    $('avatar').textContent = user.nombre.split(' ').map((part) => part[0]).slice(0, 2).join('');
    const membership = data.membresia;
    $('membresia-plan').textContent = membership
        ? membership.plan + (membership.entrenador ? ` · Entrenador/a personal: ${membership.entrenador}` : '')
        : 'Sin membresía registrada';
    $('membresia-fecha').textContent = membership ? 'Hasta ' + formatDate(membership.fecha_vencimiento) : '--';
    const isOk = membership && membership.estado === 'al_dia';
    $('membresia-estado').textContent = isOk ? 'Al día' : 'Revisar estado';
    $('membresia-estado').className = 'status-pill ' + (isOk ? 'ok' : 'warn');
    $('membresia-mensaje').textContent = isOk ? 'Tu acceso está habilitado para entrenar este mes.' : 'Acércate a recepción para renovar tu membresía.';
    $('aforo').textContent = data.aforo + ' personas';
    $('home-programas').innerHTML = data.programas.map(renderProgram).join('') || '<div class="empty">Todavía no estás inscripto en ningún programa. Consultá en recepción.</div>';
    $('home-rutinas').innerHTML = data.rutinas.map(renderRoutine).join('') || '<div class="empty">Todavía no tienes rutinas asignadas.</div>';
    bindRoutineToggle();
    const routines = await API.request('/socio/rutinas');
    if (routines.status === 'error') throw new Error(routines.message);
    $('rutinas-list').innerHTML = routines.rutinas.map(renderRoutine).join('') || '<div class="empty">Todavía no tienes rutinas asignadas.</div>';
    bindRoutineToggle();
};

// Genera el QR de acceso y lo renueva cuando vence.
const makeQr = async () => {
    try {
        const data = await API.request('/socio/qr');
        if (data.status === 'error') throw new Error(data.message);
        $('qr-code').innerHTML = '';
        new QRCode($('qr-code'), { text: data.token, width: 185, height: 185, colorDark: '#0a0d0b', colorLight: '#ffffff' });
        state.qrSeconds = data.vigencia_segundos || 60;
        $('qr-message').textContent = 'Código listo para presentar en recepción.';
        clearInterval(state.qrTimer);
        state.qrTimer = setInterval(() => {
            state.qrSeconds -= 1;
            $('qr-countdown').textContent = state.qrSeconds > 0 ? `Se renueva en ${state.qrSeconds}s` : 'Renovando...';
            if (state.qrSeconds <= 0) makeQr();
        }, 1000);
    } catch (error) {
        $('qr-message').textContent = error.message;
    }
};

// Rendimiento: récords, historial de series y ejercicios.
const loadPerformance = async () => {
    const data = await API.request('/socio/rendimiento');
    if (data.status === 'error') throw new Error(data.message);
    const chosen = $('serie-ejercicio').value;
    $('serie-ejercicio').innerHTML = data.ejercicios.map((exercise) => `<option value="${exercise.id}">${exercise.nombre}</option>`).join('');
    if (data.ejercicios.some((exercise) => String(exercise.id) === chosen)) $('serie-ejercicio').value = chosen;
    $('records-list').innerHTML = data.records.length
        ? data.records.map((record) => `<div class="record-item"><span>${record.ejercicio}</span><strong>${Number(record.peso)} kg</strong><small>${record.repeticiones} reps · ${record.fecha} · ${record.series} ${record.series === 1 ? 'serie' : 'series'} registradas</small></div>`).join('')
        : '<div class="empty">Tus récords aparecen acá cuando registres tu primera serie.</div>';
    $('series-list').innerHTML = data.series.length
        ? '<div class="chart-row"><strong>Ejercicio</strong><strong>Peso</strong><strong>Reps</strong><strong>Fecha</strong></div>' + data.series.map((series) =>
            `<div class="chart-row" data-series-id="${series.id}"><span>${series.ejercicio}${series.record ? ' <span class="tag ok">Récord</span>' : ''}</span><strong>${Number(series.peso)} kg</strong><span>${series.repeticiones}</span><span>${series.fecha}</span></div>`
          ).join('')
        : '<div class="empty">Registra tu primera serie para comenzar el historial.</div>';
};

// Conversaciones con el equipo y sus chats.
const loadReports = async () => {
    const data = await API.request('/socio/reportes');
    if (data.status === 'error') throw new Error(data.message);
    $('reportes-list').innerHTML = data.reportes.length
        ? data.reportes.map((report) => `<div class="report-item"><header><strong>${report.tipo === 'sugerencia' ? 'Sugerencia: ' : ''}${report.etiqueta}${Number(report.sin_leer) ? ` <span class="tag suggestion">${report.sin_leer} ${Number(report.sin_leer) === 1 ? 'novedad' : 'novedades'}</span>` : ''}</strong><span class="tag ${report.tipo === 'sugerencia' ? 'suggestion' : ''} ${report.estado === 'solucionado' ? 'ok' : ''}">${({ abierto: 'Abierto', en_revision: 'En revisión', en_reparacion: 'En reparación', solucionado: 'Solucionado' })[report.estado] || report.estado}</span></header><p>${report.descripcion}</p><small class="socio-meta">${report.creado} · ${report.tipo === 'sugerencia' ? 'Enviada al administrador' : 'Enviada a mantenimiento'}</small><div class="chat-box"><div class="chat-messages" id="chat-${report.id}">Cargando conversación...</div><form class="chat-form" data-report-id="${report.id}" style="display:flex;gap:7px"><input name="mensaje" placeholder="Escribe un mensaje..." required><button class="socio-button" type="submit">Enviar</button></form></div></div>`).join('')
        : '<div class="empty">Aún no tienes conversaciones.</div>';
    // Cada chat envía su mensaje.
    document.querySelectorAll('.chat-form').forEach((form) => form.addEventListener('submit', sendMessage));
    data.reportes.forEach(loadMessages);
};

// Mensajes del chat de un reporte.
const loadMessages = async (report) => {
    try {
        const data = await API.request(`/socio/reportes/${report.id}/mensajes`);
        if (data.status === 'error') throw new Error(data.message);
        const box = $('chat-' + report.id);
        box.innerHTML = data.mensajes.map((message) => `<div class="chat-message"><strong>${message.nombre}:</strong> ${message.mensaje}</div>`).join('');
        box.scrollTop = box.scrollHeight;
    } catch (error) {}
};

// Envía un mensaje en el chat de un reporte.
const sendMessage = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const input = form.querySelector('input');
    try {
        const resultado = await API.request(`/socio/reportes/${form.dataset.reportId}/mensajes`, 'POST', { mensaje: input.value });
        if (resultado.status === 'error') throw new Error(resultado.message);
        input.value = '';
        loadReports();
        toast('Mensaje enviado');
    } catch (error) {
        toast(error.message);
    }
};

// Ajusta el formulario según sea reporte o sugerencia.
const updateConversationForm = () => {
    const suggestion = $('reporte-tipo').value === 'sugerencia';
    $('foto-field').style.display = suggestion ? 'none' : 'grid';
    $('reporte-submit').textContent = suggestion ? 'Enviar sugerencia al admin' : 'Enviar reporte a mantenimiento';
};
// Reporte o sugerencia: cambia el formulario.
$('reporte-tipo').addEventListener('change', updateConversationForm);
updateConversationForm();

// Enviar un reporte (con foto opcional) o una sugerencia.
$('reporte-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const type = $('reporte-tipo').value;
    const body = new FormData();
    body.append('tipo', type);
    body.append('etiqueta', $('reporte-etiqueta').value);
    body.append('descripcion', $('reporte-descripcion').value);
    if (type === 'mantenimiento' && $('reporte-foto').files[0]) body.append('foto', $('reporte-foto').files[0]);
    try {
        const resultado = await API.request('/socio/reportes', 'POST', body);
        if (resultado.status === 'error') throw new Error(resultado.message);
        event.target.reset();
        updateConversationForm();
        loadReports();
        toast(type === 'sugerencia' ? 'Sugerencia enviada al administrador' : 'Reporte creado para mantenimiento');
    } catch (error) {
        toast(error.message);
    }
});

// Registrar una serie y arrancar el descanso.
$('serie-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const descanso = Number($('serie-descanso').value);
    try {
        const saved = await API.request('/socio/series', 'POST', {
            ejercicio_id: $('serie-ejercicio').value,
            peso: $('serie-peso').value,
            repeticiones: $('serie-reps').value,
            descanso_segundos: descanso,
            sensacion: $('serie-sensacion').value
        });
        if (saved.status === 'error') throw new Error(saved.message);
        loadPerformance();
        startTimer(descanso);
        toast(saved.record ? '¡Nuevo récord personal!' : 'Serie guardada');
    } catch (error) {
        toast(error.message);
    }
});

// Cronómetro de descanso después de registrar una serie.
const startTimer = (seconds) => {
    clearInterval(state.timer);
    $('timer').hidden = false;
    let left = seconds;
    const tick = () => {
        $('timer-value').textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
        if (left <= 0) {
            clearInterval(state.timer);
            navigator.vibrate?.([200, 100, 200]);
            toast('Descanso terminado');
        }
        left -= 1;
    };
    tick();
    state.timer = setInterval(tick, 1000);
};

// Deja solo números en los campos del cronómetro.
const cleanTimerInput = (input, maximum) => {
    input.value = input.value.replace(/[^0-9]/g, '').slice(0, 2);
    const value = Math.min(maximum, Number.parseInt(input.value || '0', 10));
    input.value = String(value);
    return value;
};

// Duración elegida en segundos.
const workoutTime = () => (cleanTimerInput($('workout-minutes'), 99) * 60) + cleanTimerInput($('workout-seconds'), 59);

// Muestra el tiempo restante del cronómetro.
const renderWorkoutTimer = () => {
    const left = Math.max(0, state.workoutRemaining);
    $('workout-timer-value').textContent = `${String(Math.floor(left / 60)).padStart(2, '0')}:${String(left % 60).padStart(2, '0')}`;
};

// Pausa o termina el cronómetro.
const stopWorkoutTimer = (finished = false) => {
    clearInterval(state.workoutTimer);
    state.workoutTimer = null;
    state.workoutRunning = false;
    $('workout-timer-state').textContent = finished ? 'Terminado' : 'Pausado';
    if (finished) {
        navigator.vibrate?.([250, 120, 250]);
        toast('Cronómetro terminado');
    }
};

// Inicia o reanuda el cronómetro de entrenamiento.
const startWorkoutTimer = () => {
    if (!state.workoutRemaining) state.workoutRemaining = workoutTime();
    if (state.workoutRemaining <= 0) return toast('Indica una duración mayor a cero');
    clearInterval(state.workoutTimer);
    state.workoutRunning = true;
    $('workout-timer-state').textContent = 'En marcha';
    renderWorkoutTimer();
    state.workoutTimer = setInterval(() => {
        state.workoutRemaining -= 1;
        renderWorkoutTimer();
        if (state.workoutRemaining <= 0) stopWorkoutTimer(true);
    }, 1000);
};

// Vuelve el cronómetro al tiempo elegido.
const resetWorkoutTimer = () => {
    clearInterval(state.workoutTimer);
    state.workoutRemaining = workoutTime();
    state.workoutRunning = false;
    $('workout-timer-state').textContent = 'Listo';
    renderWorkoutTimer();
};

// Detener el cronómetro de descanso.
$('stop-timer').addEventListener('click', () => {
    clearInterval(state.timer);
    $('timer').hidden = true;
});
// Generar un QR nuevo.
$('refresh-qr').addEventListener('click', makeQr);

// Controles del cronómetro de entrenamiento.
$('workout-start').addEventListener('click', startWorkoutTimer);
$('workout-pause').addEventListener('click', () => stopWorkoutTimer());
$('workout-reset').addEventListener('click', resetWorkoutTimer);
// Al editar minutos o segundos, el cronómetro se reinicia.
$('workout-minutes').addEventListener('input', (event) => {
    cleanTimerInput(event.currentTarget, 99);
    resetWorkoutTimer();
});
$('workout-seconds').addEventListener('input', (event) => {
    cleanTimerInput(event.currentTarget, 59);
    resetWorkoutTimer();
});

// Cerrar sesión.
$('cerrar-sesion').addEventListener('click', async () => {
    await API.request('/logout', 'POST');
    window.location.href = '../../index.html';
});

// Inicio: comprueba la sesión, carga el portal y revisa novedades cada 30 s.
(async () => {
    try {
        const sesion = await API.request('/me');
        if (sesion.status !== 'success' || sesion.usuario.rol !== 'socio') {
            window.location.href = 'login.html';
            return;
        }
        state.me = { usuario: sesion.usuario };
        await loadDashboard();
        await checkNotices(true);
        setInterval(() => checkNotices(true), 30000);
    } catch (error) {
        toast(error.message);
    }
})();
