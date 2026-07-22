// --- Lógica General: Navegación y Slider ---
function cambiarActivo(enlaceClickeado) {
    document.querySelectorAll(".link").forEach(el => el.classList.remove("activo"));
    enlaceClickeado.classList.add("activo");
}

function moverSlider(indiceBloque) {
    const riel = document.getElementById("riel-profesionales");
    if (!riel) return;
    riel.style.transform = `translateX(${indiceBloque * -33.3333}%)`;
    
    const puntos = document.querySelectorAll(".punto-nav");
    puntos.forEach(punto => punto.classList.remove("activo"));
    if (puntos[indiceBloque]) puntos[indiceBloque].classList.add("activo");
}

// --- Base de Datos ---
const baseDeDatos = {
    programas: {
        boxeo: { titulo: "BOXEO", img: "boxeo.jpg", coachNombre: "Juan Díaz", coachImg: "entrenador5.jpg", historia: "El boxeo tiene raíces milenarias, evolucionando de métodos de defensa a un deporte estratégico. En Fit Power, combinamos tradición con técnica moderna.", tecnica: "Se basa en el juego de pies, el control del centro de gravedad y la ejecución precisa de jabs y rectos.", beneficios: "Mejora cardiovascular, quema de grasa y liberación de adrenalina." },
        musculacion: { titulo: "MUSCULACIÓN", img: "musculacion.jpg", coachNombre: "Diego Torres", coachImg: "entrenador11.jpg", historia: "La base de toda transformación física. Enfocada en la sobrecarga progresiva y el desarrollo de fibras musculares.", tecnica: "Movimientos controlados, rango de movimiento completo y periodización de cargas.", beneficios: "Aumento de fuerza, mejora de la postura y metabolismo acelerado." },
        cross: { titulo: "CROSS TRAINING", img: "crosstrainning.jpg", coachNombre: "Lucas Gomez", coachImg: "entrenador7.jpeg", historia: "Entrenamiento funcional de alta intensidad que prepara el cuerpo para cualquier demanda física real.", tecnica: "Uso de pesos libres, ejercicios gimnásticos y movimientos explosivos.", beneficios: "Resistencia física extrema, agilidad y potencia muscular." },
        spinning: { titulo: "SPINNING", img: "spinning.jpg", coachNombre: "Sofía Rey", coachImg: "entrenador6.jpg", historia: "Cardio de alta intensidad en bicicleta fija, diseñado para desafiar tus límites al ritmo de la música.", tecnica: "Control de cadencia, postura ergonómica y manejo de intensidades.", beneficios: "Salud cardiovascular, quema de calorías y fortalecimiento del tren inferior." },
        yoga: { titulo: "YOGA", img: "yoga.jpg", coachNombre: "Carlos Pérez", coachImg: "entrenador1.jpg", historia: "Una práctica ancestral que conecta mente, cuerpo y espíritu mediante asanas y control respiratorio.", tecnica: "Control de la respiración (pranayama) y alineación corporal consciente.", beneficios: "Reducción de cortisol, mejora de la flexibilidad y paz mental." },
        pilates: { titulo: "PILATES", img: "pilates.jpg", coachNombre: "Elena Paz", coachImg: "entrenador8.jpg", historia: "Creado por Joseph Pilates para la rehabilitación, hoy es clave para la fuerza central (core).", tecnica: "Activación profunda de los músculos estabilizadores del abdomen y pelvis.", beneficios: "Corrección postural, alivio de dolores lumbares y tonicidad." },
        funcional: { titulo: "ENTRENO FUNCIONAL", img: "EntranamientoFuncional.jpg", coachNombre: "Bruno Soler", coachImg: "entrenador9.jpg", historia: "Entrenar para la vida cotidiana. Movimientos naturales que mejoran la eficiencia de tu cuerpo.", tecnica: "Patrones de empuje, tracción, bisagra de cadera y rotación.", beneficios: "Funcionalidad diaria, prevención de lesiones y equilibrio." },
        natacion: { titulo: "NATACIÓN", img: "natacion.jpg", coachNombre: "Matias Rossi", coachImg: "entrenador3.jpg", historia: "La disciplina más completa. El agua ofrece una resistencia natural que tonifica sin impacto.", tecnica: "Técnica de brazada, patada y coordinación respiratoria en el agua.", beneficios: "Bajo impacto, resistencia total y relajación muscular." }
    },
    planes: {
        basico: { 
            titulo: "Plan Básico", 
            precio: "$1.800", 
            descripcion: "Ideal para quienes quieren empezar a entrenar por su cuenta con guía.",
            detalles: ["3 programas a elección", "Disponibilidad a chat de limpieza"],
            color: "#A0A0A0" 
        },
        pro: { 
            titulo: "Plan Pro", 
            precio: "$2.500", 
            descripcion: "El equilibrio perfecto entre entrenamiento técnico y acompañamiento cercano.",
            detalles: ["Cuenta con entrenador propio", "Acceso a todos los programas", "Ajustes de rutinas por personal trainer", "Acceso a nutricionista"],
            color: "#AAFA64" 
        },
        elite: { 
            titulo: "Plan Elite", 
            precio: "$4.500", 
            descripcion: "La experiencia completa de alto rendimiento con personalización total.",
            detalles: ["Acceso a todos los programas", "Personalizar tus propias rutinas y cambios", "Acceso a coach y soporte técnico"],
            color: "#FFD700" 
        }
    }
};

