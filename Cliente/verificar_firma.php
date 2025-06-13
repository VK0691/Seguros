<?php
session_start();
include '../conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$solicitud_id = intval($_GET['id']);
$correo_sesion = $_SESSION['usuario'];

// Obtener usuario
$sql_usuario = "SELECT id FROM usuarios WHERE correo = ?";
$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("s", $correo_sesion);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();
$stmt_usuario->close();

if (!$usuario) {
    echo json_encode(['error' => 'Usuario no encontrado']);
    exit();
}

// Verificar si la solicitud tiene firma
$sql_firma = "SELECT firma FROM seguros_vida WHERE id = ? AND usuario_id = ?";
$stmt_firma = $conn->prepare($sql_firma);
$stmt_firma->bind_param("ii", $solicitud_id, $usuario['id']);
$stmt_firma->execute();
$result_firma = $stmt_firma->get_result();
$solicitud = $result_firma->fetch_assoc();
$stmt_firma->close();

$firmado = $solicitud && !empty($solicitud['firma']);

echo json_encode(['firmado' => $firmado]);
?>
