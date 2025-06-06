<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['session_regenerada'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerada'] = true;
}

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../index.html");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "seguros");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_usuario'])) {
    $usuario    = $conexion->real_escape_string($_POST['usuario']);
    $telefono   = $conexion->real_escape_string($_POST['telefono']);
    $direccion  = $conexion->real_escape_string($_POST['direccion']);
    $correo     = $conexion->real_escape_string($_POST['correo']);
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol        = $conexion->real_escape_string($_POST['rol']);
    $estado     = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

    $check = $conexion->query("SELECT * FROM usuarios WHERE usuario = '$usuario' OR correo = '$correo'");
    if ($check->num_rows > 0) {
        header("Location: adminpanel.php?error=usuario_existe");
        exit();
    }

    $sql = "INSERT INTO usuarios (usuario, telefono, direccion, correo, contrasena, rol, estado) 
            VALUES ('$usuario', '$telefono', '$direccion', '$correo', '$contrasena', '$rol', $estado)";

    if ($conexion->query($sql) === TRUE) {
        header("Location: adminpanel.php?success=creado");
        exit();
    } else {
        echo "Error: " . $conexion->error;
    }
}

$resultado = $conexion->query("SELECT * FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel de Administrador</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(to right,rgb(53, 56, 125),rgb(27, 75, 131));
      color: #fff;
      padding: 20px;
    }
    h1, h2 {
      color: #FFD700;
      text-align: center;
    }
    form {
      max-width: 500px;
      margin: 20px auto;
      background-color: #fff;
      color: #000;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }
    input, select {
      width: 100%;
      padding: 6px;
      margin: 10px 0;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    button {
      padding: 10px 15px;
      background-color: #002147;
      color: #FFD700;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background-color: #000;
    }
    a button {
      background-color: #FFD700;
      color: #000;
      font-weight: bold;
    }
    a button:hover {
      background-color: #000;
      color: #FFD700;
    }
    #modalInfo, #modalError {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      padding: 15px 20px;
      border-radius: 8px;
      z-index: 999;
      font-weight: bold;
      box-shadow: 0 0 10px rgba(0,0,0,0.5);
    }
    #modalInfo {
      background-color: #28a745;
      color: #fff;
    }
    #modalError {
      background-color: #dc3545;
      color: #fff;
    }
    @media (max-width: 600px) {
      form, h1, h2 {
        font-size: 90%;
      }
    }
  </style>
</head>
<body>

<?php if (isset($_GET['success']) && $_GET['success'] === 'creado'): ?>
  <div id="modalInfo">
    ✅ Usuario creado correctamente.
    <button onclick="document.getElementById('modalInfo').style.display='none'">X</button>
  </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'usuario_existe'): ?>
  <div id="modalError">
    ⚠️ El usuario o correo ya existe.
    <button onclick="document.getElementById('modalError').style.display='none'">X</button>
  </div>
<?php endif; ?>

<h1>Panel de Administrador</h1>
<p style="text-align:center;">Usuario: <?= htmlspecialchars($_SESSION['usuario']) ?></p>
<p style="text-align:center;"><a href="logout.php"><button>Cerrar sesión</button></a></p>

<h2>Crear nuevo usuario</h2>
<form method="POST" action="adminpanel.php">
    <input type="text" name="usuario" placeholder="Nombre" required>
    <input type="text" name="telefono" placeholder="Teléfono" required>
    <input type="text" name="direccion" placeholder="Dirección" required>
    <input type="email" name="correo" placeholder="Correo" required>
    <input type="password" name="contrasena" placeholder="Contraseña" required>
    <select name="rol" required>
        <option value="Administrador">Administrador</option>
        <option value="Cliente">Cliente</option>
        <option value="Agente">Agente</option>
    </select>
    <select name="estado" required>
        <option value="1">Activo</option>
        <option value="0">Inactivo</option>
    </select>
    <button type="submit" name="crear_usuario">Crear Usuario</button>
</form>

<div style="text-align:center">
  <a href="lista_usuarios.php">
      <button>Mostrar lista de usuarios</button>
  </a>
</div>

</body>
</html>