// --- Lógica Principal ---
document.addEventListener("DOMContentLoaded", function () {
    // 1. Menú Hamburguesa
    const btnMenu = document.getElementById("btn-menu");
    const navEnlaces = document.getElementById("nav-enlaces");

    if (btnMenu && navEnlaces) {
        btnMenu.addEventListener("click", () => navEnlaces.classList.toggle("abierto"));
    }

    // 2. Renderizado Dinámico
    const cont = document.getElementById('render-content');
    if (cont) {
        const params = new URLSearchParams(window.location.search);
        const path = window.location.pathname;

        if (path.includes('programas.html')) {
            const programaId = params.get('programa');
            const data = baseDeDatos.programas[programaId];
            if (data) {
                cont.innerHTML = `
                    <img src="../../assets/imagenes/${data.img}" class="img-fluid rounded mb-4" style="width:100%; height:350px; object-fit:cover;">
                    <h1 class="titulos text-uppercase">${data.titulo}</h1>
                    <div class="row mt-5">
                        <div class="col-md-8 text-start">
                            <h3 class="verde-titulo">Historia</h3><p>${data.historia}</p>
                            <h3 class="verde-titulo">Técnica</h3><p>${data.tecnica}</p>
                            <h3 class="verde-titulo">Beneficios</h3><p>${data.beneficios}</p>
                        </div>
                        <div class="col-md-4 text-center d-flex flex-column align-items-center justify-content-center">
                            <img src="../../assets/imagenes/${data.coachImg}" style="width:200px; height:200px; border-radius:50%; object-fit:cover; border: 4px solid #AAFA64; margin-bottom:15px; box-shadow: 0 0 15px rgba(170, 250, 100, 0.3);">
                            <h5 class="text-white">Coach a cargo:</h5>
                            <h4 class="verde-titulo">${data.coachNombre}</h4>
                        </div>
                    </div>`;
            } else {
                cont.innerHTML = `<div class="text-center py-5 text-danger">Programa no encontrado.</div>`;
            }
        } else if (path.includes('planes.html')) {
            const planId = params.get('plan');
            const data = baseDeDatos.planes[planId];
            if (data) {
                cont.innerHTML = `
                    <div class="text-center">
                        <h2 class="mb-3 text-uppercase fw-bold" style="background: linear-gradient(135deg, #46ECF4, ${data.color}); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            ${data.titulo}
                        </h2>
                        <p class="lead text-white-50">${data.descripcion}</p>
                        <div class="display-3 fw-bold my-4 text-white">${data.precio}<small class="h5 text-muted">/mes</small></div>
                    </div>
                    
                    <div class="card bg-dark text-white border-secondary p-4 mt-4" style="border-left: 5px solid ${data.color} !important;">
                        <h4 class="mb-3 text-center">Beneficios del plan:</h4>
                        <ul class="list-unstyled ps-3">
                            ${data.detalles.map(d => `<li class="mb-3"><span style="color: ${data.color}; font-weight: bold; margin-right: 10px;">✓</span> ${d}</li>`).join('')}
                        </ul>
                    </div>`;
            } else {
                cont.innerHTML = `<div class="text-center py-5 text-danger">Plan no encontrado.</div>`;
            }
        }
    }
});