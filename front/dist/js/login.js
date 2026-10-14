/* Inicio de sesión: con usuario y contraseña, y redirección al panel según el rol. */

// Panel de cada rol, relativo a esta página.
const PANELES = {
    admin: 'dashboard-admin.html',
    entrenador: 'dashboard-entrenador.html',
    socio: 'dashboard-socio.html',
    recepcion: 'dashboard-recepcion.html',
    personal: 'dashboard-personal.html'
};

// Por qué se volvió al login desde un panel.
const MOTIVOS = {
    expirada: 'Tu sesión venció por inactividad. Iniciá sesión de nuevo.',
    otra_cuenta: 'En este navegador se inició sesión con otra cuenta. Iniciá sesión de nuevo para seguir.'
};

const mensaje = document.getElementById('mensaje-login');

/** Muestra un aviso debajo del formulario (error en rojo, info en verde). */
const mostrarMensaje = (texto, tipo = 'error') => {
    mensaje.textContent = texto;
    mensaje.className = (tipo === 'error' ? 'text-danger' : 'text-success') + ' small mb-3';
    mensaje.style.display = texto ? 'block' : 'none';
};

// Si ya hay una sesión abierta, no hace falta volver a loguearse: se va a su panel.
(async () => {
    const motivo = new URLSearchParams(location.search).get('motivo');
    if (MOTIVOS[motivo]) mostrarMensaje(MOTIVOS[motivo], 'info');

    // Sin API o sin sesión se muestra el formulario igual.
    const sesion = await API.request('/sesion');
    if (sesion.status !== 'success' || !sesion.usuario) return;
    const panel = PANELES[sesion.usuario.rol];
    if (!panel) return;

    if (motivo === 'otra_cuenta') {
        mensaje.innerHTML = '';
        mostrarMensaje(MOTIVOS.otra_cuenta + ' Ahora la sesión abierta es de ' + sesion.usuario.nombre + '.', 'info');
        const enlace = document.createElement('a');
        enlace.href = panel;
        enlace.textContent = ' Ir a su panel';
        enlace.className = 'text-white';
        mensaje.appendChild(enlace);
        return;
    }
    window.location.replace(panel);
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

    // Si sale mal (o la API no responde) se queda en el login con el mensaje.
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
