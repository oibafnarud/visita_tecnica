<?php
// technician/actions/delete_exception.php - Elimina excepciones de disponibilidad creadas por técnicos
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
$technician_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->connect();
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Verificar que la excepción exista y pertenezca al técnico
    $stmt = $db->prepare("
        SELECT * FROM availability_exceptions 
        WHERE id = :id AND technician_id = :technician_id
    ");
    $stmt->execute([
        ':id' => $exception_id,
        ':technician_id' => $technician_id
    ]);
    $exception = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exception) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Excepción no encontrada o no tienes permiso para eliminarla'
        ]);
        exit;
    }
    
    // Verificar que la excepción sea para una fecha futura
    $exceptionDate = $exception['exception_date'];
    $today = date('Y-m-d');
    
    if ($exceptionDate < $today) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'No puedes eliminar excepciones de fechas pasadas'
        ]);
        exit;
    }
    
    // Eliminar la excepción
    $stmt = $db->prepare("DELETE FROM availability_exceptions WHERE id = :id");
    $result = $stmt->execute([':id' => $exception_id]);
    
    if ($result) {
        // Si la excepción era de no disponibilidad, notificar a los administradores
        if (!$exception['is_available']) {
            $message = "El técnico " . $_SESSION['full_name'] . " ha eliminado su excepción para el " . 
                       date('d/m/Y', strtotime($exception['exception_date'])) . 
                       ". Ahora estará disponible según su horario regular.";
            
            // Obtener administradores
            $stmt = $db->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin')");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($admins as $admin) {
                $notifStmt = $db->prepare("
                    INSERT INTO notifications 
                        (user_id, message, type, reference_id, reference_type, created_at) 
                    VALUES 
                        (:user_id, :message, 'availability', :technician_id, 'technician', NOW())
                ");
                
                $notifStmt->execute([
                    ':user_id' => $admin['id'],
                    ':message' => $message,
                    ':technician_id' => $technician_id
                ]);
            }
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