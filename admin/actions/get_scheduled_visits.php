<?php
// admin/actions/get_scheduled_visits.php - Obtiene las visitas programadas para un técnico en una fecha específica
require_once '../../includes/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Verificar parámetros requeridos
if (!isset($_GET['technician_id']) || !isset($_GET['date'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros incompletos'
    ]);
    exit;
}

$technician_id = intval($_GET['technician_id']);
$date = $_GET['date'];

// Validar fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'success' => false,
        'error' => 'Formato de fecha inválido'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();

    // Buscar todas las visitas para el técnico en la fecha especificada
    $stmt = $db->prepare("
        SELECT 
            id, 
            client_name, 
            address,
            visit_time,
            TIME_FORMAT(visit_time, '%H:%i') as formatted_time,
            status,
            service_type
        FROM visits 
        WHERE technician_id = :technician_id 
        AND visit_date = :date
        ORDER BY visit_time ASC
    ");
    
    $stmt->execute([
        ':technician_id' => $technician_id,
        ':date' => $date
    ]);
    
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear para la respuesta
    $formattedVisits = array_map(function($visit) {
        return [
            'id' => $visit['id'],
            'client_name' => $visit['client_name'],
            'address' => $visit['address'],
            'time' => $visit['formatted_time'],
            'status' => $visit['status'],
            'service_type' => $visit['service_type']
        ];
    }, $visits);
    
    echo json_encode([
        'success' => true,
        'visits' => $formattedVisits,
        'date' => $date
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener visitas: ' . $e->getMessage()
    ]);
}