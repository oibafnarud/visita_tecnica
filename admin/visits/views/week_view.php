<?php
// admin/visits/views/week_view.php - Vista semanal corregida
require_once '../../../includes/session_check.php';
require_once '../../../config/database.php';
require_once '../../../includes/utils.php';

$database = new Database();
$db = $database->connect();

// Determinar la semana a mostrar
$date = $_GET['date'] ?? date('Y-m-d');
$dateObj = new DateTime($date);
$weekStart = clone $dateObj;
$weekStart->modify('monday this week');
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

// Formatear fechas para interfaz y consultas
$weekStartStr = $weekStart->format('Y-m-d');
$weekEndStr = $weekEnd->format('Y-m-d');

// Obtener filtros
$technician = $_GET['technician'] ?? 'all';
$status = $_GET['status'] ?? 'all';

// Consultar visitas para la semana
$query = "
    SELECT v.*, u.full_name as technician_name 
    FROM visits v
    INNER JOIN users u ON v.technician_id = u.id
    WHERE v.visit_date BETWEEN :start_date AND :end_date
";

if ($technician !== 'all') {
    $query .= " AND v.technician_id = :technician_id";
}

if ($status !== 'all') {
    $query .= " AND v.status = :status";
}

$query .= " ORDER BY v.visit_date, v.visit_time";

$stmt = $db->prepare($query);
$params = [':start_date' => $weekStartStr, ':end_date' => $weekEndStr];

if ($technician !== 'all') {
    $params[':technician_id'] = $technician;
}

if ($status !== 'all') {
    $params[':status'] = $status;
}

$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar visitas por día
$visitsByDay = [];
for ($i = 0; $i < 7; $i++) {
    $day = clone $weekStart;
    $day->modify("+$i days");
    $dayStr = $day->format('Y-m-d');
    $visitsByDay[$dayStr] = [];
}

// Agrupar visitas por día
foreach ($visits as $visit) {
    $visitsByDay[$visit['visit_date']][] = $visit;
}

// Generar horas de trabajo (8am - 7pm)
$workHours = [];
for ($hour = 8; $hour <= 19; $hour++) {
    $workHours[] = sprintf('%02d:00', $hour);
}
?>

<div class="p-6">
    <!-- Navegación de semana -->
    <div class="flex justify-between items-center mb-4">
        <?php
        $prevWeek = clone $weekStart;
        $prevWeek->modify('-1 week');
        $nextWeek = clone $weekStart;
        $nextWeek->modify('+1 week');
        ?>
        <a href="?view=week&date=<?php echo $prevWeek->format('Y-m-d'); ?>&technician=<?php echo $technician; ?>&status=<?php echo $status; ?>" 
           class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">
            <i class="fas fa-chevron-left mr-2"></i>Semana Anterior
        </a>
        <div class="text-center">
            <h2 class="text-xl font-bold">
                Semana del <?php echo $weekStart->format('d/m/Y'); ?> al <?php echo $weekEnd->format('d/m/Y'); ?>
            </h2>
        </div>
        <a href="?view=week&date=<?php echo $nextWeek->format('Y-m-d'); ?>&technician=<?php echo $technician; ?>&status=<?php echo $status; ?>" 
           class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">
            Semana Siguiente<i class="fas fa-chevron-right ml-2"></i>
        </a>
    </div>

    <!-- Calendario semanal -->
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="w-full border-collapse min-w-max">
            <thead>
                <tr>
                    <th class="p-3 border bg-gray-50" style="width: 80px"></th>
                    <?php
                    for ($i = 0; $i < 7; $i++) {
                        $day = clone $weekStart;
                        $day->modify("+$i days");
                        $dayStr = $day->format('Y-m-d');
                        $isToday = ($dayStr === date('Y-m-d'));
                        $dayName = $day->format('l');
                        $dayNumber = $day->format('d');
                        
                        // Traducir días a español si es necesario
                        $dayName = str_replace(
                            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                            ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'],
                            $dayName
                        );
                        
                        echo '<th class="p-3 border ' . ($isToday ? 'bg-blue-50' : 'bg-gray-50') . '" style="min-width: 200px">';
                        echo '<div class="text-center">';
                        echo '<div class="font-medium">' . $dayName . '</div>';
                        echo '<div class="text-lg font-bold">' . $dayNumber . '</div>';
                        echo '</div>';
                        echo '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($workHours as $hour): ?>
                    <tr>
                        <td class="p-2 border text-center font-medium bg-gray-50">
                            <?php echo $hour; ?>
                        </td>
                        <?php
                        for ($i = 0; $i < 7; $i++) {
                            $day = clone $weekStart;
                            $day->modify("+$i days");
                            $dayStr = $day->format('Y-m-d');
                            
                            echo '<td class="p-1 border align-top relative" style="height: 80px;">';
                            
                            // Visitas para esta hora y día
                            $hourVisits = array_filter($visitsByDay[$dayStr], function($v) use ($hour) {
                                $visitHour = substr($v['visit_time'], 0, 5);
                                $hourStart = $hour;
                                $hourEnd = date('H:i', strtotime($hour) + 3600);
                                return $visitHour >= $hourStart && $visitHour < $hourEnd;
                            });
                            
                            if (!empty($hourVisits)) {
                                echo '<div class="space-y-1">';
                                foreach ($hourVisits as $visit) {
                                    // Determinar clases CSS según estado
                                    $statusClass = '';
                                    switch ($visit['status']) {
                                        case 'completed':
                                            $statusClass = 'bg-green-100 border-l-4 border-green-500';
                                            break;
                                        case 'in_route':
                                            $statusClass = 'bg-yellow-100 border-l-4 border-yellow-500';
                                            break;
                                        case 'pending':
                                        default:
                                            $statusClass = 'bg-blue-100 border-l-4 border-blue-500';
                                    }
                                    
                                    $visitTime = date('H:i', strtotime($visit['visit_time']));
                                    
                                    echo '<div class="p-1 text-xs rounded ' . $statusClass . '">';
                                    echo '<div class="flex justify-between items-start">';
                                    echo '<span class="font-medium">' . $visitTime . '</span>';
                                    echo '<span class="text-xs">' . htmlspecialchars($visit['service_type']) . '</span>';
                                    echo '</div>';
                                    echo '<div class="font-medium truncate">' . htmlspecialchars($visit['client_name']) . '</div>';
                                    echo '<div class="text-xs truncate">' . htmlspecialchars($visit['technician_name']) . '</div>';
                                    echo '<div class="mt-1 flex justify-end space-x-1">';
                                    echo '<a href="#" onclick="showVisitDetails(' . $visit['id'] . '); return false;" class="text-gray-600 hover:text-gray-800"><i class="fas fa-eye"></i></a>';
                                    echo '<a href="#" onclick="editVisit(' . $visit['id'] . '); return false;" class="text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></a>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                                echo '</div>';
                            } else {
                                // Celda vacía con opción para agregar
                                echo '<a href="#" onclick="showNewVisitModal(\'' . $dayStr . '\', \'' . $hour . '\'); return false;"';
                                echo ' class="block h-full w-full flex items-center justify-center text-gray-300 hover:text-gray-500 hover:bg-gray-50">';
                                echo '<i class="fas fa-plus"></i>';
                                echo '</a>';
                            }
                            
                            echo '</td>';
                        }
                        ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function showNewVisitModal(date, time) {
        // Pre-seleccionar fecha y hora en el modal
        document.querySelector('input[name="visit_date"]').value = date;
        document.querySelector('input[name="visit_time"]').value = time;
        showModal('visitFormModal');
    }
    </script>
</div>