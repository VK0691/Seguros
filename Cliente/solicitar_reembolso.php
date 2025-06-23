<?php
session_start(); // Asegúrate de iniciar la sesión

require_once '../conexion.php'; // Asegúrate que la ruta sea correcta

// Verifica si el usuario está autenticado
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php"); // Redirige si no está autenticado
    exit();
}

// Obtener el ID del usuario de la sesión
$usuario_id = $_SESSION['id']; // Cambia esto para obtener el ID del usuario actual

// Obtener todos los seguros disponibles de tu tabla seguros
$seguros_disponibles = [];
$query = "SELECT id, nombre, precio, cobertura_maxima FROM seguros";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $seguros_disponibles[] = $row;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seguro_id = intval($_POST['seguro_id']);
    $tipo = $_POST['tipo_reembolso'];
    $monto = floatval($_POST['monto']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    
    // Insertar en la tabla reembolsos
    $query = "INSERT INTO reembolsos (usuario_id, seguro_id, tipo_reembolso, monto_solicitado, descripcion, estado) 
              VALUES (?, ?, ?, ?, ?, 'pendiente')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $usuario_id, $seguro_id, $tipo, $monto, $descripcion);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Solicitud de reembolso #".$conn->insert_id." enviada correctamente";
    } else {
        $error = "❌ Error al enviar: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Reembolso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-reembolso {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .seguro-option {
            display: flex;
            justify-content: space-between;
        }
        .seguro-details {
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="card card-reembolso p-4">
            <h2 class="text-center mb-4">Solicitud de Reembolso</h2>
            
            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success"><?= $mensaje ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Plan de Seguro:</label>
                    <select class="form-select" name="seguro_id" required>
                        <option value="" disabled selected>Seleccione su plan</option>
                        <?php foreach ($seguros_disponibles as $seguro): ?>
                            <option value="<?= $seguro['id'] ?>">
                                <?= htmlspecialchars($seguro['nombre']) ?> - 
                                $<?= number_format($seguro['precio'], 2) ?> | 
                                Cobertura: $<?= number_format($seguro['cobertura_maxima'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Tipo de Reembolso:</label>
                    <select class="form-select" name="tipo_reembolso" required>
                        <option value="medicina">Medicinas y recetas</option>
                        <option value="cirugia">Procedimientos quirúrgicos</option>
                        <option value="hospitalizacion">Gastos de hospitalización</option>
                        <option value="otros">Otros gastos médicos</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Monto a Reembolsar:</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" name="monto" 
                               step="0.01" min="0" placeholder="Ej: 150.50" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Descripción Detallada:</label>
                    <textarea class="form-control" name="descripcion" rows="4" 
                              placeholder="Describa los gastos médicos incurridos..." required></textarea>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Enviar Solicitud de Reembolso
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
