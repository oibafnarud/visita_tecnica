<?php
// admin/visits/actions/create_visit.php - Formulario mejorado para crear/editar visitas
require_once '../../../includes/session_check.php';
require_once '../../../config/database.php';
require_once '../../../includes/AvailabilityUtils.php';

$database = new Database();
$db = $database->connect();

// Determinar si es una edición o creación
$visit_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$is_edit = !is_null($visit_id);

// Fecha y hora preseleccionadas (para creación)
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_time = isset($_GET['time']) ? $_GET['time'] : date('H:00');

$visit = null;
$error_message = null;
$success_message = null;

// Si es una edición, obtener datos de la visita
if ($is_edit) {
    try {
        $stmt = $db->prepare("
            SELECT 
                v.*,
                u.full_name as technician_name
            FROM visits v
            LEFT JOIN users u ON v.technician_id = u.id
            WHERE v.id = :id
        ");
        $stmt->execute([':id' => $visit_id]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$visit) {
            $error_message = "La visita solicitada no existe.";
        } else {
            $selected_date = $visit['visit_date'];
            $selected_time = substr($visit['visit_time'], 0, 5); // "HH:MM"
        }
    } catch (PDOException $e) {
        $error_message = "Error al obtener datos de la visita: " . $e->getMessage();
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos requeridos
    $required_fields = ['client_name', 'contact_name', 'contact_phone', 'address', 'visit_date', 'visit_time', 'technician_id'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error_message = "Por favor complete todos los campos requeridos.";
    } else {
        try {
            $db->beginTransaction();
            
            // Datos de la visita
            $client_name = trim($_POST['client_name']);
            $contact_name = trim($_POST['contact_name']);
            $contact_phone = trim($_POST['contact_phone']);
            $address = trim($_POST['address']);
            $reference = trim($_POST['reference'] ?? '');
            $location_url = trim($_POST['location_url'] ?? '');
            $visit_date = $_POST['visit_date'];
            $visit_time = $_POST['visit_time'];
            $service_type = trim($_POST['service_type'] ?? '');
            $technician_id = intval($_POST['technician_id']);
            $notes = trim($_POST['notes'] ?? '');
            $status = $_POST['status'] ?? 'pending';
            
            // Verificar disponibilidad del técnico
            $availabilityUtils = new AvailabilityUtils($db);
            $availability_check = $availabilityUtils->checkAvailability(
                $technician_id, 
                $visit_date, 
                $visit_time, 
                60 // Duración estándar de 1 hora
            );
            
            // Si es una edición, ignorar la propia visita en la verificación de disponibilidad
            $is_available = $availability_check['available'];
            if (!$is_available && $is_edit) {
                $overlapping_visits = $availability_check['details']['visits'] ?? [];
                if (count($overlapping_visits) === 1 && $overlapping_visits[0]['id'] == $visit_id) {
                    $is_available = true;
                }
            }
            
            if (!$is_available && $_POST['force_assign'] !== '1') {
                $error_message = "El técnico no está disponible en el horario seleccionado. " . 
                               $availability_check['message'] . 
                               "<br><button type='button' id='forceAssignBtn' class='text-blue-600 underline'>Asignar de todas formas</button>";
            } else {
                if ($is_edit) {
                    // Actualizar visita existente
                    $stmt = $db->prepare("
                        UPDATE visits SET
                            client_name = :client_name,
                            contact_name = :contact_name,
                            contact_phone = :contact_phone,
                            address = :address,
                            reference = :reference,
                            location_url = :location_url,
                            visit_date = :visit_date,
                            visit_time = :visit_time,
                            service_type = :service_type,
                            technician_id = :technician_id,
                            notes = :notes,
                            status = :status,
                            updated_at = NOW(),
                            updated_by = :updated_by
                        WHERE id = :id
                    ");
                    
                    $result = $stmt->execute([
                        ':client_name' => $client_name,
                        ':contact_name' => $contact_name,
                        ':contact_phone' => $contact_phone,
                        ':address' => $address,
                        ':reference' => $reference,
                        ':location_url' => $location_url,
                        ':visit_date' => $visit_date,
                        ':visit_time' => $visit_time,
                        ':service_type' => $service_type,
                        ':technician_id' => $technician_id,
                        ':notes' => $notes,
                        ':status' => $status,
                        ':updated_by' => $_SESSION['user_id'],
                        ':id' => $visit_id
                    ]);
                    
                    if ($result) {
                        // Si cambió el técnico, notificar al nuevo técnico
                        if ($visit['technician_id'] != $technician_id) {
                            $message = "Se te ha asignado una visita para el " . 
                                      date('d/m/Y', strtotime($visit_date)) . " a las " . 
                                      date('H:i', strtotime($visit_time)) . ".";
                            
                            // Omitido: inserción en la tabla de notificaciones
                        }
                        
                        $success_message = "Visita actualizada exitosamente.";
                    } else {
                        throw new Exception("Error al actualizar la visita.");
                    }
                } else {
                    // Crear nueva visita
                    $stmt = $db->prepare("
                        INSERT INTO visits (
                            client_name, contact_name, contact_phone, address, 
                            reference, location_url, visit_date, visit_time,
                            service_type, technician_id, notes, status,
                            created_by, created_at
                        ) VALUES (
                            :client_name, :contact_name, :contact_phone, :address,
                            :reference, :location_url, :visit_date, :visit_time,
                            :service_type, :technician_id, :notes, :status,
                            :created_by, NOW()
                        )
                    ");
                    
                    $result = $stmt->execute([
                        ':client_name' => $client_name,
                        ':contact_name' => $contact_name,
                        ':contact_phone' => $contact_phone,
                        ':address' => $address,
                        ':reference' => $reference,
                        ':location_url' => $location_url,
                        ':visit_date' => $visit_date,
                        ':visit_time' => $visit_time,
                        ':service_type' => $service_type,
                        ':technician_id' => $technician_id,
                        ':notes' => $notes,
                        ':status' => $status,
                        ':created_by' => $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        $new_visit_id = $db->lastInsertId();
                        
                        // Notificar al técnico
                        $message = "Se te ha asignado una nueva visita para el " . 
                                  date('d/m/Y', strtotime($visit_date)) . " a las " . 
                                  date('H:i', strtotime($visit_time)) . ".";
                        
                        // Omitido: inserción en la tabla de notificaciones
                        
                        $success_message = "Visita programada exitosamente.";
                        $visit_id = $new_visit_id; // Para redirección
                    } else {
                        throw new Exception("Error al crear la visita.");
                    }
                }
                
                $db->commit();
                
                // Redirect after successful operation
                if (!isset($_POST['stay_on_page']) || $_POST['stay_on_page'] !== '1') {
                    header("Location: view_visit.php?id=$visit_id&success=1");
                    exit;
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Obtener lista de técnicos para el selector
$stmt = $db->query("SELECT id, full_name FROM users WHERE role = 'technician' AND active = 1 ORDER BY full_name");
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de servicio para el selector
$stmt = $db->query("
    SELECT DISTINCT service_type 
    FROM visits 
    WHERE service_type IS NOT NULL AND service_type != '' 
    ORDER BY service_type
");
$service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Título de la página
$page_title = $is_edit ? 'Editar Visita' : 'Programar Nueva Visita';
$current_page = 'visits';

// Iniciar buffer de salida
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $page_title; ?></h1>
        <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../views/index.php'; ?>" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> Volver
        </a>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success_message; ?>
            <div class="mt-2">
                <?php if ($is_edit): ?>
                    <a href="view_visit.php?id=<?php echo $visit_id; ?>" class="text-green-700 underline mr-3">Ver Detalles</a>
                <?php endif; ?>
                <a href="../views/index.php" class="text-green-700 underline">Ver Todas las Visitas</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" id="visitForm" class="space-y-6">
            <!-- Campo oculto para asignación forzada -->
            <input type="hidden" name="force_assign" id="forceAssign" value="0">
            
            <!-- Información del Cliente -->
            <div class="border-b pb-6">
                <h2 class="text-xl font-bold mb-4">Información del Cliente</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre del Cliente/Empresa *</label>
                        <input type="text" name="client_name" required 
                               class="w-full p-2 border rounded"
                               value="<?php echo isset($visit) ? htmlspecialchars($visit['client_name']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre de Contacto *</label>
                        <input type="text" name="contact_name" required 
                               class="w-full p-2 border rounded"
                               value="<?php echo isset($visit) ? htmlspecialchars($visit['contact_name']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Teléfono de Contacto *</label>
                        <input type="tel" name="contact_phone" required 
                               class="w-full p-2 border rounded"
                               value="<?php echo isset($visit) ? htmlspecialchars($visit['contact_phone']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Tipo de Servicio *</label>
                        <select name="service_type" id="service_type" required 
                                class="w-full p-2 border rounded">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($service_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"
                                        <?php echo (isset($visit) && $visit['service_type'] == $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="Otro">Otro</option>
                        </select>
                        <div id="other_service_container" class="hidden mt-2">
                            <input type="text" id="other_service" 
                                   placeholder="Especificar tipo de servicio"
                                   class="w-full p-2 border rounded">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ubicación -->
            <div class="border-b pb-6">
                <h2 class="text-xl font-bold mb-4">Ubicación</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Dirección *</label>
                        <input type="text" name="address" required 
                               class="w-full p-2 border rounded"
                               value="<?php echo isset($visit) ? htmlspecialchars($visit['address']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Referencia</label>
                        <input type="text" name="reference" 
                               class="w-full p-2 border rounded"
                               value="<?php echo isset($visit) ? htmlspecialchars($visit['reference']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">URL de Google Maps</label>
                        <input type="url" name="location_url" 
                               placeholder="https://maps.google.com/?q=..." 
                               class="w-full p-2 border rounded"
                               value="<?php echo isset($visit) ? htmlspecialchars($visit['location_url']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Programación -->
            <div class="border-b pb-6">
                <h2 class="text-xl font-bold mb-4">Programación</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Fecha *</label>
                        <input type="date" id="visit_date" name="visit_date" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               onchange="updateTechnicianOptions()"
                               class="w-full p-2 border rounded"
                               value="<?php echo $selected_date; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Hora *</label>
                        <input type="time" id="visit_time" name="visit_time" required 
                               onchange="updateTechnicianOptions()"
                               class="w-full p-2 border rounded"
                               value="<?php echo $selected_time; ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Estado *</label>
                        <select name="status" class="w-full p-2 border rounded">
                            <option value="pending" <?php echo (isset($visit) && $visit['status'] == 'pending') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="in_route" <?php echo (isset($visit) && $visit['status'] == 'in_route') ? 'selected' : ''; ?>>En Ruta</option>
                            <option value="completed" <?php echo (isset($visit) && $visit['status'] == 'completed') ? 'selected' : ''; ?>>Completada</option>
                            <option value="cancelled" <?php echo (isset($visit) && $visit['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Asignación de Técnico -->
            <div class="border-b pb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Asignación de Técnico</h2>
                    <button type="button" id="findTechniciansBtn" 
                            onclick="findAvailableTechnicians()"
                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                        <i class="fas fa-search mr-1"></i>Buscar Disponibles
                    </button>
                </div>
                
                <div id="technician_selection_container">
                    <div id="loading_technicians" class="hidden text-center py-4">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Buscando técnicos disponibles...
                    </div>
                    
                    <div id="available_technicians_container" class="hidden mb-4">
                        <div class="bg-green-50 p-4 rounded-lg mb-4">
                            <h3 class="font-medium text-green-800 mb-2">
                                <i class="fas fa-check-circle mr-1"></i>Técnicos Disponibles
                            </h3>
                            <div id="available_technicians_list" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Los técnicos disponibles se mostrarán aquí -->
                            </div>
                        </div>
                    </div>
                    
                    <div id="no_technicians_message" class="hidden bg-yellow-50 p-4 rounded-lg mb-4">
                        <h3 class="font-medium text-yellow-800 mb-2">
                            <i class="fas fa-exclamation-triangle mr-1"></i>No hay técnicos disponibles
                        </h3>
                        <p class="text-yellow-700">
                            No se encontraron técnicos disponibles para la fecha y hora seleccionadas. 
                            Puedes intentar con otro horario o seleccionar un técnico manualmente.
                        </p>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-gray-700 mb-2">Seleccionar Técnico *</label>
                        <select name="technician_id" id="technician_id" required 
                                onchange="checkTechnicianAvailability()"
                                class="w-full p-2 border rounded">
                            <option value="">Seleccionar técnico...</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>"
                                        <?php echo (isset($visit) && $visit['technician_id'] == $tech['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tech['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="availability_status" class="hidden mt-4 p-3 rounded"></div>
                </div>
            </div>

            <!-- Notas -->
            <div>
                <label class="block text-gray-700 mb-2">Notas Adicionales</label>
                <textarea name="notes" rows="3" 
                          class="w-full p-2 border rounded"><?php echo isset($visit) ? htmlspecialchars($visit['notes']) : ''; ?></textarea>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4">
                <label class="flex items-center text-sm text-gray-600 mr-auto">
                    <input type="checkbox" name="stay_on_page" value="1" class="mr-1"> 
                    Permanecer en esta página después de guardar
                </label>
                
                <a href="<?php echo isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../views/index.php'; ?>" 
                   class="px-6 py-2 border border-gray-300 rounded hover:bg-gray-100">
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <?php echo $is_edit ? 'Actualizar Visita' : 'Programar Visita'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="../../../admin/js/availability_service.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar el tipo de servicio "Otro"
    document.getElementById('service_type').addEventListener('change', function() {
        const otherContainer = document.getElementById('other_service_container');
        const otherInput = document.getElementById('other_service');
        
        if (this.value === 'Otro') {
            otherContainer.classList.remove('hidden');
            otherInput.required = true;
            otherInput.addEventListener('input', function() {
                document.querySelector('select[name="service_type"]').value = this.value;
            });
        } else {
            otherContainer.classList.add('hidden');
            otherInput.required = false;
        }
    });
    
    // Si hay un botón para forzar asignación, configurar evento
    const forceAssignBtn = document.getElementById('forceAssignBtn');
    if (forceAssignBtn) {
        forceAssignBtn.addEventListener('click', function() {
            document.getElementById('forceAssign').value = '1';
            document.getElementById('visitForm').submit();
        });
    }
    
    // Validar formulario antes de enviar
    document.getElementById('visitForm').addEventListener('submit', function(e) {
        const visitDate = new Date(document.getElementById('visit_date').value);
        const visitTime = document.getElementById('visit_time').value;
        const now = new Date();
        
        // Reset fecha actual a medianoche para comparar solo fechas
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const selectedDate = new Date(visitDate.getFullYear(), visitDate.getMonth(), visitDate.getDate());

        if (selectedDate < today) {
            e.preventDefault();
            showNotification('La fecha de la visita no puede ser anterior a hoy', 'error');
            return;
        }

        // Si es hoy, validar la hora
        if (selectedDate.getTime() === today.getTime()) {
            const currentHour = now.getHours();
            const currentMinutes = now.getMinutes();
            const [visitHour, visitMinutes] = visitTime.split(':').map(Number);

            if (visitHour < currentHour || (visitHour === currentHour && visitMinutes <= currentMinutes)) {
                e.preventDefault();
                showNotification('La hora de la visita debe ser posterior a la hora actual', 'error');
                return;
            }
        }
    });
    
    // Check availability on load if editing
    if (document.getElementById('technician_id').value) {
        checkTechnicianAvailability();
    }
});

function findAvailableTechnicians() {
    const date = document.getElementById('visit_date').value;
    const time = document.getElementById('visit_time').value;
    const serviceType = document.getElementById('service_type').value;
    
    if (!date || !time) {
        showNotification('Debe seleccionar fecha y hora para buscar técnicos disponibles', 'error');
        return;
    }
    
    // Mostrar indicador de carga
    document.getElementById('loading_technicians').classList.remove('hidden');
    document.getElementById('available_technicians_container').classList.add('hidden');
    document.getElementById('no_technicians_message').classList.add('hidden');
    
    // Llamar al servicio de disponibilidad
    AvailabilityService.findAvailableTechnicians(date, time, 60, serviceType)
        .then(response => {
            // Ocultar indicador de carga
            document.getElementById('loading_technicians').classList.add('hidden');
            
            if (response.success) {
                if (response.technicians && response.technicians.length > 0) {
                    // Mostrar técnicos disponibles
                    displayAvailableTechnicians(response.technicians);
                } else {
                    // Mostrar mensaje de no hay técnicos disponibles
                    document.getElementById('no_technicians_message').classList.remove('hidden');
                }
            } else {
                showNotification(response.error || 'Error al buscar técnicos disponibles', 'error');
            }
        })
        .catch(error => {
            document.getElementById('loading_technicians').classList.add('hidden');
            showNotification('Error al buscar técnicos disponibles', 'error');
            console.error('Error:', error);
        });
}

function displayAvailableTechnicians(technicians) {
    const container = document.getElementById('available_technicians_list');
    container.innerHTML = '';
    
    technicians.forEach(tech => {
        const techCard = document.createElement('div');
        techCard.className = 'bg-white p-3 rounded shadow-sm border border-green-200';
        techCard.innerHTML = `
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-medium">${tech.full_name}</div>
                    <div class="text-sm text-gray-600">${tech.email || ''}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-calendar-check mr-1"></i>${tech.workload} visita(s) programada(s)
                    </div>
                </div>
                <button type="button" onclick="selectTechnician(${tech.id})" 
                        class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                    Seleccionar
                </button>
            </div>
        `;
        container.appendChild(techCard);
    });
    
    document.getElementById('available_technicians_container').classList.remove('hidden');
}

function selectTechnician(techId) {
    const selectElement = document.getElementById('technician_id');
    selectElement.value = techId;
    
    // Verificar disponibilidad para mostrar el estado
    checkTechnicianAvailability();
    
    // Hacer scroll al selector
    selectElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function checkTechnicianAvailability() {
    const technicianId = document.getElementById('technician_id').value;
    const date = document.getElementById('visit_date').value;
    const time = document.getElementById('visit_time').value;
    
    if (!technicianId || !date || !time) return;
    
    const statusContainer = document.getElementById('availability_status');
    statusContainer.classList.remove('hidden');
    statusContainer.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando disponibilidad...';
    
    AvailabilityService.checkAvailability(technicianId, date, time, 60)
        .then(response => {
            if (response.success) {
                if (response.available) {
                    statusContainer.className = 'mt-4 p-3 rounded bg-green-100 text-green-800';
                    statusContainer.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Técnico disponible para esta visita';
                } else {
                    statusContainer.className = 'mt-4 p-3 rounded bg-red-100 text-red-800';
                    statusContainer.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${response.message}`;
                }
            } else {
                statusContainer.className = 'mt-4 p-3 rounded bg-red-100 text-red-800';
                statusContainer.innerHTML = `<i class="fas fa-times-circle mr-2"></i>${response.error || 'Error al verificar disponibilidad'}`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusContainer.className = 'mt-4 p-3 rounded bg-red-100 text-red-800';
            statusContainer.innerHTML = '<i class="fas fa-times-circle mr-2"></i>Error al verificar disponibilidad';
        });
}

function updateTechnicianOptions() {
    // Limpiar mensajes previos
    document.getElementById('availability_status').classList.add('hidden');
    document.getElementById('available_technicians_container').classList.add('hidden');
    document.getElementById('no_technicians_message').classList.add('hidden');
    
    // Si ya había seleccionado un técnico, verificar disponibilidad
    const technicianId = document.getElementById('technician_id').value;
    if (technicianId) {
        checkTechnicianAvailability();
    }
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
</script>

<?php
$content = ob_get_clean();
require_once '../../../includes/layout.php';
?>