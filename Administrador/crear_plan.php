<?php
session_start();
include '../conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$correo = $_SESSION['usuario'];
$sql = "SELECT * FROM usuarios WHERE correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $correo);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if ($usuario['rol'] !== 'Administrador') {
    echo "<p>No tienes permisos para acceder a esta página.</p>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $descripcion = $_POST['descripcion'];
    $beneficios = $_POST['beneficios'];
    $monto_max = $_POST['monto_max'];
    $prima = $_POST['prima'];
    $edad_min = $_POST['edad_min'];
    $edad_max = $_POST['edad_max'];
    $duracion = $_POST['duracion'];
    $estado = $_POST['estado'];
    $archivo_pdf = "";

    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] == 0) {
        $nombreArchivo = basename($_FILES["archivo_pdf"]["name"]);
        $rutaDestino = "../uploads/" . $nombreArchivo;
        move_uploaded_file($_FILES["archivo_pdf"]["tmp_name"], $rutaDestino);
        $archivo_pdf = $nombreArchivo;
    }

    $query = "INSERT INTO planes_seguros 
        (nombre, tipo, descripcion, beneficios, monto_max, prima, edad_min, edad_max, duracion, estado, archivo_pdf, fecha_creacion, creado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssddiiisss", $nombre, $tipo, $descripcion, $beneficios, $monto_max, $prima, $edad_min, $edad_max, $duracion, $estado, $archivo_pdf, $usuario['correo']);
    
    if ($stmt->execute()) {
        echo "<script>alert('Plan creado correctamente'); window.location.href='gestion_seguros.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Crear Plan de Seguro</title>
    <link rel="stylesheet" href="../estilos.css">
</head>
<body>
    <h2>Crear Nuevo Plan de Seguro</h2>
    <form method="POST" enctype="multipart/form-data">
        <label>Nombre del seguro:</label><br>
        <input type="text" name="nombre" required><br>

        <label>Tipo de seguro:</label><br>
        <select name="tipo" required>
            <option value="Vida">Vida</option>
            <option value="Médico">Médico</option>
            <option value="Vehicular">Vehicular</option>
            <option value="Hogar">Hogar</option>
            <option value="Otros">Otros</option>
        </select><br>

        <label>Descripción breve:</label><br>
        <textarea name="descripcion" required></textarea><br>

        <label>Beneficios principales:</label><br>
        <textarea name="beneficios" required></textarea><br>

        <label>Monto asegurado máximo:</label><br>
        <input type="number" step="0.01" name="monto_max" required><br>

        <label>Prima mensual/anual:</label><br>
        <input type="number" step="0.01" name="prima" required><br>

        <label>Edad mínima permitida:</label><br>
        <input type="number" name="edad_min" required><br>

        <label>Edad máxima permitida:</label><br>
        <input type="number" name="edad_max" required><br>

        <label>Duración del seguro (años):</label><br>
        <input type="number" name="duracion" required><br>

        <label>Estado del plan:</label><br>
        <select name="estado" required>
            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>
        </select><br>

        <label>Subir contrato base (PDF):</label><br>
        <input type="file" name="archivo_pdf" accept="application/pdf"><br><br>

        <input type="submit" value="Guardar Plan">
    </form>
</body>
</html>
