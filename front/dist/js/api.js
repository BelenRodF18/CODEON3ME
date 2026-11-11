// Todas las páginas piden y envían datos a la API con API.request.

// Cuenta con la que se abrió el panel (se guarda al iniciar sesión, solo en esta pestaña).
const CLAVE_SESION = 'fp_usuario_sesion';
// Pedidos que pueden dar 401 sin que signifique "sesión vencida".
const ENDPOINTS_SIN_AVISO = ['/login', '/logout', '/sesion', '/me'];
// Textos que muestra el login según el motivo de la redirección.
const MOTIVOS_LOGIN = {
    vencida: 'Tu sesión venció por inactividad',
    cambio: 'Se inició sesión con otra cuenta en este navegador. Ingresá de nuevo.'
};

const enPanel = () => /dashboard-/.test(window.location.pathname);

// Vuelve al login explicando el motivo (solo si había una sesión abierta en esta pestaña).
function irAlLogin(motivo) {
    if (!enPanel()) return;
    let habiaSesion = false;
    try { habiaSesion = !!sessionStorage.getItem(CLAVE_SESION); sessionStorage.removeItem(CLAVE_SESION); } catch (e) { /* sin storage */ }
    window.location.href = 'login.html' + (habiaSesion ? '?motivo=' + motivo : '');
}

const API = {
    // En Docker o Laragon
    urlBase: '/api',

    async request(endpoint, method = 'GET', data = null) {
        const url = this.urlBase + endpoint;

        const opciones = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };

        // Las consultas (GET) no envían datos; guardar o editar (POST/PUT) sí.
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
            // 401 en un panel con sesión que se había abierto = la sesión venció.
            if (respuesta.status === 401 && !ENDPOINTS_SIN_AVISO.includes(endpoint)) {
                irAlLogin('vencida');
            }
            return json;
        } catch (error) {
            console.error('Error de conexión con la API:', error);
            return { status: 'error', message: 'No se pudo conectar con la API' };
        }
    }
};

// Recuerda con qué cuenta se abrió esta pestaña (lo llama el login al entrar).
function recordarSesion(usuario) {
    try { sessionStorage.setItem(CLAVE_SESION, String(usuario.id)); } catch (e) { /* sin storage */ }
}

// Mientras el panel está abierto, revisa cada tanto que la sesión siga siendo la misma cuenta.
if (enPanel()) {
    const vigilarSesion = async () => {
        let guardada = null;
        try { guardada = sessionStorage.getItem(CLAVE_SESION); } catch (e) { return; }
        if (!guardada) return;
        const sesion = await API.request('/sesion');
        if (sesion.status !== 'success') return;
        if (!sesion.usuario) return irAlLogin('vencida');
        if (String(sesion.usuario.id) !== guardada) irAlLogin('cambio');
    };
    setInterval(vigilarSesion, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) vigilarSesion(); });
}
