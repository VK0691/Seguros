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

if ($_SESSION['rol'] !== 'Cliente') {
    header("Location: ../login.php");
    exit();
}

$correoSesion = $_SESSION['usuario'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar') {
    $nuevoUsuario = trim($_POST['usuario']);
    $nuevoCorreo = trim($_POST['correo']);
    $nuevaDireccion = trim($_POST['direccion']);
    $nuevoTelefono = trim($_POST['telefono']);

    if (empty($nuevoUsuario) || empty($nuevoCorreo)) {
        $error = "El nombre y correo son obligatorios.";
    } elseif (!filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo inválido.";
    } else {
        // Procesar imagen si se subió
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ruta = '../uploads/fotos_perfil/';
            if (!file_exists($ruta)) mkdir($ruta, 0777, true);
            $nombreArchivo = 'perfil_' . time() . '_' . basename($_FILES['foto']['name']);
            $destino = $ruta . $nombreArchivo;
            move_uploaded_file($_FILES['foto']['tmp_name'], $destino);

            $sql_update = "UPDATE usuarios SET usuario=?, correo=?, direccion=?, telefono=?, foto=? WHERE correo=? AND rol='Cliente'";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssssss", $nuevoUsuario, $nuevoCorreo, $nuevaDireccion, $nuevoTelefono, $nombreArchivo, $correoSesion);
        } else {
            $sql_update = "UPDATE usuarios SET usuario=?, correo=?, direccion=?, telefono=? WHERE correo=? AND rol='Cliente'";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sssss", $nuevoUsuario, $nuevoCorreo, $nuevaDireccion, $nuevoTelefono, $correoSesion);
        }

        if ($stmt_update->execute()) {
            $_SESSION['usuario'] = $nuevoCorreo;
            $success = "Datos actualizados correctamente.";
            $correoSesion = $nuevoCorreo;
        } else {
            $error = "Error al actualizar: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}

$sql = "SELECT id, usuario, correo, direccion, telefono, foto FROM usuarios WHERE correo = ? AND rol='Cliente'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $correoSesion);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $usuario = $row['usuario'];
    $correo = $row['correo'];
    $direccion = $row['direccion'];
    $telefono = $row['telefono'];
    $foto = $row['foto'] ?? 'foto_perfil_default.png';
} else {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$stmt->close();

$sql_polizas = "SELECT numero_poliza, tipo_seguro, monto_asegurado, estado, tipo_pago FROM seguros_vida WHERE usuario_id = ? AND estado = 'Aprobado'";

$stmt_poliza = $conn->prepare($sql_polizas);
$stmt_poliza->bind_param("i", $id);
$stmt_poliza->execute();
$polizas = $stmt_poliza->get_result();
$stmt_poliza->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Cliente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #ffffff, #062D49);
      font-family: Arial, sans-serif;
      padding: 20px;
      color: #000;
    }
    .card-profile {
      max-width: 900px;
      margin: auto;
      background: #fff;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
    }
    .user-photo img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
    }
    .btn-opciones {
      margin-top: 20px;
    }
    .table {
      margin-top: 30px;
    }
    .badge-success {
      background-color: #28a745;
    }
  </style>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Cliente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* 👇 PEGAS ESTO DENTRO DEL STYLE 👇 */
    @keyframes fadeInSlide {
      0% {
        opacity: 0;
        transform: translateY(40px);
      }
      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    body {
      background: linear-gradient(to right, #ffffff, #062D49);
      font-family: Arial, sans-serif;
      padding: 20px;
      color: #000;
    }

    .card-profile {
      max-width: 900px;
      margin: auto;
      background: #fff;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 0 10px rgba(0,0,0,0.2);

      /* 👇 ESTO ACTIVA LA ANIMACIÓN 👇 */
      animation: fadeInSlide 0.8s ease-out;
    }

    .user-photo img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
    }

    .btn-opciones {
      margin-top: 20px;
    }

    .table {
      margin-top: 30px;
    }

    .badge-success {
      background-color: #28a745;
    }
  </style>
</head>
<body>

<div class="card-profile">
  <div class="text-center">
    <div class="user-photo">
      <img src="../uploads/fotos_perfil/<?= htmlspecialchars($foto) ?>" alt="Foto Perfil">
    </div>
    <form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="action" value="guardar">
  <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuario) ?>">
  <input type="hidden" name="correo" value="<?= htmlspecialchars($correo) ?>">
  <input type="hidden" name="direccion" value="<?= htmlspecialchars($direccion) ?>">
  <input type="hidden" name="telefono" value="<?= htmlspecialchars($telefono) ?>">

      <input type="hidden" name="action" value="guardar">
      <div class="mb-3 mt-2">
        <label for="foto" class="form-label">Cambiar Foto:</label>
        <input type="file" class="form-control" name="foto" accept="image/*">
      </div>
      <button type="submit" class="btn btn-sm btn-secondary">Actualizar Perfil</button>
    </form>
    <h3 class="mt-3">Información Personal</h3>
  </div>
  <div class="mt-4">
    <p><strong>Nombre:</strong> <?= htmlspecialchars($usuario) ?></p>
    <p><strong>Correo:</strong> <?= htmlspecialchars($correo) ?></p>
    <p><strong>Teléfono:</strong> <?= htmlspecialchars($telefono) ?></p>
    <p><strong>Dirección:</strong> <?= htmlspecialchars($direccion) ?></p>
    <p><strong>Rol:</strong> Cliente</p>
  </div>

  <h4 class="mt-4">Mis Pólizas</h4>
  <table class="table table-bordered table-hover">
    <thead class="table-dark">
      <tr>
        <th>Nro. Póliza</th>
        <th>Tipo</th>
        <th>Valor máximo</th>
        <th>Estado</th>
        <th>Pago</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($p = $polizas->fetch_assoc()): ?>
        <tr>
<td><?= htmlspecialchars($p['numero_poliza']) ?></td>

          <td><?= htmlspecialchars($p['tipo_seguro']) ?></td>
          <td><?= htmlspecialchars($p['monto_asegurado']) ?> $</td>
          <td><span class="badge bg-success">Activa</span></td>
          <td><?= htmlspecialchars($p['tipo_pago']) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <div class="text-center btn-opciones">
    <a href="#" class="btn btn-primary me-2">Ver historial de Siniestros</a>
    <a href="clientedash.php" class="btn btn-info">Volver Inicio</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
