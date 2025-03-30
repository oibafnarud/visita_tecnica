<?php
// includes/AvailabilityUtils.php - Clase para gestionar la disponibilidad de técnicos

class AvailabilityUtils {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Verifica si un técnico está disponible en una fecha y hora específicas
     * 
     * @param int $technicianId ID del técnico
     * @param string $date Fecha en formato Y-m-d
     * @param string $time Hora en formato H:i
     * @param int $duration Duración en minutos
     * @return array Resultado de la verificación con status, message y details
     */
    public function checkAvailability($technicianId, $date, $time, $duration = 60) {
        // Validar parámetros
        if (!$technicianId || !$date || !$time) {
            return [
                'available' => false,
                'message' => 'Parámetros incompletos',
                'details' => null
            ];
        }
        
        // Convertir a objetos DateTime para facilitar comparaciones
        $dateTime = new DateTime("$date $time");
        $endDateTime = clone $dateTime;
        $endDateTime->modify("+$duration minutes");
        
        // Convertir de nuevo a string para consultas SQL
        $timeStr = $dateTime->format('H:i:s');
        $endTimeStr = $endDateTime->format('H:i:s');
        
        // 1. Verificar si hay una excepción para esta fecha
        $exception = $this->getException($technicianId, $date);
        
        if ($exception) {
            // Si el técnico no está disponible en absoluto
            if (!$exception['is_available']) {
                return [
                    'available' => false,
                    'message' => 'El técnico no está disponible en esta fecha',
                    'details' => [
                        'reason' => $exception['reason'],
                        'type' => 'full_day_exception'
                    ]
                ];
            }
            
            // Si está disponible en un horario específico, verificar si el rango solicitado está dentro
            $exceptionStart = new DateTime("$date {$exception['start_time']}");
            $exceptionEnd = new DateTime("$date {$exception['end_time']}");
            
            if ($dateTime < $exceptionStart || $endDateTime > $exceptionEnd) {
                return [
                    'available' => false,
                    'message' => 'La hora solicitada está fuera del horario disponible',
                    'details' => [
                        'available_start' => $exception['start_time'],
                        'available_end' => $exception['end_time'],
                        'type' => 'time_exception'
                    ]
                ];
            }
        } else {
            // 2. Si no hay excepción, verificar el horario regular según el día de la semana
            $dayOfWeek = date('N', strtotime($date)); // 1 (lunes) a 7 (domingo)
            
            $regularSchedule = $this->getRegularSchedule($technicianId, $dayOfWeek);
            
            if (empty($regularSchedule)) {
                return [
                    'available' => false,
                    'message' => 'El técnico no tiene horario asignado para este día',
                    'details' => [
                        'day_of_week' => $dayOfWeek,
                        'type' => 'no_schedule'
                    ]
                ];
            }
            
            // Verificar si el horario solicitado está dentro de algún intervalo disponible
            $isWithinSchedule = false;
            
            foreach ($regularSchedule as $schedule) {
                $scheduleStart = new DateTime("$date {$schedule['start_time']}");
                $scheduleEnd = new DateTime("$date {$schedule['end_time']}");
                
                if ($dateTime >= $scheduleStart && $endDateTime <= $scheduleEnd) {
                    $isWithinSchedule = true;
                    break;
                }
            }
            
            if (!$isWithinSchedule) {
                return [
                    'available' => false,
                    'message' => 'La hora solicitada está fuera del horario de trabajo',
                    'details' => [
                        'schedules' => $regularSchedule,
                        'type' => 'outside_working_hours'
                    ]
                ];
            }
        }
        
        // 3. Verificar si ya hay visitas programadas que se solapen
        $overlappingVisits = $this->getOverlappingVisits($technicianId, $date, $timeStr, $endTimeStr);
        
        if (!empty($overlappingVisits)) {
            return [
                'available' => false,
                'message' => 'El técnico ya tiene una visita programada en este horario',
                'details' => [
                    'visits' => $overlappingVisits,
                    'type' => 'overlapping_visit'
                ]
            ];
        }
        
        // Si pasó todas las verificaciones, está disponible
        return [
            'available' => true,
            'message' => 'El técnico está disponible',
            'details' => null
        ];
    }
    
