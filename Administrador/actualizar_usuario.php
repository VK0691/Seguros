<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../index.html");
    exit();
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_usuario'])) {
    $id        = (int)$_POST['id'];  // Cambiado id_usuario por id
    $usuario   = $_POST['usuario'];
    $telefono  = $_POST['telefono'];
    $direccion = $_POST['direccion'];
    $correo    = $_POST['correo'];
    $rol       = $_POST['rol'];
    $estado    = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

    if (!empty($_POST['contrasena'])) {
        $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, telefono=?, direccion=?, correo=?, contrasena=?, rol=?, estado=? WHERE id=?");
        $stmt->bind_param("ssssssii", $usuario, $telefono, $direccion, $correo, $contrasena, $rol, $estado, $id);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, telefono=?, direccion=?, correo=?, rol=?, estado=? WHERE id=?");
        $stmt->bind_param("sssssii", $usuario, $telefono, $direccion, $correo, $rol, $estado, $id);
    }

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: lista_usuarios.php?mensaje=actualizado");
        exit();
    } else {
        echo "Error al actualizar: " . $conn->error;
    }
} else {
    header("Location: lista_usuarios.php");
    exit();
}
