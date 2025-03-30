<?php
// includes/nav.php - Menú de navegación con rutas corregidas para la carpeta visits

// Obtener notificaciones no leídas
$unreadCount = 0;
if (isset($db)) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = :user_id AND read_at IS NULL
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $result ? intval($result['count']) : 0;
}

// Verificar el rol del usuario
$is_superadmin = ($_SESSION['role'] === 'super_admin');
$is_admin = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin');

// Determinar la ruta base para los enlaces del menú
// Detectar si estamos en el área de admin o no
$current_path = $_SERVER['PHP_SELF'];
$in_admin_area = strpos($current_path, '/admin/') !== false;

// Ruta base para el área administrativa
$admin_base = $in_admin_area ? '' : 'admin/';

// Definir los elementos del menú según el rol
$menu_items = [
    [
        'name' => 'Dashboard',
        'icon' => 'fas fa-tachometer-alt',
        'url' => $admin_base . 'dashboard.php',
        'section' => 'dashboard',
        'permission' => ['super_admin', 'admin']
    ],
    [
        'name' => 'Visitas',
        'icon' => 'fas fa-calendar-check',
        'url' => $admin_base . 'visits/', // Ahora apunta a la carpeta visits
        'section' => 'visits',
        'permission' => ['super_admin', 'admin']
    ],
    [
        'name' => 'Técnicos',
        'icon' => 'fas fa-user-hard-hat',
        'url' => $admin_base . 'technicians.php',
        'section' => 'technicians',
        'permission' => ['super_admin', 'admin']
    ],
    [
        'name' => 'Disponibilidad',
        'icon' => 'fas fa-clock',
        'url' => $admin_base . 'availability.php',
        'section' => 'availability',
        'permission' => ['super_admin', 'admin']
    ],
    [
        'name' => 'Reportes',
        'icon' => 'fas fa-chart-bar',
        'url' => $admin_base . 'reports.php',
        'section' => 'reports',
        'permission' => ['super_admin', 'admin']
    ],
    [
        'name' => 'Clientes',
        'icon' => 'fas fa-users',
        'url' => $admin_base . 'clients.php',
        'section' => 'clients',
        'permission' => ['super_admin', 'admin']
    ],
    [
        'name' => 'Notificaciones',
        'icon' => 'fas fa-bell',
        'url' => $admin_base . 'notifications.php',
        'section' => 'notifications',
        'permission' => ['super_admin', 'admin'],
        'badge' => $unreadCount
    ],
    [
        'name' => 'Configuración',
        'icon' => 'fas fa-cogs',
        'url' => $admin_base . 'settings.php',
        'section' => 'settings',
        'permission' => ['super_admin']
    ],
    [
        'name' => 'Usuarios',
        'icon' => 'fas fa-user-shield',
        'url' => $admin_base . 'users.php',
        'section' => 'users',
        'permission' => ['super_admin']
    ]
];

// Filtrar el menú según los permisos del usuario
$filtered_menu = array_filter($menu_items, function($item) {
    global $is_superadmin, $is_admin;
    
    // Si es superadmin, mostrar todos los elementos
    if ($is_superadmin) {
        return true;
    }
    
    // Si es admin normal, mostrar solo los elementos permitidos para admin
    if ($is_admin && in_array('admin', $item['permission'])) {
        return true;
    }
    
    // Para otros roles, no mostrar elementos que no tengan permiso
    return false;
});

// Ruta a los activos
$assets_path = $in_admin_area ? '../assets/' : 'assets/';
?>

<nav class="bg-blue-700 text-white">
    <!-- Mobile Navigation -->
    <div class="lg:hidden">
        <div class="flex items-center justify-between p-4">
            <div class="flex items-center space-x-2">
                <img src="<?php echo $assets_path; ?>images/logo.png" alt="Logo" class="h-8">
                <span class="text-lg font-bold">Sistema de Visitas</span>
            </div>
            <button id="mobile-menu-button" class="text-white focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
        
        <div id="mobile-menu" class="hidden px-4 pb-4">
            <div class="flex flex-col space-y-2">
                <?php foreach ($filtered_menu as $item): ?>
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center p-3 rounded <?php echo ($current_page ?? '') === $item['section'] ? 'bg-blue-800' : 'hover:bg-blue-600'; ?>">
                        <i class="<?php echo $item['icon']; ?> w-5 text-center"></i>
                        <span class="ml-3"><?php echo $item['name']; ?></span>
                        <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                <?php echo $item['badge']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                
                <div class="pt-2 mt-2 border-t border-blue-600">
                    <a href="<?php echo $in_admin_area ? '../logout.php' : 'logout.php'; ?>" class="flex items-center p-3 rounded hover:bg-blue-600">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                        <span class="ml-3">Cerrar Sesión</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Desktop Navigation -->
    <div class="hidden lg:flex lg:flex-col h-screen w-64 bg-blue-700 text-white fixed left-0 top-0 overflow-y-auto">
        <div class="p-4 flex items-center space-x-2 border-b border-blue-600">
            <img src="<?php echo $assets_path; ?>images/logo.png" alt="Logo" class="h-10">
            <span class="text-lg font-bold">Sistema de Visitas</span>
        </div>
        
        <div class="p-4">
            <div class="flex items-center space-x-3 mb-6">
                <div class="bg-blue-600 p-2 rounded-full">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="font-medium"><?php echo $_SESSION['full_name']; ?></div>
                    <div class="text-sm text-blue-200">
                        <?php 
                        echo $_SESSION['role'] === 'super_admin' ? 'Super Administrador' : 
                             ($_SESSION['role'] === 'admin' ? 'Administrador' : 'Usuario');
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="space-y-2">
                <?php foreach ($filtered_menu as $item): ?>
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center p-3 rounded <?php echo ($current_page ?? '') === $item['section'] ? 'bg-blue-800' : 'hover:bg-blue-600'; ?>">
                        <i class="<?php echo $item['icon']; ?> w-5 text-center"></i>
                        <span class="ml-3"><?php echo $item['name']; ?></span>
                        <?php if (isset($item['badge']) && $item['badge'] > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                <?php echo $item['badge']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-auto p-4 border-t border-blue-600">
            <a href="<?php echo $in_admin_area ? '../logout.php' : 'logout.php'; ?>" class="flex items-center p-3 rounded hover:bg-blue-600">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span class="ml-3">Cerrar Sesión</span>
            </a>
        </div>
    </div>
</nav>

<script>
document.getElementById('mobile-menu-button').addEventListener('click', function() {
    const mobileMenu = document.getElementById('mobile-menu');
    mobileMenu.classList.toggle('hidden');
});
</script>