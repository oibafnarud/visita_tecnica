<?php
// admin/reports.php - Página mejorada de reportes y estadísticas
require_once '../includes/session_check.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

// Obtener rango de fechas y aplicar filtros
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : null;
$service_type = isset($_GET['service_type']) ? $_GET['service_type'] : null;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'general';

// Obtener lista de técnicos para el filtro
$stmt = $db->query("SELECT id, full_name FROM users WHERE role = 'technician' AND active = 1 ORDER BY full_name");
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de servicio para el filtro
$stmt = $db->query("SELECT DISTINCT service_type FROM visits WHERE service_type IS NOT NULL AND service_type != '' ORDER BY service_type");
$service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Construir condiciones SQL basadas en filtros
$where_conditions = ["visit_date BETWEEN :start_date AND :end_date"];
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($technician_id) {
    $where_conditions[] = "technician_id = :technician_id";
    $params[':technician_id'] = $technician_id;
}

if ($service_type) {
    $where_conditions[] = "service_type = :service_type";
    $params[':service_type'] = $service_type;
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener estadísticas generales
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_visits,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_visits,
        SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route_visits,
        AVG(TIMESTAMPDIFF(MINUTE, 
            CONCAT(visit_date, ' ', visit_time),
            CASE WHEN completion_time IS NOT NULL 
                THEN completion_time 
                ELSE NOW() 
            END
        )) as avg_completion_time,
        COUNT(DISTINCT technician_id) as active_technicians,
        COUNT(DISTINCT DATE(visit_date)) as working_days
    FROM visits 
    WHERE $where_clause
");
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Datos para los diferentes tipos de reportes
switch ($report_type) {
    case 'by_date':
        // Obtener datos para gráfico de visitas por día
        $stmt = $db->prepare("
            SELECT 
                DATE(visit_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route
            FROM visits 
            WHERE $where_clause
            GROUP BY DATE(visit_date)
            ORDER BY date
        ");
        $stmt->execute($params);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'by_technician':
        // Obtener rendimiento por técnico
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.full_name,
                COUNT(v.id) as total_visits,
                SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
                ROUND((SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) / COUNT(v.id)) * 100, 1) as completion_rate,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    CONCAT(v.visit_date, ' ', v.visit_time),
                    v.completion_time
                )) as avg_completion_time,
                COUNT(DISTINCT DATE(v.visit_date)) as active_days
            FROM users u
            JOIN visits v ON u.id = v.technician_id 
                AND " . str_replace('technician_id = :technician_id', 'TRUE', $where_clause) . "
            WHERE u.role = 'technician'
            GROUP BY u.id, u.full_name
            ORDER BY completion_rate DESC, total_visits DESC
        ");
        
        // Eliminar el filtro de técnico específico si existe
        if ($technician_id) {
            unset($params[':technician_id']);
        }
        
        $stmt->execute($params);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'by_service':
        // Obtener distribución por tipo de servicio
        $stmt = $db->prepare("
            SELECT 
                COALESCE(service_type, 'Sin especificar') as service_type,
                COUNT(*) as total,
                COUNT(DISTINCT technician_id) as technicians_assigned,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as completion_rate,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    CONCAT(visit_date, ' ', visit_time),
                    completion_time
                )) as avg_completion_time
            FROM visits 
            WHERE $where_clause
            GROUP BY service_type
            ORDER BY total DESC
        ");
        $stmt->execute($params);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'by_time':
        // Obtener datos por hora del día
        $stmt = $db->prepare("
            SELECT 
                HOUR(visit_time) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route
            FROM visits 
            WHERE $where_clause
            GROUP BY HOUR(visit_time)
            ORDER BY hour
        ");
        $stmt->execute($params);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    default: // general y otros casos
        // Datos combinados para gráficos generales
        $stmt = $db->prepare("
            SELECT 
                DATE(visit_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route
            FROM visits 
            WHERE $where_clause
            GROUP BY DATE(visit_date)
            ORDER BY date
        ");
        $stmt->execute($params);
        $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Obtener datos adicionales para el informe general
if ($report_type == 'general') {
    // Rendimiento por técnico (simplificado)
    $stmt = $db->prepare("
        SELECT 
            u.full_name,
            COUNT(v.id) as total_visits,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
            ROUND((SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) / COUNT(v.id)) * 100, 1) as completion_rate
        FROM users u
        JOIN visits v ON u.id = v.technician_id 
            AND " . str_replace('technician_id = :technician_id', 'TRUE', $where_clause) . "
        WHERE u.role = 'technician'
        GROUP BY u.id, u.full_name
        ORDER BY completion_rate DESC, total_visits DESC
        LIMIT 5
    ");
    
    // Eliminar el filtro de técnico específico si existe
    $local_params = $params;
    if ($technician_id) {
        unset($local_params[':technician_id']);
    }
    
    $stmt->execute($local_params);
    $top_technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribución por tipo de servicio (simplificado)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(service_type, 'Sin especificar') as service_type,
            COUNT(*) as total
        FROM visits 
        WHERE $where_clause
        GROUP BY service_type
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute($params);
    $service_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Reportes y Estadísticas';
$current_page = 'reports';

// Iniciar buffer de salida
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <form method="GET" class="space-y-4">
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicial</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                           class="p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fecha Final</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                           class="p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Técnico</label>
                    <select name="technician_id" class="p-2 border rounded">
                        <option value="">Todos los técnicos</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>" <?php echo $technician_id == $tech['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tech['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Servicio</label>
                    <select name="service_type" class="p-2 border rounded">
                        <option value="">Todos los servicios</option>
                        <?php foreach ($service_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $service_type == $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Reporte</label>
                    <select name="report_type" class="p-2 border rounded">
                        <option value="general" <?php echo $report_type == 'general' ? 'selected' : ''; ?>>General</option>
                        <option value="by_date" <?php echo $report_type == 'by_date' ? 'selected' : ''; ?>>Por Fecha</option>
                        <option value="by_technician" <?php echo $report_type == 'by_technician' ? 'selected' : ''; ?>>Por Técnico</option>
                        <option value="by_service" <?php echo $report_type == 'by_service' ? 'selected' : ''; ?>>Por Servicio</option>
                        <option value="by_time" <?php echo $report_type == 'by_time' ? 'selected' : ''; ?>>Por Hora del Día</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Aplicar Filtros
                    </button>
                    <a href="reports.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        <i class="fas fa-redo mr-2"></i>Restablecer
                    </a>
                    <button type="button" onclick="exportReport()" 
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                        <i class="fas fa-file-excel mr-2"></i>Exportar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Resumen de estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-2">Total Visitas</h3>
            <p class="text-3xl font-bold text-blue-600"><?php echo $stats['total_visits']; ?></p>
            <div class="mt-2 flex items-center text-sm text-gray-600">
                <span>Promedio: <?php echo round($stats['total_visits'] / max(1, $stats['working_days']), 1); ?> visitas/día</span>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-2">Visitas Completadas</h3>
            <p class="text-3xl font-bold text-green-600">
                <?php echo $stats['completed_visits']; ?>
                <span class="text-lg text-gray-500">
                    (<?php echo round(($stats['completed_visits'] / max(1, $stats['total_visits'])) * 100, 1); ?>%)
                </span>
            </p>
            <div class="mt-2 flex items-center">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo round(($stats['completed_visits'] / max(1, $stats['total_visits'])) * 100); ?>%"></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-2">Tiempo Promedio</h3>
            <p class="text-3xl font-bold text-yellow-600">
                <?php 
                $avg_hours = floor($stats['avg_completion_time'] / 60);
                $avg_minutes = round($stats['avg_completion_time'] % 60);
                echo $avg_hours . 'h ' . $avg_minutes . 'm'; 
                ?>
            </p>
            <div class="mt-2 text-sm text-gray-600">
                <span>Tiempo promedio para completar una visita</span>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-2">Técnicos Activos</h3>
            <p class="text-3xl font-bold text-purple-600"><?php echo $stats['active_technicians']; ?></p>
            <div class="mt-2 text-sm text-gray-600">
                <span>
                    <?php echo round($stats['total_visits'] / max(1, $stats['active_technicians']), 1); ?> 
                    visitas por técnico
                </span>
            </div>
        </div>
    </div>

    <!-- Contenido según el tipo de reporte -->
    <?php if ($report_type == 'general'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfico de visitas por día -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Tendencia de Visitas</h3>
                <div id="visitsChart" style="height: 350px;"></div>
            </div>

            <!-- Gráfico de distribución por tipo de servicio -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Distribución por Servicio</h3>
                <div id="servicesChart" style="height: 350px;"></div>
            </div>
        </div>

        <!-- Tabla de rendimiento por técnico -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Top Técnicos</h3>
                <a href="reports.php?report_type=by_technician&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                   class="text-blue-600 hover:text-blue-800 text-sm">
                    Ver todos <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Técnico
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Visitas
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Completadas
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Efectividad
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($top_technicians as $tech): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars($tech['full_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo $tech['total_visits']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo $tech['completed_visits']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2 max-w-[100px]">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $tech['completion_rate']; ?>%"></div>
                                        </div>
                                        <span><?php echo $tech['completion_rate']; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($report_type == 'by_date'): ?>
        <!-- Reporte por fecha -->
        <div class="grid grid-cols-1 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Visitas por Día</h3>
                <div id="dailyVisitsChart" style="height: 400px;"></div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold mb-4">Detalle por Fecha</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Hora
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Visitas
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Completadas
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pendientes
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    En Ruta
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($chart_data as $time): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $hour_formatted = $time['hour'] > 12 
                                            ? ($time['hour'] - 12) . ':00 PM' 
                                            : ($time['hour'] == 0 ? '12:00 AM' : $time['hour'] . ':00 AM');
                                        echo $hour_formatted;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $time['total']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $time['completed']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $time['pending']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $time['in_route']; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar gráficos según el tipo de reporte
    const reportType = '<?php echo $report_type; ?>';
    
    if (reportType === 'general' || reportType === 'by_date') {
        initVisitsChart();
    }
    
    if (reportType === 'general') {
        initServicesChart();
    }
    
    if (reportType === 'by_technician') {
        initTechnicianChart();
    }
    
    if (reportType === 'by_service') {
        initServiceDistributionChart();
    }
    
    if (reportType === 'by_time') {
        initTimeDistributionChart();
    }
    
    // Función para exportar reporte
    window.exportReport = function() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        
        window.location.href = 'export_report.php?' + params.toString();
    };
});

// Inicializar gráfico de visitas por día
function initVisitsChart() {
    const chartData = <?php echo json_encode($chart_data); ?>;
    
    if (!chartData || chartData.length === 0) {
        if (document.getElementById('visitsChart')) {
            document.getElementById('visitsChart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
        }
        if (document.getElementById('dailyVisitsChart')) {
            document.getElementById('dailyVisitsChart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
        }
        return;
    }
    
    const options = {
        chart: {
            type: 'area',
            height: 350,
            stacked: false,
            toolbar: {
                show: true
            },
            zoom: {
                enabled: true
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 2
        },
        series: [{
            name: 'Total',
            data: chartData.map(d => ({
                x: new Date(d.date).getTime(),
                y: d.total
            }))
        }, {
            name: 'Completadas',
            data: chartData.map(d => ({
                x: new Date(d.date).getTime(),
                y: d.completed
            }))
        }, {
            name: 'Pendientes',
            data: chartData.map(d => ({
                x: new Date(d.date).getTime(),
                y: d.pending
            }))
        }],
        colors: ['#3B82F6', '#10B981', '#F59E0B'],
        fill: {
            type: 'gradient',
            gradient: {
                opacityFrom: 0.6,
                opacityTo: 0.1
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right'
        },
        xaxis: {
            type: 'datetime',
            labels: {
                datetimeFormatter: {
                    year: 'yyyy',
                    month: 'MMM \'yy',
                    day: 'dd MMM'
                }
            }
        },
        tooltip: {
            x: {
                format: 'dd MMM yyyy'
            }
        }
    };
    
    if (document.getElementById('visitsChart')) {
        new ApexCharts(document.getElementById('visitsChart'), options).render();
    }
    
    if (document.getElementById('dailyVisitsChart')) {
        new ApexCharts(document.getElementById('dailyVisitsChart'), options).render();
    }
}

// Inicializar gráfico de distribución por tipo de servicio
function initServicesChart() {
    const serviceData = <?php echo json_encode($service_stats ?? []); ?>;
    
    if (!serviceData || serviceData.length === 0) {
        document.getElementById('servicesChart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
        return;
    }
    
    new ApexCharts(document.getElementById('servicesChart'), {
        chart: {
            type: 'donut',
            height: 350
        },
        series: serviceData.map(s => s.total),
        labels: serviceData.map(s => s.service_type),
        colors: ['#3B82F6', '#10B981', '#F59E0B', '#EC4899', '#8B5CF6'],
        legend: {
            position: 'bottom'
        },
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

// Inicializar gráfico de rendimiento por técnico
function initTechnicianChart() {
    const technicianData = <?php echo json_encode($chart_data); ?>;
    
    if (!technicianData || technicianData.length === 0) {
        document.getElementById('technicianChart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
        return;
    }
    
    new ApexCharts(document.getElementById('technicianChart'), {
        chart: {
            type: 'bar',
            height: 400,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                horizontal: true,
                dataLabels: {
                    position: 'top'
                }
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val + "%";
            },
            offsetX: 30
        },
        series: [{
            name: 'Efectividad',
            data: technicianData.map(t => t.completion_rate)
        }],
        colors: ['#3B82F6'],
        xaxis: {
            categories: technicianData.map(t => t.full_name),
            labels: {
                formatter: function(val) {
                    return val + "%";
                }
            }
        },
        yaxis: {
            labels: {
                maxWidth: 150
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val + "%";
                }
            }
        }
    }).render();
}

// Inicializar gráfico de distribución por tipo de servicio
function initServiceDistributionChart() {
    const serviceData = <?php echo json_encode($chart_data); ?>;
    
    if (!serviceData || serviceData.length === 0) {
        document.getElementById('serviceDistributionChart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
        return;
    }
    
    new ApexCharts(document.getElementById('serviceDistributionChart'), {
        chart: {
            type: 'bar',
            height: 400,
            stacked: false,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        series: [{
            name: 'Total',
            data: serviceData.map(s => s.total)
        }, {
            name: 'Completadas',
            data: serviceData.map(s => s.completed)
        }],
        colors: ['#3B82F6', '#10B981'],
        xaxis: {
            categories: serviceData.map(s => s.service_type),
            labels: {
                rotate: -45,
                rotateAlways: false,
                maxHeight: 120
            }
        },
        yaxis: {
            title: {
                text: 'Número de Visitas'
            }
        },
        fill: {
            opacity: 1
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val + " visitas";
                }
            }
        }
    }).render();
}

// Inicializar gráfico de distribución por hora del día
function initTimeDistributionChart() {
    const timeData = <?php echo json_encode($chart_data); ?>;
    
    if (!timeData || timeData.length === 0) {
        document.getElementById('timeDistributionChart').innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500">No hay datos suficientes para mostrar</p></div>';
        return;
    }
    
    // Ordenar datos por hora
    timeData.sort((a, b) => a.hour - b.hour);
    
    new ApexCharts(document.getElementById('timeDistributionChart'), {
        chart: {
            type: 'line',
            height: 400,
            toolbar: {
                show: true
            }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        series: [{
            name: 'Total',
            data: timeData.map(t => t.total)
        }, {
            name: 'Completadas',
            data: timeData.map(t => t.completed)
        }, {
            name: 'Pendientes',
            data: timeData.map(t => t.pending)
        }],
        colors: ['#3B82F6', '#10B981', '#F59E0B'],
        markers: {
            size: 4
        },
        xaxis: {
            categories: timeData.map(t => {
                const hour = t.hour > 12 
                    ? (t.hour - 12) + ':00 PM' 
                    : (t.hour === 0 ? '12:00 AM' : t.hour + ':00 AM');
                return hour;
            }),
            title: {
                text: 'Hora del Día'
            }
        },
        yaxis: {
            title: {
                text: 'Número de Visitas'
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val + " visitas";
                }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right'
        }
    }).render();
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>