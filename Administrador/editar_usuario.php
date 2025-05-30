<?php
session_start();

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

  <link rel="stylesheet" href="estiloadmin.css" />

<meta charset="UTF-8">
<title>Editar Usuario</title>
</head>
<body>

<h1>Editar Usuario: <?php echo htmlspecialchars($usuario['usuario']); ?></h1>

<form method="POST" action="actualizar_usuario.php">
    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">

    <label>Usuario:</label><br>
    <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" required><br><br>

    <label>Teléfono:</label><br>
    <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono']); ?>" required><br><br>

    <label>Dirección:</label><br>
    <input type="text" name="direccion" value="<?php echo htmlspecialchars($usuario['direccion']); ?>" required><br><br>

    <label>Correo:</label><br>
    <input type="email" name="correo" value="<?php echo htmlspecialchars($usuario['correo']); ?>" required><br><br>

    <label>Rol:</label><br>
    <select name="rol" required>
        <option value="Administrador" <?php if($usuario['rol'] == 'Administrador') echo 'selected'; ?>>Administrador</option>
        <option value="Cliente" <?php if($usuario['rol'] == 'Cliente') echo 'selected'; ?>>Cliente</option>
        <option value="Agente" <?php if($usuario['rol'] == 'Agente') echo 'selected'; ?>>Agente</option>
    </select><br><br>

    <label>Estado:</label><br>
    <select name="estado" required>
        <option value="1" <?php if($usuario['estado'] == 1) echo 'selected'; ?>>Activo</option>
        <option value="0" <?php if($usuario['estado'] == 0) echo 'selected'; ?>>Inactivo</option>
    </select><br><br>

    <label>Nueva Contraseña (dejar vacío para no cambiarla):</label><br>
    <input type="password" name="contrasena"><br><br>

    <button type="submit" name="actualizar_usuario">Actualizar Usuario</button>
</form>

<p><a href="lista_usuarios.php">Volver a la lista</a></p>

</body>
</html>

