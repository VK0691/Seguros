<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require('../fpdf/fpdf.php');
require_once '../conexion.php';

$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;
if ($usuario_id <= 0) exit('ID inválido');

// Obtener la última solicitud aprobada
$query = "SELECT sv.*, u.usuario, u.correo, u.telefono, u.direccion AS direccion_usuario 
          FROM seguros_vida sv
          JOIN usuarios u ON sv.usuario_id = u.id
          WHERE sv.usuario_id = ? AND sv.estado = 'Aprobado'
          ORDER BY sv.fecha_solicitud DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) exit('No se encontró la solicitud.');

$solicitud = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Buscar la firma más reciente
$firma_path = glob("../firmas/firma_canvas_{$usuario_id}_*.png");
$firma_img = null;
if ($firma_path && count($firma_path) > 0) {
    usort($firma_path, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $firma_img = $firma_path[0];
}

// Crear el PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'CONTRATO DE SEGURO DE SALUD', 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', 'I', 12);
$pdf->Cell(0, 10, 'Entre:', 0, 1);
$pdf->Cell(0, 10, 'Aseguradora: SaludVida S.A.', 0, 1);
$pdf->Cell(0, 10, 'Dirección: Av. República 1234, Quito, Ecuador', 0, 1);
$pdf->Cell(0, 10, 'RUC: 1799999999001', 0, 1);
$pdf->Ln(10);

$pdf->Cell(0, 10, 'Y', 0, 1);
$pdf->Cell(0, 10, 'Asegurado: ' . $solicitud['usuario'], 0, 1);
$pdf->Cell(0, 10, 'Cédula: ' . $solicitud['cedula'], 0, 1);
$pdf->Cell(0, 10, 'Dirección: ' . $solicitud['direccion_usuario'], 0, 1);
$pdf->Ln(10);

$pdf->Cell(0, 10, 'CONSIDERANDO QUE:', 0, 1);
$pdf->MultiCell(0, 10, 'La aseguradora SaludVida S.A. acuerda otorgar cobertura de salud al asegurado, conforme a las condiciones establecidas en este contrato.');

$pdf->Ln(5);
$pdf->Cell(0, 10, '1. COBERTURA', 0, 1);
$pdf->MultiCell(0, 10, 'Este seguro cubre los siguientes servicios médicos, de acuerdo con los términos de la póliza:
- Hospitalización
- Consultas médicas generales y especializadas
- Exámenes de laboratorio y diagnóstico
- Cirugías programadas y de emergencia
- Medicamentos recetados durante hospitalización');

$pdf->Ln(5);
$pdf->Cell(0, 10, '2. EXCLUSIONES', 0, 1);
$pdf->MultiCell(0, 10, 'Quedan expresamente excluidos:
- Tratamientos estéticos o cosméticos
- Enfermedades preexistentes no declaradas
- Daños autoinfligidos intencionalmente
- Tratamientos experimentales o no aprobados');

$pdf->Ln(5);
$pdf->Cell(0, 10, '3. OBLIGACIONES DEL ASEGURADO', 0, 1);
$pdf->MultiCell(0, 10, '- Declarar verazmente su estado de salud
- Pagar las primas conforme al cronograma establecido
- Notificar a la aseguradora sobre eventos cubiertos en un plazo máximo de 10 días hábiles');

$pdf->Ln(5);
$pdf->Cell(0, 10, '4. DURACIÓN Y TERMINACIÓN', 0, 1);
$pdf->MultiCell(0, 10, 'El presente contrato tiene una duración de 12 meses a partir de la fecha de firma. Puede renovarse automáticamente salvo notificación escrita de cancelación con al menos 30 días de antelación.');

$pdf->Ln(20);
$pdf->Cell(0, 10, 'Firmado en Quito, a ' . date('d/m/Y'), 0, 1);
$pdf->Ln(10);
$pdf->Cell(0, 10, '__________________________', 0, 1);

// Inserta la firma si existe
if ($firma_img) {
    $pdf->Image($firma_img, $pdf->GetX() + 60, $pdf->GetY() - 15, 60, 30);
    $pdf->Ln(25);
}

$pdf->Cell(0, 10, $solicitud['usuario'], 0, 1);
$pdf->Cell(0, 10, 'Asegurado', 0, 1);
$pdf->Ln(10);
$pdf->Cell(0, 10, '__________________________', 0, 1);
$pdf->Cell(0, 10, 'SaludVida S.A.', 0, 1);
$pdf->Cell(0, 10, 'Representante Legal', 0, 1);

// Guardar el PDF firmado
$nombre_pdf_firmado = '../pdf/contrato_seguro_firmado_' . $usuario_id . '_' . time() . '.pdf';
$pdf->Output('F', $nombre_pdf_firmado);

// Redirigir al PDF generado para verlo en el navegador
header("Location: /Seguros/pdf/" . basename($nombre_pdf_firmado));
exit();
?>
