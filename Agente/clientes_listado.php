<?php
session_start();
include '../conexion.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Agente') {
    header("Location: ../login.php");
    exit();
}

// Leer mensaje de éxito si existe y limpiar la sesión
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Obtener lista de clientes
$sql = "SELECT id, usuario, correo, telefono, direccion, estado FROM usuarios WHERE rol='Cliente' ORDER BY usuario ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Listado de Clientes</title>
  <link rel="stylesheet" href="estilo_agente.css" />
</head>
<body>

<h1>Listado de Clientes</h1>

<?php if ($success): ?>
  <div id="mensajeExito" class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<a href="clientes_form.php" class="btn-square">Nuevo Cliente</a>

<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; margin-top: 20px;">
  <thead>
    <tr>
      <th>ID</th>
      <th>Nombre</th>
      <th>Correo</th>
      <th>Teléfono</th>
      <th>Dirección</th>
      <th>Estado</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($cliente = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $cliente['id'] ?></td>
        <td><?= htmlspecialchars($cliente['usuario']) ?></td>
        <td><?= htmlspecialchars($cliente['correo']) ?></td>
        <td><?= htmlspecialchars($cliente['telefono']) ?></td>
        <td><?= htmlspecialchars($cliente['direccion']) ?></td>
        <td><?= $cliente['estado'] ? 'Activo' : 'Inactivo' ?></td>
        <td>
  <a href="clientes_form.php?id=<?= $cliente['id'] ?>">Editar</a> | 
  <span style="color: gray; text-decoration: none; cursor: default;">
    <?= $cliente['estado'] ? 'Desactivar ⚠️' : 'Activar ⚠️' ?>
  </span>
</td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<a href="../Agente/panel_agente.php" class="btn-square btn-regresar">Regresar</a>

<script>
  window.addEventListener('DOMContentLoaded', () => {
    const mensaje = document.getElementById('mensajeExito');
    if (mensaje) {
      setTimeout(() => {
        mensaje.style.transition = "opacity 0.5s ease";
        mensaje.style.opacity = 0;
        setTimeout(() => mensaje.style.display = 'none', 500);
      }, 3000);
    }
  });
</script>

</body>
</html>
