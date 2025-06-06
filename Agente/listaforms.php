<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

include '../conexion.php';

$correo = $_SESSION['usuario'];
$sql = "SELECT * FROM usuarios WHERE correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!in_array($usuario['rol'], ['Administrador', 'Agente'])) {
    echo "<p>No tienes permisos para acceder a esta página.</p>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitud_id']) && isset($_POST['accion'])) {
    $id = $_POST['solicitud_id'];

if ($_POST['accion'] === 'aprobar' && !empty($_POST['monto_asegurado'])) {
    $estado = 'Aprobado';
    $numero_poliza = 'POL-' . date('Ymd') . '-' . rand(100, 999);
    $estado_poliza = 'Activa';
    $tipo_pago = 'Mensual';
    $monto_asegurado = floatval($_POST['monto_asegurado']);

    // ✅ AÑADIMOS fecha_solicitud = CURDATE()
    $sql_update = "UPDATE seguros_vida 
                   SET estado = ?, 
                       numero_poliza = ?, 
                       estado_poliza = ?, 
                       tipo_pago = ?, 
                       monto_asegurado = ?, 
                       fecha_solicitud = CURDATE() 
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssssdi", $estado, $numero_poliza, $estado_poliza, $tipo_pago, $monto_asegurado, $id);
} else {
        $estado = 'Rechazado';
        $sql_update = "UPDATE seguros_vida SET estado = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("si", $estado, $id);
    }

    $stmt_update->execute();
}

$sql_solicitudes = "SELECT sv.*, u.usuario FROM seguros_vida sv INNER JOIN usuarios u ON sv.usuario_id = u.id ORDER BY sv.fecha_solicitud DESC";
$result_solicitudes = $conn->query($sql_solicitudes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Gestión de Solicitudes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #002147, #ffffff);
            color: #000;
        }
        h2 {
            color: #002147;
        }
        .table-dark {
            background-color: #002147;
        }
        .table-dark th {
            color: #FFD700;
        }
        .btn-primary {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #000;
        }
        .btn-success {
            background-color: #002147;
            border-color: #002147;
        }
        .btn-danger {
            background-color: #000;
            border-color: #000;
        }
        @media (max-width: 768px) {
            h2 {
                font-size: 1.5rem;
            }
            .table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4 text-center">Gestión de Solicitudes de Seguro de Vida</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Fecha Aprobado</th>
                    <th>Tipo Seguro</th>
                    <th>Estado</th>
                    <th>Nro. Póliza</th>
                    <th>Estado Póliza</th>
                    <th>Frecuencia de Pago</th>
                    <th>Forma de Pago</th>

                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result_solicitudes->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['usuario']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_solicitud']) ?></td>
                        <td><?= htmlspecialchars($row['tipo_seguro'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['estado']) ?></td>
                        <td><?= htmlspecialchars($row['numero_poliza'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['estado_poliza'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['tipo_pago'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['forma_pago'] ?? '-') ?></td>

                        <td>
    <?php
    // Ver PDF
    $pdfPath = glob("../pdf/solicitud_seguro_{$row['usuario_id']}*.pdf");
    if ($pdfPath && file_exists($pdfPath[0])) {
        echo '<a href="' . $pdfPath[0] . '" target="_blank" class="btn btn-sm btn-primary mb-1">Ver Solicitud</a><br>';
    } else {
        echo '<span class="text-muted">PDF no disponible</span><br>';
    }

    // Documentos de identificación (cédula escaneada)
    // Documentos de identificación (cédula escaneada)
if (!empty($row['cedula_escan_nombre'])) {
    $docsCedula = explode(', ', $row['cedula_escan_nombre']);
    foreach ($docsCedula as $archivo) {
        $ruta = '../archivos/' . $archivo;
        if (file_exists($ruta)) {
            echo '<a href="' . $ruta . '" target="_blank" class="btn btn-sm btn-secondary mb-1">Ver Documentos Personales </a><br>';
        }
    }
}

// Documentos adicionales
if (!empty($row['documentos_adicionales_nombre'])) {
    $docsAdic = explode(', ', $row['documentos_adicionales_nombre']);
$contadorAdic = 1;
foreach ($docsAdic as $archivo) {
    $ruta = '../archivos/' . $archivo;
    if (file_exists($ruta)) {
        echo '<a href="' . $ruta . '" target="_blank" class="btn btn-sm btn-warning mb-1">Ver Archivo Adicional ' . $contadorAdic . '</a><br>';
        $contadorAdic++;
    }
}

}

    ?>

    <?php if ($row['estado'] === 'En espera de aprobación'): ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="solicitud_id" value="<?= $row['id'] ?>">
            <input type="number" step="0.01" name="monto_asegurado" class="form-control form-control-sm mb-1" placeholder="Valor máximo cobertura" required>
            <button name="accion" value="aprobar" class="btn btn-success btn-sm">Aprobar</button>
            <button name="accion" value="rechazar" class="btn btn-danger btn-sm">Rechazar</button>
        </form>
    <?php endif; ?>
</td>

                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
<div style="text-align:center; margin-top: 20px;">
    <a href="panel_agente.php" style="background-color: #000; color: #fff; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-block; font-weight: bold;">Regresar</a>
</div>

    </div>
    

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
