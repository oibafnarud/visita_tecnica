<?php
// admin/export_report.php - Exportación de reportes a Excel
require_once '../includes/session_check.php';
require_once '../config/database.php';

// Verificar si es una solicitud de exportación
if (!isset($_GET['export']) || $_GET['export'] !== 'excel') {
    header('Location: reports.php');
    exit;
}

// Cargar librería PhpSpreadsheet
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Obtener parámetros de filtrado
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$technician_id = isset($_GET['technician_id']) ? intval($_GET['technician_id']) : null;
$service_type = isset($_GET['service_type']) ? $_GET['service_type'] : null;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'general';

$database = new Database();
$db = $database->connect();

// Construir condiciones SQL basadas en filtros
$where_conditions = ["visit_date BETWEEN :start_date AND :end_date"];
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($technician_id) {
    $where_conditions[] = "technician_id = :technician_id";
    $params[':technician_id'] = $technician_id;
    
    // Obtener nombre del técnico para el título del reporte
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = :id");
    $stmt->execute([':id' => $technician_id]);
    $technician_name = $stmt->fetchColumn();
}

if ($service_type) {
    $where_conditions[] = "service_type = :service_type";
    $params[':service_type'] = $service_type;
}

$where_clause = implode(" AND ", $where_conditions);

// Crear una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte de Visitas');

// Aplicar estilo para el encabezado
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '3B82F6'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB'],
        ],
    ],
];

// Estilo para las celdas de datos
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB'],
        ],
    ],
];

// Estilo para las celdas de datos alternados
$altDataStyle = [
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F9FAFB'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB'],
        ],
    ],
];

