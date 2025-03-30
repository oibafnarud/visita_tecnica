<?php
// includes/session_check.php - Verificación de sesión principal

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Obtener ruta relativa al login
    $current_path = $_SERVER['SCRIPT_NAME'];
    $path_parts = explode('/', $current_path);
    
    // Construir ruta al login
    $login_path = '';
    if (in_array('admin', $path_parts)) {
        $login_path = '../login.php';
    } else if (in_array('technician', $path_parts)) {
        $login_path = '../login.php';
    } else {
        $login_path = 'login.php';
    }
    
    // Redirigir al login
    header('Location: ' . $login_path);
    exit;
}

// Verificar roles y redirigir si es necesario
$current_path = $_SERVER['SCRIPT_NAME'];
$current_dir = dirname($current_path);
$user_role = $_SESSION['role'];

// Verificar si el usuario está en el área correcta
if (strpos($current_dir, '/admin/') !== false) {
    // Estamos en el área de administración
    if ($user_role !== 'admin' && $user_role !== 'super_admin') {
        // El usuario no es admin, redirigir
        if ($user_role === 'technician') {
            header('Location: ../technician/index.php');
        } else {
            header('Location: ../index.php');
        }
        exit;
    }
} elseif (strpos($current_dir, '/technician/') !== false) {
    // Estamos en el área de técnicos
    if ($user_role !== 'technician') {
        // El usuario no es técnico, redirigir
        if ($user_role === 'admin' || $user_role === 'super_admin') {
            header('Location: ../admin/dashboard.php');
        } else {
            header('Location: ../index.php');
        }
        exit;
    }
}

// Verificar inactividad
$max_idle_time = 7200; // 2 horas
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $max_idle_time)) {
    // Determinar ruta al logout
    $logout_path = '';
    if (strpos($current_dir, '/admin/') !== false || strpos($current_dir, '/technician/') !== false) {
        $logout_path = '../logout.php?timeout=1';
    } else {
        $logout_path = 'logout.php?timeout=1';
    }
    
    // Cerrar sesión
    session_unset();
    session_destroy();
    header('Location: ' . $logout_path);
    exit;
}

// Actualizar tiempo de última actividad
$_SESSION['last_activity'] = time();