<?php
// admin/views/technician_detail.php - Vista detallada de un técnico con calendario de disponibilidad
require_once '../includes/session_check.php';
require_once '../config/database.php';
require_once '../includes/AvailabilityUtils.php';

// Verificar si se proporcionó un ID de técnico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: technicians.php');
    exit;
}

$technician_id = intval($_GET['id']);

$database = new Database();
$db = $database->connect();

// Obtener información del técnico
$stmt = $db->prepare("
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM visits WHERE technician_id = u.id) as total_visits,
        (SELECT COUNT(*) FROM visits WHERE technician_id = u.id AND status = 'completed') as completed_visits
    FROM users u 
    WHERE u.id = :id AND u.role = 'technician'
");
$stmt->execute([':id' => $technician_id]);
$technician = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$technician) {
    header('Location: technicians.php');
    exit;
}

// Obtener disponibilidad regular
$stmt = $db->prepare("
    SELECT * FROM technician_availability 
    WHERE technician_id = :technician_id 
    ORDER BY day_of_week, start_time
");
$stmt->execute([':technician_id' => $technician_id]);
$availability = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por día de la semana
$availability_by_day = [];
foreach ($availability as $slot) {
    $day = $slot['day_of_week'];
    if (!isset($availability_by_day[$day])) {
        $availability_by_day[$day] = [];
    }
    $availability_by_day[$day][] = $slot;
}

// Obtener próximas excepciones
$availabilityUtils = new AvailabilityUtils($db);
$exceptions = $availabilityUtils->getFutureExceptions($technician_id, 10);

// Obtener próximas visitas
$stmt = $db->prepare("
    SELECT 
        v.*,
        DATE_FORMAT(v.visit_date, '%d/%m/%Y') as formatted_date,
        TIME_FORMAT(v.visit_time, '%H:%i') as formatted_time
    FROM visits v
    WHERE v.technician_id = :technician_id
    AND v.visit_date >= CURRENT_DATE
    AND v.status IN ('pending', 'in_route')
    ORDER BY v.visit_date, v.visit_time
    LIMIT 10
");
$stmt->execute([':technician_id' => $technician_id]);
$upcoming_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas mensuales
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(visit_date, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        AVG(CASE 
            WHEN status = 'completed' AND completion_time IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, CONCAT(visit_date, ' ', visit_time), completion_time)
            ELSE NULL 
        END) as avg_completion_time
    FROM visits
    WHERE technician_id = :technician_id
    AND visit_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute([':technician_id' => $technician_id]);
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Detalles del Técnico';
$current_page = 'technicians';

// Iniciar buffer de salida
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="technicians.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> Volver a Técnicos
        </a>
    </div>
    
    <!-- Cabecera con información del técnico -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div class="mb-4 md:mb-0">
                <h1 class="text-2xl font-bold mb-1"><?php echo htmlspecialchars($technician['full_name']); ?></h1>
                <div class="text-gray-600">
                    <div><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($technician['email']); ?></div>
                    <div><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($technician['phone']); ?></div>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg min-w-[100px]">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $technician['total_visits']; ?></div>
                    <div class="text-sm text-gray-600">Total Visitas</div>
                </div>
                
                <div class="text-center p-4 bg-green-50 rounded-lg min-w-[100px]">
                    <div class="text-2xl font-bold text-green-600"><?php echo $technician['completed_visits']; ?></div>
                    <div class="text-sm text-gray-600">Completadas</div>
                </div>
                
                <div class="text-center p-4 bg-purple-50 rounded-lg min-w-[100px]">
                    <?php
                    $completion_rate = $technician['total_visits'] > 0 
                        ? round(($technician['completed_visits'] / $technician['total_visits']) * 100) 
                        : 0;
                    ?>
                    <div class="text-2xl font-bold text-purple-600"><?php echo $completion_rate; ?>%</div>
                    <div class="text-sm text-gray-600">Efectividad</div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($technician['specialties'])): ?>
            <div class="mt-4 pt-4 border-t">
                <h3 class="font-medium mb-2">Especialidades</h3>
                <div class="flex flex-wrap gap-2">
                    <?php 
                    $specialties = json_decode($technician['specialties'], true) ?: [];
                    foreach ($specialties as $specialty): 
                        $label = str_replace('_', ' ', ucwords($specialty));
                    ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                            <?php echo htmlspecialchars($label); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 pt-4 border-t flex justify-end">
            <a href="edit_technician.php?id=<?php echo $technician_id; ?>" 
               class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-edit mr-1"></i>Editar Técnico
            </a>
        </div>
    </div>
    
    <!-- Pestañas de navegación -->
    <div class="mb-6">
        <div class="border-b">
            <nav class="flex -mb-px">
                <button onclick="showTab('availability')" 
                        class="px-6 py-3 border-b-2 border-blue-500 text-blue-600 tab-button active">
                    Disponibilidad
                </button>
                <button onclick="showTab('visits')" 
                        class="px-6 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button">
                    Visitas
                </button>
                <button onclick="showTab('statistics')" 
                        class="px-6 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 tab-button">
                    Estadísticas
                </button>
            </nav>
        </div>
    </div>
    
    <!-- Contenido de pestañas -->
    <div id="tab-availability" class="tab-content">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Calendario de disponibilidad -->
            <div class="lg:col-span-2">
                <div id="availability-calendar" class="bg-white rounded-lg shadow-sm p-6">
                    <!-- El calendario se cargará aquí con JavaScript -->
                </div>
            </div>
            
            <!-- Horario Regular -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Horario Regular</h2>
                
                <div class="space-y-4">
                    <?php 
                    $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                    for ($i = 1; $i <= 7; $i++): 
                        $daySlots = $availability_by_day[$i] ?? [];
                    ?>
                        <div class="border-b pb-4 last:border-0">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="font-medium"><?php echo $days[$i-1]; ?></h3>
                            </div>
                            
                            <?php if (empty($daySlots)): ?>
                                <p class="text-gray-500 text-sm">No disponible</p>
                            <?php else: ?>
                                <div class="space-y-1">
                                    <?php foreach ($daySlots as $slot): ?>
                                        <div class="flex items-center text-sm">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <div class="mt-4 pt-4 border-t flex justify-end">
                    <a href="availability.php?technician_id=<?php echo $technician_id; ?>" 
                       class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-clock mr-1"></i>Gestionar Disponibilidad
                    </a>
                </div>
            </div>
            
            <!-- Excepciones programadas -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Excepciones Programadas</h2>
                
                <?php if (empty($exceptions)): ?>
                    <p class="text-gray-500 text-center py-4">No hay excepciones programadas</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($exceptions as $exception): ?>
                            <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <div class="font-medium">
                                        <?php echo date('d/m/Y', strtotime($exception['exception_date'])); ?>
                                    </div>
                                    <?php if ($exception['is_available']): ?>
                                        <div class="text-sm text-gray-600">
                                            <?php 
                                            echo date('h:i A', strtotime($exception['start_time'])) . ' a ' . 
                                                 date('h:i A', strtotime($exception['end_time']));
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm text-red-600">No Disponible</div>
                                    <?php endif; ?>
                                    <?php if (!empty($exception['reason'])): ?>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($exception['reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <a href="availability.php?technician_id=<?php echo $technician_id; ?>"
                                   class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div id="tab-visits" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Próximas visitas -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Próximas Visitas</h2>
                
                <?php if (empty($upcoming_visits)): ?>
                    <p class="text-gray-500 text-center py-4">No hay visitas programadas próximamente</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Servicio</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 bg-gray-50"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($upcoming_visits as $visit): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php echo $visit['formatted_date']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php echo $visit['formatted_time']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php echo htmlspecialchars($visit['client_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php echo htmlspecialchars($visit['service_type']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                      <?php echo $visit['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                             ($visit['status'] === 'in_route' ? 'bg-yellow-100 text-yellow-800' : 
                                                              'bg-blue-100 text-blue-800'); ?>">
                                                <?php 
                                                echo $visit['status'] === 'completed' ? 'Completada' : 
                                                     ($visit['status'] === 'in_route' ? 'En Ruta' : 'Pendiente'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="edit_visit.php?id=<?php echo $visit['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900 mr-3">
                                                Detalles
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6 flex justify-end">
                    <a href="new_visit.php?technician_id=<?php echo $technician_id; ?>" 
                       class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        <i class="fas fa-plus mr-1"></i>Programar Nueva Visita
                    </a>
                </div>
            </div>
            
            <!-- Acciones rápidas -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Acciones Rápidas</h2>
                
                <div class="space-y-4">
                    <a href="new_visit.php?technician_id=<?php echo $technician_id; ?>" 
                       class="block w-full p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                        <div class="flex items-center">
                            <span class="p-2 bg-blue-100 rounded-full mr-3 text-blue-600">
                                <i class="fas fa-calendar-plus"></i>
                            </span>
                            <div>
                                <h3 class="font-medium">Programar Visita</h3>
                                <p class="text-sm text-gray-600">Asignar una nueva visita al técnico</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="availability.php?technician_id=<?php echo $technician_id; ?>" 
                       class="block w-full p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                        <div class="flex items-center">
                            <span class="p-2 bg-purple-100 rounded-full mr-3 text-purple-600">
                                <i class="fas fa-clock"></i>
                            </span>
                            <div>
                                <h3 class="font-medium">Gestionar Disponibilidad</h3>
                                <p class="text-sm text-gray-600">Modificar horarios y excepciones</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="visits.php?technician_id=<?php echo $technician_id; ?>" 
                       class="block w-full p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                        <div class="flex items-center">
                            <span class="p-2 bg-green-100 rounded-full mr-3 text-green-600">
                                <i class="fas fa-list"></i>
                            </span>
                            <div>
                                <h3 class="font-medium">Ver Historial</h3>
                                <p class="text-sm text-gray-600">Consultar todas las visitas asignadas</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div id="tab-statistics" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Gráfico de rendimiento mensual -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Rendimiento Mensual</h2>
                <div id="performance-chart" style="height: 300px;"></div>
            </div>
            
            <!-- Resumen estadístico -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Resumen Estadístico</h2>
                
                <div class="space-y-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <h3 class="font-medium mb-2">Efectividad General</h3>
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $completion_rate; ?>%"></div>
                            </div>
                            <span class="text-sm font-medium"><?php echo $completion_rate; ?>%</span>
                        </div>
                    </div>
                    
                    <?php if (!empty($monthly_stats)): ?>
                        <?php 
                        $last_month = end($monthly_stats);
                        $prev_month = prev($monthly_stats) ?: $last_month;
                        next($monthly_stats); // Restaurar el puntero array
                        
                        $month_change = 0;
                        if ($prev_month['completed'] > 0) {
                            $month_change = (($last_month['completed'] / $prev_month['completed']) - 1) * 100;
                        }
                        ?>
                        
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h3 class="font-medium mb-2">Último Mes</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Visitas</p>
                                    <p class="text-xl font-semibold"><?php echo $last_month['total']; ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Completadas</p>
                                    <p class="text-xl font-semibold"><?php echo $last_month['completed']; ?></p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <p class="text-sm <?php echo $month_change >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <i class="fas <?php echo $month_change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                    <?php echo abs(round($month_change)); ?>% vs mes anterior
                                </p>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h3 class="font-medium mb-2">Tiempo Promedio</h3>
                            <?php if ($last_month['avg_completion_time']): ?>
                                <?php 
                                $avg_hours = floor($last_month['avg_completion_time'] / 60);
                                $avg_minutes = round($last_month['avg_completion_time'] % 60);
                                ?>
                                <p class="text-xl font-semibold">
                                    <?php echo $avg_hours; ?>h <?php echo $avg_minutes; ?>m
                                </p>
                                <p class="text-sm text-gray-500">para completar una visita</p>
                            <?php else: ?>
                                <p class="text-sm text-gray-500">No hay datos suficientes</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <p class="text-center text-gray-500">No hay datos estadísticos suficientes</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/availability_service.js"></script>
<script src="js/availability_calendar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar calendario de disponibilidad
    const calendar = new AvailabilityCalendar('availability-calendar', {
        mode: 'week',
        startDate: new Date(),
        readOnly: true,
        showNavigation: true
    });
    
    // Cargar datos del técnico
    calendar.loadTechnicianAvailability(<?php echo $technician_id; ?>);
    
    // Manejo de pestañas
    window.showTab = function(tabName) {
        // Ocultar todas las pestañas
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Desactivar todos los botones
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        
        // Mostrar la pestaña seleccionada
        document.getElementById(`tab-${tabName}`).classList.remove('hidden');
        
        // Activar el botón correspondiente
        document.querySelector(`button[onclick="showTab('${tabName}')"]`).classList.remove('border-transparent', 'text-gray-500');
        document.querySelector(`button[onclick="showTab('${tabName}')"]`).classList.add('border-blue-500', 'text-blue-600');
        
        // Inicializar gráficos si es la pestaña de estadísticas
        if (tabName === 'statistics') {
            initializeCharts();
        }
    };
    
    // Inicializar gráficos
    function initializeCharts() {
        const chartData = <?php echo json_encode($monthly_stats); ?>;
        
        if (!chartData || chartData.length === 0) {
            document.getElementById('performance-chart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
            return;
        }
        
        new ApexCharts(document.querySelector("#performance-chart"), {
            chart: {
                type: 'bar',
                height: 300,
                stacked: false,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Total Visitas',
                data: chartData.map(d => d.total)
            }, {
                name: 'Completadas',
                data: chartData.map(d => d.completed)
            }],
            xaxis: {
                categories: chartData.map(d => {
                    const [year, month] = d.month.split('-');
                    return `${month}/${year}`;
                })
            },
            colors: ['#93C5FD', '#4F46E5'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            legend: {
                position: 'top'
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " visitas"
                    }
                }
            }
        }).render();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>