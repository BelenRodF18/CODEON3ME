/* Cambio de idioma ES/EN: botón flotante + traducción del DOM con diccionarios (ver FPIdioma.agregar). */

(function () {
    const CLAVE = "fp-idioma";
    const ATRIBUTOS = ["placeholder", "alt", "title", "aria-label"];

    const EN = {
        // Menú y encabezados
        "Fit Power Vista Principal": "Fit Power Home",
        "Inicio": "Home",
        "Empieza Ya": "Start Now",
        "Programas": "Programs",
        "Planes": "Plans",
        "Staff": "Staff",
        "Nuestra Historia": "Our Story",
        "Iniciar Sesión": "Log In",
        "Mi panel": "My dashboard",
        "Navegación principal": "Main navigation",
        "Abrir menú": "Open menu",
        "Fondo": "Background",

        // Portada
        "Empieza a": "Start",
        "Cuidarte": "Taking Care",
        "Despierta tu potencial. Tu mejor versión te espera. Comienza tu viaje hoy.": "Awaken your potential. Your best self is waiting. Start your journey today.",
        "Conocer más": "Learn more",

        // Programas
        "NUESTROS PROGRAMAS": "OUR PROGRAMS",
        "BOXEO": "BOXING",
        "Boxeo": "Boxing",
        "MUSCULACIÓN": "WEIGHT TRAINING",
        "Musculación": "Weight training",
        "CROSS TRAINING": "CROSS TRAINING",
        "SPINNING": "SPINNING",
        "YOGA": "YOGA",
        "PILATES": "PILATES",
        "ENTRENO FUNCIONAL": "FUNCTIONAL TRAINING",
        "Entreno Funcional": "Functional Training",
        "NATACIÓN": "SWIMMING",
        "Natación": "Swimming",
        "Saber más →": "Learn more →",

        // Planes
        "PLANES": "PLANS",
        "Plan Básico": "Basic Plan",
        "Plan Pro": "Pro Plan",
        "Plan Elite": "Elite Plan",
        "/mes": "/month",
        "- 3 programas a elección": "- 3 programs of your choice",
        "- Ver todas las rutinas": "- See all routines",
        "- Disponibilidad a soporte técnico": "- Technical support available",
        "- Cuenta con entrenador propio": "- Your own trainer",
        "- Acceso a todos los programas": "- Access to all programs",
        "- Ajustes de rutinas por personal trainer": "- Routine adjustments by a personal trainer",
        "- Acceso a nutricionista": "- Access to a nutritionist",
        "- Personalizar tus propias rutinas y cambios": "- Customize your own routines and changes",
        "- Acceso a coach y soporte técnico": "- Access to a coach and technical support",
        "Conocer plan": "View plan",

        // Staff
        "Conocé a nuestros profesionales": "Meet our professionals",
        "Funcional": "Functional",
        "Nutricionista": "Nutritionist",

        // Historia / cierre
        "Nuestra historia": "Our story",
        "Ni vos ni nosotros empezamos siendo los mejores. Este gimnasio lo creamos a base de esfuerzo, ganando con sudor las bases para avanzar y fortalecernos. Lo mismo lograrás en este espacio que te brindamos, ganarás la fuerza, la motivación, te daremos el poder de lograr tus metas.":
            "Neither you nor we started out as the best. We built this gym through effort, earning the foundations to move forward and grow stronger with sweat. You will achieve the same in the space we offer you: you will gain strength and motivation, and we will give you the power to reach your goals.",
        "¡Te esperamos!": "We are waiting for you!",
        "La mejor forma de entrenar tu cuerpo y tu mente. Únete a nosotros.": "The best way to train your body and mind. Join us.",
        "La mejor manera de entrenar tu body y mente.": "The best way to train your body and mind.",
        "Servicios": "Services",
        "Nuestros Servicios": "Our Services",
        "Textos Legales": "Legal Notices",
        "Política de privacidad": "Privacy Policy",
        "Política de cookies": "Cookie Policy",
        "Aviso legal": "Legal Notice",
        "© 2026 FIT POWER. Todos los derechos reservados.": "© 2026 FIT POWER. All rights reserved.",
        "Inicio del gimnasio": "Gym opening",
        "Construcción del gimnasio": "Gym construction",

        // Aviso legal
        "Aviso Legal - Fit Power": "Legal Notice - Fit Power",
        "El contenido de esta web tiene fines informativos y promocionales. Fit Power se reserva el derecho de modificar textos, precios, horarios y servicios sin previo aviso.":
            "The content of this website is for informational and promotional purposes. Fit Power reserves the right to modify texts, prices, schedules and services without prior notice.",
        "El acceso y uso de este sitio implica la aceptación de las condiciones aquí expuestas. Toda la información proporcionada debe considerarse orientativa y no sustituye el asesoramiento profesional correspondiente.":
            "Accessing and using this site implies acceptance of the conditions set out here. All information provided should be considered indicative and does not replace the corresponding professional advice.",

        // Login
        "Fit Power - Iniciar Sesión": "Fit Power - Log In",
        "← Volver al inicio": "← Back to home",
        "Acceso al Sistema": "System Access",
        "Usuario": "Username",
        "Contraseña": "Password",
        "¿No tienes cuenta? Solicita tu acceso presencialmente con el Administrador.": "Don't have an account? Request access in person from the Administrator.",
        "ej. juan.diaz": "e.g. juan.diaz",

        // Más información
        "Más Información - Fit Power": "More Information - Fit Power",
        "SISTEMA INTELIGENTE & VENTAJAS COMPETITIVAS": "SMART SYSTEM & COMPETITIVE ADVANTAGES",
        "Mantenimiento Autónomo": "Autonomous Maintenance",
        "¿Una polea floja o un equipo con fallas? Reportalo en tiempo real subiendo una foto directo desde la app. Nuestro sistema genera un ticket automático y abre un chat interactivo para avisarte apenas la máquina vuelva a estar 100% operativa.":
            "A loose pulley or a faulty machine? Report it in real time by uploading a photo straight from the app. Our system creates an automatic ticket and opens an interactive chat to let you know as soon as the machine is 100% operational again.",
        "Acceso por QR Dinámico": "Dynamic QR Access",
        "Olvidate de las tarjetas físicas o accesos lentos. Generá tu código QR dinámico de alta seguridad en tu Portal de Socio que se actualiza cada pocos segundos. Ingresá al instante y revisá el aforo total del gimnasio en tiempo real.":
            "Forget physical cards and slow entry. Generate your high-security dynamic QR code in your Member Portal, which refreshes every few seconds. Get in instantly and check the gym's total occupancy in real time.",
        "Monitoreo de PRs & Smart Timer": "PR Tracking & Smart Timer",
        "Llevá el control absoluto de tus Récords Personales (PRs) mutables por sesión. Al registrar tu serie, la app activa de manera automática un cronómetro de descanso adaptativo con alertas por vibración para que no bajes la intensidad.":
            "Keep full control of your Personal Records (PRs), updated every session. When you log your set, the app automatically starts an adaptive rest timer with vibration alerts so you never lose intensity.",
        "Contactanos": "Contact us",
        "Escribinos si tenés alguna duda, consulta o si estás listo para dar el primer paso hacia tus metas. Nuestro equipo te responderá lo antes posible.":
            "Write to us if you have any questions, or if you're ready to take the first step toward your goals. Our team will reply as soon as possible.",
        "Enviar comentario": "Send comment",
        "Ubicación": "Location",
        "Nombre": "First name",
        "Apellido": "Last name",
        "Email o Teléfono": "Email or phone",
        "Mensaje": "Message",

        // Nuestra historia
        "Nuestra Historia - Fit Power": "Our Story - Fit Power",
        "EL ORIGEN: DEL GARAJE AL ASFALTO": "THE ORIGIN: FROM THE GARAGE TO THE ASPHALT",
        "Fit Power no nació en un edificio corporativo de cristal, nació en un sótano húmedo de 40 metros cuadrados en el año 2018. Con solo tres barras oxidadas, un par de mancuernas caseras y un sistema de sonido viejo, nos propusimos crear algo diferente: un refugio para los que buscan romper sus límites reales sin pretensiones ni filtros de Instagram.":
            "Fit Power was not born in a glass corporate building; it was born in a damp 40-square-meter basement in 2018. With just three rusty bars, a couple of homemade dumbbells and an old sound system, we set out to create something different: a refuge for those who want to break their real limits, with no pretenses and no Instagram filters.",
        "La regla era simple: entrenar con intensidad ciega y dejar los pretextos afuera. Ese espíritu crudo e implacable es la base que hoy sostiene cada una de nuestras sedes.":
            "The rule was simple: train with blind intensity and leave the excuses outside. That raw, relentless spirit is the foundation that supports each of our locations today.",
        "Mantra Fundacional": "Founding Mantra",
        "\"Tu mente se rinde miles de repeticiones antes que tu cuerpo. Domina la máquina interna.\"": "\"Your mind gives up thousands of reps before your body does. Master the inner machine.\"",
        "LA EVOLUCIÓN: CYBER-FITNESS": "THE EVOLUTION: CYBER-FITNESS",
        "A medida que crecimos, nos dimos cuenta de que la fuerza bruta necesita dirección inteligente. En 2024 decidimos fusionar nuestra cultura de alta intensidad con innovación tecnológica radical. Cambiamos los accesos obsoletos por sistemas inteligentes de QR biométricos, digitalizamos el reporte de mantenimiento y automatizamos los cronómetros de series directamente en tu app.":
            "As we grew, we realized that brute strength needs smart direction. In 2024 we decided to merge our high-intensity culture with radical technological innovation. We replaced obsolete access with smart biometric QR systems, digitized maintenance reporting and automated set timers right in your app.",
        "Hoy somos un ecosistema de rendimiento masivo. Conservamos el alma pesada de aquel sótano inicial, pero potenciada por la ingeniería moderna del mañana.":
            "Today we are a massive performance ecosystem. We keep the heavy soul of that first basement, powered by tomorrow's modern engineering.",
        "Socios Activos": "Active Members",
        "Autónomo": "Autonomous",

        // Planes / programas (páginas dinámicas)
        "Planes - Fit Power": "Plans - Fit Power",
        "Programas - Fit Power": "Programs - Fit Power",
        "Cargando detalles de tu plan...": "Loading your plan details...",
        "Cargando información del programa...": "Loading program information...",
        "Historia": "History",
        "Técnica": "Technique",
        "Beneficios": "Benefits",
        "Incluido en": "Included in",
        "Coach a cargo:": "Coach in charge:",
        "Próximamente": "Coming soon",
        "Beneficios del plan:": "Plan benefits:",
        "Programas incluidos:": "Included programs:",
        "Sin programas asignados.": "No programs assigned.",
        "Programa no encontrado.": "Program not found.",
        "Plan no encontrado.": "Plan not found.",
        "3 programas incluidos": "3 programs included",
        "Disponibilidad a chat de limpieza": "Cleaning chat availability",
        "Cuenta con entrenador propio": "Your own trainer",
        "Acceso a todos los programas": "Access to all programs",
        "Ajustes de rutinas por personal trainer": "Routine adjustments by a personal trainer",
        "Acceso a nutricionista": "Access to a nutritionist",
        "Personalizar tus propias rutinas y cambios": "Customize your own routines and changes",
        "Acceso a coach y soporte técnico": "Access to a coach and technical support",

        // Cookies
        "Política de Cookies - Fit Power": "Cookie Policy - Fit Power",
        "Utilizamos cookies para recordar tus preferencias, mantener tu sesión activa y mejorar la navegación en el sitio web de Fit Power.":
            "We use cookies to remember your preferences, keep your session active and improve navigation on the Fit Power website.",
        "Estas cookies pueden ser de sesión o persistentes y ayudan a cargar contenido de forma más rápida y a personalizar la experiencia del usuario. Puedes deshabilitarlas desde la configuración de tu navegador, aunque algunas funciones del sitio podrían verse afectadas.":
            "These cookies may be session-based or persistent and help load content faster and personalize the user experience. You can disable them in your browser settings, although some site features may be affected.",

        // Privacidad
        "Política de Privacidad - Fit Power": "Privacy Policy - Fit Power",
        "En Fit Power tratamos tus datos con responsabilidad y solo los utilizamos para brindar un mejor servicio, gestionar tu acceso al sistema y mejorar la experiencia de entreno.":
            "At Fit Power we handle your data responsibly and only use it to provide a better service, manage your access to the system and improve your training experience.",
        "Los datos recopilados pueden incluir nombre, correo, teléfono, datos de acceso y, en algunos casos, información relacionada con tus preferencias de entrenamiento. No compartimos esta información con terceros sin tu autorización, salvo cuando la ley o la operación del servicio lo exija.":
            "The data collected may include name, email, phone, access data and, in some cases, information related to your training preferences. We do not share this information with third parties without your authorization, except when required by law or by the operation of the service.",
        "Puedes solicitar acceso, rectificación o eliminación de tus datos contactándonos a través de nuestras redes o canales de atención del gimnasio.":
            "You can request access to, correction of or deletion of your data by contacting us through our social networks or the gym's customer service channels.",

        // Redes sociales
        "Redes Sociales - Fit Power": "Social Media - Fit Power",
        "Síguenos en redes": "Follow us on social media",
        "Mantente conectado con Fit Power para ver novedades, rutinas, eventos y contenido exclusivo.":
            "Stay connected with Fit Power for news, routines, events and exclusive content.",
        "Ver perfil": "View profile",
        "Ver comunidad": "View community",
        "Escribir": "Write",

        // Página 404
        "Página no encontrada - Fit Power": "Page not found - Fit Power",
        "Página no encontrada": "Page not found",
        "La dirección que buscás no existe o se movió de lugar. Volvé al inicio y seguí entrenando.": "The address you are looking for does not exist or has moved. Go back home and keep training.",
        "Volver al inicio": "Back to home",

        // Servicios
        "Servicios - Fit Power": "Services - Fit Power",
        "Nuestros servicios": "Our services",
        "Ofrecemos entrenamiento funcional, clases dirigidas, musculación, cardio avanzado y planes adaptados a cada objetivo.":
            "We offer functional training, group classes, weight training, advanced cardio and plans tailored to every goal.",
        "Entrenamiento funcional": "Functional training",
        "Clases dirigidas": "Group classes",
        "Rutinas pensadas para mejorar fuerza, movilidad y rendimiento diario.": "Routines designed to improve strength, mobility and everyday performance.",
        "Boxeo, spinning, yoga, pilates y más, con seguimiento profesional.": "Boxing, spinning, yoga, pilates and more, with professional follow-up.",
        "Planes a medida": "Tailored plans",
        "Adaptamos tu plan según tus metas, tiempos y nivel de experiencia.": "We adapt your plan to your goals, schedule and experience level."
    };

    let idioma = "es";
    try { idioma = localStorage.getItem(CLAVE) === "en" ? "en" : "es"; } catch (e) { }

    const originalesTexto = new WeakMap();
    const originalesAttr = new WeakMap();
    let observador = null;

    const normalizar = (t) => t.replace(/\s+/g, " ").trim();

    // ---- Motor ----
    const REGLAS = [];
    const FRASES = {};
    let reFrases = null;

    const MESES = { enero: "January", febrero: "February", marzo: "March", abril: "April", mayo: "May", junio: "June",
        julio: "July", agosto: "August", septiembre: "September", setiembre: "September", octubre: "October",
        noviembre: "November", diciembre: "December" };

    function escRe(t) { return t.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); }

    function compilarFrases() {
        const claves = Object.keys(FRASES).sort((a, b) => b.length - a.length);
        reFrases = claves.length
            ? new RegExp("(?<![\\p{L}\\p{N}])(?:" + claves.map(escRe).join("|") + ")(?![\\p{L}\\p{N}])", "gu")
            : null;
    }

    // Convierte fechas y precios al formato inglés.
    function posproceso(t) {
        return t
            .replace(/(\d{1,2}) de (enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)/gi,
                (_, d, mes) => MESES[mes.toLowerCase()] + " " + d)
            .replace(/\$(\d{1,3}(?:\.\d{3})+)(?!\d)/g, (_, n) => "$" + n.replace(/\./g, ","));
    }

    const aplicarFrases = (t) => (reFrases ? t.replace(reFrases, (f) => FRASES[f]) : t);

    // Busca la traducción: exacta, por regla, por partes "A · B" o por frases. Devuelve null si no hay.
    function traducir(clave, profundidad = 0) {
        if (!clave) return null;
        if (Object.prototype.hasOwnProperty.call(EN, clave)) return EN[clave];
        for (const [re, rep] of REGLAS) {
            const m = re.exec(clave);
            if (m && m[0] === clave) return posproceso(aplicarFrases(clave.replace(re, rep)));
        }
        if (profundidad === 0 && clave.includes(" · ")) {
            const partes = clave.split(" · ");
            const tr = partes.map((p) => traducir(p, 1));
            if (tr.some((p) => p !== null)) return partes.map((p, i) => (tr[i] !== null ? tr[i] : p)).join(" · ");
        }
        const salida = posproceso(aplicarFrases(clave));
        return salida !== clave ? salida : null;
    }

    function traducirConEspacios(actual) {
        const clave = normalizar(actual);
        const en = traducir(clave);
        if (en === null) return null;
        const ini = actual.match(/^\s*/)[0], fin = actual.match(/\s*$/)[0];
        return ini + en + fin;
    }

    // Traduce o restaura un nodo de texto guardando su original en español.
    function procesarTexto(nodo) {
        const padre = nodo.parentElement;
        if (!padre || /^(SCRIPT|STYLE|NOSCRIPT|TEXTAREA)$/.test(padre.tagName)) return;
        const actual = nodo.nodeValue;
        let reg = originalesTexto.get(nodo);
        if (!reg || (actual !== reg.es && actual !== reg.en)) {
            reg = { es: actual, en: traducirConEspacios(actual) };
            originalesTexto.set(nodo, reg);
        }
        const destino = idioma === "en" && reg.en ? reg.en : reg.es;
        if (nodo.nodeValue !== destino) nodo.nodeValue = destino;
    }

    function procesarAtributos(el) {
        let mapa = originalesAttr.get(el);
        ATRIBUTOS.forEach((attr) => {
            if (!el.hasAttribute(attr)) return;
            const actual = el.getAttribute(attr);
            if (!mapa) { mapa = {}; originalesAttr.set(el, mapa); }
            let reg = mapa[attr];
            if (!reg || (actual !== reg.es && actual !== reg.en)) {
                reg = { es: actual, en: traducirConEspacios(actual) };
                mapa[attr] = reg;
            }
            const destino = idioma === "en" && reg.en ? reg.en : reg.es;
            if (actual !== destino) el.setAttribute(attr, destino);
        });
    }

    function recorrer(raiz) {
        if (raiz.nodeType === Node.TEXT_NODE) { procesarTexto(raiz); return; }
        if (raiz.nodeType !== Node.ELEMENT_NODE) return;
        if (raiz.closest && raiz.closest("[data-i18n-skip]")) return;
        procesarAtributos(raiz);
        const caminante = document.createTreeWalker(raiz, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
        let n;
        while ((n = caminante.nextNode())) {
            if (n.nodeType === Node.TEXT_NODE) {
                if (!n.parentElement.closest("[data-i18n-skip]")) procesarTexto(n);
            } else if (!n.closest("[data-i18n-skip]")) {
                procesarAtributos(n);
            }
        }
    }

    function aplicar() {
        if (observador) observador.disconnect();
        recorrer(document.body);
        const titulo = document.querySelector("title");
        if (titulo) {
            if (!titulo.dataset.es) titulo.dataset.es = titulo.textContent;
            titulo.textContent = (idioma === "en" && traducir(normalizar(titulo.dataset.es))) || titulo.dataset.es;
        }
        document.documentElement.lang = idioma;
        actualizarBoton();
        if (observador) observador.observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    let boton = null;
    function crearBoton() {
        const estilo = document.createElement("style");
        estilo.textContent =
            ".fp-idioma{position:fixed;right:16px;bottom:16px;z-index:9999;display:flex;align-items:center;gap:6px;" +
            "padding:9px 14px;border:1px solid rgba(170,250,100,.6);border-radius:999px;background:rgba(15,15,15,.92);" +
            "color:#fff;font:600 13px/1 'Space Grotesk','Inter',sans-serif;letter-spacing:.04em;cursor:pointer;" +
            "box-shadow:0 4px 14px rgba(0,0,0,.4);transition:transform .15s,background .15s}" +
            ".fp-idioma:hover{transform:translateY(-2px);background:#1c1c1c}" +
            ".fp-idioma:focus-visible{outline:2px solid #AAFA64;outline-offset:2px}" +
            ".fp-idioma b{color:#AAFA64}" +
            ".fp-idioma--oculto{opacity:0;transform:translateY(16px);pointer-events:none}" +
            "@media (max-width:600px){.fp-idioma{right:12px;bottom:12px;padding:7px 11px;font-size:12px}}" +
            ".fp-idioma--en-menu{position:static;box-shadow:none;margin:0;padding:6px 10px;font-size:12px;flex:0 0 auto}" +
            ".fp-idioma--en-lado{position:static;box-shadow:none;align-self:flex-start;margin:auto 0 12px;padding:7px 12px;font-size:12px}" +
            ".fp-idioma--en-lado + .socio-inicio{margin-top:0}";
        document.head.appendChild(estilo);
        boton = document.createElement("button");
        boton.type = "button";
        boton.className = "fp-idioma";
        boton.setAttribute("data-i18n-skip", "");
        boton.addEventListener("click", () => cambiarIdioma(idioma === "es" ? "en" : "es"));
        document.body.appendChild(boton);
        // En celular no flota: va dentro de la cabecera (paneles) o del menú de arriba (web pública).
        // En los paneles de escritorio va dentro del menú lateral. Así no queda encima de otro botón.
        const movil = window.matchMedia("(max-width:780px)");
        const lado = document.querySelector(".socio-sidebar");
        const ubicar = () => {
            const marca = document.querySelector(".socio-sidebar .socio-brand, .rec-topbar");
            const menuPublico = document.querySelector("nav.navbar");
            boton.classList.remove("fp-idioma--en-menu", "fp-idioma--en-lado");
            if (movil.matches && marca) {
                marca.appendChild(boton);
                boton.classList.add("fp-idioma--en-menu");
            } else if (movil.matches && menuPublico) {
                // En la web pública, en celular, va en el menú de arriba (al lado de la hamburguesa).
                menuPublico.insertBefore(boton, menuPublico.querySelector(".menu-hamburguesa"));
                boton.classList.add("fp-idioma--en-menu");
            } else if (lado && !movil.matches) {
                lado.insertBefore(boton, lado.querySelector(".socio-inicio") || lado.querySelector(".socio-logout"));
                boton.classList.add("fp-idioma--en-lado");
            } else {
                document.body.appendChild(boton);
            }
        };
        ubicar();
        if (movil.addEventListener) movil.addEventListener("change", ubicar);
        // Se esconde al bajar la página (para no tapar botones) y vuelve al subir o al llegar al final.
        let ultimoY = window.scrollY;
        window.addEventListener("scroll", () => {
            const y = window.scrollY;
            const alFinal = window.innerHeight + y >= document.documentElement.scrollHeight - 40;
            if (boton.classList.contains("fp-idioma--en-lado") || boton.classList.contains("fp-idioma--en-menu")) return;
            if (y > ultimoY + 4 && y > 120 && !alFinal) boton.classList.add("fp-idioma--oculto");
            else if (y < ultimoY - 4 || y <= 120 || alFinal) boton.classList.remove("fp-idioma--oculto");
            ultimoY = y;
        }, { passive: true });
    }

    function actualizarBoton() {
        if (!boton) return;
        boton.innerHTML = "🌐 " + (idioma === "es" ? "<b>ES</b> | EN" : "ES | <b>EN</b>");
        boton.setAttribute("aria-label", idioma === "es" ? "Switch to English" : "Cambiar a español");
        boton.title = boton.getAttribute("aria-label");
    }

    function cambiarIdioma(nuevo) {
        idioma = nuevo;
        try { localStorage.setItem(CLAVE, idioma); } catch (e) { }
        aplicar();
    }

    function iniciar() {
        crearBoton();
        observador = new MutationObserver((registros) => {
            if (idioma !== "en") return;
            observador.disconnect();
            registros.forEach((r) => {
                if (r.type === "characterData") procesarTexto(r.target);
                else r.addedNodes.forEach((n) => recorrer(n));
            });
            observador.observe(document.body, { childList: true, subtree: true, characterData: true });
        });
        aplicar();
    }

    window.FPIdioma = {
        actual: () => idioma,
        cambiar: cambiarIdioma,
        t: (s) => (idioma === "en" && traducir(normalizar(String(s)))) || s,
        agregar: ({ exactos = {}, frases = {}, reglas = [] } = {}) => {
            Object.assign(EN, exactos);
            Object.assign(FRASES, frases);
            reglas.forEach((r) => REGLAS.push(r));
            compilarFrases();
            if (document.body && boton) aplicar();
        }
    };

    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", iniciar);
    else iniciar();
})();
