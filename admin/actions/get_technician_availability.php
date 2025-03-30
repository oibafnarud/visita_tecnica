<?php
// admin/actions/get_technician_availability.php - API para obtener la disponibilidad de un técnico en un rango de fechas
require_once '../../includes/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Verificar parámetros requeridos
if (!isset($_GET['technician_id']) || !isset($_GET['start_date']) || !isset($_GET['end_date'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros incompletos'
    ]);
    exit;
}

$technician_id = intval($_GET['technician_id']);
$start_date = $_GET['start_date'];
$end_date = $_GET['end_date'];

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato de fecha inválido. Debe ser YYYY-MM-DD.'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Obtener información del técnico
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, specialties, role
        FROM users 
        WHERE id = :id AND role = 'technician'
    ");
    
    $stmt->execute([':id' => $technician_id]);
    $technician = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$technician) {
        echo json_encode([
            'success' => false,
            'error' => 'Técnico no encontrado'
        ]);
        exit;
    }
    
    // Obtener disponibilidad regular
    $stmt = $db->prepare("
        SELECT * FROM technician_availability 
        WHERE technician_id = :technician_id 
        ORDER BY day_of_week, start_time
    ");
    
    $stmt->execute([':technician_id' => $technician_id]);
    $regular_availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener excepciones
    $stmt = $db->prepare("
        SELECT * FROM availability_exceptions 
        WHERE technician_id = :technician_id 
        AND exception_date BETWEEN :start_date AND :end_date
        ORDER BY exception_date
    ");
    
    $stmt->execute([
        ':technician_id' => $technician_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    $exceptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear excepciones para facilitar su uso en JS
    $formatted_exceptions = [];
    foreach ($exceptions as $exception) {
        $formatted_exceptions[$exception['exception_date']] = [
            'id' => $exception['id'],
            'is_available' => (bool)$exception['is_available'],
            'start_time' => $exception['start_time'],
            'end_time' => $exception['end_time'],
            'reason' => $exception['reason']
        ];
    }
    
    // Obtener visitas programadas
    $stmt = $db->prepare("
        SELECT 
            id, client_name, visit_date, visit_time, 
            TIME_FORMAT(visit_time, '%H:%i') as formatted_time,
            status, service_type
        FROM visits 
        WHERE technician_id = :technician_id 
        AND visit_date BETWEEN :start_date AND :end_date
        ORDER BY visit_date, visit_time
    ");
    
    $stmt->execute([
        ':technician_id' => $technician_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear visitas por fecha para facilitar su uso en JS
    $visits_by_date = [];
    foreach ($visits as $visit) {
        $date = $visit['visit_date'];
        if (!isset($visits_by_date[$date])) {
            $visits_by_date[$date] = [];
        }
        $visits_by_date[$date][] = [
            'id' => $visit['id'],
            'client_name' => $visit['client_name'],
            'time' => $visit['formatted_time'],
            'status' => $visit['status'],
            'service_type' => $visit['service_type']
        ];
    }
    
    // Devolver toda la información
    echo json_encode([
        'success' => true,
        'technician' => [
            'id' => $technician['id'],
            'name' => $technician['full_name'],
            'email' => $technician['email'],
            'phone' => $technician['phone']
        ],
        'regular_availability' => $regular_availability,
        'exceptions' => $formatted_exceptions,
        'visits' => $visits_by_date,
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener disponibilidad: ' . $e->getMessage()
    ]);
}