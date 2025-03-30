<?php
// admin/actions/save_exception.php - Guarda excepciones de disponibilidad con validaciones mejoradas
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

// Verificar los campos requeridos
if (!isset($_POST['technician_id']) || !isset($_POST['exception_date'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Faltan datos requeridos'
    ]);
    exit;
}

$technician_id = intval($_POST['technician_id']);
$exception_date = $_POST['exception_date'];
$is_available = isset($_POST['is_available']) ? 1 : 0;

// Si está disponible, validar que se hayan enviado las horas
if ($is_available) {
    if (empty($_POST['start_time']) || empty($_POST['end_time'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Debe especificar las horas de inicio y fin'
        ]);
        exit;
    }
    
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    
    // Validar que la hora de fin sea posterior a la de inicio
    if (strtotime($start_time) >= strtotime($end_time)) {
        echo json_encode([
            'success' => false,
            'error' => 'La hora de fin debe ser posterior a la hora de inicio'
        ]);
        exit;
    }
} else {
    $start_time = null;
    $end_time = null;
}

$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

try {
    $database = new Database();
    $db = $database->connect();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Verificar si ya existe una excepción para esta fecha
    $stmt = $db->prepare("
        SELECT id FROM availability_exceptions 
        WHERE technician_id = :technician_id 
        AND exception_date = :exception_date
    ");
    $stmt->execute([
        ':technician_id' => $technician_id,
        ':exception_date' => $exception_date
    ]);
    $existingException = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingException) {
        // Actualizar la excepción existente
        $stmt = $db->prepare("
            UPDATE availability_exceptions 
            SET is_available = :is_available, 
                start_time = :start_time, 
                end_time = :end_time, 
                reason = :reason,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':is_available' => $is_available,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':reason' => $reason,
            ':id' => $existingException['id']
        ]);
        
        $exceptionId = $existingException['id'];
    } else {
        // Crear una nueva excepción
        $stmt = $db->prepare("
            INSERT INTO availability_exceptions 
                (technician_id, exception_date, is_available, start_time, end_time, reason, created_at, updated_at)
            VALUES 
                (:technician_id, :exception_date, :is_available, :start_time, :end_time, :reason, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            ':technician_id' => $technician_id,
            ':exception_date' => $exception_date,
            ':is_available' => $is_available,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':reason' => $reason
        ]);
        
        $exceptionId = $db->lastInsertId();
    }
    
    if ($result) {
        // Si la excepción marca como no disponible, verificar si hay visitas que deban reprogramarse
        if (!$is_available) {
            $stmt = $db->prepare("
                SELECT id FROM visits 
                WHERE technician_id = :technician_id 
                AND visit_date = :exception_date
                AND status IN ('pending', 'in_route')
            ");
            $stmt->execute([
                ':technician_id' => $technician_id,
                ':exception_date' => $exception_date
            ]);
            $affectedVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($affectedVisits)) {
                // Agregar una notificación al administrador sobre visitas afectadas
                $visitCount = count($affectedVisits);
                $message = "Se ha marcado como no disponible al técnico para el día {$exception_date}. " .
                          "Hay {$visitCount} visita(s) que necesitan ser reprogramadas.";
                
                $stmt = $db->prepare("
                    INSERT INTO notifications 
                        (user_id, message, type, reference_id, reference_type, created_at) 
                    VALUES 
                        (:user_id, :message, 'exception', :reference_id, 'exception', NOW())
                ");
                
                // Enviar notificación al usuario administrador actual
                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':message' => $message,
                    ':reference_id' => $exceptionId
                ]);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Excepción guardada exitosamente'
        ]);
    } else {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar la excepción'
        ]);
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar la excepción: ' . $e->getMessage()
    ]);
}