/**
 * availability_calendar.js - Componente para visualizar la disponibilidad de técnicos en calendario
 * 
 * Este componente permite mostrar la disponibilidad de un técnico en un formato de calendario
 * semanal o mensual, integrando su horario regular, excepciones y visitas programadas.
 */

class AvailabilityCalendar {
    /**
     * Constructor para inicializar el calendario de disponibilidad
     * @param {string} containerId - ID del elemento HTML que contendrá el calendario
     * @param {Object} options - Opciones de configuración
     */
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`Elemento con ID ${containerId} no encontrado`);
            return;
        }
        
        // Opciones por defecto
        this.options = Object.assign({
            mode: 'week',          // 'week' o 'month'
            startDate: new Date(), // Fecha de inicio para la visualización
            onDateClick: null,     // Callback cuando se hace clic en una fecha
            onSlotClick: null,     // Callback cuando se hace clic en un intervalo de hora
            readOnly: true,        // Si es solo lectura o permite interacción
            startHour: 8,          // Hora de inicio del día (para vista semanal)
            endHour: 19,           // Hora de fin del día (para vista semanal)
            showNavigation: true   // Mostrar controles de navegación
        }, options);
        
        // Estado interno
        this.currentDate = new Date(this.options.startDate);
        this.selectedDate = null;
        this.technicianId = null;
        this.technician = null;
        this.availabilityData = null;
        
        // Inicializar estructura
        this.initializeCalendar();
    }
    
    /**
     * Inicializa la estructura del calendario
     */
    initializeCalendar() {
        this.container.innerHTML = '';
        this.container.className = 'availability-calendar bg-white rounded-lg shadow-sm p-4';
        
        // Cabecera con título y controles
        if (this.options.showNavigation) {
            const header = document.createElement('div');
            header.className = 'flex justify-between items-center mb-4';
            
            // Título
            const title = document.createElement('h3');
            title.className = 'text-lg font-semibold';
            title.textContent = 'Calendario de Disponibilidad';
            
            // Controles de navegación
            const controls = document.createElement('div');
            controls.className = 'flex items-center space-x-2';
            
            // Botón anterior
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'p-1 rounded hover:bg-gray-100';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.addEventListener('click', () => this.navigate('prev'));
            
            // Selector de fecha actual
            const dateDisplay = document.createElement('span');
            dateDisplay.id = `${this.container.id}-date-display`;
            dateDisplay.className = 'text-gray-600 px-2';
            
            // Botón siguiente
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'p-1 rounded hover:bg-gray-100';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.addEventListener('click', () => this.navigate('next'));
            
            // Selector de modo
            const modeToggle = document.createElement('button');
            modeToggle.type = 'button';
            modeToggle.className = 'ml-4 px-3 py-1 border rounded text-sm';
            modeToggle.textContent = this.options.mode === 'week' ? 'Ver Mes' : 'Ver Semana';
            modeToggle.addEventListener('click', () => this.toggleMode());
            
            // Agregar controles
            controls.appendChild(prevBtn);
            controls.appendChild(dateDisplay);
            controls.appendChild(nextBtn);
            controls.appendChild(modeToggle);
            
            header.appendChild(title);
            header.appendChild(controls);
            this.container.appendChild(header);
        }
        
        // Contenedor del calendario
        const calendarView = document.createElement('div');
        calendarView.id = `${this.container.id}-calendar-view`;
        calendarView.className = 'calendar-view';
        this.container.appendChild(calendarView);
        
        // Leyenda
        const legend = document.createElement('div');
        legend.className = 'legend flex flex-wrap mt-4 text-sm text-gray-600';
        legend.innerHTML = `
            <div class="flex items-center mr-4 mb-2">
                <div class="w-3 h-3 bg-blue-100 border border-blue-200 rounded mr-1"></div>
                <span>Disponible</span>
            </div>
            <div class="flex items-center mr-4 mb-2">
                <div class="w-3 h-3 bg-red-100 border border-red-200 rounded mr-1"></div>
                <span>No Disponible</span>
            </div>
            <div class="flex items-center mr-4 mb-2">
                <div class="w-3 h-3 bg-green-100 border border-green-200 rounded mr-1"></div>
                <span>Visita Programada</span>
            </div>
        `;
        this.container.appendChild(legend);
    }
    
    /**
     * Carga los datos de disponibilidad para un técnico
     * @param {number} technicianId - ID del técnico
     * @param {Object} options - Opciones adicionales
     */
    loadTechnicianAvailability(technicianId, options = {}) {
        this.technicianId = technicianId;
        
        // Calcular rango de fechas según el modo
        let startDate, endDate;
        if (this.options.mode === 'week') {
            const currentDate = new Date(this.currentDate);
            startDate = new Date(currentDate);
            startDate.setDate(currentDate.getDate() - currentDate.getDay()); // Inicio de la semana (domingo)
            
            endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6); // Fin de la semana (sábado)
        } else {
            const currentDate = new Date(this.currentDate);
            startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            endDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
        }
        
        const formattedStartDate = this.formatDate(startDate);
        const formattedEndDate = this.formatDate(endDate);
        
        // Mostrar carga
        const calendarView = document.getElementById(`${this.container.id}-calendar-view`);
        calendarView.innerHTML = `
            <div class="flex items-center justify-center h-64">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-2"></div>
                    <p class="text-gray-500">Cargando disponibilidad...</p>
                </div>
            </div>
        `;
        
        // Actualizar el display de fecha
        const dateDisplay = document.getElementById(`${this.container.id}-date-display`);
        if (dateDisplay) {
            if (this.options.mode === 'week') {
                dateDisplay.textContent = this.formatDateRange(startDate, endDate);
            } else {
                dateDisplay.textContent = this.formatMonthYear(this.currentDate);
            }
        }
        
        // Cargar datos
        AvailabilityService.getTechnicianAvailability(technicianId, formattedStartDate, formattedEndDate)
            .then(response => {
                if (response.success) {
                    this.availabilityData = response;
                    this.technician = response.technician;
                    
                    // Renderizar calendario según el modo
                    if (this.options.mode === 'week') {
                        this.renderWeekView();
                    } else {
                        this.renderMonthView();
                    }
                } else {
                    calendarView.innerHTML = `
                        <div class="text-center py-8 text-red-600">
                            <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                            <p>${response.error || 'Error al cargar la disponibilidad'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading availability:', error);
                calendarView.innerHTML = `
                    <div class="text-center py-8 text-red-600">
                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                        <p>Error al cargar la disponibilidad</p>
                    </div>
                `;
            });
    }
    
    /**
     * Renderiza la vista semanal del calendario
     */
    renderWeekView() {
        const calendarView = document.getElementById(`${this.container.id}-calendar-view`);
        if (!calendarView) return;
        
        // Preparar datos para la semana
        const startDate = new Date(this.currentDate);
        startDate.setDate(startDate.getDate() - startDate.getDay());
        
        const days = [];
        for (let i = 0; i < 7; i++) {
            const date = new Date(startDate);
            date.setDate(startDate.getDate() + i);
            days.push(date);
        }
        
        // Crear estructura de la tabla
        const table = document.createElement('div');
        table.className = 'grid grid-cols-8 gap-1';
        
        // Encabezados de días
        table.innerHTML = `
            <div class="p-2"></div>
            ${days.map(day => {
                const isToday = this.isToday(day);
                return `
                    <div class="p-2 text-center ${isToday ? 'bg-blue-50 rounded' : ''}">
                        <div class="font-medium">${this.getDayName(day)}</div>
                        <div class="text-sm text-gray-500">${day.getDate()}</div>
                    </div>
                `;
            }).join('')}
        `;
        
        // Filas de horas
        for (let hour = this.options.startHour; hour <= this.options.endHour; hour++) {
            const hourRow = document.createElement('div');
            hourRow.className = 'contents';
            
            // Columna de hora
            const hourCell = document.createElement('div');
            hourCell.className = 'p-2 text-right text-sm text-gray-500';
            hourCell.textContent = this.formatHour(hour);
            hourRow.appendChild(hourCell);
            
            // Celdas para cada día
            for (let i = 0; i < 7; i++) {
                const day = days[i];
                const dateStr = this.formatDate(day);
                const dayOfWeek = day.getDay() + 1; // 1 (lunes) a 7 (domingo) según el formato de PHP
                
                const cell = document.createElement('div');
                cell.className = 'border rounded p-2 h-16 relative group hover:bg-gray-50';
                
                // Verificar disponibilidad para esta celda
                const isAvailable = this.checkAvailability(dayOfWeek, hour, dateStr);
                
                // Verificar visitas programadas
                const visits = this.getVisitsForTimeSlot(dateStr, hour);
                
                // Aplicar estilo según disponibilidad
                if (visits.length > 0) {
                    cell.classList.add('bg-green-50');
                    cell.innerHTML = visits.map(visit => `
                        <div class="text-xs bg-green-100 text-green-800 p-1 rounded mb-1 truncate" 
                             title="${visit.client_name}">
                            ${visit.time} - ${visit.client_name}
                        </div>
                    `).join('');
                } else if (isAvailable) {
                    cell.classList.add('bg-blue-50');
                } else {
                    cell.classList.add('bg-red-50');
                }
                
                // Si hay interacción, agregar evento de clic
                if (!this.options.readOnly && this.options.onSlotClick) {
                    cell.addEventListener('click', () => {
                        const data = {
                            date: dateStr,
                            hour: hour,
                            formattedHour: this.formatHour(hour),
                            isAvailable: isAvailable,
                            visits: visits
                        };
                        this.options.onSlotClick(data);
                    });
                }
                
                hourRow.appendChild(cell);
            }
            
            table.appendChild(hourRow);
        }
        
        calendarView.innerHTML = '';
        calendarView.appendChild(table);
    }
    
    /**
     * Renderiza la vista mensual del calendario
     */
    renderMonthView() {
        const calendarView = document.getElementById(`${this.container.id}-calendar-view`);
        if (!calendarView) return;
        
        // Obtener primer día del mes
        const firstDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
        const lastDay = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0);
        
        // Obtener el día de la semana del primer día (0 = Domingo, 6 = Sábado)
        const firstDayOfWeek = firstDay.getDay();
        
        // Crear array con todos los días que se mostrarán en el calendario
        const days = [];
        
        // Días anteriores del mes (del mes anterior)
        for (let i = firstDayOfWeek; i > 0; i--) {
            const date = new Date(firstDay);
            date.setDate(firstDay.getDate() - i);
            days.push({ date, isCurrentMonth: false });
        }
        
        // Días del mes actual
        for (let i = 1; i <= lastDay.getDate(); i++) {
            const date = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), i);
            days.push({ date, isCurrentMonth: true });
        }
        
        // Completar la última semana si es necesario
        const remainingDays = 7 - (days.length % 7);
        if (remainingDays < 7) {
            for (let i = 1; i <= remainingDays; i++) {
                const date = new Date(lastDay);
                date.setDate(lastDay.getDate() + i);
                days.push({ date, isCurrentMonth: false });
            }
        }
        
        // Crear estructura de la tabla
        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-7 gap-1';
        
        // Encabezados de días
        const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        dayNames.forEach(day => {
            const header = document.createElement('div');
            header.className = 'p-2 text-center font-medium';
            header.textContent = day;
            grid.appendChild(header);
        });
        
        // Celdas de días
        days.forEach(({ date, isCurrentMonth }) => {
            const cell = document.createElement('div');
            cell.className = `p-2 h-28 border rounded relative ${isCurrentMonth ? '' : 'bg-gray-50 text-gray-400'} ${this.isToday(date) ? 'bg-blue-50' : ''}`;
            
            // Fecha del día
            const dateHeader = document.createElement('div');
            dateHeader.className = 'flex justify-between items-start';
            dateHeader.innerHTML = `
                <span class="font-medium ${this.isToday(date) ? 'text-blue-600' : ''}">${date.getDate()}</span>
            `;
            
            // Contenedor para visitas y disponibilidad
            const content = document.createElement('div');
            content.className = 'mt-1 space-y-1 overflow-y-auto max-h-20';
            
            // Verificar disponibilidad para esta fecha
            const dateStr = this.formatDate(date);
            const dayOfWeek = date.getDay() + 1;
            const isAvailable = this.checkDayAvailability(dayOfWeek, dateStr);
            
            // Indicador de disponibilidad
            const availabilityBadge = document.createElement('div');
            if (isAvailable) {
                availabilityBadge.className = 'absolute top-1 right-1 w-2 h-2 bg-blue-500 rounded-full';
            } else {
                availabilityBadge.className = 'absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full';
            }
            
            // Visitas del día
            const visits = this.getVisitsForDay(dateStr);
            if (visits.length > 0) {
                visits.forEach(visit => {
                    const visitDiv = document.createElement('div');
                    visitDiv.className = 'text-xs bg-green-100 text-green-800 p-1 rounded truncate';
                    visitDiv.textContent = `${visit.time} - ${visit.client_name}`;
                    content.appendChild(visitDiv);
                });
            }
            
            // Si hay excepciones, mostrar indicador
            const exception = this.getException(dateStr);
            if (exception) {
                const exceptionDiv = document.createElement('div');
                
                if (exception.is_available) {
                    exceptionDiv.className = 'text-xs bg-blue-100 text-blue-800 p-1 rounded';
                    exceptionDiv.textContent = `Disponible: ${exception.start_time} - ${exception.end_time}`;
                } else {
                    exceptionDiv.className = 'text-xs bg-red-100 text-red-800 p-1 rounded';
                    exceptionDiv.textContent = exception.reason || 'No disponible';
                }
                
                content.appendChild(exceptionDiv);
            }
            
            // Añadir eventos
            if (!this.options.readOnly && this.options.onDateClick && isCurrentMonth) {
                cell.addEventListener('click', () => {
                    this.selectedDate = date;
                    this.options.onDateClick({
                        date: dateStr,
                        isAvailable: isAvailable,
                        visits: visits,
                        exception: exception
                    });
                });
                cell.classList.add('cursor-pointer', 'hover:bg-gray-50');
            }
            
            cell.appendChild(dateHeader);
            cell.appendChild(availabilityBadge);
            cell.appendChild(content);
            grid.appendChild(cell);
        });
        
        calendarView.innerHTML = '';
        calendarView.appendChild(grid);
    }
    
    /**
     * Verifica si un técnico está disponible en un horario específico
     * @param {number} dayOfWeek - Día de la semana (1-7)
     * @param {number} hour - Hora del día
     * @param {string} dateStr - Fecha en formato YYYY-MM-DD
     * @returns {boolean} Si está disponible o no
     */
    checkAvailability(dayOfWeek, hour, dateStr) {
        if (!this.availabilityData) return false;
        
        // Verificar excepciones para la fecha
        const exception = this.getException(dateStr);
        if (exception) {
            if (!exception.is_available) return false;
            
            // Si hay una excepción con horario específico, verificar
            if (exception.start_time && exception.end_time) {
                const exceptionStartHour = parseInt(exception.start_time.split(':')[0]);
                const exceptionEndHour = parseInt(exception.end_time.split(':')[0]);
                return hour >= exceptionStartHour && hour < exceptionEndHour;
            }
        }
        
        // Verificar disponibilidad regular según el día de la semana
        const regularSchedule = this.availabilityData.regular_availability.filter(
            a => a.day_of_week == dayOfWeek
        );
        
        for (const slot of regularSchedule) {
            const startHour = parseInt(slot.start_time.split(':')[0]);
            const endHour = parseInt(slot.end_time.split(':')[0]);
            
            if (hour >= startHour && hour < endHour) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verifica si un técnico está disponible en un día completo
     * @param {number} dayOfWeek - Día de la semana (1-7)
     * @param {string} dateStr - Fecha en formato YYYY-MM-DD
     * @returns {boolean} Si está disponible o no
     */
    checkDayAvailability(dayOfWeek, dateStr) {
        if (!this.availabilityData) return false;
        
        // Verificar excepciones para la fecha
        const exception = this.getException(dateStr);
        if (exception) {
            return exception.is_available;
        }
        
        // Verificar disponibilidad regular según el día de la semana
        const regularSchedule = this.availabilityData.regular_availability.filter(
            a => a.day_of_week == dayOfWeek
        );
        
        return regularSchedule.length > 0;
    }
    
    /**
     * Obtiene las visitas programadas para una hora específica
     * @param {string} dateStr - Fecha en formato YYYY-MM-DD
     * @param {number} hour - Hora del día
     * @returns {Array} Lista de visitas
     */
    getVisitsForTimeSlot(dateStr, hour) {
        if (!this.availabilityData || !this.availabilityData.visits || !this.availabilityData.visits[dateStr]) {
            return [];
        }
        
        return this.availabilityData.visits[dateStr].filter(visit => {
            const visitHour = parseInt(visit.time.split(':')[0]);
            return visitHour === hour;
        });
    }
    
    /**
     * Obtiene las visitas programadas para un día completo
     * @param {string} dateStr - Fecha en formato YYYY-MM-DD
     * @returns {Array} Lista de visitas
     */
    getVisitsForDay(dateStr) {
        if (!this.availabilityData || !this.availabilityData.visits || !this.availabilityData.visits[dateStr]) {
            return [];
        }
        
        return this.availabilityData.visits[dateStr];
    }
    
    /**
     * Obtiene la excepción para una fecha específica
     * @param {string} dateStr - Fecha en formato YYYY-MM-DD
     * @returns {Object|null} Datos de la excepción o null si no hay
     */
    getException(dateStr) {
        if (!this.availabilityData || !this.availabilityData.exceptions || !this.availabilityData.exceptions[dateStr]) {
            return null;
        }
        
        return this.availabilityData.exceptions[dateStr];
    }
    
    /**
     * Navega en el calendario (anterior o siguiente)
     * @param {string} direction - Dirección ('prev' o 'next')
     */
    navigate(direction) {
        if (this.options.mode === 'week') {
            // Navegar por semanas
            const days = direction === 'prev' ? -7 : 7;
            this.currentDate.setDate(this.currentDate.getDate() + days);
        } else {
            // Navegar por meses
            const months = direction === 'prev' ? -1 : 1;
            this.currentDate.setMonth(this.currentDate.getMonth() + months);
        }
        
        // Recargar datos si hay un técnico seleccionado
        if (this.technicianId) {
            this.loadTechnicianAvailability(this.technicianId);
        } else {
            // Solo actualizar la vista
            this.updateDateDisplay();
        }
    }
    
    /**
     * Cambia entre vista semanal y mensual
     */
    toggleMode() {
        this.options.mode = this.options.mode === 'week' ? 'month' : 'week';
        
        // Actualizar botón de modo
        const modeToggle = this.container.querySelector('button:last-child');
        if (modeToggle) {
            modeToggle.textContent = this.options.mode === 'week' ? 'Ver Mes' : 'Ver Semana';
        }
        
        // Recargar datos si hay un técnico seleccionado
        if (this.technicianId) {
            this.loadTechnicianAvailability(this.technicianId);
        }
    }
    
    /**
     * Actualiza el display de fecha
     */
    updateDateDisplay() {
        const dateDisplay = document.getElementById(`${this.container.id}-date-display`);
        if (!dateDisplay) return;
        
        if (this.options.mode === 'week') {
            const startDate = new Date(this.currentDate);
            startDate.setDate(startDate.getDate() - startDate.getDay());
            
            const endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);
            
            dateDisplay.textContent = this.formatDateRange(startDate, endDate);
        } else {
            dateDisplay.textContent = this.formatMonthYear(this.currentDate);
        }
    }
    
    /**
     * Verifica si una fecha es hoy
     * @param {Date} date - Fecha a verificar
     * @returns {boolean} Si es hoy o no
     */
    isToday(date) {
        const today = new Date();
        return date.getDate() === today.getDate() && 
               date.getMonth() === today.getMonth() && 
               date.getFullYear() === today.getFullYear();
    }
    
    /**
     * Formatea una fecha en formato YYYY-MM-DD
     * @param {Date} date - Fecha a formatear
     * @returns {string} Fecha formateada
     */
    formatDate(date) {
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    /**
     * Formatea una hora en formato de 12 horas
     * @param {number} hour - Hora (0-23)
     * @returns {string} Hora formateada (ej: "9:00 AM")
     */
    formatHour(hour) {
        const period = hour >= 12 ? 'PM' : 'AM';
        const h = hour % 12 || 12;
        return `${h}:00 ${period}`;
    }
    
    /**
     * Obtiene el nombre del día de la semana
     * @param {Date} date - Fecha
     * @returns {string} Nombre del día
     */
    getDayName(date) {
        const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        return days[date.getDay()];
    }
    
    /**
     * Formatea un rango de fechas
     * @param {Date} start - Fecha de inicio
     * @param {Date} end - Fecha de fin
     * @returns {string} Rango formateado
     */
    formatDateRange(start, end) {
        const startStr = `${start.getDate()}/${start.getMonth() + 1}`;
        const endStr = `${end.getDate()}/${end.getMonth() + 1}/${end.getFullYear()}`;
        return `${startStr} - ${endStr}`;
    }
    
    /**
     * Formatea mes y año
     * @param {Date} date - Fecha
     * @returns {string} Mes y año formateados
     */
    formatMonthYear(date) {
        const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return `${months[date.getMonth()]} ${date.getFullYear()}`;
    }
}

// Exportar la clase para uso global
window.AvailabilityCalendar = AvailabilityCalendar;