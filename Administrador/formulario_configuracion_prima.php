<?php
session_start();
require_once '../conexion.php';

// Verificar permisos
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$seguro_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos del seguro
$seguro = $conn->query("SELECT * FROM tipos_seguro WHERE id = $seguro_id")->fetch_assoc();
$config = $conn->query("SELECT * FROM configuraciones_seguro WHERE tipo_seguro_id = $seguro_id")->fetch_assoc();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $formula = $conn->real_escape_string($_POST['formula']);
    $factores = $conn->real_escape_string(json_encode($_POST['factores']));
    
    $sql = "UPDATE configuraciones_seguro SET 
            formula_prima = '$formula',
            factores_riesgo = '$factores'
            WHERE tipo_seguro_id = $seguro_id";
    
    if ($conn->query($sql)) {
        $_SESSION['success'] = "Configuración de primas actualizada correctamente";
        header("Location: gestion_seguros.php");
        exit();
    } else {
        $error = "Error al guardar: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Primas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-calculator"></i> Cálculo de Primas - <?= htmlspecialchars($seguro['nombre']) ?></h4>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Fórmula de Cálculo</label>
                        <input type="text" name="formula" class="form-control" 
                               value="<?= htmlspecialchars($config['formula_prima'] ?? 'monto_base * (1 + (edad_factor + riesgo_factor))') ?>" required>
                        <small class="text-muted">Variables disponibles: monto_base, edad, antiguedad, riesgo_factor, edad_factor</small>
                    </div>
                    
                    <h5 class="mt-4">Factores de Riesgo</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Edad Mínima</label>
                            <input type="number" name="factores[edad_min]" class="form-control" 
                                   value="<?= htmlspecialchars(json_decode($config['factores_riesgo'] ?? '{}')->edad_min ?? '18') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Edad Máxima</label>
                            <input type="number" name="factores[edad_max]" class="form-control" 
                                   value="<?= htmlspecialchars(json_decode($config['factores_riesgo'] ?? '{}')->edad_max ?? '65') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Factor por Edad</label>
                            <input type="number" step="0.01" name="factores[edad_factor]" class="form-control" 
                                   value="<?= htmlspecialchars(json_decode($config['factores_riesgo'] ?? '{}')->edad_factor ?? '0.05') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Factor de Riesgo Base</label>
                            <input type="number" step="0.01" name="factores[riesgo_base]" class="form-control" 
                                   value="<?= htmlspecialchars(json_decode($config['factores_riesgo'] ?? '{}')->riesgo_base ?? '0.1') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incremento por Año</label>
                            <input type="number" step="0.01" name="factores[incremento_anual]" class="form-control" 
                                   value="<?= htmlspecialchars(json_decode($config['factores_riesgo'] ?? '{}')->incremento_anual ?? '0.02') ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success mt-4">
                        <i class="fas fa-save"></i> Guardar Configuración
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>