// Cliente HTTP: todas las páginas hablan con la API a través de API.request.

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
            // Formulario con archivos (foto del reporte).
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
