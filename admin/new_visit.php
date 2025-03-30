<?php
// admin/new_visit.php - Formulario mejorado para crear nuevas visitas
require_once '../includes/session_check.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->connect();

// Obtener lista de técnicos activos
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

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $db->prepare("
            INSERT INTO visits (
                client_name, contact_name, contact_phone, address, 
                reference, location_url, visit_date, visit_time,
                service_type, technician_id, notes, status,
                created_by, created_at
            ) VALUES (
                :client_name, :contact_name, :contact_phone, :address,
                :reference, :location_url, :visit_date, :visit_time,
                :service_type, :technician_id, :notes, 'pending',
                :created_by, NOW()
            )
        ");
        
        $result = $stmt->execute([
            ':client_name' => $_POST['client_name'],
            ':contact_name' => $_POST['contact_name'],
            ':contact_phone' => $_POST['contact_phone'],
            ':address' => $_POST['address'],
            ':reference' => $_POST['reference'],
            ':location_url' => $_POST['location_url'],
            ':visit_date' => $_POST['visit_date'],
            ':visit_time' => $_POST['visit_time'],
            ':service_type' => $_POST['service_type'],
            ':technician_id' => $_POST['technician_id'],
            ':notes' => $_POST['notes'],
            ':created_by' => $_SESSION['user_id']
        ]);

        if ($result) {
            $visit_id = $db->lastInsertId();
            
            // Notificar al técnico asignado
            $stmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, message, type, reference_id, reference_type, created_at
                ) VALUES (
                    :user_id, :message, 'visit_assigned', :reference_id, 'visit', NOW()
                )
            ");
            
            $technician_id = $_POST['technician_id'];
            $visit_date = date('d/m/Y', strtotime($_POST['visit_date']));
            $visit_time = date('H:i', strtotime($_POST['visit_time']));
            
            $message = "Se te ha asignado una nueva visita para el $visit_date a las $visit_time.";
            
            $stmt->execute([
                ':user_id' => $technician_id,
                ':message' => $message,
                ':reference_id' => $visit_id
            ]);
            
            $success = "Visita programada exitosamente";
        }
    } catch(PDOException $e) {
        $error = "Error al programar la visita: " . $e->getMessage();
    }
}

$page_title = "Programar Nueva Visita";
$current_page = "new_visit";

// Iniciar buffer de salida para el contenido principal
ob_start();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold"><?php echo $page_title; ?></h1>
        <a href="visits.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-1"></i> Volver a Visitas
        </a>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success; ?>
            <div class="mt-2">
                <a href="visits.php" class="text-green-700 underline">Ver todas las visitas</a> | 
                <a href="new_visit.php" class="text-green-700 underline">Programar otra visita</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" id="visitForm" class="space-y-6">
            <!-- Información del Cliente -->
            <div class="border-b pb-6">
                <h2 class="text-xl font-bold mb-4">Información del Cliente</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre del Cliente/Empresa *</label>
                        <input type="text" name="client_name" required 
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre de Contacto *</label>
                        <input type="text" name="contact_name" required 
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Teléfono de Contacto *</label>
                        <input type="tel" name="contact_phone" required 
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Tipo de Servicio *</label>
                        <select name="service_type" id="service_type" required 
                                onchange="updateTechnicianOptions()"
                                class="w-full p-2 border rounded">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($service_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>">
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
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Referencia</label>
                        <input type="text" name="reference" 
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">URL de Google Maps</label>
                        <input type="url" name="location_url" 
                               placeholder="https://maps.google.com/?q=..." 
                               class="w-full p-2 border rounded">
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
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Hora *</label>
                        <input type="time" id="visit_time" name="visit_time" required 
                               onchange="updateTechnicianOptions()"
                               class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Duración Estimada *</label>
                        <select name="duration" id="duration" required 
                                onchange="updateTechnicianOptions()"
                                class="w-full p-2 border rounded">
                            <option value="30">30 minutos</option>
                            <option value="60" selected>1 hora</option>
                            <option value="90">1.5 horas</option>
                            <option value="120">2 horas</option>
                            <option value="180">3 horas</option>
                            <option value="240">4 horas</option>
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
                                <option value="<?php echo $tech['id']; ?>">
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
                          class="w-full p-2 border rounded"></textarea>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4">
                <a href="visits.php" 
                   class="px-6 py-2 border border-gray-300 rounded hover:bg-gray-100">
                    Cancelar
                </a>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Programar Visita
                </button>
            </div>
        </form>
    </div>
</div>

<script src="js/availability_service.js"></script>
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
});

function findAvailableTechnicians() {
    const date = document.getElementById('visit_date').value;
    const time = document.getElementById('visit_time').value;
    const duration = document.getElementById('duration').value;
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
    AvailabilityService.findAvailableTechnicians(date, time, duration, serviceType)
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
                    <div class="text-sm text-gray-600">${tech.email}</div>
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
    const duration = document.getElementById('duration').value;
    
    if (!technicianId || !date || !time) return;
    
    const statusContainer = document.getElementById('availability_status');
    statusContainer.classList.remove('hidden');
    statusContainer.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Verificando disponibilidad...';
    
    AvailabilityService.checkAvailability(technicianId, date, time, duration)
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
require_once '../includes/layout.php';
?>