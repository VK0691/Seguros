<?php
session_start();
// Verificación de sesión y permisos

$id = intval($_GET['id']);
$seguro = $conexion->query("SELECT sv.*, u.usuario, u.correo, u.telefono 
                           FROM seguros_vida sv 
                           INNER JOIN usuarios u ON sv.usuario_id = u.id 
                           WHERE sv.id = $id")->fetch_assoc();
?>

<div class="card">
  <div class="card-header bg-danger text-white">
    <h3>Detalles del Seguro de Vida #<?php echo $seguro['id']; ?></h3>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <h5>Información del Cliente</h5>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($seguro['usuario']); ?></p>
        <p><strong>Correo:</strong> <?php echo htmlspecialchars($seguro['correo']); ?></p>
        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($seguro['telefono']); ?></p>
      </div>
      <div class="col-md-6">
        <h5>Detalles del Seguro</h5>
        <p><strong>Estado:</strong> 
          <span class="badge bg-<?php echo $seguro['estado'] == 'Aprobado' ? 'success' : 'warning'; ?>">
            <?php echo htmlspecialchars($seguro['estado']); ?>
          </span>
        </p>
        <p><strong>N° Póliza:</strong> <?php echo $seguro['numero_poliza'] ?? 'N/A'; ?></p>
        <p><strong>Monto asegurado:</strong> <?php echo isset($seguro['monto_asegurado']) ? '$' . number_format($seguro['monto_asegurado'], 2) : 'N/A'; ?></p>
      </div>
    </div>
  </div>
</div>