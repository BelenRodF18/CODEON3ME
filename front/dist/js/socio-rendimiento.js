(() => {
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const api = async (path, options = {}) => { const response = await fetch('/api' + path, options); const data = await response.json(); if (!response.ok) throw new Error(data.message || 'No se pudo completar la operación'); return data; };
    const renderActions = async () => {
        const list = document.getElementById('series-list');
        if (!list || list.dataset.actionsReady === '1') return;
        const data = await api('/socio/rendimiento');
        const rows = [...list.querySelectorAll('.chart-row')].slice(1);
        rows.forEach((row, index) => {
            const series = data.series[index];
            if (!series) return;
            row.dataset.seriesId = series.id;
            row.insertAdjacentHTML('beforeend', `<span class="series-actions"><button type="button" class="socio-button secondary series-edit">Editar</button><button type="button" class="socio-button secondary series-delete">Eliminar</button></span>`);
        });
        list.dataset.actionsReady = '1';
        list.querySelectorAll('.series-edit').forEach((button) => button.addEventListener('click', async (event) => {
            event.stopPropagation();
            const row = button.closest('.chart-row');
            const series = data.series.find((item) => String(item.id) === row.dataset.seriesId);
            const peso = prompt('Peso (kg)', series.peso);
            const repeticiones = prompt('Repeticiones', series.repeticiones);
            const sensacion = prompt('Sensación', series.sensacion || '');
            if (peso === null || repeticiones === null) return;
            try { await api(`/socio/series/${series.id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ peso, repeticiones, descanso_segundos: series.descanso_segundos, sensacion }) }); location.reload(); } catch (error) { alert(error.message); }
        }));
        list.querySelectorAll('.series-delete').forEach((button) => button.addEventListener('click', async (event) => {
            event.stopPropagation();
            if (!confirm('¿Eliminar este registro?')) return;
            try { await api(`/socio/series/${button.closest('.chart-row').dataset.seriesId}`, { method: 'DELETE' }); location.reload(); } catch (error) { alert(error.message); }
        }));
    };
    const observer = new MutationObserver(() => renderActions().catch(() => {}));
    observer.observe(document.body, { childList: true, subtree: true });
})();
