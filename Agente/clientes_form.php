<?php
session_start();
include '../conexion.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Agente') {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$usuario = $correo = $telefono = $direccion = '';
$estado = 1; // Por defecto activo
$error = '';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT usuario, correo, telefono, direccion, estado FROM usuarios WHERE id = ? AND rol='Cliente'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($cliente = $result->fetch_assoc()) {
        $usuario = $cliente['usuario'];
        $correo = $cliente['correo'];
        $telefono = $cliente['telefono'];
        $direccion = $cliente['direccion'];
        $estado = $cliente['estado'];
    } else {
        die("Cliente no encontrado.");
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

    // Contraseña
    $contrasena = trim($_POST['contrasena'] ?? '');

    // Validación del límite de caracteres para la contraseña
    if (strlen($contrasena) > 15) {
        $error = "La contraseña no puede tener más de 15 caracteres.";
    } elseif (empty($usuario) || empty($correo)) {
        $error = "Nombre y correo son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo inválido.";
    } elseif ($id == 0 && empty($contrasena)) {
        // Al crear nuevo cliente, contraseña obligatoria
        $error = "La contraseña es obligatoria para nuevo cliente.";
    } else {
        $contrasenaHasheada = null;
        if (!empty($contrasena)) {
            $contrasenaHasheada = password_hash($contrasena, PASSWORD_DEFAULT);
        }

        if ($id > 0) {
            if ($contrasenaHasheada !== null) {
                $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, correo=?, telefono=?, direccion=?, estado=?, contrasena=? WHERE id=? AND rol='Cliente'");
                $stmt->bind_param("ssssisi", $usuario, $correo, $telefono, $direccion, $estado, $contrasenaHasheada, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, correo=?, telefono=?, direccion=?, estado=? WHERE id=? AND rol='Cliente'");
                $stmt->bind_param("ssssii", $usuario, $correo, $telefono, $direccion, $estado, $id);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO usuarios (usuario, correo, telefono, direccion, contrasena, rol, estado) VALUES (?, ?, ?, ?, ?, 'Cliente', ?)");
            $stmt->bind_param("sssssi", $usuario, $correo, $telefono, $direccion, $contrasenaHasheada, $estado);
        }

        if ($stmt->execute()) {
            if ($id > 0) {
                $_SESSION['success'] = "Datos actualizados correctamente.";
            } else {
                $_SESSION['success'] = "Cliente creado correctamente.";
            }
            header("Location: clientes_listado.php");
            exit();
        } else {
            $error = "Error al guardar: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title><?= $id > 0 ? 'Editar Cliente' : 'Nuevo Cliente' ?></title>
  <link rel="stylesheet" href="estilo_agente.css" />
</head>
<body>

<h1><?= $id > 0 ? 'Editar Cliente' : 'Nuevo Cliente' ?></h1>

<?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
  <label for="usuario">Nombre:</label>
  <input id="usuario" type="text" name="usuario" value="<?= htmlspecialchars($usuario) ?>" required>

  <label for="contrasena">Contraseña (máx 15 caracteres):</label>
  <input id="contrasena" type="password" name="contrasena" maxlength="15" <?= $id == 0 ? 'required' : '' ?>>
  <small id="mensajeContrasena" style="color: red; display: none;">Permitido máximo 15 caracteres.</small>

  <label for="correo">Correo:</label>
  <input id="correo" type="email" name="correo" value="<?= htmlspecialchars($correo) ?>" required>

  <label for="telefono">Teléfono:</label>
  <input id="telefono" type="text" name="telefono" value="<?= htmlspecialchars($telefono) ?>">

  <label for="direccion">Dirección:</label>
  <input id="direccion" type="text" name="direccion" value="<?= htmlspecialchars($direccion) ?>">

  <label for="estado">Estado:</label>
  <select id="estado" name="estado" required>
    <option value="1" <?= $estado == 1 ? 'selected' : '' ?>>Activo</option>
    <option value="0" <?= $estado == 0 ? 'selected' : '' ?>>Inactivo</option>
  </select>

  <br>

  <button type="submit" class="btn-square">Guardar</button>
  <a href="clientes_listado.php" class="btn-square btn-cancel">Cancelar</a>
</form>

<script>
  const inputContrasena = document.getElementById('contrasena');
  const mensaje = document.getElementById('mensajeContrasena');

  inputContrasena.addEventListener('input', () => {
    if (inputContrasena.value.length > 15) {
      mensaje.style.display = 'inline';
    } else {
      mensaje.style.display = 'none';
    }
  });
</script>

</body>
</html>
