<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: index.html");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "seguros");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
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
        echo "<script>
            alert('El usuario o correo ya existe.');
            window.history.back();
        </script>";
        exit;
    }

    $sql = "INSERT INTO usuarios (usuario, telefono, direccion, correo, contrasena, rol, estado) 
            VALUES ('$usuario', '$telefono', '$direccion', '$correo', '$contrasena', '$rol', $estado)";

    if ($conexion->query($sql) === TRUE) {
        echo "<script>
            alert('Usuario creado correctamente.');
            window.location.href = 'adminpanel.php';
        </script>";
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
  <link rel="stylesheet" href="css/estilos.css" />
</head>
<body>
  <h1>Bignvenido al Panel de Administrador</h1>
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
  </form>  <!-- <- CIERRO EL FORMULARIO AQUÍ -->

  <h2>Lista de Usuarios</h2>
  
  <table border="1">
    <thead>
        <tr>
            <th>Usuario</th>
            <th>Teléfono</th>
            <th>Dirección</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($fila['usuario']); ?></td>
                <td><?php echo htmlspecialchars($fila['telefono']); ?></td>
                <td><?php echo htmlspecialchars($fila['direccion']); ?></td>
                <td><?php echo htmlspecialchars($fila['correo']); ?></td>
                <td><?php echo htmlspecialchars($fila['rol']); ?></td>
                <td><?php echo $fila['estado'] ? 'Activo' : 'Inactivo'; ?></td>
                <td>
                    <a href="editar_usuario.php?id_usuario=<?php echo htmlspecialchars($fila['id_usuario']); ?>">Editar</a> |
    <a href="eliminar_usuario.php?id_usuario=<?php echo htmlspecialchars($fila['id_usuario']); ?>" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
  </table>


  

</body>
</html>
