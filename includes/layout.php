<?php
// includes/layout.php - Layout principal con corrección de márgenes
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistema de Visitas Técnicas'; ?></title>
    
    <!-- Estilos -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Scripts globales -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    
    <!-- Estilos personalizados -->
    <style>
        body {
            background-color: #f3f4f6;
        }
        
        /* Corregimos el margen para el contenido principal */
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 16rem !important; /* Ancho exacto del sidebar */
                width: calc(100% - 16rem) !important; /* Ancho total menos el ancho del sidebar */
            }
        }
        
        /* Para dispositivos móviles */
        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        /* Scroll del menú */
        .menu-sidebar {
            position: fixed;
            height: 100vh;
            width: 16rem;
            z-index: 10;
            top: 0;
            left: 0;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <!-- Incluir navegación -->
    <?php include 'nav.php'; ?>
    
    <!-- Contenido principal con margen corregido -->
    <main class="main-content pt-6 px-6 pb-8 transition-all duration-300">
        <?php if (isset($success_message)): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php echo $content ?? ''; ?>
    </main>
    
    <!-- Scripts específicos de la página -->
    <?php if (isset($page_scripts)): ?>
        <?php echo $page_scripts; ?>
    <?php endif; ?>
    
    <script>
    // Script para asegurar que el menú lateral tenga el estilo correcto
    document.addEventListener('DOMContentLoaded', function() {
        const desktopNav = document.querySelector('.lg\\:flex.lg\\:flex-col.h-screen');
        if (desktopNav) {
            desktopNav.classList.add('menu-sidebar');
        }
    });
    </script>
</body>
</html>