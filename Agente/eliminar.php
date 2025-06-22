<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

include '../conexion.php';

$correo = $_SESSION['usuario'];
$sql = "SELECT * FROM usuarios WHERE correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!in_array($usuario['rol'], ['Administrador', 'Agente'])) {
    $_SESSION['error'] = "No tienes permisos para realizar esta acción";
    header("Location: panel_agente.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitud_id'])) {
    $id = $_POST['solicitud_id'];
    
    // Verificar que la póliza existe y está aprobada
    $sql_verificar = "SELECT * FROM seguros_vida WHERE id = ? AND estado = 'Aprobado'";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("i", $id);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();
    
    if ($result_verificar->num_rows === 0) {
        $_SESSION['error'] = "La póliza no existe o no está aprobada";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $poliza = $result_verificar->fetch_assoc();
    
    // Actualizar estado a Cancelado (versión segura sin columnas inexistentes)
    $sql_update = "UPDATE seguros_vida SET 
                  estado = 'Cancelado', 
                  estado_poliza = 'Cancelada'
                  WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $id);
    
    if ($stmt_update->execute()) {
        $_SESSION['mensaje'] = "Póliza cancelada correctamente";
    } else {
        $_SESSION['error'] = "Error al cancelar la póliza: " . $conn->error;
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

header("Location: panel_agente.php");
exit();
?>