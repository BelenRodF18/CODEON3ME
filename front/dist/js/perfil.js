/* Perfil: al tocar el círculo de arriba a la derecha se abre una ventana con los datos de la cuenta. */

(() => {
    const boton = document.querySelector('.socio-avatar');
    if (!boton) return;

    // El rol llega sin tilde desde la base.
    const NOMBRES_ROL = { Recepcion: 'Recepción', Personal: 'Personal de mantenimiento' };

    // "Martina López" -> "ML"
    const iniciales = (nombre) => nombre.split(' ').filter(Boolean).map((parte) => parte[0]).slice(0, 2).join('').toUpperCase();

    // Cambia el nombre que se ve en el panel (cada panel lo muestra en un lugar distinto).
    const actualizarNombre = (nombre) => {
        boton.textContent = iniciales(nombre);

        const adminNombre = document.getElementById('admin-nombre');
        if (adminNombre) adminNombre.textContent = nombre;

        const saludo = document.getElementById('saludo');
        if (saludo) saludo.textContent = 'Hola, ' + nombre.split(' ')[0];
    };

    // Trae mis datos con GET /perfil.
    const cargarPerfil = async () => {
        const respuesta = await API.request('/perfil');
        if (respuesta.status !== 'success') return null;

        return respuesta.usuario;
    };

    const abrirPerfil = async () => {
        const usuario = await cargarPerfil();
        if (!usuario) {
            avisoError('No se pudo abrir el perfil', 'Probá de nuevo en un momento.');
            return;
        }

        abrirModal({
            titulo: 'Mi perfil',
            descripcion: 'Estos son los datos de tu cuenta.',
            cuerpo: '<div class="fp-form">' +
                '<div class="fp-campo"><label for="perfil-usuario">Usuario</label>' +
                '<input id="perfil-usuario" value="' + escapar(usuario.nombre_usuario) + '" disabled></div>' +
                '<div class="fp-campo"><label for="perfil-rol">Rol</label>' +
                '<input id="perfil-rol" value="' + escapar(NOMBRES_ROL[usuario.rol] || usuario.rol) + '" disabled></div>' +
                '<div class="fp-campo fp-campo--full"><label for="perfil-nombre">Nombre</label>' +
                '<input id="perfil-nombre" name="nombre" required maxlength="100" value="' + escapar(usuario.nombre) + '"></div>' +
                '<div class="fp-campo fp-campo--full"><label for="perfil-telefono">Teléfono (opcional)</label>' +
                '<input id="perfil-telefono" name="telefono" maxlength="30" value="' + escapar(usuario.telefono || '') + '"></div>' +
                '<div class="fp-campo"><label for="perfil-actual">Contraseña actual</label>' +
                '<input id="perfil-actual" name="password_actual" type="password" autocomplete="current-password"></div>' +
                '<div class="fp-campo"><label for="perfil-nueva">Contraseña nueva</label>' +
                '<input id="perfil-nueva" name="password_nueva" type="password" autocomplete="new-password" minlength="6"></div>' +
                '<p class="socio-meta fp-campo--full">Si no querés cambiar la contraseña, dejá esos dos campos vacíos.</p></div>',
            aceptar: 'Guardar cambios',
            alAceptar: async (formulario) => {
                const datos = Object.fromEntries(new FormData(formulario));
                const respuesta = await API.request('/perfil', 'PUT', datos);

                if (respuesta.status !== 'success') {
                    avisoError('No se pudo guardar el perfil', respuesta.message);
                    return false;
                }

                actualizarNombre(respuesta.usuario.nombre);
                avisoExito('Perfil actualizado', 'Se guardaron tus datos.');
                return true;
            }
        });
    };

    boton.addEventListener('click', abrirPerfil);

    // Al entrar, el círculo muestra las iniciales de la persona.
    cargarPerfil().then((usuario) => {
        if (usuario) boton.textContent = iniciales(usuario.nombre);
    });
})();
