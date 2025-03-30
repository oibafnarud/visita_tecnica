/**
 * availability_service.js - Servicio para gestionar la disponibilidad de técnicos
 */

const AvailabilityService = {
    /**
     * Verifica la disponibilidad de un técnico para una fecha y hora
     * @param {number} technicianId - ID del técnico
     * @param {string} date - Fecha en formato YYYY-MM-DD
     * @param {string} time - Hora en formato HH:MM
     * @param {number} duration - Duración en minutos
     * @returns {Promise} Promesa con el resultado de la verificación
     */
    checkAvailability: async function(technicianId, date, time, duration = 60) {
        try {
            const response = await fetch('actions/check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    technicianId: technicianId,
                    date: date,
                    time: time,
                    duration: duration
                })
            });
            
            return await response.json();
        } catch (error) {
            console.error('Error al verificar disponibilidad:', error);
            return {
                success: false,
                error: 'Error de conexión al verificar disponibilidad'
            };
        }
    },
    
    /**
     * Busca técnicos disponibles para una fecha y hora específicas
     * @param {string} date - Fecha en formato YYYY-MM-DD
     * @param {string} time - Hora en formato HH:MM
     * @param {number} duration - Duración en minutos
     * @returns {Promise} Promesa con la lista de técnicos disponibles
     */
    findAvailableTechnicians: async function(date, time, duration = 60) {
        try {
            const response = await fetch('actions/find_available_technicians.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    date: date,
                    time: time,
                    duration: duration
                })
            });
            
            return await response.json();
        } catch (error) {
            console.error('Error al buscar técnicos disponibles:', error);
            return {
                success: false,
                error: 'Error de conexión al buscar técnicos disponibles'
            };
        }
    },
    
    /**
     * Obtiene la disponibilidad de un técnico para un rango de fechas
     * @param {number} technicianId - ID del técnico
     * @param {string} startDate - Fecha de inicio en formato YYYY-MM-DD
     * @param {string} endDate - Fecha de fin en formato YYYY-MM-DD
     * @returns {Promise} Promesa con los datos de disponibilidad
     */
    getTechnicianAvailability: async function(technicianId, startDate, endDate) {
        try {
            const response = await fetch(`actions/get_technician_availability.php?technician_id=${technicianId}&start_date=${startDate}&end_date=${endDate}`);
            return await response.json();
        } catch (error) {
            console.error('Error al obtener disponibilidad:', error);
            return {
                success: false,
                error: 'Error de conexión al obtener disponibilidad'
            };
        }
    },
    
    /**
     * Formatea la hora para mostrarla en un formato amigable
     * @param {string} time - Hora en formato HH:MM o HH:MM:SS
     * @returns {string} Hora formateada (por ejemplo: "3:30 PM")
     */
    formatTime: function(time) {
        if (!time) return '';
        
        const [hours, minutes] = time.split(':');
        let hour = parseInt(hours);
        const period = hour >= 12 ? 'PM' : 'AM';
        
        // Convertir a formato 12 horas
        if (hour > 12) hour -= 12;
        else if (hour === 0) hour = 12;
        
        return `${hour}:${minutes} ${period}`;
    },
    
    /**
     * Formatea una fecha para mostrarla en un formato amigable
     * @param {string} date - Fecha en formato YYYY-MM-DD
     * @returns {string} Fecha formateada (por ejemplo: "12 de enero de 2023")
     */
    formatDate: function(date) {
        if (!date) return '';
        
        const options = { day: 'numeric', month: 'long', year: 'numeric' };
        return new Date(date).toLocaleDateString('es-ES', options);
    },
    
    /**
     * Aplica estilos según el estado de disponibilidad
     * @param {HTMLElement} element - Elemento al que aplicar los estilos
     * @param {boolean} isAvailable - Si está disponible o no
     */
    applyAvailabilityStyle: function(element, isAvailable) {
        if (isAvailable) {
            element.classList.add('bg-green-100', 'text-green-800');
            element.classList.remove('bg-red-100', 'text-red-800');
        } else {
            element.classList.add('bg-red-100', 'text-red-800');
            element.classList.remove('bg-green-100', 'text-green-800');
        }
    }
};

// Exportar el servicio para su uso en diferentes archivos
window.AvailabilityService = AvailabilityService;