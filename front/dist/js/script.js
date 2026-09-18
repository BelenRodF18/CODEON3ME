// --- Lógica General: Navegación y Slider ---
function cambiarActivo(enlaceClickeado) {
    document.querySelectorAll(".link").forEach(el => el.classList.remove("activo"));
    enlaceClickeado.classList.add("activo");
}

// Cierra el menú hamburguesa en celulares.
function cerrarMenuMovil() {
    const navEnlaces = document.getElementById("nav-enlaces");
    const btnMenu = document.getElementById("btn-menu");
    if (navEnlaces) navEnlaces.classList.remove("abierto");
    if (btnMenu) {
        btnMenu.setAttribute("aria-expanded", "false");
    }
}

// Mueve el carrusel de profesionales al bloque indicado.
function moverSlider(indiceBloque) {
    const riel = document.getElementById("riel-profesionales");
    if (!riel) return;
    riel.style.transform = `translateX(${indiceBloque * -33.3333}%)`;
    
    const puntos = document.querySelectorAll(".punto-nav");
    puntos.forEach(punto => punto.classList.remove("activo"));
    if (puntos[indiceBloque]) puntos[indiceBloque].classList.add("activo");
}

// --- Presentación de los planes ---
// Precio, descripción, programas y entrenadores vienen de MySQL (/api/planes).
// Acá solo queda lo visual: color y beneficios comerciales de cada plan.
const presentacionPlanes = {
    basico: {
        color: "#A0A0A0",
        detalles: ["3 programas incluidos", "Disponibilidad a chat de limpieza"]
    },
    pro: {
        color: "#AAFA64",
        detalles: ["Cuenta con entrenador propio", "Acceso a todos los programas", "Ajustes de rutinas por personal trainer", "Acceso a nutricionista"]
    },
    elite: {
        color: "#FFD700",
        detalles: ["Acceso a todos los programas", "Personalizar tus propias rutinas y cambios", "Acceso a coach y soporte técnico"]
    }
};

// Escapa el texto antes de insertarlo en el HTML.
const escaparHtml = (valor) => String(valor ?? "").replace(/[&<>'"]/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#039;", '"': "&quot;"
}[c]));

// Pide programas o planes a la API pública.
async function pedirCatalogo(ruta) {
    const respuesta = await fetch("/api" + ruta);
    const datos = await respuesta.json();
    if (!respuesta.ok) throw new Error(datos.message || "No se pudo cargar la información");
    return datos;
}

// Página de un programa: historia, técnica, beneficios, coach y planes.
function renderPrograma(cont, programa) {
    const planes = programa.planes.map((plan) =>
        `<a href="../planes/planes.html?plan=${encodeURIComponent(plan.slug)}" class="verde-titulo">${escaparHtml(plan.nombre)}</a>`
    ).join(" · ");
    const coach = programa.entrenador
        ? `<img src="../../assets/imagenes/${escaparHtml(programa.entrenador_foto)}" alt="${escaparHtml(programa.entrenador)}" style="width:180px; height:180px; border-radius:50%; object-fit:cover; border: 4px solid #AAFA64; margin-bottom:15px; box-shadow: 0 0 15px rgba(170, 250, 100, 0.3);">
           <h5 class="text-white">Coach a cargo:</h5>
           <h4 class="verde-titulo">${escaparHtml(programa.entrenador)}</h4>`
        : `<h5 class="text-white">Coach a cargo:</h5><h4 class="verde-titulo">Próximamente</h4>`;

    cont.innerHTML = `
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-8">
                <div class="p-4 p-md-5 rounded-4 h-100" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(170,250,100,0.2); min-height: 420px;">
                    <img src="../../assets/imagenes/${escaparHtml(programa.imagen)}" alt="${escaparHtml(programa.nombre)}" class="img-fluid rounded-4 mb-4" style="width:100%; height:260px; object-fit:cover;">
                    <h1 class="titulos text-uppercase mb-3">${escaparHtml(programa.nombre)}</h1>
                    <h3 class="verde-titulo">Historia</h3><p>${escaparHtml(programa.historia)}</p>
                    <h3 class="verde-titulo">Técnica</h3><p>${escaparHtml(programa.tecnica)}</p>
                    <h3 class="verde-titulo">Beneficios</h3><p>${escaparHtml(programa.beneficios)}</p>
                    ${planes ? `<h3 class="verde-titulo">Incluido en</h3><p>${planes}</p>` : ""}
                </div>
            </div>
            <div class="col-lg-4">
                <div class="p-4 p-md-5 rounded-4 h-100 d-flex flex-column align-items-center justify-content-center" style="background: rgba(170,250,100,0.08); border: 1px solid rgba(170,250,100,0.2); min-height: 420px;">
                    ${coach}
                </div>
            </div>
        </div>`;
}

