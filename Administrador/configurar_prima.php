<?php
session_start();
require_once '../conexion.php';

// Verificar permisos
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Administrador') {
    header("Location: ../login.php");
    exit();
}

// Verificar si se recibió el ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "ID de tipo de seguro no válido.";
    exit();
}

$id = intval($_GET['id']);

// Obtener los datos del tipo de seguro
$stmt = $conn->prepare("SELECT * FROM tipos_seguro WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo "Tipo de seguro no encontrado.";
    exit();
}

$seguro = $resultado->fetch_assoc();
$stmt->close();

// Si se envió el formulario para calcular la prima
$prima_calculada = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto_base = floatval($_POST['monto_base']);
    $factor_riesgo = floatval($_POST['factor_riesgo']);

    // Ejemplo simple de cálculo
    $prima_calculada = $monto_base * $factor_riesgo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Prima</title>
</head>
<body>
    <h1>Configurar Prima para: <?php echo htmlspecialchars($seguro['nombre']); ?></h1>

    <form method="post">
        <label>Monto Base:</label>
        <input type="number" name="monto_base" step="0.01" required><br><br>

        <label>Factor de Riesgo:</label>
        <input type="number" name="factor_riesgo" step="0.01" required><br><br>

        <button type="submit">Calcular Prima</button>
    </form>

    <?php if (!is_null($prima_calculada)): ?>
        <h3>Prima Calculada: $<?php echo number_format($prima_calculada, 2); ?></h3>
    <?php endif; ?>

    <br>
    <a href="gestion_seguros.php">← Volver</a>
</body>
</html>
