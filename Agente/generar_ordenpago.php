<?php
session_start();
require('../fpdf/fpdf.php');
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

// Función para convertir a ISO-8859-1
function iso($txt) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $txt);
}

// Crear el PDF de la orden de pago
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, iso('ORDEN DE PAGO'), 0, 1, 'C'); // Título
$pdf->Ln(10);

// Información del usuario
$pdf->SetFont('Arial', 'I', 12);
$pdf->Cell(0, 10, iso('Cliente: ' . $solicitud['usuario']), 0, 1);
$pdf->Cell(0, 10, iso('Cédula: ' . $solicitud['cedula']), 0, 1);
$pdf->Cell(0, 10, iso('Teléfono: ' . $solicitud['telefono']), 0, 1);
$pdf->Cell(0, 10, iso('Correo: ' . $solicitud['correo']), 0, 1);
$pdf->Cell(0, 10, iso('Dirección: ' . $solicitud['direccion_usuario']), 0, 1);
$pdf->Ln(10);

// Detalles de la orden de pago
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, iso('Detalles de la Orden de Pago'), 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, iso('Monto a Pagar: $' . number_format($solicitud['monto_asegurado'], 2)), 0, 1);
$pdf->Cell(0, 10, iso('Fecha de Vencimiento: ' . date('d/m/Y', strtotime('+30 days'))), 0, 1);
$pdf->Ln(10);

// Instrucciones de pago
$pdf->Cell(0, 10, iso('Instrucciones de Pago:'), 0, 1);
$pdf->MultiCell(0, 10, iso('Por favor, realice el pago a la cuenta bancaria proporcionada. Recuerde que el pago debe realizarse antes de la fecha de vencimiento para evitar cargos adicionales'));

// Guardar el PDF
$nombre_pdf = '../pdf/orden_pago_' . $solicitud['usuario_id'] . '_' . time() . '.pdf';
$pdf->Output('F', $nombre_pdf);

// Obtener el rol del usuario logueado
$usuario_id = $_SESSION['id'];
$stmt_rol = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
$stmt_rol->bind_param("i", $usuario_id);
$stmt_rol->execute();
$result_rol = $stmt_rol->get_result();
$usuario = $result_rol->fetch_assoc();
$stmt_rol->close();

$conn->close(); // <-- CIERRA LA CONEXIÓN AQUÍ, DESPUÉS DE TODAS LAS CONSULTAS

if ($usuario['rol'] === 'Administrador') {
    $redirect = "../Administrador/adminpanel.php?mensaje=" . urlencode("Orden de pago generada con éxito.") . "&pdf=" . urlencode(basename($nombre_pdf));
} else {
    $redirect = "panel_agente.php?mensaje=" . urlencode("Orden de pago generada con éxito.") . "&pdf=" . urlencode(basename($nombre_pdf));
}
header("Location: $redirect");
exit();
?>
