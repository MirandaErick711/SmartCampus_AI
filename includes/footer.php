<?php
/**
 * SmartCampus AI - Pie de Pagina y Scripts Base (Footer)
 */
?>
        </div><!-- /#view -->
    </main><!-- /.main -->
</div><!-- /.app -->

<script>
    // Inicializar iconos de Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Alternar menu lateral en dispositivos moviles
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        if (sidebar && overlay) {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
    }
</script>
</body>
</html>
