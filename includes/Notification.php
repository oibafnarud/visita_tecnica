<?php
/**
 * Notification.php - Sistema de notificaciones para la aplicación
 * Esta clase maneja la creación, lectura y gestión de notificaciones para usuarios
 */
class Notification {
    private $db;
    
    /**
     * Constructor de la clase
     * @param PDO $db Conexión a la base de datos
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Crea una nueva notificación
     * @param int $user_id ID del usuario destinatario
     * @param string $title Título de la notificación
     * @param string $message Mensaje de la notificación
     * @param string $type Tipo de notificación (visit_assigned, status_change, etc)
     * @param string $entity_type Tipo de entidad relacionada (visit, user, etc)
     * @param int $entity_id ID de la entidad relacionada
     * @return bool True si la notificación se creó correctamente
     */
    public function create($user_id, $title, $message, $type, $entity_type = null, $entity_id = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type, entity_type, entity_id, created_at
                ) VALUES (
                    :user_id, :title, :message, :type, :entity_type, :entity_id, NOW()
                )
            ");
            
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':title' => $title,
                ':message' => $message,
                ':type' => $type,
                ':entity_type' => $entity_type,
                ':entity_id' => $entity_id
            ]);
            
            // Registrar en el log para debugging
            if ($result) {
                error_log("Notificación creada para el usuario $user_id: $title");
            } else {
                error_log("Error al crear notificación: " . implode(', ', $stmt->errorInfo()));
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Excepción al crear notificación: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene las notificaciones de un usuario
     * @param int $user_id ID del usuario
     * @param bool $unread_only Si solo debe devolver las no leídas
     * @param int $limit Límite de resultados
     * @return array Arreglo de notificaciones
     */
    public function getForUser($user_id, $unread_only = false, $limit = 20) {
        $query = "
            SELECT * FROM notifications 
            WHERE user_id = :user_id
        ";
        
        if ($unread_only) {
            $query .= " AND read_at IS NULL";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marca una notificación como leída
     * @param int $notification_id ID de la notificación
     * @return bool True si se actualizó correctamente
     */
    public function markAsRead($notification_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET read_at = NOW() 
            WHERE id = :id AND read_at IS NULL
        ");
        
        return $stmt->execute([':id' => $notification_id]);
    }
    
    /**
     * Marca todas las notificaciones de un usuario como leídas
     * @param int $user_id ID del usuario
     * @return bool True si se actualizaron correctamente
     */
    public function markAllAsRead($user_id) {
        $stmt = $this->db->prepare("
            UPDATE notifications 
            SET read_at = NOW() 
            WHERE user_id = :user_id AND read_at IS NULL
        ");
        
        return $stmt->execute([':user_id' => $user_id]);
    }
    
    /**
     * Obtiene la cantidad de notificaciones no leídas de un usuario
     * @param int $user_id ID del usuario
     * @return int Cantidad de notificaciones no leídas
     */
    public function getUnreadCount($user_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = :user_id AND read_at IS NULL
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Elimina notificaciones antiguas (más de 30 días)
     * @return bool True si se eliminaron correctamente
     */
    public function purgeOld() {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return $stmt->execute();
    }
}