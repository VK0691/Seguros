<?php
// Verificación de sesión y permisos (similar al anterior)

$id = intval($_GET['id']);
$seguro = $conexion->query("SELECT * FROM seguros_planes WHERE id = $id")->fetch_assoc();
$coberturas = $conexion->query("SELECT * FROM seguros_coberturas WHERE plan_id = $id");
?>

<!-- HTML para mostrar todos los detalles del plan -->
<div class="card">
  <div class="card-header bg-primary text-white">
    <h3><?php echo htmlspecialchars($seguro['nombre']); ?></h3>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <h5>Información Básica</h5>
        <p><strong>Tipo:</strong> <?php echo $seguro['tipo']; ?></p>
        <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($seguro['descripcion'])); ?></p>
        <p><strong>Beneficios:</strong> <?php echo nl2br(htmlspecialchars($seguro['beneficios'])); ?></p>
      </div>
      <div class="col-md-6">
        <h5>Detalles Técnicos</h5>
        <p><strong>Monto máximo:</strong> $<?php echo number_format($seguro['monto_maximo'], 2); ?></p>
        <p><strong>Prima mensual:</strong> $<?php echo number_format($seguro['prima_mensual'], 2); ?></p>
        <p><strong>Edad permitida:</strong> <?php echo $seguro['edad_minima']; ?> a <?php echo $seguro['edad_maxima']; ?> años</p>
        <p><strong>Duración:</strong> <?php echo $seguro['duracion']; ?></p>
      </div>
    </div>
    
    <hr>
    
    <h4>Coberturas Incluidas</h4>
    <?php if($coberturas->num_rows > 0): ?>
      <div class="row">
        <?php while($cobertura = $coberturas->fetch_assoc()): ?>
        <div class="col-md-4 mb-3">
          <div class="card">
            <div class="card-body">
              <h5><?php echo htmlspecialchars($cobertura['nombre']); ?></h5>
              <p>Límite: $<?php echo number_format($cobertura['limite'], 2); ?></p>
              <p>Deducible: $<?php echo number_format($cobertura['deducible'], 2); ?></p>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-info">Este plan no tiene coberturas definidas aún.</div>
    <?php endif; ?>
  </div>
</div>