<?php
$conexion = new mysqli("localhost", "root", "", "seguros");

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$usuario    = $_POST['usuario'];
$telefono   = $_POST['telefono'];
$direccion  = $_POST['direccion'];
$correo     = $_POST['correo'];
$contrasena = $_POST['contrasena'];
$rol        = $_POST['rol'];
$estado     = isset($_POST['estado']) ? $_POST['estado'] : 1;

// Validar usuario duplicado
$check = $conexion->query("SELECT * FROM usuarios WHERE usuario = '$usuario'");
if ($check->num_rows > 0) {
    echo "<script>
        alert('El usuario ya existe.');
        window.history.back();
    </script>";
    exit;
}

// Inserta los datos
$sql = "INSERT INTO usuarios (usuario, telefono, direccion, correo, contrasena, rol, estado)
        VALUES ('$usuario', '$telefono', '$direccion', '$correo', '$contrasena', '$rol', $estado)";

if ($conexion->query($sql) === TRUE) {
    echo "<script>
        alert('Usuario creado correctamente.');
        window.history.back();
    </script>";
} else {
    echo "Error: " . $conexion->error;
}

$conexion->close();
?>
