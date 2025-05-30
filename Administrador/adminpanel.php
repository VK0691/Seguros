<?php
error_reporting(0); // Oculta los errores
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../index.html");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "seguros");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Crear usuario (procesar formulario)
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

// Obtener usuarios para listar
$resultado = $conexion->query("SELECT * FROM usuarios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel de Administrador - Seguro de Salud</title>
  <link rel="stylesheet" href="estiloadmin.css" />
</head>
<body>

<?php if (isset($_GET['success']) && $_GET['success'] === 'creado'): ?>
  <div id="modalInfo" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);
      background:#4CAF50;color:white;padding:15px;border-radius:8px;z-index:999;">
    ✅ Usuario creado correctamente.
    <button onclick="document.getElementById('modalInfo').style.display='none'" 
      style="margin-left:10px;background:transparent;color:white;border:none;font-weight:bold;">X</button>
  </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'usuario_existe'): ?>
  <div id="modalError" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);
      background:#f44336;color:white;padding:15px;border-radius:8px;z-index:999;">
    ⚠️ El usuario o correo ya existe.
    <button onclick="document.getElementById('modalError').style.display='none'" 
      style="margin-left:10px;background:transparent;color:white;border:none;font-weight:bold;">X</button>
  </div>
<?php endif; ?>

<h1>Bienvenido al Panel de Administrador</h1>
<p>Usuario: <?php echo htmlspecialchars($_SESSION['usuario']); ?></p>
<p><a href="logout.php">Cerrar sesión</a></p>

<h2>Crear nuevo usuario</h2>
<form method="POST" action="adminpanel.php">
    <input type="text" name="usuario" placeholder="Usuario" required>
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

<!-- Enlace para ir a la lista de usuarios -->
<a href="lista_usuarios.php">
    <button style="margin-top: 20px;">Mostrar lista de usuarios</button>
</a>

</body>
</html>
