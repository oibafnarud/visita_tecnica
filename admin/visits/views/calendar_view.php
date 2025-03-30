<?php
// admin/visits/views/calendar_view.php - Vista de calendario mejorada
require_once '../../../includes/session_check.php';
require_once '../../../config/database.php';
require_once '../../../includes/AvailabilityUtils.php';

$database = new Database();
$db = $database->connect();

// Determinar el mes y año a mostrar
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validar mes y año
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2000 || $year > 2100) $year = date('Y');

// Obtener filtros adicionales
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$service_type = isset($_GET['service_type']) ? $_GET['service_type'] : null;

// Construir condiciones SQL para filtros
$where_conditions = ["MONTH(visit_date) = :month AND YEAR(visit_date) = :year"];
$params = [':month' => $month, ':year' => $year];

if ($technician_id) {
    $where_conditions[] = "technician_id = :technician_id";
    $params[':technician_id'] = $technician_id;
}

if ($status) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status;
}

if ($service_type) {
    $where_conditions[] = "service_type = :service_type";
    $params[':service_type'] = $service_type;
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener lista de técnicos para el filtro
$stmt = $db->query("SELECT id, full_name FROM users WHERE role = 'technician' AND active = 1 ORDER BY full_name");
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estados disponibles
$status_options = ['pending' => 'Pendiente', 'in_route' => 'En Ruta', 'completed' => 'Completada', 'cancelled' => 'Cancelada'];

// Obtener tipos de servicio
$stmt = $db->query("SELECT DISTINCT service_type FROM visits WHERE service_type IS NOT NULL ORDER BY service_type");
$service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener visitas para el mes seleccionado
$stmt = $db->prepare("
    SELECT 
        v.id, 
        v.client_name, 
        v.visit_date, 
        v.visit_time, 
        DATE_FORMAT(v.visit_date, '%d/%m/%Y') as formatted_date,
        TIME_FORMAT(v.visit_time, '%H:%i') as formatted_time,
        v.status,
        v.service_type,
        v.address,
        v.contact_name,
        v.contact_phone,
        u.full_name as technician_name,
        u.id as technician_id
    FROM visits v
    LEFT JOIN users u ON v.technician_id = u.id
    WHERE $where_clause
    ORDER BY v.visit_date, v.visit_time
");
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar visitas por día
$visitsByDay = [];
foreach ($visits as $visit) {
    $day = date('j', strtotime($visit['visit_date']));
    if (!isset($visitsByDay[$day])) {
        $visitsByDay[$day] = [];
    }
    $visitsByDay[$day][] = $visit;
}

// Obtener información del mes
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numDays = date('t', $firstDayOfMonth);
$firstDayOfWeek = date('w', $firstDayOfMonth); // 0 (domingo) a 6 (sábado)

// Calcular mes anterior y siguiente
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Función para obtener el nombre del mes
function getMonthName($month) {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[$month];
}

$page_title = 'Calendario de Visitas';
$current_page = 'visits';

// Iniciar buffer de salida
ob_start();
?>

<div class="container mx-auto px-4 py-6">
    <!-- Encabezado con navegación del mes -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Calendario de Visitas</h1>
        
        <div class="flex items-center space-x-2">
            <a href="?view=calendar&month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>&technician_id=<?php echo $technician_id; ?>&status=<?php echo $status; ?>&service_type=<?php echo urlencode($service_type); ?>"
               class="p-2 border rounded hover:bg-gray-100">
                <i class="fas fa-chevron-left"></i>
            </a>
            
            <span class="font-medium text-lg px-2">
                <?php echo getMonthName($month) . ' ' . $year; ?>
            </span>
            
            <a href="?view=calendar&month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>&technician_id=<?php echo $technician_id; ?>&status=<?php echo $status; ?>&service_type=<?php echo urlencode($service_type); ?>"
               class="p-2 border rounded hover:bg-gray-100">
                <i class="fas fa-chevron-right"></i>
            </a>
            
            <a href="?view=calendar&month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>&technician_id=<?php echo $technician_id; ?>&status=<?php echo $status; ?>&service_type=<?php echo urlencode($service_type); ?>"
               class="ml-2 px-3 py-2 border rounded hover:bg-gray-100">
                Hoy
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="view" value="calendar">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Técnico</label>
                <select name="technician_id" class="border rounded-lg px-3 py-2 w-full min-w-[200px]">
                    <option value="">Todos los técnicos</option>
                    <?php foreach ($technicians as $tech): ?>
                        <option value="<?php echo $tech['id']; ?>" <?php echo $technician_id == $tech['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tech['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select name="status" class="border rounded-lg px-3 py-2 w-full min-w-[150px]">
                    <option value="">Todos los estados</option>
                    <?php foreach ($status_options as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Servicio</label>
                <select name="service_type" class="border rounded-lg px-3 py-2 w-full min-w-[200px]">
                    <option value="">Todos los servicios</option>
                    <?php foreach ($service_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $service_type == $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-1"></i>Filtrar
                </button>
                
                <a href="?view=calendar&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                   class="ml-2 px-4 py-2 border rounded-lg hover:bg-gray-100">
                    <i class="fas fa-redo mr-1"></i>Restablecer
                </a>
            </div>
        </form>
    </div>
    
    <!-- Calendario -->
    <div class="bg-white rounded-lg shadow-sm p-4">
        <!-- Leyenda de estados -->
        <div class="flex flex-wrap gap-4 mb-4">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-blue-100 border border-blue-200 rounded-full mr-2"></div>
                <span class="text-sm">Pendiente</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-yellow-100 border border-yellow-200 rounded-full mr-2"></div>
                <span class="text-sm">En Ruta</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-100 border border-green-200 rounded-full mr-2"></div>
                <span class="text-sm">Completada</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-gray-100 border border-gray-200 rounded-full mr-2"></div>
                <span class="text-sm">Cancelada</span>
            </div>
        </div>
        
        <!-- Días de la semana -->
        <div class="grid grid-cols-7 gap-1 text-center font-medium mb-1">
            <div class="p-2">Domingo</div>
            <div class="p-2">Lunes</div>
            <div class="p-2">Martes</div>
            <div class="p-2">Miércoles</div>
            <div class="p-2">Jueves</div>
            <div class="p-2">Viernes</div>
            <div class="p-2">Sábado</div>
        </div>
        
        <!-- Días del mes -->
        <div class="grid grid-cols-7 gap-1">
            <?php
            // Días previos del mes anterior (para rellenar la primera semana)
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo '<div class="h-32 p-2 bg-gray-50 text-gray-400">';
                echo '</div>';
            }
            
            // Días del mes actual
            for ($day = 1; $day <= $numDays; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday = $date === date('Y-m-d');
                $hasVisits = isset($visitsByDay[$day]) && count($visitsByDay[$day]) > 0;
                
                echo '<div class="h-32 p-2 border overflow-y-auto ' . ($isToday ? 'bg-blue-50' : 'bg-white') . '">';
                
                // Número del día
                echo '<div class="flex justify-between items-center mb-1">';
                echo '<span class="font-bold ' . ($isToday ? 'text-blue-600' : '') . '">' . $day . '</span>';
                
                // Botón para agregar visita en este día
                echo '<a href="../actions/create_visit.php?date=' . $date . '" class="text-blue-600 hover:text-blue-800 text-sm">';
                echo '<i class="fas fa-plus"></i>';
                echo '</a>';
                echo '</div>';
                
                // Visitas del día
                if ($hasVisits) {
                    echo '<div class="space-y-1">';
                    foreach ($visitsByDay[$day] as $visit) {
                        // Determinar color según estado
                        $bgColor = 'bg-blue-100 text-blue-800'; // Pendiente por defecto
                        
                        if ($visit['status'] === 'in_route') {
                            $bgColor = 'bg-yellow-100 text-yellow-800';
                        } elseif ($visit['status'] === 'completed') {
                            $bgColor = 'bg-green-100 text-green-800';
                        } elseif ($visit['status'] === 'cancelled') {
                            $bgColor = 'bg-gray-100 text-gray-800';
                        }
                        
                        echo '<a href="../actions/view_visit.php?id=' . $visit['id'] . '" ';
                        echo 'class="block p-1 text-xs ' . $bgColor . ' rounded hover:opacity-90">';
                        echo '<div class="font-medium truncate">' . htmlspecialchars($visit['client_name']) . '</div>';
                        echo '<div class="flex justify-between text-xxs">';
                        echo '<span>' . $visit['formatted_time'] . '</span>';
                        echo '<span>' . htmlspecialchars($visit['technician_name']) . '</span>';
                        echo '</div>';
                        echo '</a>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
                
                // Si llegamos al final de la semana, cerrar la fila
                if (($day + $firstDayOfWeek) % 7 === 0) {
                    echo "\n";
                }
            }
            
            // Días siguientes del próximo mes (para completar la última semana)
            $remainingDays = 7 - (($numDays + $firstDayOfWeek) % 7);
            if ($remainingDays < 7) {
                for ($i = 0; $i < $remainingDays; $i++) {
                    echo '<div class="h-32 p-2 bg-gray-50 text-gray-400">';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="mt-6 flex justify-between">
        <div>
            <a href="?view=list" class="px-4 py-2 bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200">
                <i class="fas fa-list mr-1"></i>Vista de Lista
            </a>
            <a href="?view=week" class="ml-2 px-4 py-2 bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200">
                <i class="fas fa-calendar-week mr-1"></i>Vista Semanal
            </a>
        </div>
        
        <a href="../actions/create_visit.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <i class="fas fa-plus mr-1"></i>Nueva Visita
        </a>
    </div>
</div>

<!-- Script para mostrar tooltip con detalles de visita al pasar el mouse -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Crear un elemento para el tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'fixed z-50 bg-white border rounded-lg shadow-lg p-3 max-w-xs hidden';
    document.body.appendChild(tooltip);
    
    // Obtener todos los elementos de visita
    const visitElements = document.querySelectorAll('.calendar-view a[href*="view_visit.php"]');
    
    visitElements.forEach(el => {
        el.addEventListener('mouseenter', e => {
            const rect = e.target.getBoundingClientRect();
            const visitId = e.target.href.split('id=')[1];
            
            // Aquí podrías cargar más detalles mediante AJAX si lo necesitas
            tooltip.innerHTML = 'Cargando...';
            tooltip.style.top = `${rect.bottom + window.scrollY + 5}px`;
            tooltip.style.left = `${rect.left + window.scrollX}px`;
            tooltip.classList.remove('hidden');
            
            // Esta parte podría reemplazarse con una llamada AJAX real
            setTimeout(() => {
                const visit = e.target.getAttribute('data-visit');
                if (visit) {
                    const visitData = JSON.parse(visit);
                    tooltip.innerHTML = `
                        <div class="font-medium">${visitData.client_name}</div>
                        <div class="text-sm">${visitData.address}</div>
                        <div class="text-sm">Contacto: ${visitData.contact_name}</div>
                        <div class="text-sm">Teléfono: ${visitData.contact_phone}</div>
                        <div class="text-sm">Servicio: ${visitData.service_type || 'No especificado'}</div>
                    `;
                } else {
                    tooltip.innerHTML = 'No hay detalles disponibles';
                }
            }, 200);
        });
        
        el.addEventListener('mouseleave', () => {
            tooltip.classList.add('hidden');
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require_once '../../../includes/layout.php';
?>