// Generar contenido según el tipo de reporte
switch ($report_type) {
    case 'by_date':
        // Título del reporte
        $title = "Reporte de Visitas por Fecha";
        if ($technician_id) {
            $title .= " - Técnico: " . $technician_name;
        }
        if ($service_type) {
            $title .= " - Servicio: " . $service_type;
        }
        $title .= " (" . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . ")";
        
        // Configurar encabezados
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', 'Fecha');
        $sheet->setCellValue('B3', 'Total Visitas');
        $sheet->setCellValue('C3', 'Completadas');
        $sheet->setCellValue('D3', 'Pendientes');
        $sheet->setCellValue('E3', 'En Ruta');
        $sheet->setCellValue('F3', '% Completado');
        $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
        
        // Obtener datos
        $stmt = $db->prepare("
            SELECT 
                DATE(visit_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route
            FROM visits 
            WHERE $where_clause
            GROUP BY DATE(visit_date)
            ORDER BY date
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Llenar datos
        $row = 4;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($item['date'])));
            $sheet->setCellValue('B' . $row, $item['total']);
            $sheet->setCellValue('C' . $row, $item['completed']);
            $sheet->setCellValue('D' . $row, $item['pending']);
            $sheet->setCellValue('E' . $row, $item['in_route']);
            
            $completion_rate = $item['total'] > 0 
                ? round(($item['completed'] / $item['total']) * 100, 1) . '%' 
                : '0%';
            $sheet->setCellValue('F' . $row, $completion_rate);
            
            // Aplicar estilo alternante
            $style = ($row % 2 == 0) ? $dataStyle : $altDataStyle;
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($style);
            
            $row++;
        }
        
        // Agregar totales
        $lastRow = $row;
        $sheet->setCellValue('A' . $lastRow, 'TOTAL');
        $sheet->setCellValue('B' . $lastRow, '=SUM(B4:B' . ($lastRow - 1) . ')');
        $sheet->setCellValue('C' . $lastRow, '=SUM(C4:C' . ($lastRow - 1) . ')');
        $sheet->setCellValue('D' . $lastRow, '=SUM(D4:D' . ($lastRow - 1) . ')');
        $sheet->setCellValue('E' . $lastRow, '=SUM(E4:E' . ($lastRow - 1) . ')');
        $sheet->setCellValue('F' . $lastRow, '=IF(B' . $lastRow . '>0,ROUND((C' . $lastRow . '/B' . $lastRow . ')*100,1)&"%",0)');
        
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray($headerStyle);
        
        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        
        break;
        
    case 'by_technician':
        // Título del reporte
        $title = "Reporte de Rendimiento por Técnico";
        if ($service_type) {
            $title .= " - Servicio: " . $service_type;
        }
        $title .= " (" . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . ")";
        
        // Configurar encabezados
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', 'Técnico');
        $sheet->setCellValue('B3', 'Total Visitas');
        $sheet->setCellValue('C3', 'Completadas');
        $sheet->setCellValue('D3', 'Efectividad (%)');
        $sheet->setCellValue('E3', 'Tiempo Promedio');
        $sheet->setCellValue('F3', 'Días Activos');
        $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
        
        // Obtener datos
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.full_name,
                COUNT(v.id) as total_visits,
                SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
                ROUND((SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) / COUNT(v.id)) * 100, 1) as completion_rate,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    CONCAT(v.visit_date, ' ', v.visit_time),
                    v.completion_time
                )) as avg_completion_time,
                COUNT(DISTINCT DATE(v.visit_date)) as active_days
            FROM users u
            JOIN visits v ON u.id = v.technician_id 
                AND " . str_replace('technician_id = :technician_id', 'TRUE', $where_clause) . "
            WHERE u.role = 'technician'
            GROUP BY u.id, u.full_name
            ORDER BY completion_rate DESC, total_visits DESC
        ");
        
        // Eliminar el filtro de técnico específico si existe
        $local_params = $params;
        if ($technician_id) {
            unset($local_params[':technician_id']);
        }
        
        $stmt->execute($local_params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Llenar datos
        $row = 4;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['full_name']);
            $sheet->setCellValue('B' . $row, $item['total_visits']);
            $sheet->setCellValue('C' . $row, $item['completed_visits']);
            $sheet->setCellValue('D' . $row, $item['completion_rate'] . '%');
            
            if ($item['avg_completion_time']) {
                $hours = floor($item['avg_completion_time'] / 60);
                $minutes = round($item['avg_completion_time'] % 60);
                $sheet->setCellValue('E' . $row, $hours . 'h ' . $minutes . 'm');
            } else {
                $sheet->setCellValue('E' . $row, '-');
            }
            
            $sheet->setCellValue('F' . $row, $item['active_days']);
            
            // Aplicar estilo alternante
            $style = ($row % 2 == 0) ? $dataStyle : $altDataStyle;
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($style);
            
            $row++;
        }
        
        // Agregar totales
        $lastRow = $row;
        $sheet->setCellValue('A' . $lastRow, 'TOTAL');
        $sheet->setCellValue('B' . $lastRow, '=SUM(B4:B' . ($lastRow - 1) . ')');
        $sheet->setCellValue('C' . $lastRow, '=SUM(C4:C' . ($lastRow - 1) . ')');
        $sheet->setCellValue('D' . $lastRow, '=IF(B' . $lastRow . '>0,ROUND((C' . $lastRow . '/B' . $lastRow . ')*100,1)&"%",0)');
        
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray($headerStyle);
        
        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        
        break;
        
    case 'by_service':
        // Título del reporte
        $title = "Reporte por Tipo de Servicio";
        if ($technician_id) {
            $title .= " - Técnico: " . $technician_name;
        }
        $title .= " (" . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . ")";
        
        // Configurar encabezados
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', 'Tipo de Servicio');
        $sheet->setCellValue('B3', 'Total Visitas');
        $sheet->setCellValue('C3', 'Técnicos Asignados');
        $sheet->setCellValue('D3', 'Completadas');
        $sheet->setCellValue('E3', 'Efectividad (%)');
        $sheet->setCellValue('F3', 'Tiempo Promedio');
        $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
        
        // Obtener datos
        $stmt = $db->prepare("
            SELECT 
                COALESCE(service_type, 'Sin especificar') as service_type,
                COUNT(*) as total,
                COUNT(DISTINCT technician_id) as technicians_assigned,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as completion_rate,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    CONCAT(visit_date, ' ', visit_time),
                    completion_time
                )) as avg_completion_time
            FROM visits 
            WHERE $where_clause
            GROUP BY service_type
            ORDER BY total DESC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Llenar datos
        $row = 4;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item['service_type']);
            $sheet->setCellValue('B' . $row, $item['total']);
            $sheet->setCellValue('C' . $row, $item['technicians_assigned']);
            $sheet->setCellValue('D' . $row, $item['completed']);
            $sheet->setCellValue('E' . $row, $item['completion_rate'] . '%');
            
            if ($item['avg_completion_time']) {
                $hours = floor($item['avg_completion_time'] / 60);
                $minutes = round($item['avg_completion_time'] % 60);
                $sheet->setCellValue('F' . $row, $hours . 'h ' . $minutes . 'm');
            } else {
                $sheet->setCellValue('F' . $row, '-');
            }
            
            // Aplicar estilo alternante
            $style = ($row % 2 == 0) ? $dataStyle : $altDataStyle;
            $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($style);
            
            $row++;
        }
        
        // Agregar totales
        $lastRow = $row;
        $sheet->setCellValue('A' . $lastRow, 'TOTAL');
        $sheet->setCellValue('B' . $lastRow, '=SUM(B4:B' . ($lastRow - 1) . ')');
        $sheet->setCellValue('C' . $lastRow, '=MAX(C4:C' . ($lastRow - 1) . ')');
        $sheet->setCellValue('D' . $lastRow, '=SUM(D4:D' . ($lastRow - 1) . ')');
        $sheet->setCellValue('E' . $lastRow, '=IF(B' . $lastRow . '>0,ROUND((D' . $lastRow . '/B' . $lastRow . ')*100,1)&"%",0)');
        
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray($headerStyle);
        
        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        
        break;
        
    case 'by_time':
        // Título del reporte
        $title = "Reporte por Hora del Día";
        if ($technician_id) {
            $title .= " - Técnico: " . $technician_name;
        }
        if ($service_type) {
            $title .= " - Servicio: " . $service_type;
        }
        $title .= " (" . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . ")";
        
        // Configurar encabezados
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', 'Hora');
        $sheet->setCellValue('B3', 'Total Visitas');
        $sheet->setCellValue('C3', 'Completadas');
        $sheet->setCellValue('D3', 'Pendientes');
        $sheet->setCellValue('E3', 'En Ruta');
        $sheet->getStyle('A3:E3')->applyFromArray($headerStyle);
        
        // Obtener datos
        $stmt = $db->prepare("
            SELECT 
                HOUR(visit_time) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route
            FROM visits 
            WHERE $where_clause
            GROUP BY HOUR(visit_time)
            ORDER BY hour
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Llenar datos
        $row = 4;
        foreach ($data as $item) {
            $hour_formatted = $item['hour'] > 12 
                ? ($item['hour'] - 12) . ':00 PM' 
                : ($item['hour'] == 0 ? '12:00 AM' : $item['hour'] . ':00 AM');
            
            $sheet->setCellValue('A' . $row, $hour_formatted);
            $sheet->setCellValue('B' . $row, $item['total']);
            $sheet->setCellValue('C' . $row, $item['completed']);
            $sheet->setCellValue('D' . $row, $item['pending']);
            $sheet->setCellValue('E' . $row, $item['in_route']);
            
            // Aplicar estilo alternante
            $style = ($row % 2 == 0) ? $dataStyle : $altDataStyle;
            $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($style);
            
            $row++;
        }
        
        // Agregar totales
        $lastRow = $row;
        $sheet->setCellValue('A' . $lastRow, 'TOTAL');
        $sheet->setCellValue('B' . $lastRow, '=SUM(B4:B' . ($lastRow - 1) . ')');
        $sheet->setCellValue('C' . $lastRow, '=SUM(C4:C' . ($lastRow - 1) . ')');
        $sheet->setCellValue('D' . $lastRow, '=SUM(D4:D' . ($lastRow - 1) . ')');
        $sheet->setCellValue('E' . $lastRow, '=SUM(E4:E' . ($lastRow - 1) . ')');
        
        $sheet->getStyle('A' . $lastRow . ':E' . $lastRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $lastRow . ':E' . $lastRow)->applyFromArray($headerStyle);
        
        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        
        break;
        
    default: // general
        // Título del reporte
        $title = "Reporte General de Visitas";
        if ($technician_id) {
            $title .= " - Técnico: " . $technician_name;
        }
        if ($service_type) {
            $title .= " - Servicio: " . $service_type;
        }
        $title .= " (" . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . ")";
        
        // Configurar encabezados de la primera hoja (Resumen)
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Estadísticas generales
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_visits,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_visits,
                SUM(CASE WHEN status = 'in_route' THEN 1 ELSE 0 END) as in_route_visits,
                AVG(TIMESTAMPDIFF(MINUTE, 
                    CONCAT(visit_date, ' ', visit_time),
                    CASE WHEN completion_time IS NOT NULL 
                        THEN completion_time 
                        ELSE NOW() 
                    END
                )) as avg_completion_time,
                COUNT(DISTINCT technician_id) as active_technicians,
                COUNT(DISTINCT DATE(visit_date)) as working_days
            FROM visits 
            WHERE $where_clause
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sheet->setCellValue('A3', 'Métrica');
        $sheet->setCellValue('B3', 'Valor');
        $sheet->getStyle('A3:B3')->applyFromArray($headerStyle);
        
        $row = 4;
        $sheet->setCellValue('A' . $row, 'Total Visitas');
        $sheet->setCellValue('B' . $row, $stats['total_visits']);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Visitas Completadas');
        $sheet->setCellValue('B' . $row, $stats['completed_visits']);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Visitas Pendientes');
        $sheet->setCellValue('B' . $row, $stats['pending_visits']);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Visitas En Ruta');
        $sheet->setCellValue('B' . $row, $stats['in_route_visits']);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Efectividad');
        $completion_rate = $stats['total_visits'] > 0 
            ? round(($stats['completed_visits'] / $stats['total_visits']) * 100, 1) . '%' 
            : '0%';
        $sheet->setCellValue('B' . $row, $completion_rate);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Tiempo Promedio');
        $avg_hours = floor($stats['avg_completion_time'] / 60);
        $avg_minutes = round($stats['avg_completion_time'] % 60);
        $sheet->setCellValue('B' . $row, $avg_hours . 'h ' . $avg_minutes . 'm');
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Técnicos Activos');
        $sheet->setCellValue('B' . $row, $stats['active_technicians']);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Días Trabajados');
        $sheet->setCellValue('B' . $row, $stats['working_days']);
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Visitas por Día');
        $sheet->setCellValue('B' . $row, round($stats['total_visits'] / max(1, $stats['working_days']), 1));
        $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray(($row % 2 == 0) ? $dataStyle : $altDataStyle);
        
        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        
        // Crear segunda hoja con detalle de visitas
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Detalle de Visitas');
        
        // Configurar encabezados
        $detailSheet->setCellValue('A1', 'Detalle de Visitas - ' . $title);
        $detailSheet->mergeCells('A1:I1');
        $detailSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $detailSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $detailSheet->setCellValue('A3', 'Fecha');
        $detailSheet->setCellValue('B3', 'Hora');
        $detailSheet->setCellValue('C3', 'Cliente');
        $detailSheet->setCellValue('D3', 'Dirección');
        $detailSheet->setCellValue('E3', 'Servicio');
        $detailSheet->setCellValue('F3', 'Técnico');
        $detailSheet->setCellValue('G3', 'Estado');
        $detailSheet->setCellValue('H3', 'Hora Completado');
        $detailSheet->setCellValue('I3', 'Duración (min)');
        $detailSheet->getStyle('A3:I3')->applyFromArray($headerStyle);
        
        // Obtener datos detallados de visitas
        $stmt = $db->prepare("
            SELECT 
                v.visit_date,
                v.visit_time,
                v.client_name,
                v.address,
                v.service_type,
                u.full_name as technician_name,
                v.status,
                v.completion_time,
                CASE 
                    WHEN v.completion_time IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, CONCAT(v.visit_date, ' ', v.visit_time), v.completion_time)
                    ELSE NULL
                END as duration
            FROM visits v
            LEFT JOIN users u ON v.technician_id = u.id
            WHERE $where_clause
            ORDER BY v.visit_date, v.visit_time
        ");
        $stmt->execute($params);
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Llenar datos
        $row = 4;
        foreach ($visits as $visit) {
            $detailSheet->setCellValue('A' . $row, date('d/m/Y', strtotime($visit['visit_date'])));
            $detailSheet->setCellValue('B' . $row, date('H:i', strtotime($visit['visit_time'])));
            $detailSheet->setCellValue('C' . $row, $visit['client_name']);
            $detailSheet->setCellValue('D' . $row, $visit['address']);
            $detailSheet->setCellValue('E' . $row, $visit['service_type'] ?: 'Sin especificar');
            $detailSheet->setCellValue('F' . $row, $visit['technician_name']);
            
            // Formatear estado
            $status = 'Pendiente';
            if ($visit['status'] == 'completed') {
                $status = 'Completada';
            } elseif ($visit['status'] == 'in_route') {
                $status = 'En Ruta';
            }
            $detailSheet->setCellValue('G' . $row, $status);
            
            // Hora de completado
            if ($visit['completion_time']) {
                $detailSheet->setCellValue('H' . $row, date('d/m/Y H:i', strtotime($visit['completion_time'])));
            } else {
                $detailSheet->setCellValue('H' . $row, '-');
            }
            
            // Duración
            $detailSheet->setCellValue('I' . $row, $visit['duration'] ?: '-');
            
            // Aplicar estilo alternante
            $style = ($row % 2 == 0) ? $dataStyle : $altDataStyle;
            $detailSheet->getStyle('A' . $row . ':I' . $row)->applyFromArray($style);
            
            $row++;
        }
        
        // Ajustar ancho de columnas
        $detailSheet->getColumnDimension('A')->setWidth(12);
        $detailSheet->getColumnDimension('B')->setWidth(10);
        $detailSheet->getColumnDimension('C')->setWidth(25);
        $detailSheet->getColumnDimension('D')->setWidth(30);
        $detailSheet->getColumnDimension('E')->setWidth(20);
        $detailSheet->getColumnDimension('F')->setWidth(20);
        $detailSheet->getColumnDimension('G')->setWidth(12);
        $detailSheet->getColumnDimension('H')->setWidth(16);
        $detailSheet->getColumnDimension('I')->setWidth(12);
        
        break;
}

// Configurar la hoja activa como la primera
$spreadsheet->setActiveSheetIndex(0);

// Configurar encabezados para la descarga
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="reporte_visitas_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;