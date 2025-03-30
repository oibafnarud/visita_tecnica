<div id="visitFormModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="relative top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-4xl">
        <div class="bg-white rounded-lg shadow-xl m-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b sticky top-0 bg-white z-10">
                <h3 class="text-xl font-bold" id="modalTitle">Nueva Visita</h3>
                <button type="button" onclick="closeModal('visitFormModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="visitForm" onsubmit="saveVisit(event)" class="p-6">
                <input type="hidden" name="visit_id" id="visit_id_input">
                
                <!-- Progreso del formulario -->
                <div class="mb-6 bg-gray-100 rounded-lg p-2">
                    <div class="flex justify-between">
                        <div class="flex items-center">
                            <span id="stepIndicator1" class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center mr-2">1</span>
                            <span class="text-sm font-medium">Cliente</span>
                        </div>
                        <div class="flex items-center">
                            <span id="stepIndicator2" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mr-2">2</span>
                            <span class="text-sm font-medium">Ubicación</span>
                        </div>
                        <div class="flex items-center">
                            <span id="stepIndicator3" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mr-2">3</span>
                            <span class="text-sm font-medium">Programación</span>
                        </div>
                        <div class="flex items-center">
                            <span id="stepIndicator4" class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center mr-2">4</span>
                            <span class="text-sm font-medium">Detalles</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                        <div id="progressBar" class="bg-blue-600 h-2.5 rounded-full" style="width: 25%"></div>
                    </div>
                </div>

                <!-- Sección 1: Cliente y Contacto -->
                <div id="section1" class="mb-8">
                    <h4 class="text-lg font-semibold mb-4 text-gray-700">Información del Cliente</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium mb-1">Cliente/Empresa *</label>
                            <div class="relative">
                                <input type="text" name="client_name" id="client_name" required 
                                    class="w-full p-2.5 pr-10 border rounded focus:ring-2 focus:ring-blue-500"
                                    autocomplete="off" list="clientSuggestions">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-building"></i>
                                </span>
                                <datalist id="clientSuggestions">
                                    <!-- Sugerencias basadas en clientes existentes -->
                                </datalist>
                            </div>
                            <div id="clientNameError" class="text-red-500 text-xs mt-1 hidden">Por favor ingrese el nombre del cliente</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Contacto *</label>
                            <div class="relative">
                                <input type="text" name="contact_name" id="contact_name" required 
                                    class="w-full p-2.5 pr-10 border rounded focus:ring-2 focus:ring-blue-500">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                            <div id="contactNameError" class="text-red-500 text-xs mt-1 hidden">Por favor ingrese el nombre del contacto</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Teléfono *</label>
                            <div class="relative">
                                <input type="tel" name="contact_phone" id="contact_phone" required 
                                    class="w-full p-2.5 pr-10 border rounded focus:ring-2 focus:ring-blue-500"
                                    pattern="[0-9]{10}">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-phone"></i>
                                </span>
                            </div>
                            <div id="contactPhoneError" class="text-red-500 text-xs mt-1 hidden">Por favor ingrese un número de teléfono válido</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Tipo de Servicio *</label>
                            <select name="service_type" id="service_type" required 
                                class="w-full p-2.5 border rounded focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleccionar tipo...</option>
                                <option value="Instalación">Instalación</option>
                                <option value="Reparación">Reparación</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Revisión">Revisión</option>
                                <option value="Otro">Otro...</option>
                            </select>
                            <div id="otherServiceType" class="mt-2 hidden">
                                <input type="text" name="other_service_type" placeholder="Especificar tipo de servicio" 
                                    class="w-full p-2 border rounded">
                            </div>
                            <div id="serviceTypeError" class="text-red-500 text-xs mt-1 hidden">Por favor seleccione un tipo de servicio</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" onclick="goToSection(2)" 
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center">
                            Siguiente <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Sección 2: Ubicación -->
                <div id="section2" class="mb-8 hidden">
                    <h4 class="text-lg font-semibold mb-4 text-gray-700">Ubicación</h4>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Dirección *</label>
                            <div class="relative">
                                <input type="text" name="address" id="address" required 
                                    class="w-full p-2.5 pr-10 border rounded focus:ring-2 focus:ring-blue-500">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-map-marker-alt"></i>
                                </span>
                            </div>
                            <div id="addressError" class="text-red-500 text-xs mt-1 hidden">Por favor ingrese la dirección</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Referencia</label>
                            <input type="text" name="reference" 
                                class="w-full p-2.5 border rounded focus:ring-2 focus:ring-blue-500"
                                placeholder="Ej: Cerca del supermercado X, frente a la farmacia Y">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">URL de Google Maps</label>
                            <div class="relative">
                                <input type="url" name="location_url" id="location_url"
                                    class="w-full p-2.5 pr-10 border rounded focus:ring-2 focus:ring-blue-500"
                                    placeholder="https://maps.google.com/?q=...">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-map"></i>
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Abra Google Maps, busque la ubicación, y use el botón "Compartir" para obtener el enlace
                            </div>
                            <div id="locationUrlError" class="text-red-500 text-xs mt-1 hidden">La URL debe ser de Google Maps</div>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <button type="button" onclick="goToSection(1)" 
                                class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Anterior
                        </button>
                        <button type="button" onclick="goToSection(3)" 
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center">
                            Siguiente <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Sección 3: Programación -->
                <div id="section3" class="mb-8 hidden">
                    <h4 class="text-lg font-semibold mb-4 text-gray-700">Programación</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Fecha *</label>
                            <div class="relative">
                                <input type="date" name="visit_date" id="visit_date" required 
                                    min="<?php echo date('Y-m-d'); ?>"
                                    onchange="checkTechnicianAvailability()"
                                    class="w-full p-2.5 pr-10 border rounded focus:ring-2 focus:ring-blue-500">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                                    <i class="fas fa-calendar"></i>
                                </span>
                            </div>
                            <div id="visitTimeError" class="text-red-500 text-xs mt-1 hidden">Por favor seleccione una hora válida</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Duración *</label>
                            <select name="duration" id="duration" required 
                                    onchange="checkTechnicianAvailability()"
                                    class="w-full p-2.5 border rounded focus:ring-2 focus:ring-blue-500">
                                <option value="30">30 minutos</option>
                                <option value="60" selected>1 hora</option>
                                <option value="90">1.5 horas</option>
                                <option value="120">2 horas</option>
                                <option value="180">3 horas</option>
                                <option value="240">4 horas</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label class="block text-sm font-medium mb-1">Técnico Asignado *</label>
                        <select name="technician_id" id="technician_id" required 
                                onchange="checkTechnicianAvailability()"
                                class="w-full p-2.5 border rounded focus:ring-2 focus:ring-blue-500">
                            <option value="">Seleccionar técnico...</option>
                            <?php foreach ($technicians as $tech): ?>
                                <option value="<?php echo $tech['id']; ?>">
                                    <?php echo htmlspecialchars($tech['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="technicianError" class="text-red-500 text-xs mt-1 hidden">Por favor seleccione un técnico</div>
                    </div>
                    
                    <!-- Estado de disponibilidad -->
                    <div id="availabilityFeedback" class="mt-4 p-3 rounded-lg hidden">
                        <!-- El contenido se actualizará dinámicamente -->
                    </div>
                    
                    <div class="mt-4 flex justify-between">
                        <button type="button" onclick="goToSection(2)" 
                                class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Anterior
                        </button>
                        <button type="button" onclick="goToSection(4)" 
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center">
                            Siguiente <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Sección 4: Detalles -->
                <div id="section4" class="mb-8 hidden">
                    <h4 class="text-lg font-semibold mb-4 text-gray-700">Detalles Adicionales</h4>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Notas</label>
                        <textarea name="notes" rows="3" 
                                class="w-full p-2.5 border rounded focus:ring-2 focus:ring-blue-500"
                                placeholder="Detalles importantes para el técnico, requerimientos especiales, etc."></textarea>
                    </div>
                    
                    <div class="border p-4 rounded-lg bg-blue-50 mb-4">
                        <h5 class="font-medium text-blue-800 mb-2">
                            <i class="fas fa-bell mr-1"></i> Notificaciones
                        </h5>
                        <div class="flex items-center">
                            <input type="checkbox" name="notify_technician" id="notify_technician" 
                                   class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500" checked>
                            <label for="notify_technician" class="ml-2 text-sm text-gray-700">
                                Notificar al técnico sobre esta asignación
                            </label>
                        </div>
                    </div>
                    
                    <!-- Resumen de la visita -->
                    <div class="border p-4 rounded-lg mb-4">
                        <h5 class="font-medium mb-3">Resumen de la Visita</h5>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-gray-600">Cliente:</span>
                                <span id="summary_client" class="font-medium ml-1">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Contacto:</span>
                                <span id="summary_contact" class="font-medium ml-1">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Fecha/Hora:</span>
                                <span id="summary_datetime" class="font-medium ml-1">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Técnico:</span>
                                <span id="summary_technician" class="font-medium ml-1">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Tipo de Servicio:</span>
                                <span id="summary_service" class="font-medium ml-1">-</span>
                            </div>
                            <div>
                                <span class="text-gray-600">Ubicación:</span>
                                <span id="summary_location" class="font-medium ml-1">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex justify-between">
                        <button type="button" onclick="goToSection(3)" 
                                class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-100 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Anterior
                        </button>
                        <button type="submit"
                                class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 flex items-center">
                            <i class="fas fa-check mr-2"></i> Guardar Visita
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar validaciones
    initValidations();
    
    // Manejar cambio en tipo de servicio
    document.getElementById('service_type').addEventListener('change', function() {
        const otherServiceContainer = document.getElementById('otherServiceType');
        otherServiceContainer.classList.toggle('hidden', this.value !== 'Otro');
    });
    
    // Cargar sugerencias de clientes
    loadClientSuggestions();
});

