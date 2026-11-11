# FitPower

FitPower es la web de un gimnasio. Tiene una página pública para que cualquiera conozca los programas y planes, y paneles privados para cada persona que trabaja o entrena ahí: socios, entrenadores, administración, recepción y mantenimiento.

Lo hicimos en **Codeón S.R.L.** (Belén Rodriguez, David Sasías, Violeta Peralta y Rodrigo Tchakerian) como proyecto de egreso de 3.º de Informática, UTU Atlántida.

## Qué se puede hacer

| Quién | Qué hace |
| --- | --- |
| **Socio** | Ve si su mensualidad está al día, qué programas tiene y qué entrenador lo guía. Consulta las rutinas, genera su QR para entrar, registra sus series y sus récords, usa los cronómetros y le escribe al equipo. |
| **Entrenador** | Ve a los socios que tiene y los programas que dirige. Arma varias rutinas para cada socio con ejercicios del catálogo. |
| **Administrador** | Crea, edita, da de baja o borra usuarios. Actualiza la membresía de cada socio, le asigna plan, entrenador y programas, y maneja el catálogo de ejercicios. Responde los mensajes de los socios. |
| **Recepción** | Escanea el QR de los socios en la puerta, registra quién entra y quién sale, y ve cuánta gente hay adentro. |
| **Mantenimiento** | Atiende los reportes de máquinas que mandan los socios y les responde por chat. |

Además de lo que pedía la letra, sumamos tres funciones propias:

1. **Reportes de máquinas con chat:** el socio avisa de una falla (con foto si quiere) y sigue la conversación hasta que se arregla.
2. **QR de acceso que cambia cada minuto:** reemplaza la tarjeta y controla quién entra, quién sale y cuánta gente hay en el gimnasio.
3. **Récords personales y cronómetro de descanso:** el socio anota sus series, el sistema marca cuándo bate un récord y le avisa cuándo terminó el descanso.

La web está en **español e inglés**: el botón 🌐 ES | EN cambia el idioma y la página recuerda tu elección. En los paneles de escritorio está en el menú de la izquierda; en el celular, en la cabecera; en la web pública y en Recepción, abajo a la derecha.

En todos los paneles:

- Tocar el **logo** o el botón **Ir al inicio** vuelve a la página principal sin cerrar la sesión (ahí el botón de arriba pasa a decir «Mi panel»).
- Tocar el **círculo con las iniciales** (arriba a la derecha) abre **Mi perfil**: se cambian el nombre, el teléfono y la contraseña (hay que escribir la actual). El usuario y el rol los cambia solo el administrador. Usa las rutas `GET /api/perfil` y `PUT /api/perfil`.

## Cómo ponerlo en marcha

Lo único que necesitás es tener **Docker** instalado.

1. Bajá el proyecto y entrá a la carpeta:

   ```bash
   git clone https://github.com/BelenRodF18/CODEON3ME.git
   cd CODEON3ME
   ```

2. Copiá el archivo de configuración (trae valores de ejemplo que ya funcionan):

   ```bash
   cp .env.example .env
   ```

3. Levantá todo:

   ```bash
   docker compose up -d --build
   ```

4. Esperá unos segundos. La primera vez MySQL tiene que crear la base y cargar los datos de ejemplo. Para saber si ya terminó, abrí http://localhost:8080/api/estado: cuando diga `"base_datos": "conectada"` está listo.

5. Entrá a **http://localhost:8080** y listo.

### Direcciones útiles

| Qué | Dónde |
| --- | --- |
| La web | http://localhost:8080 |
| Iniciar sesión | http://localhost:8080/front/dist/pages/login.html |
| phpMyAdmin (ver la base) | http://localhost:8081 (usuario `fitpower_user`, contraseña `fitpower_password`) |
| Estado de la API | http://localhost:8080/api/estado |

Para apagarlo: `docker compose down`. Si querés empezar de cero y borrar también la base: `docker compose down -v`.

## Cuentas de prueba

Todas tienen la misma contraseña: **`fitpower123`**

| Usuario | Rol |
| --- | --- |
| `admin` | Administrador |
| `entrenador` | Entrenador (Carlos Pérez) |
| `socio` | Socio (Socio Demo) |
| `martina.lopez` | Socio con plan Pro, rutinas y programas ya armados |
| `recepcion` | Recepción |
| `personal` | Mantenimiento |

También hay entrenadores de ejemplo: `juan.diaz`, `diego.torres`, `lucas.gomez`, `sofia.rey`, `elena.paz`, `bruno.soler` y `matias.rossi`.

> Si querés probar el QR, necesitás dos sesiones a la vez (un socio y Recepción). Usá dos navegadores distintos o una ventana privada, porque las pestañas del mismo navegador comparten la sesión.

## Cómo está organizado

```
CODEON3ME/
├── api/                    El "cerebro": recibe pedidos y habla con la base de datos (PHP)
│   ├── index.php           Puerta de entrada de la API
│   ├── routes.php          Lista de direcciones y quién las atiende
│   ├── config/             Conexión a MySQL, init.sql (crea las tablas y los datos de ejemplo)
│   ├── src/
│   │   ├── Controllers/    Reciben el pedido, lo validan y arman la respuesta
│   │   ├── Models/         Hacen las consultas a la base de datos
│   │   └── Utils/          Router, sesión, QR y lectura del .env
│   ├── tests/              Pruebas automáticas
│   └── uploads/            Fotos de los reportes de mantenimiento
├── front/                  Lo que ve la gente (HTML, CSS y JavaScript)
│   ├── index.html          Página de inicio
│   └── dist/
│       ├── pages/          Resto de las páginas y los paneles de cada rol
│       ├── js/             Código de cada página (más el cambio de idioma)
│       ├── scss/ y css/    Estilos
│       └── assets/         Imágenes y logos
├── docker-compose.yml      Define los tres contenedores: web, MySQL y phpMyAdmin
├── Dockerfile              Prepara el servidor web con PHP y Apache
└── .env.example            Plantilla de la configuración
```

El código sigue la idea de capas: la **pantalla** (front) le pide cosas a la **API**; en la API el **controlador** atiende el pedido, el **modelo** consulta la base y el resultado vuelve como JSON.

## Seguridad

- Las contraseñas se guardan cifradas (`password_hash`), nunca en texto plano.
- Cada rol solo entra a su panel. Si alguien pide algo sin iniciar sesión, o con otro rol, la API responde que no tiene permiso.
- Los datos se validan en el navegador y también en el servidor.
- Los QR llevan una firma secreta: no se pueden inventar ni modificar, vencen al minuto y sirven una sola vez.

## Pruebas

Hay pruebas automáticas que revisan usuarios, QR, accesos, programas, chat y récords. Se corren así:

```bash
docker exec fitpower_app php /var/www/html/api/tests/run.php
```

Si todo está bien, al final dice `0 fallas`.

## Si algo no anda

- **La web carga pero no aparecen programas ni planes:** la API todavía no terminó de arrancar o abriste `index.html` como archivo suelto. Entrá por http://localhost:8080 y esperá a que `/api/estado` diga `conectada`.
- **Puerto ocupado:** si otro programa usa el 8080 u 8081, cambiá el número de la izquierda en `docker-compose.yml` (por ejemplo `"8090:80"`).
- **Quiero volver a cargar los datos de ejemplo:** `docker compose down -v` y después `docker compose up -d --build`.
- **Cambié el código y no veo el cambio:** recargá con Ctrl + F5.

## Tecnologías

PHP 8.2 y Apache, MySQL 8, HTML, CSS (SCSS) y JavaScript, Bootstrap 5, y Docker para que funcione igual en cualquier computadora.
