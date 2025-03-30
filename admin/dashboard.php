<?php
// admin/dashboard.php - Dashboard administrativo mejorado con estadísticas de disponibilidad
require_once '../includes/session_check.php';
require_once '../config/database.php';
require_once '../includes/AvailabilityUtils.php';

$database = new Database();
$db = $database->connect();

// Datos para widgets principales
try {
    // Total de visitas pendientes para hoy
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM visits 
        WHERE visit_date = CURRENT_DATE 
        AND status IN ('pending', 'in_route')
    ");
    $stmt->execute();
    $pending_today = $stmt->fetchColumn();
    
    // Total de visitas de hoy ya completadas
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM visits 
        WHERE visit_date = CURRENT_DATE 
        AND status = 'completed'
    ");
    $stmt->execute();
    $completed_today = $stmt->fetchColumn();
    
    // Total de técnicos activos hoy
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT technician_id) 
        FROM visits 
        WHERE visit_date = CURRENT_DATE
    ");
    $stmt->execute();
    $active_technicians_today = $stmt->fetchColumn();
    
    // Total de técnicos activos en general
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE role = 'technician' 
        AND active = 1
    ");
    $stmt->execute();
    $total_technicians = $stmt->fetchColumn();
    
    // Técnicos con excepciones de disponibilidad para hoy
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM availability_exceptions 
        WHERE exception_date = CURRENT_DATE 
        AND is_available = 0
    ");
    $stmt->execute();
    $unavailable_technicians = $stmt->fetchColumn();
    
    // Próximas 5 visitas más cercanas
    $stmt = $db->prepare("
        SELECT 
            v.id, 
            v.client_name, 
            v.visit_date, 
            v.visit_time, 
            DATE_FORMAT(v.visit_date, '%d/%m/%Y') as formatted_date,
            TIME_FORMAT(v.visit_time, '%H:%i') as formatted_time,
            u.full_name as technician_name,
            v.status
        FROM visits v
        LEFT JOIN users u ON v.technician_id = u.id
        WHERE v.visit_date >= CURRENT_DATE
        AND (v.visit_date > CURRENT_DATE OR (v.visit_date = CURRENT_DATE AND v.visit_time >= CURRENT_TIME))
        AND v.status IN ('pending', 'in_route')
        ORDER BY v.visit_date, v.visit_time
        LIMIT 5
    ");
    $stmt->execute();
    $upcoming_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Visitas pendientes que requieren atención urgente (programadas para hoy o ya retrasadas)
    $stmt = $db->prepare("
        SELECT 
            v.id, 
            v.client_name, 
            v.visit_date, 
            v.visit_time, 
            DATE_FORMAT(v.visit_date, '%d/%m/%Y') as formatted_date,
            TIME_FORMAT(v.visit_time, '%H:%i') as formatted_time,
            u.full_name as technician_name,
            v.status,
            (v.visit_date < CURRENT_DATE OR (v.visit_date = CURRENT_DATE AND v.visit_time < CURRENT_TIME)) as is_delayed
        FROM visits v
        LEFT JOIN users u ON v.technician_id = u.id
        WHERE v.status = 'pending'
        AND (
            v.visit_date < CURRENT_DATE OR 
            (v.visit_date = CURRENT_DATE)
        )
        ORDER BY is_delayed DESC, v.visit_date, v.visit_time
        LIMIT 5
    ");
    $stmt->execute();
    $urgent_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Técnicos ocupados hoy (con más visitas pendientes)
    $stmt = $db->prepare("
        SELECT 
            u.id, 
            u.full_name, 
            COUNT(v.id) as pending_visits,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits
        FROM users u
        LEFT JOIN visits v ON u.id = v.technician_id AND v.visit_date = CURRENT_DATE
        WHERE u.role = 'technician' AND u.active = 1
        GROUP BY u.id, u.full_name
        HAVING COUNT(v.id) > 0
        ORDER BY pending_visits DESC
        LIMIT 5
    ");
    $stmt->execute();
    $busy_technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Técnicos más eficientes (mayor % de visitas completadas)
    $stmt = $db->prepare("
        SELECT 
            u.id, 
            u.full_name, 
            COUNT(v.id) as total_visits,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
            ROUND((SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) / COUNT(v.id)) * 100, 1) as completion_rate
        FROM users u
        JOIN visits v ON u.id = v.technician_id
        WHERE u.role = 'technician' AND u.active = 1
        AND v.visit_date BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND CURRENT_DATE
        GROUP BY u.id, u.full_name
        HAVING COUNT(v.id) >= 5
        ORDER BY completion_rate DESC, completed_visits DESC
        LIMIT 5
    ");
    $stmt->execute();
    $efficient_technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener disponibilidad actual de técnicos
    $stmt = $db->prepare("
        SELECT 
            u.id, 
            u.full_name
        FROM users u
        WHERE u.role = 'technician' AND u.active = 1
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar disponibilidad para cada técnico en este momento
    $availabilityUtils = new AvailabilityUtils($db);
    $current_hour = date('H:i');
    $today = date('Y-m-d');
    $dayOfWeek = date('N'); // 1 (lunes) a 7 (domingo)
    
    foreach ($technicians as &$tech) {
        $result = $availabilityUtils->checkAvailability($tech['id'], $today, $current_hour);
        $tech['is_available'] = $result['available'];
        $tech['availability_message'] = $result['message'];
    }
    
    // Datos para gráficos
    
    // Visitas por día (últimos 7 días)
    $stmt = $db->prepare("
        SELECT 
            visit_date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM visits
        WHERE visit_date BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND CURRENT_DATE
        GROUP BY visit_date
        ORDER BY visit_date
    ");
    $stmt->execute();
    $visits_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Visitas por tipo de servicio (últimos 30 días)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(service_type, 'Sin especificar') as service_type,
            COUNT(*) as total
        FROM visits
        WHERE visit_date BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND CURRENT_DATE
        GROUP BY service_type
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute();
    $visits_by_service = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener las últimas notificaciones del sistema
    $stmt = $db->prepare("
        SELECT 
            n.id,
            n.message,
            n.type,
            n.created_at,
            u.full_name
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        WHERE n.reference_type IN ('system', 'admin')
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $system_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error al cargar los datos del dashboard: " . $e->getMessage();
}

$page_title = 'Dashboard';
$current_page = 'dashboard';

// Iniciar buffer de salida
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Encabezado con fecha actual -->
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold">Panel de Control</h1>
        <div class="text-gray-600">
            <?php echo date('l, j \d\e F \d\e Y'); ?>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Widgets principales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Visitas Pendientes Hoy -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-yellow-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm">Visitas Pendientes Hoy</p>
                    <p class="text-3xl font-bold text-yellow-600"><?php echo $pending_today; ?></p>
                </div>
                <div class="p-3 bg-yellow-100 rounded-full text-yellow-500">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="visits.php?status=pending&date=today" class="text-sm text-yellow-600 hover:text-yellow-800">
                    Ver todas <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Visitas Completadas Hoy -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm">Completadas Hoy</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $completed_today; ?></p>
                </div>
                <div class="p-3 bg-green-100 rounded-full text-green-500">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="visits.php?status=completed&date=today" class="text-sm text-green-600 hover:text-green-800">
                    Ver todas <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Técnicos Activos -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm">Técnicos Activos Hoy</p>
                    <p class="text-3xl font-bold text-blue-600">
                        <?php echo $active_technicians_today; ?> / <?php echo $total_technicians; ?>
                    </p>
                </div>
                <div class="p-3 bg-blue-100 rounded-full text-blue-500">
                    <i class="fas fa-user-hard-hat text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="technicians.php" class="text-sm text-blue-600 hover:text-blue-800">
                    Ver todos <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Técnicos No Disponibles -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-red-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm">Técnicos No Disponibles</p>
                    <p class="text-3xl font-bold text-red-600"><?php echo $unavailable_technicians; ?></p>
                </div>
                <div class="p-3 bg-red-100 rounded-full text-red-500">
                    <i class="fas fa-user-clock text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <a href="availability.php" class="text-sm text-red-600 hover:text-red-800">
                    Ver disponibilidad <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Gráficos principales -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Visitas por día (gráfico) -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Tendencia de Visitas (Últimos 7 días)</h2>
            <div id="visits-trend-chart" style="height: 300px;"></div>
        </div>
        
        <!-- Visitas por servicio (gráfico) -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Distribución por Servicio</h2>
            <div id="service-distribution-chart" style="height: 300px;"></div>
        </div>
    </div>
    
    <!-- Disponibilidad de técnicos y próximas visitas -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Próximas visitas -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Próximas Visitas</h2>
                <a href="visits.php?status=pending" class="text-sm text-blue-600 hover:text-blue-800">
                    Ver todas <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (empty($upcoming_visits)): ?>
                <p class="text-gray-500 text-center py-4">No hay visitas programadas próximamente</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha/Hora
                                </th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cliente
                                </th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Técnico
                                </th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 bg-gray-50"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($upcoming_visits as $visit): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $visit['formatted_date']; ?><br>
                                        <span class="text-gray-500"><?php echo $visit['formatted_time']; ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo htmlspecialchars($visit['client_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo htmlspecialchars($visit['technician_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                  <?php echo $visit['status'] === 'in_route' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $visit['status'] === 'in_route' ? 'En Ruta' : 'Pendiente'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="edit_visit.php?id=<?php echo $visit['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            Detalles
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Disponibilidad de técnicos -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Disponibilidad Actual</h2>
                <a href="availability.php" class="text-sm text-blue-600 hover:text-blue-800">
                    Gestionar <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (empty($technicians)): ?>
                <p class="text-gray-500 text-center py-4">No hay técnicos registrados</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($technicians as $tech): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full mr-3 <?php echo $tech['is_available'] ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                                <span><?php echo htmlspecialchars($tech['full_name']); ?></span>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full <?php echo $tech['is_available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $tech['is_available'] ? 'Disponible' : 'No disponible'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Visitas urgentes y técnicos ocupados -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Visitas que requieren atención urgente -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Visitas Urgentes</h2>
            
            <?php if (empty($urgent_visits)): ?>
                <p class="text-gray-500 text-center py-4">No hay visitas urgentes pendientes</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cliente
                                </th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha/Hora
                                </th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Técnico
                                </th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($urgent_visits as $visit): ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <a href="edit_visit.php?id=<?php echo $visit['id']; ?>" 
                                           class="font-medium text-blue-600 hover:text-blue-900">
                                            <?php echo htmlspecialchars($visit['client_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <div class="<?php echo $visit['is_delayed'] ? 'text-red-600 font-medium' : ''; ?>">
                                            <?php echo $visit['formatted_date']; ?><br>
                                            <?php echo $visit['formatted_time']; ?>
                                            <?php if ($visit['is_delayed']): ?>
                                                <span class="text-xs ml-1 bg-red-100 text-red-800 px-1 rounded">Retrasada</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <?php echo htmlspecialchars($visit['technician_name']); ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                              <?php echo $visit['status'] === 'in_route' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $visit['status'] === 'in_route' ? 'En Ruta' : 'Pendiente'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Técnicos ocupados hoy -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Técnicos Más Ocupados Hoy</h2>
            
            <?php if (empty($busy_technicians)): ?>
                <p class="text-gray-500 text-center py-4">No hay técnicos con visitas asignadas hoy</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($busy_technicians as $tech): ?>
                        <div class="bg-gray-50 p-3 rounded">
                            <div class="flex justify-between items-center mb-2">
                                <div class="font-medium"><?php echo htmlspecialchars($tech['full_name']); ?></div>
                                <div class="text-sm">
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">
                                        <?php echo $tech['pending_visits'] - $tech['completed_visits']; ?> pendientes
                                    </span>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <?php $completion_percentage = $tech['pending_visits'] > 0 
                                    ? ($tech['completed_visits'] / $tech['pending_visits']) * 100 
                                    : 0; 
                                ?>
                                <div class="bg-blue-600 h-2.5 rounded-full" 
                                     style="width: <?php echo min(100, $completion_percentage); ?>%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span><?php echo $tech['completed_visits']; ?> completadas</span>
                                <span><?php echo $tech['pending_visits']; ?> total</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Técnicos más eficientes y notificaciones del sistema -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Técnicos más eficientes -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Técnicos Más Eficientes (30 días)</h2>
            
            <?php if (empty($efficient_technicians)): ?>
                <p class="text-gray-500 text-center py-4">No hay datos suficientes</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left">Técnico</th>
                                <th class="px-4 py-2 text-left">Visitas</th>
                                <th class="px-4 py-2 text-left">Efectividad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($efficient_technicians as $tech): ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <a href="reports.php?technician_id=<?php echo $tech['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($tech['full_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php echo $tech['completed_visits']; ?> / <?php echo $tech['total_visits']; ?>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-full bg-gray-200 rounded-full h-2 mr-2 max-w-[100px]">
                                                <div class="bg-green-600 h-2 rounded-full" 
                                                     style="width: <?php echo $tech['completion_rate']; ?>%"></div>
                                            </div>
                                            <span><?php echo $tech['completion_rate']; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Notificaciones recientes -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Actividad Reciente</h2>
            
            <?php if (empty($system_notifications)): ?>
                <p class="text-gray-500 text-center py-4">No hay notificaciones recientes</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($system_notifications as $notification): ?>
                        <div class="flex items-start space-x-3 pb-4 border-b last:border-0">
                            <div class="bg-blue-100 rounded-full p-2 text-blue-600">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <p class="text-sm"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de tendencia de visitas
    const visitsTrendData = <?php echo json_encode($visits_by_day); ?>;
    
    if (visitsTrendData && visitsTrendData.length > 0 && document.getElementById('visits-trend-chart')) {
        new ApexCharts(document.getElementById('visits-trend-chart'), {
            chart: {
                type: 'area',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Total Visitas',
                data: visitsTrendData.map(d => d.total)
            }, {
                name: 'Completadas',
                data: visitsTrendData.map(d => d.completed)
            }],
            colors: ['#3B82F6', '#10B981'],
            fill: {
                type: 'gradient',
                gradient: {
                    opacityFrom: 0.6,
                    opacityTo: 0.1
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            xaxis: {
                categories: visitsTrendData.map(d => {
                    const date = new Date(d.visit_date);
                    return date.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric' });
                })
            },
            legend: {
                position: 'top'
            }
        }).render();
    }
    
    // Gráfico de distribución por servicio
    const serviceData = <?php echo json_encode($visits_by_service); ?>;
    
    if (serviceData && serviceData.length > 0 && document.getElementById('service-distribution-chart')) {
        new ApexCharts(document.getElementById('service-distribution-chart'), {
            chart: {
                type: 'pie',
                height: 300
            },
            series: serviceData.map(d => d.total),
            labels: serviceData.map(d => d.service_type),
            colors: ['#3B82F6', '#10B981', '#F59E0B', '#EC4899', '#8B5CF6'],
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }]
        }).render();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>