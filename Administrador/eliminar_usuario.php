<?php
session_start();
include '../conexion.php';

// Validar que sea administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../index.html");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: lista_usuarios.php");
    exit();
}

$id = $_GET['id'];

$sql = "DELETE FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: lista_usuarios.php?mensaje=eliminado");
    exit();
} else {
    echo "Error al eliminar: " . $conn->error;
}

$conn->close();
?>
