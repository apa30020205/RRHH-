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
        
        // Detectar hash en URL al cargar
        const hash = window.location.hash.replace('#', '');
        if (hash) {
            mostrarSeccion(hash);
        } else {
            // Mostrar "importar-excel" por defecto
            const seccionDefault = document.getElementById('importar-excel');
            if (seccionDefault) {
                mostrarSeccion('importar-excel');
            } else if (sections.length > 0) {
                mostrarSeccion(sections[0].id);
            }
        }
        
        // Escuchar cambios en el hash
        window.addEventListener('hashchange', function() {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                mostrarSeccion(hash);
            }
        });
    });
    </script>
</body>
</html>