    /**
     * Obtiene una excepción de disponibilidad para un técnico y fecha
     * 
     * @param int $technicianId ID del técnico
     * @param string $date Fecha en formato Y-m-d
     * @return array|null Datos de la excepción o null si no hay
     */
    public function getException($technicianId, $date) {
        $stmt = $this->db->prepare("
            SELECT * FROM availability_exceptions 
            WHERE technician_id = :technician_id 
            AND exception_date = :date
        ");
        
        $stmt->execute([
            ':technician_id' => $technicianId,
            ':date' => $date
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene el horario regular de un técnico para un día de la semana
     * 
     * @param int $technicianId ID del técnico
     * @param int $dayOfWeek Día de la semana (1-7, donde 1 es lunes)
     * @return array Lista de horarios disponibles
     */
    public function getRegularSchedule($technicianId, $dayOfWeek) {
        $stmt = $this->db->prepare("
            SELECT * FROM technician_availability 
            WHERE technician_id = :technician_id 
            AND day_of_week = :day_of_week
            ORDER BY start_time
        ");
        
        $stmt->execute([
            ':technician_id' => $technicianId,
            ':day_of_week' => $dayOfWeek
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca visitas que se solapen con un horario específico
     * 
     * @param int $technicianId ID del técnico
     * @param string $date Fecha en formato Y-m-d
     * @param string $startTime Hora de inicio
     * @param string $endTime Hora de fin
     * @return array Lista de visitas que se solapan
     */
    public function getOverlappingVisits($technicianId, $date, $startTime, $endTime) {
        // Aproximación: consideramos que cada visita dura 1 hora por defecto
        // si no hay un campo de duración explícito
        $stmt = $this->db->prepare("
            SELECT 
                id, client_name, visit_time, 
                TIME_FORMAT(visit_time, '%H:%i') as formatted_time,
                ADDTIME(visit_time, '01:00:00') as end_time
            FROM visits 
            WHERE technician_id = :technician_id 
            AND visit_date = :date
            AND status IN ('pending', 'in_route')
            AND (
                (visit_time <= :start_time AND ADDTIME(visit_time, '01:00:00') > :start_time) OR
                (visit_time < :end_time AND ADDTIME(visit_time, '01:00:00') >= :end_time) OR
                (visit_time >= :start_time AND ADDTIME(visit_time, '01:00:00') <= :end_time)
            )
            ORDER BY visit_time
        ");
        
        $stmt->execute([
            ':technician_id' => $technicianId,
            ':date' => $date,
            ':start_time' => $startTime,
            ':end_time' => $endTime
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene todas las excepciones futuras para un técnico
     * 
     * @param int $technicianId ID del técnico
     * @param int $limit Límite de resultados (opcional)
     * @return array Lista de excepciones
     */
    public function getFutureExceptions($technicianId, $limit = null) {
        $sql = "
            SELECT * FROM availability_exceptions 
            WHERE technician_id = :technician_id 
            AND exception_date >= CURRENT_DATE
            ORDER BY exception_date
        ";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':technician_id' => $technicianId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene todas las visitas futuras para un técnico
     * 
     * @param int $technicianId ID del técnico
     * @param string $status Estado de las visitas (opcional)
     * @param int $limit Límite de resultados (opcional)
     * @return array Lista de visitas
     */
    public function getFutureVisits($technicianId, $status = null, $limit = null) {
        $sql = "
            SELECT * FROM visits 
            WHERE technician_id = :technician_id 
            AND (
                visit_date > CURRENT_DATE OR 
                (visit_date = CURRENT_DATE AND visit_time >= CURRENT_TIME)
            )
        ";
        
        $params = [':technician_id' => $technicianId];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        $sql .= " ORDER BY visit_date, visit_time";
        
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}