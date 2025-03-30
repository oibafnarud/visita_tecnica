<?php
// admin/actions/delete_exception.php - Elimina excepciones de disponibilidad
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

// Obtener y validar datos
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['exception_id']) || !is_numeric($data['exception_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de excepción inválido'
    ]);
    exit;
}

$exception_id = intval($data['exception_id']);

try {
    $database = new Database();
    $db = $database->connect();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Obtener información de la excepción antes de eliminarla
    $stmt = $db->prepare("
        SELECT technician_id, exception_date, is_available 
        FROM availability_exceptions 
        WHERE id = :id
    ");
    
    $stmt->execute([':id' => $exception_id]);
    $exception = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exception) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Excepción no encontrada'
        ]);
        exit;
    }
    
    // Eliminar la excepción
    $stmt = $db->prepare("DELETE FROM availability_exceptions WHERE id = :id");
    $result = $stmt->execute([':id' => $exception_id]);
    
    if ($result) {
        // Si la excepción era de no disponibilidad, registrar que ahora el técnico vuelve a estar disponible
        if (!$exception['is_available']) {
            $stmt = $db->prepare("
                INSERT INTO notifications 
                    (user_id, message, type, reference_id, reference_type, created_at) 
                VALUES 
                    (:user_id, :message, 'exception', :technician_id, 'technician', NOW())
            ");
            
            $message = "Se ha eliminado la excepción para el " . date('d/m/Y', strtotime($exception['exception_date'])) . 
                      ". El técnico ahora está disponible según su horario regular.";
                      
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':message' => $message,
                ':technician_id' => $exception['technician_id']
            ]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Excepción eliminada exitosamente'
        ]);
    } else {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Error al eliminar la excepción'
        ]);
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Error al eliminar la excepción: ' . $e->getMessage()
    ]);
}