/* Inicio de sesión y redirección al panel según el rol. */

// Panel de cada rol.
const PANELES = {
    admin: 'dashboard-admin.html',
    entrenador: 'dashboard-entrenador.html',
    socio: 'dashboard-socio.html',
    recepcion: 'dashboard-recepcion.html',
    personal: 'dashboard-personal.html'
};

const mensaje = document.getElementById('mensaje-login');

// Muestra un aviso debajo del formulario.
const mostrarMensaje = (texto, tipo = 'error') => {
    mensaje.textContent = texto;
    mensaje.className = (tipo === 'error' ? 'text-danger' : 'text-success') + ' small mb-3';
    mensaje.style.display = texto ? 'block' : 'none';
};

// Si ya hay sesión, va directo a su panel.
(async () => {
    const sesion = await API.request('/sesion');
    if (sesion.status !== 'success' || !sesion.usuario) return;
    const panel = PANELES[sesion.usuario.rol];
    if (!panel) return;

    window.location.href = panel;
})();

// Iniciar sesión y redirigir al panel de su rol.
document.getElementById('form-login').addEventListener('submit', async function(e) {
    e.preventDefault();

    const usuario = document.getElementById('username').value.trim().toLowerCase();
    const password = document.getElementById('password').value;

    mostrarMensaje('');

    const respuesta = await API.request('/login', 'POST', {
        usuario: usuario,
        password: password
    });

    if (respuesta.status !== 'success') {
        mostrarMensaje(respuesta.message || 'Credenciales inválidas');
        return;
    }

    const panel = PANELES[respuesta.usuario.rol];
    if (panel) {
        window.location.href = panel;
    } else {
        mostrarMensaje('Rol no reconocido');
    }
});
