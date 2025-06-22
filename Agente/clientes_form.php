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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$usuario = $correo = $telefono = $direccion = '';
$estado = 1;
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
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (strlen($contrasena) > 15) {
        $error = "La contraseña no puede tener más de 15 caracteres.";
    } elseif (empty($usuario) || empty($correo)) {
        $error = "Nombre y correo son obligatorios.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo inválido.";
    } elseif ($id == 0 && empty($contrasena)) {
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
            $_SESSION['success'] = $id > 0 ? "Datos actualizados correctamente." : "Cliente creado correctamente.";
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $id > 0 ? 'Editar Cliente' : 'Nuevo Cliente' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <style>
    body {
      background: linear-gradient(to right, #ffffff, #002147);
      color: #000;
      padding: 30px;
      font-size: calc(1rem + 0.2vw);
    }
    h1 {
      color: #002147;
      text-align: center;
      margin-bottom: 30px;
    }
    form {
      max-width: 600px;
      margin: auto;
      background-color: #fff;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    label {
      font-weight: bold;
      margin-top: 10px;
    }
    .form-control {
      margin-bottom: 15px;
      font-size: 1rem;
    }
    .btn-square, .btn-cancel {
      display: inline-block;
      margin-top: 15px;
      padding: 10px 20px;
      background-color: #063047;
      color: #fff;
      border-radius: 8px;
      text-decoration: none;
      transition: background-color 0.3s, transform 0.2s;
      border: none;
      font-size: 1rem;
    }
    .btn-square:hover, .btn-cancel:hover {
      background-color: #084d6e;
      transform: scale(1.05);
    }
    .error {
      background-color: #f8d7da;
      color: #721c24;
      padding: 10px;
      border: 1px solid #f5c6cb;
      border-radius: 5px;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

<h1><i class="fas fa-user-edit"></i> <?= $id > 0 ? 'Editar Cliente' : 'Nuevo Cliente' ?></h1>

<?php if ($error): ?>
  <div class="error"> <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?> </div>
<?php endif; ?>

<form method="POST" action="">
  <label for="usuario">Nombre:</label>
  <input id="usuario" class="form-control" type="text" name="usuario" value="<?= htmlspecialchars($usuario) ?>" required>

  <label for="contrasena">Contraseña (máx 15 caracteres):</label>
  <input id="contrasena" class="form-control" type="password" name="contrasena" maxlength="15" <?= $id == 0 ? 'required' : '' ?> >
  <small id="mensajeContrasena" style="color: red; display: none;">Permitido máximo 15 caracteres.</small>

  <label for="correo">Correo:</label>
  <input id="correo" class="form-control" type="email" name="correo" value="<?= htmlspecialchars($correo) ?>" required>

  <label for="telefono">Teléfono:</label>
  <input id="telefono" class="form-control" type="text" name="telefono" value="<?= htmlspecialchars($telefono) ?>">

  <label for="direccion">Dirección:</label>
  <input id="direccion" class="form-control" type="text" name="direccion" value="<?= htmlspecialchars($direccion) ?>">

  <label for="estado">Estado:</label>
  <select id="estado" class="form-control" name="estado" required>
    <option value="1" <?= $estado == 1 ? 'selected' : '' ?>>Activo</option>
    <option value="0" <?= $estado == 0 ? 'selected' : '' ?>>Inactivo</option>
  </select>

  <div class="d-flex justify-content-between">
    <button type="submit" class="btn-square"><i class="fas fa-save"></i> Guardar</button>
    <a href="clientes_listado.php" class="btn-cancel"><i class="fas fa-times"></i> Cancelar</a>
  </div>
</form>

<script>
  const inputContrasena = document.getElementById('contrasena');
  const mensaje = document.getElementById('mensajeContrasena');

  inputContrasena.addEventListener('input', () => {
    mensaje.style.display = inputContrasena.value.length > 15 ? 'inline' : 'none';
  });
</script>

</body>
</html>
