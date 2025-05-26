<?php
include 'conexion.php';

$id = $_GET['id_usuario'];

$sql = "DELETE FROM usuarios WHERE id_usuario=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "Usuario eliminado correctamente.";
    header("Location: adminpanel.php");
} else {
    echo "Error al eliminar: " . $conn->error;
}

$conn->close();
?>
