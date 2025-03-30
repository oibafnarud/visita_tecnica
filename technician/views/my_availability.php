<?php
// technician/views/my_availability.php - Vista de disponibilidad para el técnico
require_once '../includes/session_check.php';
require_once '../config/database.php';
require_once '../includes/AvailabilityUtils.php';

// Verificar que el usuario tenga rol de técnico
if ($_SESSION['role'] !== 'technician') {
    header('Location: /login.php');
    exit;
}

$database = new Database();
$db = $database->connect();
$technician_id = $_SESSION['user_id'];

// Obtener disponibilidad regular
$stmt = $db->prepare("
    SELECT * FROM technician_availability 
    WHERE technician_id = ? 
    ORDER BY day_of_week, start_time
");
$stmt->execute([$technician_id]);
$availability = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por día de la semana
$availability_by_day = [];
foreach ($availability as $slot) {
    $day = $slot['day_of_week'];
    if (!isset($availability_by_day[$day])) {
        $availability_by_day[$day] = [];
    }
    $availability_by_day[$day][] = $slot;
}

// Obtener excepciones futuras
$availabilityUtils = new AvailabilityUtils($db);
$exceptions = $availabilityUtils->getFutureExceptions($technician_id);

// Obtener visitas programadas de los próximos días
$stmt = $db->prepare("
    SELECT 
        v.*,
        DATE_FORMAT(v.visit_date, '%d/%m/%Y') as formatted_date,
        TIME_FORMAT(v.visit_time, '%H:%i') as formatted_time
    FROM visits v
    WHERE v.technician_id = :technician_id
    AND v.visit_date >= CURRENT_DATE
    AND v.status IN ('pending', 'in_route')
    ORDER BY v.visit_date, v.visit_time
    LIMIT 10
");

$stmt->execute([':technician_id' => $technician_id]);
$upcoming_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Mi Disponibilidad';
$current_section = 'availability';

// Iniciar buffer de salida
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Gestión de Disponibilidad</h1>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Horario Regular -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Mi Horario Regular</h2>
            
            <div class="space-y-4">
                <?php 
                $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                for ($i = 1; $i <= 7; $i++): 
                    $daySlots = $availability_by_day[$i] ?? [];
                ?>
                    <div class="border-b pb-4 last:border-0">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-medium"><?php echo $days[$i-1]; ?></h3>
                        </div>
                        
                        <?php if (empty($daySlots)): ?>
                            <p class="text-gray-500 text-sm">No disponible</p>
                        <?php else: ?>
                            <div class="space-y-1">
                                <?php foreach ($daySlots as $slot): ?>
                                    <div class="flex items-center text-sm">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                            <?php echo date('h:i A', strtotime($slot['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($slot['end_time'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
                
                <div class="text-sm text-gray-600 mt-2">
                    <p>Este es tu horario regular establecido por el administrador. Si necesitas solicitar un cambio o tienes alguna consulta, comunícate con tu supervisor.</p>
                </div>
            </div>
        </div>
        
        <!-- Excepciones -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Mis Excepciones</h2>
                <button onclick="showAddExceptionModal()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Nueva Excepción
                </button>
            </div>
            
            <div id="exceptionsContainer" class="space-y-4">
                <?php if (empty($exceptions)): ?>
                    <p class="text-gray-500 text-center py-4">No tienes excepciones programadas</p>
                <?php else: ?>
                    <?php foreach ($exceptions as $exception): ?>
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-medium">
                                    <?php echo date('d/m/Y', strtotime($exception['exception_date'])); ?>
                                </div>
                                <?php if ($exception['is_available']): ?>
                                    <div class="text-sm text-gray-600">
                                        <?php 
                                        echo date('h:i A', strtotime($exception['start_time'])) . ' a ' . 
                                             date('h:i A', strtotime($exception['end_time']));
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-red-600">No Disponible</div>
                                <?php endif; ?>
                                <?php if (!empty($exception['reason'])): ?>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($exception['reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button onclick="deleteException(<?php echo $exception['id']; ?>)"
                                    class="text-red-600 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="text-sm text-gray-600 mt-4">
                <p>Las excepciones te permiten indicar días específicos donde no estarás disponible o tendrás un horario diferente al habitual.</p>
            </div>
        </div>
    </div>
    
    <!-- Próximas Visitas -->
    <div class="mt-8 bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">Próximas Visitas Programadas</h2>
        
        <?php if (empty($upcoming_visits)): ?>
            <p class="text-gray-500 text-center py-4">No tienes visitas programadas próximamente</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dirección</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Servicio</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($upcoming_visits as $visit): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $visit['formatted_date']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $visit['formatted_time']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($visit['client_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($visit['address']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($visit['service_type']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para agregar excepción -->
<div id="exceptionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
        <div class="bg-white rounded-lg shadow-xl mx-4">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Nueva Excepción</h3>
                    <button onclick="hideExceptionModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="exceptionForm" onsubmit="saveException(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Fecha</label>
                            <input type="date" name="exception_date" required
                                   min="<?php echo date('Y-m-d'); ?>"
                                   onchange="checkAffectedVisits(this.value)"
                                   class="w-full p-2 border rounded">
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_available" value="1" 
                                       onchange="toggleTimeInputs(this)"
                                       class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2">Disponible en horario específico</span>
                            </label>
                        </div>

                        <div id="timeInputs" class="hidden space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Hora Inicio</label>
                                <input type="time" name="start_time" class="w-full p-2 border rounded">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-1">Hora Fin</label>
                                <input type="time" name="end_time" class="w-full p-2 border rounded">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">Motivo</label>
                            <input type="text" name="reason"
                                   class="w-full p-2 border rounded"
                                   placeholder="Ej: Cita médica, Capacitación, etc.">
                        </div>

                        <div id="affectedVisitsContainer" class="hidden">
                            <div class="p-3 bg-yellow-100 text-yellow-800 rounded">
                                <div class="font-medium mb-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Visitas programadas para esta fecha
                                </div>
                                <div id="affectedVisits" class="text-sm">
                                </div>
                                <div class="mt-2 text-xs">
                                    <strong>Nota:</strong> Si continúas, es posible que estas visitas necesiten ser reprogramadas.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="hideExceptionModal()"
                                class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showAddExceptionModal() {
    document.getElementById('exceptionModal').classList.remove('hidden');
    document.getElementById('exceptionForm').reset();
    document.getElementById('timeInputs').classList.add('hidden');
    document.getElementById('affectedVisitsContainer').classList.add('hidden');
}

function hideExceptionModal() {
    document.getElementById('exceptionModal').classList.add('hidden');
}

function toggleTimeInputs(checkbox) {
    const timeInputs = document.getElementById('timeInputs');
    if (checkbox.checked) {
        timeInputs.classList.remove('hidden');
        timeInputs.querySelectorAll('input[type="time"]').forEach(input => {
            input.required = true;
        });
    } else {
        timeInputs.classList.add('hidden');
        timeInputs.querySelectorAll('input[type="time"]').forEach(input => {
            input.required = false;
        });
    }
}

function checkAffectedVisits(date) {
    if (!date) return;
    
    fetch(`actions/check_affected_visits.php?date=${date}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('affectedVisitsContainer');
            const visitsContainer = document.getElementById('affectedVisits');
            
            if (data.success && data.visits && data.visits.length > 0) {
                container.classList.remove('hidden');
                visitsContainer.innerHTML = data.visits.map(visit => `
                    <div class="mb-1">
                        <span class="font-medium">${visit.time}</span> - ${visit.client_name}
                    </div>
                `).join('');
            } else {
                container.classList.add('hidden');
                visitsContainer.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

function saveException(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar que si está disponible, las horas sean válidas
    if (formData.get('is_available') && formData.has('start_time') && formData.has('end_time')) {
        const startTime = formData.get('start_time');
        const endTime = formData.get('end_time');
        
        if (startTime >= endTime) {
            showNotification('La hora de fin debe ser posterior a la hora de inicio', 'error');
            return;
        }
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Guardando...';

    fetch('actions/save_exception.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Excepción guardada exitosamente', 'success');
            hideExceptionModal();
            // Recargar la página para mostrar la nueva excepción
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.error || 'Error al guardar la excepción', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al guardar la excepción', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function deleteException(exceptionId) {
    if (!confirm('¿Está seguro de eliminar esta excepción?')) {
        return;
    }

    fetch('actions/delete_exception.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ exception_id: exceptionId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Excepción eliminada exitosamente', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.error || 'Error al eliminar la excepción', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al eliminar la excepción', 'error');
    });
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Cerrar modal al hacer clic fuera
document.getElementById('exceptionModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideExceptionModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?>