// Página de un plan: precio, beneficios y programas incluidos.
function renderPlan(cont, plan) {
    const estilo = presentacionPlanes[plan.slug] || { color: "#AAFA64", detalles: [] };
    const precio = "$" + Number(plan.precio).toLocaleString("es-UY");
    const programas = plan.programas.map((programa) =>
        `<li class="mb-2"><a href="../programas/programas.html?programa=${encodeURIComponent(programa.slug)}" class="text-white">${escaparHtml(programa.nombre)}</a>
         <span class="text-white-50"> · ${escaparHtml(programa.entrenador || "coach por asignar")}</span></li>`
    ).join("");

    cont.innerHTML = `
        <div class="row g-4 align-items-stretch">
            <div class="col-12">
                <div class="text-center p-4 p-md-5 rounded-4" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(170,250,100,0.12); min-height: 220px;">
                    <h2 class="mb-3 text-uppercase fw-bold" style="background: linear-gradient(135deg, #46ECF4, ${estilo.color}); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                        ${escaparHtml(plan.nombre)}
                    </h2>
                    <p class="lead text-white-50">${escaparHtml(plan.descripcion)}</p>
                    <div class="display-3 fw-bold my-4 text-white">${precio}<small class="h5 text-muted">/mes</small></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark text-white border-secondary p-4 p-md-5 rounded-4 h-100" style="border-left: 5px solid ${estilo.color} !important; min-height: 240px;">
                    <h4 class="mb-3 text-center">Beneficios del plan:</h4>
                    <ul class="list-unstyled ps-3">
                        ${estilo.detalles.map(d => `<li class="mb-3"><span style="color: ${estilo.color}; font-weight: bold; margin-right: 10px;">✓</span> ${d}</li>`).join('')}
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-dark text-white border-secondary p-4 p-md-5 rounded-4 h-100" style="border-left: 5px solid ${estilo.color} !important; min-height: 240px;">
                    <h4 class="mb-3 text-center">Programas incluidos:</h4>
                    <ul class="list-unstyled ps-3">${programas || '<li class="text-white-50">Sin programas asignados.</li>'}</ul>
                </div>
            </div>
        </div>`;
}

// --- Sesión abierta ---
// Si el usuario ya inició sesión, el botón "Iniciar Sesión" pasa a ser
// "Mi panel" y lleva a su dashboard, así puede volver sin loguearse de nuevo.
async function mostrarAccesoAlPanel() {
    try {
        const respuesta = await fetch("/api/sesion");
        if (!respuesta.ok) return;
        const { usuario } = await respuesta.json();
        if (!usuario) return;
        const carpeta = { admin: "admin", entrenador: "entrenador", socio: "socio", personal: "personal", recepcion: "recepcion" }[usuario.rol];
        if (!carpeta) return;

        document.querySelectorAll('a[href$="formularios/login.html"]').forEach((enlace) => {
            enlace.href = enlace.getAttribute("href").replace("formularios/login.html", `dashboard/${carpeta}/dashboard-${carpeta}.html`);
            enlace.textContent = "Mi panel";
            enlace.title = "Sesión de " + usuario.nombre;
        });
    } catch (error) { /* sin API: se deja "Iniciar Sesión" */ }
}

// --- Lógica Principal ---
document.addEventListener("DOMContentLoaded", function () {
    mostrarAccesoAlPanel();

    // 1. Menú Hamburguesa
    const btnMenu = document.getElementById("btn-menu");
    const navEnlaces = document.getElementById("nav-enlaces");

    if (btnMenu && navEnlaces) {
        btnMenu.addEventListener("click", () => {
            const abierto = navEnlaces.classList.toggle("abierto");
            btnMenu.setAttribute("aria-expanded", abierto ? "true" : "false");
        });

        navEnlaces.querySelectorAll(".link").forEach(link => {
            link.addEventListener("click", cerrarMenuMovil);
        });
    }

    document.querySelectorAll('.link').forEach(link => {
        link.addEventListener('click', function () {
            if (this.getAttribute('href') && this.getAttribute('href').startsWith('#')) {
                cambiarActivo(this);
            }
        });
    });

    // 2. Renderizado Dinámico
    const cont = document.getElementById('render-content');
    if (cont) {
        const params = new URLSearchParams(window.location.search);
        const path = window.location.pathname;

        // Carga el programa o plan de la URL y lo dibuja (o avisa que no existe).
        const mostrar = (ruta, clave, render, noEncontrado) => {
            if (!params.get(clave)) {
                cont.innerHTML = `<div class="text-center py-5 text-danger">${noEncontrado}</div>`;
                return;
            }
            pedirCatalogo(ruta + encodeURIComponent(params.get(clave)))
                .then((datos) => render(cont, datos[clave]))
                .catch(() => { cont.innerHTML = `<div class="text-center py-5 text-danger">${noEncontrado}</div>`; });
        };

        if (path.includes('programas.html')) {
            mostrar('/programas/', 'programa', renderPrograma, 'Programa no encontrado.');
        } else if (path.includes('planes.html')) {
            mostrar('/planes/', 'plan', renderPlan, 'Plan no encontrado.');
        }
    }
});