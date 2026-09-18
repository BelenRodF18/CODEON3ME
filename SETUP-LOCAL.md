# FitPower · Puesta en marcha local (sin Docker)

El proyecto corre con **PHP + MySQL instalados directamente en la PC**. No hace
falta Docker, Docker Compose ni contenedores para nada.

Arquitectura, sin cambios respecto de lo que ya había:

```
front/  (HTML + CSS + JS sin framework)
   ↓ fetch
api/    (PHP 8, router propio, controladores, modelos, PDO)
   ↓
MySQL   (base fitpower)
```

---

## 1. Requisitos

| Componente | Versión mínima | Cómo comprobarlo    |
|------------|----------------|---------------------|
| PHP        | 8.1            | `php -v`            |
| MySQL      | 8.0            | `mysql --version`   |

PHP necesita la extensión **pdo_mysql** activada
(`php -m | findstr pdo_mysql`).

### Ya instalado en esta PC

PHP 8.3.33 y MySQL 8.4.9 quedaron instalados y configurados:

| Qué            | Dónde                                                                                 |
|----------------|---------------------------------------------------------------------------------------|
| `php.exe`      | `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_*\php.exe` (el alias `php` ya está en el PATH) |
| `php.ini`      | en esa misma carpeta, con `pdo_mysql`, `mysqli`, `openssl`, `mbstring` y `fileinfo` habilitados |
| MySQL          | `C:\Program Files\MySQL\MySQL Server 8.4\bin`                                          |
| Datos + my.ini | `C:\ProgramData\MySQL\MySQL Server 8.4`                                                |

> El PATH se actualiza recién al abrir una terminal nueva. Si `php` no se
> reconoce, cerrá y volvé a abrir la consola.

### Arrancar MySQL

MySQL quedó instalado **sin servicio de Windows** (registrarlo requiere permisos
de administrador). Para levantarlo:

```bash
"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini"
```

Dejá esa ventana abierta mientras trabajás. Si preferís que arranque solo con
Windows, abrí una terminal **como administrador** una única vez:

```bash
"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --install MySQL84 --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini"
net start MySQL84
```

El usuario `root` quedó **sin contraseña** (instalación local de desarrollo). Si
le ponés una, acordate de reflejarla en `api/.env`.

### Otras opciones de instalación