function loadClientSuggestions() {
    // Aquí puedes cargar clientes existentes mediante AJAX
    fetch('actions/get_clients.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const datalist = document.getElementById('clientSuggestions');
                datalist.innerHTML = '';
                data.clients.forEach(client => {
                    const option = document.createElement('option');
                    option.value = client.name;
                    datalist.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading client suggestions:', error));
}

function initValidations() {
    // Validación del nombre del cliente
    document.getElementById('client_name').addEventListener('blur', function() {
        const error = document.getElementById('clientNameError');
        error.classList.toggle('hidden', this.value.trim() !== '');
    });
    
    // Validación del nombre de contacto
    document.getElementById('contact_name').addEventListener('blur', function() {
        const error = document.getElementById('contactNameError');
        error.classList.toggle('hidden', this.value.trim() !== '');
    });
    
    // Validación del teléfono
    document.getElementById('contact_phone').addEventListener('blur', function() {
        const error = document.getElementById('contactPhoneError');
        const isValid = /^\d{10}$/.test(this.value.replace(/\D/g, ''));
        error.classList.toggle('hidden', isValid);
    });
    
    // Validación del tipo de servicio
    document.getElementById('service_type').addEventListener('change', function() {
        const error = document.getElementById('serviceTypeError');
        error.classList.toggle('hidden', this.value !== '');
    });
    
    // Validación de la dirección
    document.getElementById('address').addEventListener('blur', function() {
        const error = document.getElementById('addressError');
        error.classList.toggle('hidden', this.value.trim() !== '');
    });
    
    // Validación de URL de Google Maps
    document.getElementById('location_url').addEventListener('blur', function() {
        if (this.value.trim() === '') return;
        
        const error = document.getElementById('locationUrlError');
        const isGoogleMapsUrl = this.value.includes('google.com/maps') || 
                                this.value.includes('maps.app.goo.gl') ||
                                this.value.includes('maps.google.com');
        error.classList.toggle('hidden', isGoogleMapsUrl);
    });
    
    // Validación de la fecha
    document.getElementById('visit_date').addEventListener('blur', function() {
        const error = document.getElementById('visitDateError');
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        const isValid = this.value !== '' && selectedDate >= today;
        error.classList.toggle('hidden', isValid);
    });
    
    // Validación de la hora
    document.getElementById('visit_time').addEventListener('blur', function() {
        const error = document.getElementById('visitTimeError');
        error.classList.toggle('hidden', this.value !== '');
    });
    
    // Validación del técnico
    document.getElementById('technician_id').addEventListener('change', function() {
        const error = document.getElementById('technicianError');
        error.classList.toggle('hidden', this.value !== '');
    });
}

function goToSection(sectionNumber) {
    // Validar sección actual antes de avanzar
    if (sectionNumber > 1 && !validateSection(sectionNumber - 1)) {
        return false;
    }
    
    // Actualizar secciones visibles
    for (let i = 1; i <= 4; i++) {
        document.getElementById(`section${i}`).classList.toggle('hidden', i !== sectionNumber);
        
        // Actualizar indicadores
        const indicator = document.getElementById(`stepIndicator${i}`);
        if (i < sectionNumber) {
            indicator.classList.remove('bg-gray-300', 'text-gray-600', 'bg-blue-600', 'text-white');
            indicator.classList.add('bg-green-600', 'text-white');
            indicator.innerHTML = '<i class="fas fa-check"></i>';
        } else if (i === sectionNumber) {
            indicator.classList.remove('bg-gray-300', 'text-gray-600', 'bg-green-600');
            indicator.classList.add('bg-blue-600', 'text-white');
            indicator.innerHTML = i;
        } else {
            indicator.classList.remove('bg-blue-600', 'text-white', 'bg-green-600');
            indicator.classList.add('bg-gray-300', 'text-gray-600');
            indicator.innerHTML = i;
        }
    }
    
    // Actualizar barra de progreso
    const progress = (sectionNumber - 1) * 25;
    document.getElementById('progressBar').style.width = `${progress}%`;
    
    // Si estamos en la última sección, actualizar el resumen
    if (sectionNumber === 4) {
        updateSummary();
    }
    
    return true;
}

function validateSection(sectionNumber) {
    let isValid = true;
    
    if (sectionNumber === 1) {
        // Validar sección 1: Cliente
        const clientName = document.getElementById('client_name').value.trim();
        const contactName = document.getElementById('contact_name').value.trim();
        const contactPhone = document.getElementById('contact_phone').value.trim();
        const serviceType = document.getElementById('service_type').value;
        
        if (clientName === '') {
            document.getElementById('clientNameError').classList.remove('hidden');
            isValid = false;
        }
        
        if (contactName === '') {
            document.getElementById('contactNameError').classList.remove('hidden');
            isValid = false;
        }
        
        if (contactPhone === '' || !/^\d{10}$/.test(contactPhone.replace(/\D/g, ''))) {
            document.getElementById('contactPhoneError').classList.remove('hidden');
            isValid = false;
        }
        
        if (serviceType === '') {
            document.getElementById('serviceTypeError').classList.remove('hidden');
            isValid = false;
        }
    } else if (sectionNumber === 2) {
        // Validar sección 2: Ubicación
        const address = document.getElementById('address').value.trim();
        const locationUrl = document.getElementById('location_url').value.trim();
        
        if (address === '') {
            document.getElementById('addressError').classList.remove('hidden');
            isValid = false;
        }
        
        if (locationUrl !== '') {
            const isGoogleMapsUrl = locationUrl.includes('google.com/maps') || 
                                   locationUrl.includes('maps.app.goo.gl') ||
                                   locationUrl.includes('maps.google.com');
            if (!isGoogleMapsUrl) {
                document.getElementById('locationUrlError').classList.remove('hidden');
                isValid = false;
            }
        }
    } else if (sectionNumber === 3) {
        // Validar sección 3: Programación
        const visitDate = document.getElementById('visit_date').value;
        const visitTime = document.getElementById('visit_time').value;
        const technicianId = document.getElementById('technician_id').value;
        
        if (visitDate === '') {
            document.getElementById('visitDateError').classList.remove('hidden');
            isValid = false;
        } else {
            const selectedDate = new Date(visitDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                document.getElementById('visitDateError').classList.remove('hidden');
                isValid = false;
            }
        }
        
        if (visitTime === '') {
            document.getElementById('visitTimeError').classList.remove('hidden');
            isValid = false;
        }
        
        if (technicianId === '') {
            document.getElementById('technicianError').classList.remove('hidden');
            isValid = false;
        }
    }
    
    return isValid;
}

function updateSummary() {
    // Actualizar el resumen con los datos ingresados
    document.getElementById('summary_client').textContent = document.getElementById('client_name').value;
    document.getElementById('summary_contact').textContent = document.getElementById('contact_name').value + ' - ' + document.getElementById('contact_phone').value;
    
    const date = document.getElementById('visit_date').value;
    const time = document.getElementById('visit_time').value;
    const duration = document.getElementById('duration').value;
    const formattedDate = new Date(date).toLocaleDateString('es-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    
    let durationText = '';
    if (duration === '60') durationText = '1 hora';
    else if (duration === '30') durationText = '30 minutos';
    else if (duration === '90') durationText = '1.5 horas';
    else if (duration === '120') durationText = '2 horas';
    else if (duration === '180') durationText = '3 horas';
    else if (duration === '240') durationText = '4 horas';
    
    document.getElementById('summary_datetime').textContent = `${formattedDate} a las ${time} (${durationText})`;
    
    const technicianSelect = document.getElementById('technician_id');
    const technicianText = technicianSelect.value !== '' ? 
        technicianSelect.options[technicianSelect.selectedIndex].text : '-';
    document.getElementById('summary_technician').textContent = technicianText;
    
    const serviceTypeSelect = document.getElementById('service_type');
    let serviceType = serviceTypeSelect.value;
    if (serviceType === 'Otro') {
        serviceType = document.getElementById('other_service_type').value || 'Otro';
    }
    document.getElementById('summary_service').textContent = serviceType;
    
    document.getElementById('summary_location').textContent = document.getElementById('address').value;
}

async function checkTechnicianAvailability() {
    const technicianId = document.getElementById('technician_id').value;
    const visitDate = document.getElementById('visit_date').value;
    const visitTime = document.getElementById('visit_time').value;
    const duration = document.getElementById('duration').value;
    const visitId = document.getElementById('visit_id_input').value || null;
    
    // Si no hay suficiente información, no hacer nada
    if (!technicianId || !visitDate || !visitTime) {
        document.getElementById('availabilityFeedback').classList.add('hidden');
        return;
    }
    
    try {
        // Mostrar indicador de carga
        const feedback = document.getElementById('availabilityFeedback');
        feedback.classList.remove('hidden', 'bg-green-100', 'bg-red-100', 'bg-yellow-100');
        feedback.classList.add('bg-gray-100');
        feedback.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Verificando disponibilidad...';
        
        // Realizar la verificación de disponibilidad
        const response = await fetch('actions/check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                technician_id: technicianId,
                visit_date: visitDate,
                visit_time: visitTime,
                duration: duration,
                visit_id: visitId
            })
        });
        
        const data = await response.json();
        
        // Actualizar el feedback según el resultado
        if (data.success) {
            if (data.available) {
                feedback.classList.remove('bg-gray-100', 'bg-red-100', 'bg-yellow-100');
                feedback.classList.add('bg-green-100');
                feedback.innerHTML = '<i class="fas fa-check-circle mr-2 text-green-600"></i> ' +
                    '<span class="text-green-800">El técnico está disponible en este horario.</span>';
            } else {
                // Si hay conflictos, mostrarlos
                feedback.classList.remove('bg-gray-100', 'bg-green-100');
                feedback.classList.add('bg-yellow-100');
                
                let message = '<i class="fas fa-exclamation-triangle mr-2 text-yellow-600"></i> ' +
                    '<span class="text-yellow-800">El técnico tiene conflictos de horario:</span>';
                
                message += '<ul class="mt-2 ml-6 list-disc text-yellow-800">';
                data.conflicts.forEach(conflict => {
                    message += `<li>${conflict.client_name} - ${conflict.time} (${conflict.status})</li>`;
                });
                message += '</ul>';
                
                feedback.innerHTML = message;
            }
        } else {
            // Error al verificar
            feedback.classList.remove('bg-gray-100', 'bg-green-100', 'bg-yellow-100');
            feedback.classList.add('bg-red-100');
            feedback.innerHTML = '<i class="fas fa-times-circle mr-2 text-red-600"></i> ' +
                '<span class="text-red-800">Error al verificar disponibilidad: ' + data.error + '</span>';
        }
    } catch (error) {
        console.error('Error checking availability:', error);
        const feedback = document.getElementById('availabilityFeedback');
        feedback.classList.remove('bg-gray-100', 'bg-green-100', 'bg-yellow-100');
        feedback.classList.add('bg-red-100');
        feedback.innerHTML = '<i class="fas fa-times-circle mr-2 text-red-600"></i> ' +
            '<span class="text-red-800">Error al verificar disponibilidad. Intente de nuevo.</span>';
    }
}

async function saveVisit(event) {
    event.preventDefault();
    
    // Validar todo el formulario
    for (let i = 1; i <= 3; i++) {
        if (!validateSection(i)) {
            goToSection(i);
            return;
        }
    }
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    try {
        // Deshabilitar botón y mostrar spinner
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Guardando...';
        
        // Preparar datos del servicio "Otro" si aplica
        if (document.getElementById('service_type').value === 'Otro') {
            const otherServiceType = document.getElementById('other_service_type').value;
            if (otherServiceType) {
                document.getElementById('service_type').value = otherServiceType;
            }
        }
        
        // Enviar formulario
        const formData = new FormData(form);
        
        const response = await fetch('actions/save_visit.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Mostrar mensaje de éxito
            showNotification(
                formData.get('visit_id') ? 'Visita actualizada exitosamente' : 'Visita creada exitosamente', 
                'success'
            );
            
            // Cerrar modal y recargar página después de un momento
            closeModal('visitFormModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.error || 'Error al guardar la visita', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    } finally {
        // Restaurar botón
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
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

function showNewVisitModal(presetDate = null, presetTime = null) {
    // Limpiar formulario
    document.getElementById('visitForm').reset();
    document.getElementById('visit_id_input').value = '';
    document.getElementById('modalTitle').textContent = 'Nueva Visita';
    
    // Ir a la primera sección
    goToSection(1);
    
    // Preseleccionar fecha y hora si se proporcionan
    if (presetDate) {
        document.getElementById('visit_date').value = presetDate;
    }
    
    if (presetTime) {
        document.getElementById('visit_time').value = presetTime;
    }
    
    // Mostrar modal
    showModal('visitFormModal');
}

function editVisit(visitId) {
    // Limpiar formulario
    document.getElementById('visitForm').reset();
    document.getElementById('visit_id_input').value = visitId;
    document.getElementById('modalTitle').textContent = 'Editar Visita';
    
    // Mostrar indicador de carga
    document.getElementById('section1').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-blue-600 text-3xl"></i><p class="mt-2 text-gray-600">Cargando datos de la visita...</p></div>';
    
    // Ir a la primera sección
    goToSection(1);
    
    // Cargar datos de la visita
    fetch(`actions/get_visit_details.php?id=${visitId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Restaurar secciones
                goToSection(1);
                
                // Llenar formulario con datos de la visita
                document.getElementById('client_name').value = data.visit.client_name;
                document.getElementById('contact_name').value = data.visit.contact_name;
                document.getElementById('contact_phone').value = data.visit.contact_phone;
                document.getElementById('service_type').value = data.visit.service_type;
                
                document.getElementById('address').value = data.visit.address;
                document.getElementById('reference').value = data.visit.reference;
                document.getElementById('location_url').value = data.visit.location_url;
                
                document.getElementById('visit_date').value = data.visit.visit_date;
                document.getElementById('visit_time').value = data.visit.visit_time;
                document.getElementById('duration').value = data.visit.duration;
                document.getElementById('technician_id').value = data.visit.technician_id;
                
                document.querySelector('textarea[name="notes"]').value = data.visit.notes;
                
                // Verificar disponibilidad
                checkTechnicianAvailability();
            } else {
                showNotification(data.error || 'Error al cargar la visita', 'error');
                closeModal('visitFormModal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar la visita', 'error');
            closeModal('visitFormModal');
        });
    
    // Mostrar modal
    showModal('visitFormModal');
}

function showModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.style.overflow = 'auto';
}
</script>