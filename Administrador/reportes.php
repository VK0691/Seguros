<?php
session_start();
if (
    !isset($_SESSION['usuario']) ||
    !isset($_SESSION['rol']) ||
    ($_SESSION['rol'] !== 'Administrador' && $_SESSION['rol'] !== 'Agente')
) {
    header("Location: ../login.php");
    exit();
}
require_once '../conexion.php';

// Reporte: Seguros impagos
$impagos = $conn->query("SELECT sv.*, u.usuario FROM seguros_vida sv JOIN usuarios u ON sv.usuario_id = u.id WHERE sv.estado = 'Aprobado' AND (sv.estado_pago IS NULL OR sv.estado_pago != 'Pagado')");

// Reporte: Contratos por cliente
$contratos_cliente = $conn->query("SELECT u.usuario, COUNT(sv.id) as total_contratos FROM usuarios u LEFT JOIN seguros_vida sv ON u.id = sv.usuario_id GROUP BY u.id");

// Reporte: Solicitudes pendientes de revisión
$pendientes = $conn->query("SELECT sv.*, u.usuario FROM seguros_vida sv JOIN usuarios u ON sv.usuario_id = u.id WHERE sv.estado = 'En espera de aprobación'");

// Reporte: Contratos vencidos o por vencer (vencen en los próximos 30 días)
$hoy = date('Y-m-d');
$en_30 = date('Y-m-d', strtotime('+30 days'));
$vencidos = $conn->query("SELECT sv.*, u.usuario FROM seguros_vida sv JOIN usuarios u ON sv.usuario_id = u.id WHERE sv.fecha_vencimiento <= '$en_30'");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes - Panel Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-chart-bar"></i> Reportes</h2>

    <div class="mb-5">
        <h4><i class="fas fa-exclamation-triangle text-danger"></i> Seguros Impagos</h4>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Cliente</th><th>Seguro</th><th>Estado</th></tr></thead>
            <tbody>
            <?php while($row = $impagos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['usuario']) ?></td>
                    <td><?= htmlspecialchars($row['tipo_seguro'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['estado']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mb-5">
        <h4><i class="fas fa-users"></i> Contratos por Cliente</h4>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Cliente</th><th>Total Contratos</th></tr></thead>
            <tbody>
            <?php while($row = $contratos_cliente->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['usuario']) ?></td>
                    <td><?= htmlspecialchars($row['total_contratos']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mb-5">
        <h4><i class="fas fa-hourglass-half text-warning"></i> Solicitudes Pendientes de Revisión</h4>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Cliente</th><th>Seguro</th><th>Estado</th></tr></thead>
            <tbody>
            <?php while($row = $pendientes->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['usuario']) ?></td>
                    <td><?= htmlspecialchars($row['tipo_seguro'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['estado']) ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mb-5">
        <h4><i class="fas fa-calendar-times text-secondary"></i> Contratos Vencidos o por Vencer (próximos 30 días)</h4>
        <table class="table table-bordered table-sm">
            <thead><tr><th>Cliente</th><th>Seguro</th><th>Fecha Vencimiento</th></tr></thead>
            <tbody>
            <?php while($row = $vencidos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['usuario']) ?></td>
                    <td><?= htmlspecialchars($row['tipo_seguro'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['fecha_vencimiento'] ?? '-') ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

<?php
// Redirige según el rol del usuario usando la sesión
if ($_SESSION['rol'] === 'Administrador') {
    $panel_url = '../Administrador/adminpanel.php';
} elseif ($_SESSION['rol'] === 'Agente') {
    $panel_url = '../Agente/panel_agente.php';
} else {
    $panel_url = '../login.php'; // Por si acaso, para otros roles no permitidos
}
?>
<div class="text-center mt-4">
    <a href="<?= $panel_url ?>" class="btn btn-dark">
        <i class="fas fa-arrow-left"></i> Regresar al Panel
    </a>
</div></div>
</body>
</html>