<?php
// admin/actions/check_availability.php - Verifica la disponibilidad de un técnico para una visita
require_once '../../includes/session_check.php';
require_once '../../config/database.php';
require_once '../../includes/AvailabilityUtils.php';

header('Content-Type: application/json');

// Obtener y validar parámetros
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['technicianId']) || !isset($data['date']) || !isset($data['time'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros incompletos'
    ]);
    exit;
}

$technicianId = intval($data['technicianId']);
$date = $data['date'];
$time = $data['time'];
$duration = isset($data['duration']) ? intval($data['duration']) : 60; // Duración predeterminada: 60 minutos

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
    $result = $availabilityUtils->checkAvailability($technicianId, $date, $time, $duration);
    
    // Devolver respuesta
    echo json_encode([
        'success' => true,
        'available' => $result['available'],
        'message' => $result['message'],
        'details' => $result['details']
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar disponibilidad: ' . $e->getMessage()
    ]);
}