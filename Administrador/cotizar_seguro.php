<?php
session_start();
require_once '../conexion.php';
require_once 'includes/CalculadoraPrimas.php';

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$calculadora = new CalculadoraPrimas($conn);

// Procesar cotización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $seguro_id = intval($_POST['seguro_id']);
    $edad = intval($_POST['edad']);
    $antiguedad = intval($_POST['antiguedad'] ?? 0);
    $riesgo_extra = floatval($_POST['riesgo_extra'] ?? 0);
    
    try {
        $prima = $calculadora->calcularPrima($seguro_id, $edad, $antiguedad, $riesgo_extra);
        $_SESSION['cotizacion'] = [
            'seguro_id' => $seguro_id,
            'prima' => $prima,
            'detalles' => $_POST
        ];
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener seguros disponibles
$seguros = $conn->query("SELECT id, nombre FROM tipos_seguro WHERE estado = 1");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización de Seguro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-calculator"></i> Cotizar Seguro</h4>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['cotizacion'])): ?>
                    <div class="alert alert-success">
                        <h5>Cotización Generada</h5>
                        <p>Prima mensual calculada: <strong>$<?= number_format($_SESSION['cotizacion']['prima'], 2) ?></strong></p>
                        <a href="contratar_seguro.php" class="btn btn-success">Contratar Seguro</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Seleccione el Seguro</label>
                            <select name="seguro_id" class="form-select" required>
                                <?php while($seguro = $seguros->fetch_assoc()): ?>
                                <option value="<?= $seguro['id'] ?>"><?= htmlspecialchars($seguro['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Edad del Asegurado</label>
                            <input type="number" name="edad" class="form-control" min="18" max="99" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Antigüedad (años)</label>
                            <input type="number" name="antiguedad" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Factor de Riesgo Extra</label>
                            <input type="number" step="0.01" name="riesgo_extra" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-calculator"></i> Calcular Prima
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>