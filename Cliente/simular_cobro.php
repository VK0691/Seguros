<?php
require_once '../conexion.php';

// Obtener el ID de la solicitud
$reembolso_id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Solo validar que los campos estén llenos
    $usuario_id = $_POST['usuario_id'];
    $monto = $_POST['monto'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $tarjeta = trim($_POST['tarjeta']);
    $cvv = trim($_POST['cvv']);
    $expiracion = trim($_POST['expiracion']);

    if ($tarjeta === '' || $cvv === '' || $expiracion === '') {
        $error = "Todos los campos son obligatorios.";
    } else {
        // Actualizar el estado de pago en la base de datos
        $update = $conn->prepare("UPDATE seguros_vida SET estado_pago = 'Pagado' WHERE id = ?");
        $update->bind_param("i", $reembolso_id);
        $update->execute();
        $update->close();

        header("Location: notificaciones.php?mensaje=" . urlencode("Cobro simulado exitosamente."));
        exit();
    }
} else {
    // Mostrar el formulario
    $query = "SELECT sv.*, u.id as usuario_id 
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
    $usuario_id = $solicitud['usuario_id'];
    $monto = $solicitud['monto_asegurado'];
    $fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Simular Cobro Automático</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Simular Cobro Automático</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario_id) ?>">
                        <input type="hidden" name="monto" value="<?= htmlspecialchars($monto) ?>">
                        <input type="hidden" name="fecha_vencimiento" value="<?= htmlspecialchars($fecha_vencimiento) ?>">
                        <div class="mb-3">
                            <label class="form-label">Monto a cobrar</label>
                            <input type="text" class="form-control" value="$<?= number_format($monto, 2) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Número de Tarjeta</label>
                            <input type="text" name="tarjeta" class="form-control" maxlength="19" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CVV</label>
                            <input type="text" name="cvv" class="form-control" maxlength="4" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha de Expiración (MM/AA)</label>
                            <input type="text" name="expiracion" class="form-control" maxlength="5" placeholder="MM/AA" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Simular Cobro</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
