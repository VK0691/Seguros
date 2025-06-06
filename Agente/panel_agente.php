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

if ($_SESSION['rol'] != 'Agente') {
    header("Location: ../login.php");
    exit();
}

$correoSesion = $_SESSION['usuario'];
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'guardar') {
    $nuevoUsuario = trim($_POST['usuario']);
    $nuevoCorreo = trim($_POST['correo']);
    $nuevaDireccion = trim($_POST['direccion']);
    $nuevoTelefono = trim($_POST['telefono']);

    if (empty($nuevoUsuario) || empty($nuevoCorreo)) {
        $error = "El nombre y correo son obligatorios.";
    } elseif (!filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo inválido.";
    } else {
        $sql_update = "UPDATE usuarios SET usuario=?, correo=?, direccion=?, telefono=? WHERE correo=?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("sssss", $nuevoUsuario, $nuevoCorreo, $nuevaDireccion, $nuevoTelefono, $correoSesion);

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

$sql = "SELECT id, usuario, correo, direccion, telefono FROM usuarios WHERE correo = ?";
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
} else {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Panel Agente</title>
  <!-- Forzar recarga del CSS -->
  <link rel="stylesheet" href="estilo_agente.css?v=<?= time(); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<h1 style="color: black;">Bienvenido, <?= htmlspecialchars($usuario) ?></h1>


<div class="container">
  <div class="left">
    <div class="user-photo">
      <img src="icono_seguro.jfif" alt="Usuario">
    </div>

    <div class="user-name"><?= htmlspecialchars($usuario) ?></div>

    <div class="user-buttons">
      <button id="btnEditar" class="btn-square">Editar perfil</button>
      <a href="logout.php" class="btn-square btn-logout">Cerrar sesión</a>
      <a href="clientes_listado.php" class="btn-square btn-gestion-clientes">Gestionar clientes</a>
      <a href="listaforms.php" class="btn-square btn-solicitudes">Revisar Solicitudes</a>
    </div>
  </div>

  <div class="right" id="rightPanel">
    <h3>Detalles del Agente</h3>

    <?php if ($error): ?>
      <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div id="mensajeExito" class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form id="formDetalles" method="POST" action="panel_agente.php" style="display: none;">
      <input type="hidden" name="action" value="guardar" />
      <div class="detail edit-mode">
        <strong>ID:</strong> <?= htmlspecialchars($id) ?>
      </div>
      <div class="detail edit-mode">
        <label for="usuarioInput"><strong>Nombre:</strong></label>
        <input id="usuarioInput" name="usuario" type="text" value="<?= htmlspecialchars($usuario) ?>" required>
      </div>
      <div class="detail edit-mode">
        <label for="correoInput"><strong>Correo:</strong></label>
        <input id="correoInput" name="correo" type="email" value="<?= htmlspecialchars($correo) ?>" required>
      </div>
      <div class="detail edit-mode">
        <label for="direccionInput"><strong>Dirección:</strong></label>
        <input id="direccionInput" name="direccion" type="text" value="<?= htmlspecialchars($direccion) ?>">
      </div>
      <div class="detail edit-mode">
        <label for="telefonoInput"><strong>Teléfono:</strong></label>
        <input id="telefonoInput" name="telefono" type="tel" value="<?= htmlspecialchars($telefono) ?>">
      </div>

      <div class="btn-group-edit">
        <button type="submit">Guardar</button>
        <button type="button" id="btnCancelar" class="btn-cancel">Cancelar</button>
      </div>
    </form>

    <div id="datosMostrar">
      <div class="detail"><strong>ID:</strong> <?= htmlspecialchars($id) ?></div>
      <div class="detail"><strong>Nombre:</strong> <?= htmlspecialchars($usuario) ?></div>
      <div class="detail"><strong>Correo:</strong> <?= htmlspecialchars($correo) ?></div>
      <div class="detail"><strong>Dirección:</strong> <?= htmlspecialchars($direccion) ?></div>
      <div class="detail"><strong>Teléfono:</strong> <?= htmlspecialchars($telefono) ?></div>
    </div>
  </div>
</div>

<script>
  const btnEditar = document.getElementById('btnEditar');
  const btnCancelar = document.getElementById('btnCancelar');
  const formDetalles = document.getElementById('formDetalles');
  const datosMostrar = document.getElementById('datosMostrar');

  btnEditar.addEventListener('click', () => {
    formDetalles.style.display = 'block';
    datosMostrar.style.display = 'none';
    btnEditar.style.display = 'none';
  });

  btnCancelar.addEventListener('click', () => {
    formDetalles.style.display = 'none';
    datosMostrar.style.display = 'block';
    btnEditar.style.display = 'inline-block';
  });

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
