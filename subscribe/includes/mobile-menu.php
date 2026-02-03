<?php
// Archivo para incluir el menú móvil en todas las páginas
?>
<!-- Botón hamburguesa para móvil -->
<button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
    <span class="hamburger"></span>
    <span class="hamburger"></span>
    <span class="hamburger"></span>
</button>

<style>
/* MENÚ MÓVIL - ESTILOS */
.nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.nav-menu {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.nav-link {
    text-decoration: none;
    color: #64748b;
    font-weight: 500;
    transition: color 0.3s ease;
}

.nav-link:hover {
    color: #059669;
}

.nav-toggle {
    display: none;
    flex-direction: column;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    z-index: 10000;
}

.hamburger {
    width: 25px;
    height: 3px;
    background: #059669;
    margin: 3px 0;
    transition: 0.3s;
    border-radius: 2px;
}

.nav-toggle.active .hamburger:nth-child(1) {
    transform: rotate(-45deg) translate(-5px, 6px);
}

.nav-toggle.active .hamburger:nth-child(2) {
    opacity: 0;
}

.nav-toggle.active .hamburger:nth-child(3) {
    transform: rotate(45deg) translate(-5px, -6px);
}

@media (max-width: 768px) {
    .nav-toggle {
        display: flex !important;
        background: #f0f0f0 !important;
        border: 2px solid #059669 !important;
    }
    
    .nav-menu {
        position: fixed !important;
        top: 0 !important;
        right: -100% !important;
        width: 100% !important;
        height: 100vh !important;
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(10px) !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        gap: 2rem !important;
        transition: right 0.3s ease !important;
        z-index: 9999 !important;
        padding-top: 80px !important;
        overflow-y: auto !important;
    }
    
    .nav-menu.active {
        right: 0 !important;
    }
    
    .nav-link {
        font-size: 1.2rem !important;
        padding: 1rem 0 !important;
        text-align: center !important;
        width: 100% !important;
        border-bottom: 1px solid #e5e7eb !important;
    }
    
    .nav-link:last-child {
        border-bottom: none !important;
    }
}
</style>

<script>
function initMobileMenu() {
    console.log('=== INICIANDO MENÚ MÓVIL ===');
    
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.getElementById('nav-menu');
    
    console.log('navToggle encontrado:', !!navToggle);
    console.log('navMenu encontrado:', !!navMenu);
    
    if (navToggle && navMenu) {
        console.log('Agregando event listener al botón hamburguesa');
        
        navToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Botón hamburguesa clickeado!');
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
            console.log('Menú activo:', navMenu.classList.contains('active'));
        });
        
        // Cerrar menú al hacer clic en enlaces
        const navLinks = navMenu.querySelectorAll('.nav-link');
        console.log('Enlaces encontrados:', navLinks.length);
        navLinks.forEach(function(link, index) {
            console.log('Agregando listener al enlace', index, 'href:', link.href);
            link.onclick = function(e) {
                console.log('Enlace clickeado:', link.href);
                console.log('Cerrando menú...');
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
                console.log('Menú cerrado, permitiendo navegación');
                return true; // Permitir navegación
            };
        });
        
        console.log('Menú móvil inicializado correctamente');
    } else {
        console.error('No se encontraron los elementos del menú móvil');
    }
}

// Inicializar menú móvil
setTimeout(function() {
    try {
        console.log('=== INICIALIZANDO MENÚ MÓVIL CON TIMEOUT ===');
        initMobileMenu();
    } catch (error) {
        console.error('Error al inicializar menú móvil:', error);
    }
}, 1000);
</script>





