<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once '../fpdf/fpdf.php';
require_once '../conexion.php';

// Obtener el ID de la solicitud
$reembolso_id = $_GET['id'];

// Consulta para obtener los detalles del reembolso
$query = "SELECT sv.*, u.usuario, u.correo, u.telefono, u.direccion AS direccion_usuario 
          FROM seguros_vida sv
          JOIN usuarios u ON sv.usuario_id = u.id
          WHERE sv.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reembolso_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: No se encontró la solicitud con ID " . $reembolso_id);
}

$solicitud = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Crear el PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, utf8_decode('CONTRATO DE SEGURO DE SALUD'), 0, 1, 'C'); // Título
$pdf->Ln(10);

// Información de la aseguradora
$pdf->SetFont('Arial', 'I', 12);
$pdf->Cell(0, 10, utf8_decode('Entre:'), 0, 1);
$pdf->Cell(0, 10, utf8_decode('Aseguradora: SaludVida S.A.'), 0, 1);
$pdf->Cell(0, 10, utf8_decode('Dirección: Av. República 1234, Quito, Ecuador'), 0, 1);
$pdf->Cell(0, 10, 'RUC: 1799999999001', 0, 1);
$pdf->Ln(10);

// Información del asegurado
$pdf->Cell(0, 10, utf8_decode('Y'), 0, 1);
$pdf->Cell(0, 10, utf8_decode('Asegurado: ') . utf8_decode($solicitud['usuario']), 0, 1);
$pdf->Cell(0, 10, utf8_decode('Cédula: ') . utf8_decode($solicitud['cedula']), 0, 1);
$pdf->Cell(0, 10, utf8_decode('Dirección: ') . utf8_decode($solicitud['direccion_usuario']), 0, 1);
$pdf->Ln(10);

// Considerando que
$pdf->Cell(0, 10, 'CONSIDERANDO QUE:', 0, 1);
$pdf->MultiCell(0, 10, utf8_decode('La aseguradora SaludVida S.A. acuerda otorgar cobertura de salud al asegurado, conforme a las condiciones establecidas en este contrato.'));

// Cobertura
$pdf->Ln(5);
$pdf->Cell(0, 10, '1. COBERTURA', 0, 1);
$pdf->MultiCell(0, 10, utf8_decode('Este seguro cubre los siguientes servicios médicos, de acuerdo con los términos de la póliza:
- Hospitalización
- Consultas médicas generales y especializadas
- Exámenes de laboratorio y diagnóstico
- Cirugías programadas y de emergencia
- Medicamentos recetados durante hospitalización'));

// Exclusiones
$pdf->Ln(5);
$pdf->Cell(0, 10, '2. EXCLUSIONES', 0, 1);
$pdf->MultiCell(0, 10, utf8_decode('Quedan expresamente excluidos:
- Tratamientos estéticos o cosméticos
- Enfermedades preexistentes no declaradas
- Daños autoinfligidos intencionalmente
- Tratamientos experimentales o no aprobados'));

// Obligaciones del Asegurado
$pdf->Ln(5);
$pdf->Cell(0, 10, '3. OBLIGACIONES DEL ASEGURADO', 0, 1);
$pdf->MultiCell(0, 10, utf8_decode('- Declarar verazmente su estado de salud
- Pagar las primas conforme al cronograma establecido
- Notificar a la aseguradora sobre eventos cubiertos en un plazo máximo de 10 días hábiles'));

// Duración y Terminación
$pdf->Ln(5);
$pdf->Cell(0, 10, utf8_decode('4. DURACIÓN Y TERMINACIÓN'), 0, 1);
$pdf->MultiCell(0, 10, utf8_decode('El presente contrato tiene una duración de 12 meses a partir de la fecha de firma. Puede renovarse automáticamente salvo notificación escrita de cancelación con al menos 30 días de antelación.'));

// Firmas
$pdf->Ln(20);
$pdf->Cell(0, 10, 'Firmado en Quito, a ' . date('d/m/Y'), 0, 1);
$pdf->Ln(10);

// Línea para la firma del asegurado
$pdf->Cell(0, 10, '__________________________', 0, 1);

// Si existe firma del cliente, la inserta en el PDF
$firma_path = glob("../firmas/firma_canvas_{$solicitud['usuario_id']}*.png");
if ($firma_path && file_exists($firma_path[0])) {
    // Ajusta la posición y tamaño según tu diseño
    $pdf->Image($firma_path[0], $pdf->GetX() + 60, $pdf->GetY() - 15, 60, 30);
    $pdf->Ln(25);
}

$pdf->Cell(0, 10, utf8_decode($solicitud['usuario']), 0, 1);
$pdf->Cell(0, 10, 'Asegurado', 0, 1);
$pdf->Ln(10);
$pdf->Cell(0, 10, '__________________________', 0, 1);
$pdf->Cell(0, 10, 'SaludVida S.A.', 0, 1);
$pdf->Cell(0, 10, 'Representante Legal', 0, 1);

// Guardar el PDF
$nombre_pdf = '../pdf/contrato_seguro_' . $solicitud['usuario_id'] . '_' . time() . '.pdf';
$pdf->Output('F', $nombre_pdf);

// Redirigir o mostrar mensaje de éxito
$_SESSION['mensaje'] = "Contrato generado con éxito. <a href='$nombre_pdf' target='_blank'>Ver Contrato</a>";
header("Location: /Seguros/Agente/panel_agente.php");
exit();
?>
