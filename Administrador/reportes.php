<?php
session_start();
require_once '../conexion.php';

// Verificar permisos
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] != 'Administrador') {
    header("Location: ../login.php");
    exit();
}

// Procesar filtros
$filtros = [
    'tipo' => isset($_GET['tipo']) ? $conn->real_escape_string($_GET['tipo']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? $conn->real_escape_string($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? $conn->real_escape_string($_GET['fecha_hasta']) : ''
];

// Construir consulta con filtros
$where = [];
$params = [];
$types = '';

if (!empty($filtros['tipo'])) {
    $where[] = "t.nombre = ?";
    $params[] = $filtros['tipo'];
    $types .= 's';
}

if (!empty($filtros['fecha_desde'])) {
    $where[] = "s.fecha_creacion >= ?";
    $params[] = $filtros['fecha_desde'];
    $types .= 's';
}

if (!empty($filtros['fecha_hasta'])) {
    $where[] = "s.fecha_creacion <= ?";
    $params[] = $filtros['fecha_hasta'];
    $types .= 's';
}

$sql = "SELECT t.nombre AS tipo_seguro, 
               COUNT(s.id) AS total,
               SUM(s.monto_asegurado) AS suma_asegurada
        FROM seguros s
        JOIN tipos_seguro t ON s.tipo_seguro_id = t.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY t.nombre";

// Preparar y ejecutar consulta
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reporte = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Seguros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media (max-width: 992px) {
            .filter-col {
                margin-bottom: 20px;
            }
            .export-buttons {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3 filter-col">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-filter"></i> Filtros</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="reportes.php">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Seguro</label>
                                <select name="tipo" class="form-select">
                                    <option value="">Todos</option>
                                    <?php 
                                    $tipos = $conn->query("SELECT nombre FROM tipos_seguro");
                                    while ($tipo = $tipos->fetch_assoc()): ?>
                                    <option value="<?php echo $tipo['nombre']; ?>" <?php echo ($filtros['tipo'] == $tipo['nombre']) ? 'selected' : ''; ?>>
                                        <?php echo $tipo['nombre']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Fecha Desde</label>
                                <input type="date" name="fecha_desde" class="form-control" value="<?php echo $filtros['fecha_desde']; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Fecha Hasta</label>
                                <input type="date" name="fecha_hasta" class="form-control" value="<?php echo $filtros['fecha_hasta']; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Aplicar Filtros
                            </button>
                        </form>
                        
                        <div class="export-buttons mt-3">
                            <a href="exportar_reporte.php?tipo=excel&<?php echo http_build_query($filtros); ?>" class="btn btn-success w-100 mb-2">
                                <i class="fas fa-file-excel"></i> Exportar Excel
                            </a>
                            <a href="exportar_reporte.php?tipo=pdf&<?php echo http_build_query($filtros); ?>" class="btn btn-danger w-100">
                                <i class="fas fa-file-pdf"></i> Exportar PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-chart-bar"></i> Reporte de Seguros</h4>
                    </div>
                    <div class="card-body">
                        <?php if(empty($reporte)): ?>
                            <div class="alert alert-info">No se encontraron resultados con los filtros aplicados</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo de Seguro</th>
                                            <th>Total Contratos</th>
                                            <th>Suma Asegurada</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reporte as $fila): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fila['tipo_seguro']); ?></td>
                                            <td><?php echo $fila['total']; ?></td>
                                            <td>$<?php echo number_format($fila['suma_asegurada'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>