- **XAMPP** — PHP y MySQL juntos. Agregá `C:\xampp\php` y `C:\xampp\mysql\bin` al PATH.
- **Manual** — ZIP "Thread Safe" de [windows.php.net](https://windows.php.net/download/)
  y MySQL de [dev.mysql.com](https://dev.mysql.com/downloads/mysql/).

---

## 2. Configurar la conexión

`api/.env` ya quedó creado y apuntando al MySQL local. Si necesitás rehacerlo:

```bash
cp api/.env.example api/.env
```

Contenido actual:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=fitpower
DB_USER=root
DB_PASS=

# Opcional: si se deja vacío se genera solo en api/config/.qr_secret
QR_SECRET=
```

`api/.env` y `api/config/.qr_secret` están en el `.gitignore`: no se suben al
repositorio.

---

## 3. Crear/actualizar la base de datos

```bash
php api/config/setup.php
```

El script crea la base, aplica el esquema de `api/config/init.sql` y deja las
cinco cuentas base. **Es idempotente**: se puede volver a correr cuantas veces
haga falta, no duplica datos ni pisa contraseñas ya cambiadas.

> Ya se ejecutó una vez: la base `fitpower` está creada con el esquema completo
> y las cinco cuentas. Volvé a correrlo solo si cambiás `init.sql` o si querés
> reconstruir la base desde cero.

Si alguna vez querés volver las cuentas base a su contraseña original:

```bash
php api/config/setup.php --reset-passwords
```

---

## 4. Levantar la aplicación

```bash
php -S localhost:8000 server.php
```

`server.php` hace lo mismo que el `.htaccess` de producción: manda `/api/...` a
`api/index.php` y el resto a los archivos de `front/`.

Abrí **http://localhost:8000/front/dist/pages/formularios/login.html**

> La cámara del lector QR solo funciona en `localhost` o por HTTPS: es una
> restricción del navegador, no del proyecto. Con `localhost:8000` funciona.

Si preferís Apache (XAMPP), apuntá el DocumentRoot a la carpeta del proyecto y
los `.htaccess` que ya estaban hacen el resto.

---

## 5. Cuentas iniciales

Se inicia sesión con **nombre de usuario** (no con email). Contraseña:
`fitpower123` para todas.

| Usuario      | Rol           | Panel                                    |
|--------------|---------------|------------------------------------------|
| `admin`      | Administrador | `front/dist/pages/dashboard/admin/`      |
| `entrenador` | Entrenador    | `front/dist/pages/dashboard/entrenador/` |
| `socio`      | Socio         | `front/dist/pages/dashboard/socio/`      |
| `personal`   | Personal      | `front/dist/pages/dashboard/personal/`   |
| `recepcion`  | Recepcion     | `front/dist/pages/dashboard/recepcion/`  |

### Programas y entrenadores

Cada programa tiene un entrenador a cargo, que es un usuario real
(`Programas.ID_entrenador`). El administrador lo puede cambiar desde
**Socios → Programas y entrenadores**.

| Programa          | Entrenador   | Usuario        | Planes que lo incluyen |
|-------------------|--------------|----------------|------------------------|
| Boxeo             | Juan Díaz    | `juan.diaz`    | Pro, Elite             |
| Musculación       | Diego Torres | `diego.torres` | Pro, Elite             |
| Cross Training    | Lucas Gómez  | `lucas.gomez`  | Pro, Elite             |
| Spinning          | Sofía Rey    | `sofia.rey`    | Básico, Pro, Elite     |
| Yoga              | Carlos Pérez | `entrenador`   | Básico, Pro, Elite     |
| Pilates           | Elena Paz    | `elena.paz`    | Pro, Elite             |
| Entreno Funcional | Bruno Soler  | `bruno.soler`  | Básico, Pro, Elite     |
| Natación          | Matías Rossi | `matias.rossi` | Pro, Elite             |

Reglas:

- Un socio solo se inscribe en programas que incluye su plan (`Socio_Programa`).
- Pro y Elite incluyen entrenador personal (`Socio_Entrenador`); el Básico no.
- Si el socio baja de plan, se quitan las inscripciones y el entrenador
  personal que el plan nuevo no incluye.
- Un entrenador arma rutinas para los socios que lo tienen como entrenador
  personal o que están inscriptos en sus programas. La rutina queda ligada al
  programa (`Rutinas.ID_programa`, `NULL` = entrenamiento personal).

### Socia de ejemplo: `martina.lopez`

- **Plan Pro** pagado: dos pagos en `Historial_membresias`, membresía al día.
- **Entrenador personal:** Diego Torres.
- **Programas:** Musculación (Diego Torres), Spinning (Sofía Rey) y Boxeo
  (Juan Díaz), con una rutina de cada entrenador más una del entrenamiento
  personal.
- Progreso cargado en Press banca y Sentadilla, e ingresos anteriores
  registrados por Recepción.

Las contraseñas se guardan con `password_hash()` (bcrypt). En ningún punto del
sistema se guarda ni se transmite una contraseña en texto plano.

---

## 6. Tests

```bash
php api/tests/run.php
```

No necesita composer ni PHPUnit. Los tests que dependen de MySQL se saltan solos
si la base no está levantada, así que la suite corre igual.

Cubren:

- Firma, expiración, unicidad y rechazo de tokens QR manipulados.
- Que generar un QR **no** escriba filas en `Tokens_QR`.
- Canje en Recepción: token usado + ingreso registrado con operador y hora.
- Rechazos: QR ya utilizado, socio inactivo, membresía vencida, token expirado,
  formato inválido, socio inexistente.
- Alta, edición, baja lógica, habilitación y borrado sin registros huérfanos.
- Que las contraseñas queden hasheadas y que siempre quede un admin activo.
- Formato del nombre de usuario (rechaza emails y mayúsculas).
- Programas con entrenador válido, inscripciones siempre dentro del plan,
  rutinas hechas por el entrenador del programa y la socia de ejemplo completa.
- Salida del gimnasio: el segundo QR del socio que está adentro registra la
  salida; también se puede cargar a mano una sola vez.
- Aviso al socio cuando cambia el estado de su reporte.
- Récords personales recalculados al registrar, editar y borrar series.

---

## Sesiones

- Se inicia sesión con **nombre de usuario**. La sesión dura 8 horas sin
  actividad (configurada en `api/src/Utils/Sesion.php`).
- **Una sesión por navegador:** todas las pestañas y ventanas del mismo
  navegador comparten la sesión. Para usar dos cuentas a la vez (por ejemplo,
  socio y recepción) usá dos navegadores o una ventana privada.
- Si la sesión vence, o en ese navegador se entra con otra cuenta, el panel
  abierto vuelve al login y explica el motivo.
- En la web pública, con la sesión abierta, el botón "Iniciar Sesión" pasa a
  ser **Mi panel**.

---

## 7. Cómo probar el circuito completo del QR

1. Entrá como `socio` (o `martina.lopez`) y andá a **Mi acceso**. Ahí se ve el QR.
   Revisá en MySQL: `SELECT COUNT(*) FROM Tokens_QR;` — el número **no cambia**
   por más que el QR se renueve.
2. En **otro navegador**, en una ventana privada o en el celular, entrá como
   `recepcion` (en el mismo navegador las ventanas comparten la sesión).
3. Tocá **Escanear QR** y apuntá la cámara al código del socio.
   Sin cámara, copiá el token y usá el campo de **ingreso manual**.
4. Se muestra `INGRESO AUTORIZADO` con el nombre del socio y la hora.
5. Ahora sí: `SELECT * FROM Tokens_QR;` tiene una fila nueva y
   `SELECT * FROM Registro_Accesos;` tiene el ingreso con socio, token, fecha,
   hora, recepción que escaneó y estado.
6. Volvé a escanear el mismo código: `INGRESO RECHAZADO · QR ya utilizado`.
7. Pasado un minuto, escaneá el QR nuevo del socio: se muestra
   `SALIDA REGISTRADA` con la hora de ingreso, la de salida y la estadía.
   Si el socio se fue sin escanear, en el historial está **Registrar salida**.
   La afluencia del portal del socio cuenta a quienes siguen adentro.
