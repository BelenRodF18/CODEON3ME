/* Panel del personal de mantenimiento: reportes de máquinas, estado y chat con el socio.
   Usa la API /personal/... a través de window.FP.api (ui.js). */

const reports = document.getElementById('reports');

// Misma llamada a la API que el resto de los paneles: si la sesión
// vence o cambia de cuenta, vuelve al login con el motivo.
const api = window.FP.api;

// Escapa el texto antes de insertarlo en el HTML.
const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
}[c]));

const ESTADOS = { abierto: 'Abierto', en_revision: 'En revisión', en_reparacion: 'En reparación', solucionado: 'Solucionado' };

// Carga el chat de un reporte.
const loadMessages = async (id, box) => {
    const data = await api(`/personal/conversaciones/${id}/mensajes`);
    box.innerHTML = data.mensajes.map((message) =>
        `<div class="chat-message"><strong>${escapeHtml(message.nombre)}:</strong> ${escapeHtml(message.mensaje)}</div>`
    ).join('') || '<small>No hay mensajes.</small>';
};

// Carga los reportes con su estado y chat.
const load = async () => {
    const data = await api('/personal/conversaciones');

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
            await api(`/personal/conversaciones/${button.dataset.id}/estado`, 'PUT', { estado: state });
            window.FP.exito('Estado actualizado', 'El reporte pasó a "' + ESTADOS[state] + '".');
            load();
        } catch (error) {
            window.FP.error('No se pudo cambiar el estado', error.message);
        }
    });

    // Responder al socio en el chat.
    document.querySelectorAll('.reply').forEach((form) => form.onsubmit = async (event) => {
        event.preventDefault();
        const input = form.querySelector('input');
        try {
            await api(`/personal/conversaciones/${form.dataset.id}/mensajes`, 'POST', { mensaje: input.value });
            input.value = '';
            load();
        } catch (error) {
            window.FP.error('No se pudo enviar la respuesta', error.message);
        }
    });
};

// Recargar los reportes.
document.getElementById('refresh').onclick = () => load().catch((error) => reports.textContent = error.message);

// Cerrar sesión.
document.getElementById('logout').onclick = (event) => {
    event.preventDefault();
    window.FP.cerrarSesion();
};

// Inicio: exige sesión del personal y carga los reportes.
(async () => {
    await window.FP.exigirSesion('personal');
    await load();
})().catch((error) => { reports.textContent = error.message; });
