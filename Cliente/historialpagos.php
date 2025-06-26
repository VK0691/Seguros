
<?php
session_start();
require_once '../conexion.php';

// Verifica que el usuario esté logueado y sea cliente
if (!isset($_SESSION['id']) || $_SESSION['rol'] !== 'Cliente') {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['id'];

// Consulta los pagos realizados por el cliente
$query = "SELECT tipo_seguro, monto_asegurado, estado_pago, fecha_solicitud, fecha_vencimiento
          FROM seguros_vida
          WHERE usuario_id = ? AND estado_pago = 'Pagado'
          ORDER BY fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Pagos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-history"></i> Historial de Pagos</h2>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Tipo de Seguro</th>
                <th>Monto Pagado</th>
                <th>Fecha de Solicitud</th>
                <th>Fecha de Vencimiento</th>
                <th>Estado de Pago</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['tipo_seguro'] ?? '-') ?></td>
                <td>$<?= number_format($row['monto_asegurado'], 2) ?></td>
                <td><?= htmlspecialchars($row['fecha_solicitud'] ?? '-') ?></td>
                <td><?= htmlspecialchars($row['fecha_vencimiento'] ?? '-') ?></td>
                <td><span class="badge bg-success"><?= htmlspecialchars($row['estado_pago']) ?></span></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <a href="clientedash.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
</div>
<!-- FontAwesome para el icono -->
<script src="https://kit.fontawesome.com/1c2e3c3b2e.js" crossorigin="anonymous"></script>
</body>
</html>