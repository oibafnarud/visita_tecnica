<?php
// technician/actions/check_affected_visits.php - Verifica visitas afectadas por una excepción
require_once '../../includes/session_check.php';
require_once '../../config/database.php';

// Verificar que el usuario sea un técnico
if ($_SESSION['role'] !== 'technician') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Acceso no autorizado'
    ]);
    exit;
}

header('Content-Type: application/json');

// Verificar parámetros requeridos
if (!isset($_GET['date'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Fecha no especificada'
    ]);
    exit;
}

$technician_id = $_SESSION['user_id'];
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

    // Buscar visitas programadas para el técnico en la fecha especificada
    $stmt = $db->prepare("
        SELECT 
            id, 
            client_name, 
            visit_time,
            TIME_FORMAT(visit_time, '%H:%i') as formatted_time,
            status
        FROM visits 
        WHERE technician_id = :technician_id 
        AND visit_date = :date
        AND status IN ('pending', 'in_route')
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
            'time' => $visit['formatted_time'],
            'status' => $visit['status']
        ];
    }, $visits);
    
    echo json_encode([
        'success' => true,
        'visits' => $formattedVisits
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar visitas: ' . $e->getMessage()
    ]);
}