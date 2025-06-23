<?php
require_once '../conexion.php';

// Obtener parámetros de filtrado
$estado = isset($_GET['estado']) ? $_GET['estado'] : 'pendiente';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Consulta base con JOINs a tus tablas
$query = "SELECT r.*, 
          u.telefono, 
          u.correo, 
          s.nombre as seguro_nombre
          FROM reembolsos r
          JOIN usuarios u ON r.usuario_id = u.id
          JOIN seguros s ON r.seguro_id = s.id
          WHERE r.estado = ?
          ORDER BY r.fecha_solicitud DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("sii", $estado, $por_pagina, $offset);
$stmt->execute();
$reembolsos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Contar total para paginación
$query_count = "SELECT COUNT(*) as total FROM reembolsos WHERE estado = ?";
$stmt_count = $conn->prepare($query_count);
$stmt_count->bind_param("s", $estado);
$stmt_count->execute();
$total = $stmt_count->get_result()->fetch_assoc()['total'];
$paginas = ceil($total / $por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Reembolsos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .badge-estado {
            font-size: 0.85em;
            padding: 5px 10px;
            border-radius: 10px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-cash-stack"></i> Gestión de Reembolsos</h2>
            <div class="btn-group">
                <a href="?estado=pendiente" class="btn btn-outline-primary <?= $estado === 'pendiente' ? 'active' : '' ?>">Pendientes</a>
                <a href="?estado=en_revision" class="btn btn-outline-info <?= $estado === 'en_revision' ? 'active' : '' ?>">En Revisión</a>
                <a href="?estado=aprobado" class="btn btn-outline-success <?= $estado === 'aprobado' ? 'active' : '' ?>">Aprobados</a>
                <a href="?estado=rechazado" class="btn btn-outline-danger <?= $estado === 'rechazado' ? 'active' : '' ?>">Rechazados</a>
                <a href="?estado=pagado" class="btn btn-outline-secondary <?= $estado === 'pagado' ? 'active' : '' ?>">Pagados</a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Usuario (Teléfono)</th>
                                <th>Seguro</th>
                                <th>Tipo</th>
                                <th>Monto Solicitado</th>
                                <th>Estado</th>
                                <th>Fecha Solicitud</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reembolsos as $r): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['telefono']) ?> (<?= htmlspecialchars($r['correo']) ?>)</td>
                                <td><?= htmlspecialchars($r['seguro_nombre']) ?></td>
                                <td><?= ucfirst($r['tipo_reembolso']) ?></td>
                                <td class="fw-bold">$<?= number_format($r['monto_solicitado'], 2) ?></td>
                                <td>
                                    <?php 
                                    $badge_class = [
                                        'pendiente' => 'bg-warning',
                                        'en_revision' => 'bg-info',
                                        'aprobado' => 'bg-success',
                                        'rechazado' => 'bg-danger',
                                        'pagado' => 'bg-secondary'
                                    ][$r['estado']] ?? 'bg-light';
                                    ?>
                                    <span class="badge <?= $badge_class ?> badge-estado">
                                        <?= ucfirst(str_replace('_', ' ', $r['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="ver_reembolso.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php for ($i = 1; $i <= $paginas; $i++): ?>
                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                            <a class="page-link" href="?estado=<?= $estado ?>&pagina=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</body>
</html>
