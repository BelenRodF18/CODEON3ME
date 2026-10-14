/* Panel del personal de mantenimiento: reportes de máquinas, estado y chat con el socio. */

const reports = document.getElementById('reports');

// Escapa el texto antes de insertarlo en el HTML.
const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
}[c]));

const ESTADOS = { abierto: 'Abierto', en_revision: 'En revisión', en_reparacion: 'En reparación', solucionado: 'Solucionado' };

// Carga el chat de un reporte.
const loadMessages = async (id, box) => {
    const data = await API.request(`/personal/conversaciones/${id}/mensajes`);
    if (data.status === 'error') throw new Error(data.message);
    box.innerHTML = data.mensajes.map((message) =>
        `<div class="chat-message"><strong>${escapeHtml(message.nombre)}:</strong> ${escapeHtml(message.mensaje)}</div>`
    ).join('') || '<small>No hay mensajes.</small>';
};

// Carga los reportes con su estado y chat.
const load = async () => {
    const data = await API.request('/personal/conversaciones');
    if (data.status === 'error') throw new Error(data.message);

    reports.innerHTML = data.conversaciones.map((item) => `<article class="socio-card full">
        <div class="socio-status">
            <div><h3>${escapeHtml(item.etiqueta)}</h3><p>${escapeHtml(item.descripcion)}</p></div>
            <span class="tag ${item.estado === 'solucionado' ? 'ok' : ''}">${escapeHtml(ESTADOS[item.estado] || item.estado)}</span>
        </div>
        <small class="socio-meta">${escapeHtml(item.socio)} · ${escapeHtml(item.creado)}</small>
        <div class="fila-control">
            <select class="state" data-id="${item.id}" aria-label="Estado del reporte">
                ${Object.entries(ESTADOS).map(([valor, texto]) =>
                    `<option value="${valor}" ${item.estado === valor ? 'selected' : ''}>${texto}</option>`).join('')}
            </select>
            <button class="socio-button save" data-id="${item.id}">Guardar estado</button>
        </div>
        <div class="chat-box">
            <strong>Chat con el socio</strong>
            <div class="messages chat-messages">Cargando...</div>
            <form class="reply fila-control" data-id="${item.id}">
                <input required placeholder="Responder al socio" aria-label="Respuesta al socio">
                <button class="socio-button secondary">Enviar</button>
            </form>
        </div>
    </article>`).join('') || '<div class="empty">No hay reportes de mantenimiento.</div>';

    for (const item of data.conversaciones) {
        const form = document.querySelector(`.reply[data-id="${item.id}"]`);
        await loadMessages(item.id, form.closest('.socio-card').querySelector('.messages'));
    }

    // Guardar el estado de un reporte.
    document.querySelectorAll('.save').forEach((button) => button.onclick = async () => {
        const state = document.querySelector(`.state[data-id="${button.dataset.id}"]`).value;
        try {
            const resultado = await API.request(`/personal/conversaciones/${button.dataset.id}/estado`, 'PUT', { estado: state });
            if (resultado.status === 'error') throw new Error(resultado.message);
            avisoExito('Estado actualizado', 'El reporte pasó a "' + ESTADOS[state] + '".');
            load();
        } catch (error) {
            avisoError('No se pudo cambiar el estado', error.message);
        }
    });

    // Responder al socio en el chat.
    document.querySelectorAll('.reply').forEach((form) => form.onsubmit = async (event) => {
        event.preventDefault();
        const input = form.querySelector('input');
        try {
            const resultado = await API.request(`/personal/conversaciones/${form.dataset.id}/mensajes`, 'POST', { mensaje: input.value });
            if (resultado.status === 'error') throw new Error(resultado.message);
            input.value = '';
            load();
        } catch (error) {
            avisoError('No se pudo enviar la respuesta', error.message);
        }
    });
};

// Recargar los reportes.
document.getElementById('refresh').onclick = () => load().catch((error) => reports.textContent = error.message);

// Cerrar sesión.
document.getElementById('logout').onclick = async (event) => {
    event.preventDefault();
    await API.request('/logout', 'POST');
    window.location.href = '../../index.html';
};

// Inicio: comprueba la sesión y carga los reportes.
(async () => {
    const sesion = await API.request('/me');
    if (sesion.status !== 'success' || sesion.usuario.rol !== 'personal') {
        window.location.href = 'login.html';
        return;
    }
    await load();
})().catch((error) => { reports.textContent = error.message; });
