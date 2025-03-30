<?php
// login.php - Página de inicio de sesión sin dependencia de la tabla login_history

// Verificar si ya hay una sesión iniciada
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirigir según el rol
    if ($_SESSION['role'] === 'technician') {
        header('Location: technician/index.php');
    } else {
        header('Location: admin/dashboard.php');
    }
    exit;
}

// Incluir la clase de base de datos
require_once 'config/database.php';

// Proceso de login
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese su email y contraseña.';
    } else {
        try {
            // Crear conexión a la base de datos
            $database = new Database();
            $db = $database->connect();
            
            // Verificar si la conexión fue exitosa
            if (!$db) {
                throw new Exception("Error de conexión a la base de datos");
            }
            
            // Buscar usuario
            $stmt = $db->prepare("
                SELECT id, full_name, email, password, role, active
                FROM users 
                WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Verificar si la cuenta está activa
                if ($user['active'] != 1) {
                    $error = 'Su cuenta está desactivada. Contacte al administrador.';
                } else {
                    // Iniciar sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    
                    // Nota: Omitimos el registro en login_history porque la tabla no existe
                    
                    // Redirigir según el rol
                    if ($user['role'] === 'technician') {
                        header('Location: technician/index.php');
                    } else {
                        header('Location: admin/dashboard.php');
                    }
                    exit;
                }
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
        } catch (Exception $e) {
            $error = 'Error al iniciar sesión: ' . $e->getMessage();
        }
    }
}

// Mensaje de timeout
$timeout_message = isset($_GET['timeout']) ? 'Su sesión ha expirado por inactividad. Por favor inicie sesión nuevamente.' : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Visitas</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8">
        <div class="flex justify-center mb-6">
            <img src="assets/images/logo.png" alt="Logo" class="h-16">
        </div>
        
        <h1 class="text-2xl font-bold text-center mb-6">Iniciar Sesión</h1>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($timeout_message): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p><?php echo $timeout_message; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label for="email" class="block text-gray-700 mb-2">Correo Electrónico</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" id="email" name="email" required autofocus
                           class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="ejemplo@correo.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div>
                <label for="password" class="block text-gray-700 mb-2">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           class="w-full pl-10 pr-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="••••••••">
                    <button type="button" id="togglePassword" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <i class="fas fa-eye text-gray-400"></i>
                    </button>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" 
                           class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Recordarme
                    </label>
                </div>
                
                <a href="forgot_password.php" class="text-sm text-blue-600 hover:text-blue-800">
                    ¿Olvidó su contraseña?
                </a>
            </div>
            
            <button type="submit" 
                    class="w-full py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow">
                Iniciar Sesión
            </button>
        </form>
    </div>
    
    <script>
    // Mostrar/ocultar contraseña
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
    </script>
</body>
</html>