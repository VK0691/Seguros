<?php
include 'conexion.php';

$id = $_POST['id_usuario'];
$usuario = $_POST['usuario'];
$contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
$rol = $_POST['rol'];

$sql = "UPDATE usuarios SET usuario=?, contrasena=?, rol=? WHERE id_usuario=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $usuario, $contrasena, $rol, $id);

if ($stmt->execute()) {
    echo "Usuario actualizado con éxito.";
    header("Location: adminpanel.php");
} else {
    echo "Error al actualizar: " . $conn->error;
}

$conn->close();
?>
