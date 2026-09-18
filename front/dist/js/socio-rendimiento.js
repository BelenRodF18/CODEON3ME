/* Acciones de editar/eliminar sobre el historial de series del socio.
   Usa los modales y toasts de ui.js: nada de prompt()/confirm()/alert(). */

(() => {
    const { api, escapar, exito, error: errorToast, modal, confirmar } = window.FP;

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
    const editarSerie = (serie, recargar) => modal({
        titulo: 'Editar serie',
        descripcion: serie.ejercicio + ' · ' + serie.fecha,
        cuerpo: formularioSerie(serie),
        aceptar: 'Guardar cambios',
        alAceptar: async (formulario) => {
            const datos = Object.fromEntries(new FormData(formulario));

            try {
                await api('/socio/series/' + serie.id, {
                    method: 'PUT',
                    body: {
                        peso: datos.peso,
                        repeticiones: datos.repeticiones,
                        descanso_segundos: serie.descanso_segundos,
                        sensacion: datos.sensacion
                    }
                });
                exito('Serie actualizada', 'Tu historial ya refleja el cambio.');
                recargar();
                return true;
            } catch (fallo) {
                errorToast('No se pudo guardar', fallo.message);
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
            await api('/socio/series/' + serie.id, { method: 'DELETE' });
            exito('Registro eliminado', 'La serie ya no figura en tu historial.');
            recargar();
        } catch (fallo) {
            errorToast('No se pudo eliminar', fallo.message);
        }
    };

    // El historial lo dibuja el dashboard; se recarga para verlo actualizado.
    const recargar = () => window.location.reload();

    /**
     * Agrega Editar/Eliminar a cada fila que todavía no los tiene. El historial
     * se vuelve a dibujar después de cada serie, así que se revisa en cada cambio.
     */
    let buscando = false;
    const renderActions = async () => {
        const lista = document.getElementById('series-list');
        if (!lista || buscando) return;

        const pendientes = [...lista.querySelectorAll('.chart-row[data-series-id]')]
            .filter((fila) => !fila.querySelector('.series-actions'));
        if (!pendientes.length) return;

        buscando = true;
        try {
            const datos = await api('/socio/rendimiento');

            pendientes.forEach((fila) => {
                const serie = datos.series.find((item) => String(item.id) === fila.dataset.seriesId);
                if (!serie || fila.querySelector('.series-actions')) return;

                fila.insertAdjacentHTML('beforeend',
                    '<span class="series-actions" style="display:flex;gap:6px;justify-content:flex-end">' +
                    '<button type="button" class="fp-btn fp-btn--ghost fp-btn--sm series-edit">Editar</button>' +
                    '<button type="button" class="fp-btn fp-btn--peligro-ghost fp-btn--sm series-delete">Eliminar</button>' +
                    '</span>');

                fila.querySelector('.series-edit').addEventListener('click', (evento) => {
                    evento.stopPropagation();
                    editarSerie(serie, recargar);
                });
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
