<?php
// admin/actions/save_availability.php - Guarda la disponibilidad regular de los técnicos
require_once '../../includes/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

// Verificar datos requeridos
if (!isset($_POST['technician_id']) || !is_numeric($_POST['technician_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de técnico inválido'
    ]);
    exit;
}

$technician_id = intval($_POST['technician_id']);

// Validar que existan datos de horarios
if (!isset($_POST['start_time']) || !isset($_POST['end_time']) || 
    !is_array($_POST['start_time']) || !is_array($_POST['end_time'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos de horario inválidos'
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->connect();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Eliminar horarios existentes
    $stmt = $db->prepare("DELETE FROM technician_availability WHERE technician_id = :technician_id");
    $stmt->execute([':technician_id' => $technician_id]);
    
    // Insertar nuevos horarios
    $stmt = $db->prepare("
        INSERT INTO technician_availability
            (technician_id, day_of_week, start_time, end_time)
        VALUES
            (:technician_id, :day_of_week, :start_time, :end_time)
    ");
    
    $hasError = false;
    $inserted = 0;
    
    // Procesar cada día de la semana
    foreach ($_POST['start_time'] as $day => $startTimes) {
        if (!is_array($startTimes)) continue;
        
        $endTimes = $_POST['end_time'][$day] ?? [];
        
        // Validar que haya el mismo número de horas de inicio y fin
        if (count($startTimes) !== count($endTimes)) {
            $hasError = true;
            break;
        }
        
        // Procesar cada intervalo de tiempo
        for ($i = 0; $i < count($startTimes); $i++) {
            $start = $startTimes[$i];
            $end = $endTimes[$i];
            
            // Validar que las horas sean válidas
            if (empty($start) || empty($end)) {
                continue; // Ignorar entradas vacías
            }
            
            // Verificar que la hora de fin sea posterior a la de inicio
            if (strtotime($start) >= strtotime($end)) {
                $hasError = true;
                break;
            }
            
            // Insertar el horario
            $result = $stmt->execute([
                ':technician_id' => $technician_id,
                ':day_of_week' => intval($day),
                ':start_time' => $start,
                ':end_time' => $end
            ]);
            
            if (!$result) {
                $hasError = true;
                break;
            }
            
            $inserted++;
        }
        
        if ($hasError) break;
    }
    
    if (!$hasError) {
        // Registrar actualización en el log del sistema
        $logStmt = $db->prepare("
            INSERT INTO activity_log 
                (user_id, action, entity_type, entity_id, details, created_at)
            VALUES 
                (:user_id, 'update', 'technician', :technician_id, :details, NOW())
        ");
        
        $logResult = $logStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':technician_id' => $technician_id,
            ':details' => json_encode(['message' => 'Actualización de horario regular', 'slots' => $inserted])
        ]);
        
        if ($logResult) {
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Horario guardado exitosamente',
                'slots' => $inserted
            ]);
        } else {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'error' => 'Error al registrar la actividad'
            ]);
        }
    } else {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar el horario. Verifique que las horas de fin sean posteriores a las de inicio.'
        ]);
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar el horario: ' . $e->getMessage()
    ]);
}