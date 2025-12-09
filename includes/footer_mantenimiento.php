    </main>
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sistema RRHH. Todos los derechos reservados.</p>
        </div>
    </footer>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    <script>
    // Navegación entre secciones del módulo de mantenimiento
    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('.nav-mant-link');
        const sections = document.querySelectorAll('.seccion-mantenimiento');
        
        // Función para mostrar sección y ocultar otras
        function mostrarSeccion(seccionId) {
            sections.forEach(function(sec) {
                sec.style.display = 'none';
            });
            
            const seccion = document.getElementById(seccionId);
            if (seccion) {
                seccion.style.display = 'block';
            }
            
            // Actualizar botones activos
            links.forEach(function(link) {
                link.classList.remove('active');
                if (link.getAttribute('data-section') === seccionId) {
                    link.classList.add('active');
                }
            });
        }
        
        // Manejar clics en los links
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const seccionId = this.getAttribute('data-section');
                mostrarSeccion(seccionId);
                
                // Actualizar hash en URL
                window.location.hash = seccionId;
            });
        });
        
        // Función para obtener la sección desde URL (hash o parámetro GET)
        function obtenerSeccionDesdeURL() {
            // Primero intentar desde el hash
            let hash = window.location.hash.replace('#', '');
            // Si el hash contiene &, tomar solo la primera parte (nombre de sección)
            if (hash && hash.includes('&')) {
                hash = hash.split('&')[0];
            }
            if (hash) {
                return hash;
            }
            
            // Si no hay hash, intentar desde parámetro GET
            const urlParams = new URLSearchParams(window.location.search);
            const seccionParam = urlParams.get('seccion');
            if (seccionParam) {
                return seccionParam;
            }
            
            return null;
        }
        
        // Función para inicializar la sección
        function inicializarSeccion() {
            const seccionInicial = obtenerSeccionDesdeURL();
            if (seccionInicial) {
                mostrarSeccion(seccionInicial);
            } else {
                // Mostrar "importar-excel" por defecto
                const seccionDefault = document.getElementById('importar-excel');
                if (seccionDefault) {
                    mostrarSeccion('importar-excel');
                } else if (sections.length > 0) {
                    mostrarSeccion(sections[0].id);
                }
            }
        }
        
        // Detectar sección en URL al cargar (con pequeño delay para asegurar que el DOM esté listo)
        setTimeout(function() {
            inicializarSeccion();
        }, 100);
        
        // También ejecutar inmediatamente por si el DOM ya está listo
        inicializarSeccion();
        
        // Escuchar cambios en el hash
        window.addEventListener('hashchange', function() {
            const seccion = obtenerSeccionDesdeURL();
            if (seccion) {
                mostrarSeccion(seccion);
            }
        });
        
        // Escuchar cuando la página se carga completamente (por si viene de redirección)
        window.addEventListener('load', function() {
            inicializarSeccion();
        });
    });
    </script>
</body>
</html>

