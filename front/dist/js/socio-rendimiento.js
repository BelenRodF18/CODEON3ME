/* Acciones de editar/eliminar sobre el historial de series del socio. */

(() => {

    // Formulario para corregir una serie.
    const formularioSerie = (serie) =>
        '<div class="fp-form">' +
        '<div class="fp-campo"><label for="serie-peso-edit">Peso (kg)</label>' +
        '<input id="serie-peso-edit" name="peso" type="number" min="0" step="0.5" required value="' + escapar(serie.peso) + '"></div>' +
        '<div class="fp-campo"><label for="serie-reps-edit">Repeticiones</label>' +
        '<input id="serie-reps-edit" name="repeticiones" type="number" min="1" required value="' + escapar(serie.repeticiones) + '"></div>' +
        '<div class="fp-campo fp-campo--full"><label for="serie-sens-edit">Sensación</label>' +
        '<input id="serie-sens-edit" name="sensacion" maxlength="120" placeholder="Fácil, intensa, al límite..." value="' +
        escapar(serie.sensacion || '') + '"></div>' +
        '</div>';

    // Modal para editar una serie del historial.
    const editarSerie = (serie, recargar) => abrirModal({
        titulo: 'Editar serie',
        descripcion: serie.ejercicio + ' · ' + serie.fecha,
        cuerpo: formularioSerie(serie),
        aceptar: 'Guardar cambios',
        alAceptar: async (formulario) => {
            const datos = Object.fromEntries(new FormData(formulario));

            try {
                const resultado = await API.request('/socio/series/' + serie.id, 'PUT', {
                    peso: datos.peso,
                    repeticiones: datos.repeticiones,
                    descanso_segundos: serie.descanso_segundos,
                    sensacion: datos.sensacion
                });
                if (resultado.status === 'error') throw new Error(resultado.message);
                avisoExito('Serie actualizada', 'Tu historial ya refleja el cambio.');
                recargar();
                return true;
            } catch (fallo) {
                avisoError('No se pudo guardar', fallo.message);
                return false;
            }
        }
    });

    // Pide confirmación y borra una serie.
    const eliminarSerie = async (serie, recargar) => {
        const confirmado = await confirmar({
            titulo: 'Eliminar registro',
            descripcion: 'Se va a borrar la serie de ' + serie.ejercicio + ' del ' + serie.fecha +
                '. Esta acción es permanente.',
            aceptar: 'Eliminar',
            peligro: true
        });

        if (!confirmado) return;

        try {
            const resultado = await API.request('/socio/series/' + serie.id, 'DELETE');
            if (resultado.status === 'error') throw new Error(resultado.message);
            avisoExito('Registro eliminado', 'La serie ya no figura en tu historial.');
            recargar();
        } catch (fallo) {
            avisoError('No se pudo eliminar', fallo.message);
        }
    };

    // Recarga el historial.
    const recargar = () => window.location.reload();

    // Agrega Editar/Eliminar a cada fila del historial.
    let buscando = false;
    const renderActions = async () => {
        const lista = document.getElementById('series-list');
        if (!lista || buscando) return;

        const pendientes = [...lista.querySelectorAll('.chart-row[data-series-id]')]
            .filter((fila) => !fila.querySelector('.series-actions'));
        if (!pendientes.length) return;

        buscando = true;
        try {
            const datos = await API.request('/socio/rendimiento');
            if (datos.status === 'error') throw new Error(datos.message);

            pendientes.forEach((fila) => {
                const serie = datos.series.find((item) => String(item.id) === fila.dataset.seriesId);
                if (!serie || fila.querySelector('.series-actions')) return;

                fila.insertAdjacentHTML('beforeend',
                    '<span class="series-actions" style="display:flex;gap:6px;justify-content:flex-end">' +
                    '<button type="button" class="fp-btn fp-btn--ghost fp-btn--sm series-edit">Editar</button>' +
                    '<button type="button" class="fp-btn fp-btn--peligro-ghost fp-btn--sm series-delete">Eliminar</button>' +
                    '</span>');

                // Editar esta serie.
                fila.querySelector('.series-edit').addEventListener('click', (evento) => {
                    evento.stopPropagation();
                    editarSerie(serie, recargar);
                });
                // Eliminar esta serie.
                fila.querySelector('.series-delete').addEventListener('click', (evento) => {
                    evento.stopPropagation();
                    eliminarSerie(serie, recargar);
                });
            });
        } finally {
            buscando = false;
        }
    };

    const observador = new MutationObserver(() => renderActions().catch(() => {}));
    observador.observe(document.body, { childList: true, subtree: true });
})();
