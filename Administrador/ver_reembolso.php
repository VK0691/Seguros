<?php
session_start();
require_once '../conexion.php'; // Asegúrate de que la ruta sea correcta

// Verifica si se ha pasado un ID de reembolso
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: ID de reembolso no proporcionado.");
}

$reembolso_id = intval($_GET['id']);

// Consulta para obtener los detalles del reembolso
$query = "SELECT r.*, u.id AS usuario_id, u.telefono, u.correo, s.nombre AS seguro_nombre, s.cobertura_maxima 
          FROM reembolsos r
          JOIN usuarios u ON r.usuario_id = u.id
          JOIN seguros s ON r.seguro_id = s.id
          WHERE r.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reembolso_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: No se encontró el reembolso con ID " . $reembolso_id);
}

$reembolso = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Reembolso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #002552;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="header text-center">
                <h2>Detalles del Reembolso</h2>
                <h4>Reembolso #<?= htmlspecialchars($reembolso['id']) ?></h4>
            </div>
            <div class="card-body">
                <h5 class="card-title">Información de la Solicitud</h5>
                <p class="card-text"><strong>Usuario:</strong> <?= htmlspecialchars($reembolso['usuario_id']) ?> (<?= htmlspecialchars($reembolso['telefono']) ?>, <?= htmlspecialchars($reembolso['correo']) ?>)</p>
                <p class="card-text"><strong>Plan de Seguro:</strong> <?= htmlspecialchars($reembolso['seguro_nombre']) ?></p>
                <p class="card-text"><strong>Cobertura máxima:</strong> $<?= number_format($reembolso['cobertura_maxima'], 2) ?></p>
                <p class="card-text"><strong>Tipo de Reembolso:</strong> <?= htmlspecialchars($reembolso['tipo_reembolso']) ?></p>
                <p class="card-text"><strong>Monto Solicitado:</strong> $<?= number_format($reembolso['monto_solicitado'], 2) ?></p>
                <p class="card-text"><strong>Estado Actual:</strong> <?= htmlspecialchars($reembolso['estado']) ?></p>
                <p class="card-text"><strong>Fecha Solicitud:</strong> <?= date("d/m/Y H:i", strtotime($reembolso['fecha_solicitud'])) ?></p>
                <p class="card-text"><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($reembolso['descripcion'])) ?></p>
            </div>
            <div class="card-footer text-center">
                <a href="reembolsos.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver a la lista de reembolsos</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
