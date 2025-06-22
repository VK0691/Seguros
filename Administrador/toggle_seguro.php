<?php
session_start();
require_once '../conexion.php';

// Verificar permisos
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Administrador') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Verificar existencia del seguro
    $stmt = $conn->prepare("SELECT estado FROM tipos_seguro WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $seguro = $result->fetch_assoc();
        $nuevo_estado = $seguro['estado'] ? 0 : 1;

        // Actualizar estado
        $stmt_update = $conn->prepare("UPDATE tipos_seguro SET estado = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $nuevo_estado, $id);
        $stmt_update->execute();
        $stmt_update->close();

        // Registrar en historial si hay usuario_id
        if (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
            $usuario_id = intval($_SESSION['usuario_id']);
            $accion = $nuevo_estado ? 'activó' : 'desactivó';
            $tabla = 'tipos_seguro';

            $stmt_historial = $conn->prepare("INSERT INTO historial (usuario_id, accion, tabla, registro_id) VALUES (?, ?, ?, ?)");
            $stmt_historial->bind_param("issi", $usuario_id, $accion, $tabla, $id);
            $stmt_historial->execute();
            $stmt_historial->close();
        } else {
            error_log("⚠️ usuario_id no está definido en la sesión.");
        }
    }

    $stmt->close();
}

header("Location: gestion_seguros.php");
exit();
?>
