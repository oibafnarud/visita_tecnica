<?php
// admin/actions/find_available_technicians.php - API para buscar técnicos disponibles para una visita
require_once '../../includes/session_check.php';
require_once '../../config/database.php';
require_once '../../includes/AvailabilityUtils.php';

header('Content-Type: application/json');

// Obtener y validar parámetros
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['date']) || !isset($data['time'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros incompletos'
    ]);
    exit;
}

$date = $data['date'];
$time = $data['time'];
$duration = isset($data['duration']) ? intval($data['duration']) : 60; // Duración predeterminada: 60 minutos
$service_type = isset($data['service_type']) ? $data['service_type'] : null;

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato de fecha inválido. Debe ser YYYY-MM-DD.'
    ]);
    exit;
}

// Validar formato de hora
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato de hora inválido. Debe ser HH:MM o HH:MM:SS.'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    $availabilityUtils = new AvailabilityUtils($db);
    
    // Obtener todos los técnicos activos
    $params = [':active' => 1];
    $serviceFilter = '';
    
    if ($service_type) {
        $serviceFilter = "AND (specialties LIKE :service_type OR specialties = 'all')";
        $params[':service_type'] = '%' . $service_type . '%';
    }
    
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, specialties 
        FROM users 
        WHERE role = 'technician' 
        AND active = :active
        $serviceFilter
        ORDER BY full_name
    ");
    
    $stmt->execute($params);
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar disponibilidad para cada técnico
    $availableTechnicians = [];
    
    foreach ($technicians as $technician) {
        $result = $availabilityUtils->checkAvailability($technician['id'], $date, $time, $duration);
        
        if ($result['available']) {
            // Obtener carga de trabajo para el día (número de visitas)
            $stmt = $db->prepare("
                SELECT COUNT(*) as visit_count 
                FROM visits 
                WHERE technician_id = :technician_id 
                AND visit_date = :date
                AND status IN ('pending', 'in_route')
            ");
            
            $stmt->execute([
                ':technician_id' => $technician['id'],
                ':date' => $date
            ]);
            
            $workload = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Agregar información adicional
            $technician['workload'] = $workload['visit_count'] ?? 0;
            $technician['is_available'] = true;
            
            if ($service_type && !empty($technician['specialties'])) {
                $specialties = json_decode($technician['specialties'], true) ?: [];
                $technician['has_specialty'] = in_array($service_type, $specialties) || in_array('all', $specialties);
            } else {
                $technician['has_specialty'] = true;
            }
            
            // Calcular el factor de adecuación (para ordenar los resultados)
            $technician['suitability'] = 100;
            
            // Reducir adecuación si tienen muchas visitas ese día
            if ($technician['workload'] > 0) {
                $technician['suitability'] -= min(50, $technician['workload'] * 10);
            }
            
            // Aumentar adecuación si tienen la especialidad específica
            if (isset($technician['has_specialty']) && $technician['has_specialty']) {
                $technician['suitability'] += 20;
            }
            
            $availableTechnicians[] = $technician;
        }
    }
    
    // Ordenar por adecuación (de mayor a menor)
    usort($availableTechnicians, function($a, $b) {
        return $b['suitability'] - $a['suitability'];
    });
    
    // Devolver respuesta
    echo json_encode([
        'success' => true,
        'technicians' => $availableTechnicians,
        'count' => count($availableTechnicians)
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar técnicos disponibles: ' . $e->getMessage()
    ]);
}