// front/dist/js/api.js
// Cliente HTTP centralizado: todas las páginas hablan con PHP a través de API.request.

const API = {
    // En Docker o Laragon
    urlBase: '/api',

    async request(endpoint, method = 'GET', data = null) {
        const url = this.urlBase + endpoint;

        const opciones = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };

        // GET no lleva body. POST/PUT sí.
        if (data instanceof FormData) {
            // Formulario con archivos (foto del reporte): el navegador arma la cabecera.
            delete opciones.headers;
            opciones.body = data;
        } else if (data) {
            opciones.body = JSON.stringify(data);
        }

        try {
            const respuesta = await fetch(url, opciones);
            const json = await respuesta.json();
            return json;
        } catch (error) {
            console.error('Error de conexión con la API:', error);
            return { status: 'error', message: 'No se pudo conectar con la API' };
        }
    }
};
