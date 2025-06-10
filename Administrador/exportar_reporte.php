<?php
session_start();
require_once '../conexion.php';

// Verificar permisos
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Administrador') {
    header("Location: ../login.php");
    exit();
}

// Procesar filtros (similar a reportes.php)
$filtros = [
    'tipo' => isset($_GET['tipo']) ? $conn->real_escape_string($_GET['tipo']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? $conn->real_escape_string($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? $conn->real_escape_string($_GET['fecha_hasta']) : ''
];

// Construir consulta (similar a reportes.php)
// ...

$tipo_export = $_GET['tipo'] ?? 'excel';

if ($tipo_export == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="reporte_seguros.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>Tipo de Seguro</th><th>Total Contratos</th><th>Suma Asegurada</th></tr>";
    
    foreach ($reporte as $fila) {
        echo "<tr>";
        echo "<td>".htmlspecialchars($fila['tipo_seguro'])."</td>";
        echo "<td>".$fila['total']."</td>";
        echo "<td>$".number_format($fila['suma_asegurada'], 2)."</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} elseif ($tipo_export == 'pdf') {
    require_once __DIR__ . '/tcpdf/tcpdf.php';
    if (!class_exists('TCPDF')) {
        die('TCPDF library not found. Please make sure tcpdf is installed and the path is correct.');
    }
    $pdf->SetCreator('Sistema de Seguros');
    $pdf->SetTitle('Reporte de Seguros');
    $pdf->AddPage();
    
    // Contenido del PDF
    $html = '<h1>Reporte de Seguros</h1>';
    $html .= '<table border="1" cellpadding="4">';
    $html .= '<tr><th>Tipo de Seguro</th><th>Total Contratos</th><th>Suma Asegurada</th></tr>';
    
    foreach ($reporte as $fila) {
        $html .= '<tr>';
        $html .= '<td>'.htmlspecialchars($fila['tipo_seguro']).'</td>';
        $html .= '<td>'.$fila['total'].'</td>';
        $html .= '<td>$'.number_format($fila['suma_asegurada'], 2).'</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('reporte_seguros.pdf', 'D');
}
exit();
?>