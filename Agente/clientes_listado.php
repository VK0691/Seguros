<?php
session_start();

if (!isset($_SESSION['session_regenerada'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerada'] = true;
}

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

include '../conexion.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Agente') {
    header("Location: ../login.php");
    exit();
}

$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

$sql = "SELECT id, usuario, correo, telefono, direccion, estado FROM usuarios WHERE rol='Cliente' ORDER BY usuario ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Listado de Clientes</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #ffffff, #002147);
      color: #000;
      font-family: Arial, sans-serif;
      padding: 30px;
    }
    h1 {
      color: #002147;
      text-align: center;
      margin-bottom: 30px;
    }
    .btn-square, .btn-regresar {
      display: inline-block;
      padding: 10px 20px;
      background-color: #063047;
      color: #fff;
      border-radius: 8px;
      text-decoration: none;
      transition: background-color 0.3s;
      margin-bottom: 20px;
    }
    .btn-square:hover, .btn-regresar:hover {
      background-color: #084d6e;
    }
    #mensajeExito {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
    }
    table {
      background-color: #fff;
      border-collapse: collapse;
      width: 100%;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    th, td {
      text-align: center;
      padding: 10px;
    }
    th {
      background-color: #002147;
      color: #FFD700;
    }
    tr:nth-child(even) {
      background-color: #f2f2f2;
    }
    @media (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        display: block;
      }
      th {
        position: absolute;
        top: -9999px;
        left: -9999px;
      }
      td {
        border: none;
        position: relative;
        padding-left: 50%;
        text-align: left;
      }
      td::before {
        position: absolute;
        top: 10px;
        left: 10px;
        white-space: nowrap;
        font-weight: bold;
      }
    }
  </style>
</head>
<body>

<h1>Listado de Clientes</h1>

<?php if ($success): ?>
  <div id="mensajeExito" class="success"> <?= htmlspecialchars($success) ?> </div>
<?php endif; ?>

<a href="clientes_form.php" class="btn-square">Nuevo Cliente</a>

<div class="table-responsive">
<table class="table table-bordered">
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
          <a href="clientes_form.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
          <span class="text-muted">
            <?= $cliente['estado'] ? 'Desactivar ⚠️' : 'Activar ⚠️' ?>
          </span>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
</div>

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
