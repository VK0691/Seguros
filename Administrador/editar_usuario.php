<?php
session_start();

if (!isset($_SESSION['session_regenerada'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerada'] = true;
}

// Validar que haya sesión activa
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

// Verificar rol administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../index.html");
    exit();
}

include '../conexion.php'; // Ajusta la ruta si es necesario

// Verificar que llegue el id por GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: lista_usuarios.php");
    exit();
}

$id = (int) $_GET['id'];

// Obtener datos del usuario
$sql = "SELECT * FROM usuarios WHERE id = $id LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "Usuario no encontrado.";
    exit();
}

$usuario = $result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Usuario</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estiloadmin.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #002147 50%, #ffffff 50%);
      margin: 0;
      color: #222;
      padding: 20px;
      animation: fadeIn 1s ease-in-out;
    }

    h1 {
      color: #002147;
      font-weight: 700;
      text-align: center;
      margin-top: 10px;
    }

    form {
      max-width: 600px;
      margin: auto;
      background-color: white;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
      animation: slideInUp 0.6s ease;
    }

    label {
      font-weight: bold;
      color: #002147;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      margin-bottom: 20px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
    }

    button {
      background-color: #002147;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 16px;
      cursor: pointer;
      transition: 0.3s;
    }

    button:hover {
      background-color: #000;
      transform: scale(1.03);
    }

    p a {
      text-decoration: none;
      color: #002147;
      font-weight: bold;
      display: block;
      margin-top: 20px;
      text-align: center;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideInUp {
      from { transform: translateY(40px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    @media screen and (max-width: 768px) {
      form {
        padding: 20px;
      }

      h1 {
        font-size: 22px;
      }
    }
  </style>
</head>
<body>

<h1>Editar Usuario: <?= htmlspecialchars($usuario['usuario']) ?></h1>

<form method="POST" action="actualizar_usuario.php">
  <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

  <label>Usuario:</label>
  <input type="text" name="usuario" value="<?= htmlspecialchars($usuario['usuario']) ?>" required>

  <label>Teléfono:</label>
  <input type="text" name="telefono" value="<?= htmlspecialchars($usuario['telefono']) ?>" required>

  <label>Dirección:</label>
  <input type="text" name="direccion" value="<?= htmlspecialchars($usuario['direccion']) ?>" required>

  <label>Correo:</label>
  <input type="email" name="correo" value="<?= htmlspecialchars($usuario['correo']) ?>" required>

  <label>Rol:</label>
  <select name="rol" required>
    <option value="Administrador" <?= $usuario['rol'] === 'Administrador' ? 'selected' : '' ?>>Administrador</option>
    <option value="Cliente" <?= $usuario['rol'] === 'Cliente' ? 'selected' : '' ?>>Cliente</option>
    <option value="Agente" <?= $usuario['rol'] === 'Agente' ? 'selected' : '' ?>>Agente</option>
  </select>

  <label>Estado:</label>
  <select name="estado" required>
    <option value="1" <?= $usuario['estado'] == 1 ? 'selected' : '' ?>>Activo</option>
    <option value="0" <?= $usuario['estado'] == 0 ? 'selected' : '' ?>>Inactivo</option>
  </select>

  <label>Nueva Contraseña (dejar vacío para no cambiarla):</label>
  <input type="password" name="contrasena">

  <button type="submit" name="actualizar_usuario">Actualizar Usuario</button>
</form>

<p><a href="lista_usuarios.php">Volver a la lista</a></p>

</body>
</html>