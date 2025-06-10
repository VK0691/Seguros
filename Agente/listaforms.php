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
    echo "<p>No tienes permisos para acceder a esta página.</p>";
    exit();
}

$mensaje = "";

// Procesar acciones de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['solicitud_id']) && isset($_POST['accion'])) {
    $id = intval($_POST['solicitud_id']);
    $accion = $_POST['accion'];
    
    try {
        $conn->begin_transaction();
        
        if ($accion === 'aprobar' && !empty($_POST['monto_asegurado'])) {
            $estado = 'Aprobado';
            $numero_poliza = 'POL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $estado_poliza = 'Activa';
            $tipo_pago = $_POST['tipo_pago'] ?? 'Mensual';
            $monto_asegurado = floatval($_POST['monto_asegurado']);

            $sql_update = "UPDATE seguros_vida 
                           SET estado = ?, 
                               numero_poliza = ?, 
                               estado_poliza = ?, 
                               tipo_pago = ?, 
                               monto_asegurado = ?, 
                               fecha_solicitud = CURDATE()
                           WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ssssdi", $estado, $numero_poliza, $estado_poliza, $tipo_pago, $monto_asegurado, $id);
            
            $mensaje = "<div class='alert alert-success'>Solicitud aprobada exitosamente. Número de póliza: <strong>$numero_poliza</strong></div>";
            
        } elseif ($accion === 'rechazar') {
            $estado = 'Rechazado';
            
            $sql_update = "UPDATE seguros_vida 
                           SET estado = ?
                           WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $estado, $id);
            
            $mensaje = "<div class='alert alert-warning'>Solicitud rechazada correctamente.</div>";
        }

        if ($stmt_update->execute()) {
            $conn->commit();
        } else {
            throw new Exception("Error al actualizar la solicitud");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

$where_conditions = [];
$params = [];
$types = "";

if ($filtro_estado) {
    $where_conditions[] = "sv.estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
}

if ($filtro_fecha) {
    $where_conditions[] = "DATE(sv.fecha_solicitud) = ?";
    $params[] = $filtro_fecha;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Consulta SQL para debug - vamos a ver todos los estados
$sql_solicitudes = "SELECT sv.*, u.usuario, u.correo, u.telefono
                    FROM seguros_vida sv 
                    INNER JOIN usuarios u ON sv.usuario_id = u.id 
                    $where_clause
                    ORDER BY sv.fecha_solicitud DESC";

$stmt_solicitudes = $conn->prepare($sql_solicitudes);
if (!empty($params)) {
    $stmt_solicitudes->bind_param($types, ...$params);
}
$stmt_solicitudes->execute();
$result_solicitudes = $stmt_solicitudes->get_result();

// Debug: Obtener todos los estados únicos
$debug_sql = "SELECT DISTINCT estado FROM seguros_vida";
$debug_result = $conn->query($debug_sql);
$estados_existentes = [];
while ($row = $debug_result->fetch_assoc()) {
    $estados_existentes[] = $row['estado'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Gestión de Solicitudes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #002147, #ffffff);
            color: #000;
        }
        h2 {
            color: #002147;
        }
        .table-dark {
            background-color: #002147;
        }
        .table-dark th {
            color: #FFD700;
        }
        .btn-primary {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #000;
        }
        .btn-success {
            background-color: #002147;
            border-color: #002147;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .estado-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .action-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin: 5px 0;
        }
        .modal-header {
            background-color: #002147;
            color: white;
        }
        .debug-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            h2 {
                font-size: 1.5rem;
            }
            .table {
                font-size: 0.8rem;
            }
            .action-form {
                padding: 5px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4 text-center">
                <i class="fas fa-clipboard-list"></i> Gestión de Solicitudes de Seguro de Vida
            </h2>
            
            <!-- Debug Info -->
            <div class="debug-info">
                <h6><i class="fas fa-bug"></i> Información de Debug</h6>
                <p><strong>Estados encontrados en la base de datos:</strong></p>
                <ul>
                    <?php foreach ($estados_existentes as $estado): ?>
                        <li><code><?= htmlspecialchars($estado) ?></code></li>
                    <?php endforeach; ?>
                </ul>
                <p><small>Los botones de aprobar/rechazar aparecen solo para solicitudes con estado: <code>En espera de aprobación</code></small></p>
            </div>
            
            <?= $mensaje ?>
            
            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-filter"></i> Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="">Todos los estados</option>
                                <?php foreach ($estados_existentes as $estado): ?>
                                    <option value="<?= htmlspecialchars($estado) ?>" <?= $filtro_estado == $estado ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($estado) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($filtro_fecha) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Estadísticas rápidas -->
            <div class="row mb-4">
                <?php
                $stats_sql = "SELECT 
                                COUNT(*) as total,
                                SUM(CASE WHEN estado = 'En espera de aprobación' THEN 1 ELSE 0 END) as pendientes,
                                SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) as aprobados,
                                SUM(CASE WHEN estado = 'Rechazado' THEN 1 ELSE 0 END) as rechazados,
                                SUM(CASE WHEN estado = 'Pendiente de Firma' THEN 1 ELSE 0 END) as pendiente_firma
                              FROM seguros_vida";
                $stats_result = $conn->query($stats_sql);
                $stats = $stats_result->fetch_assoc();
                ?>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><?= $stats['total'] ?></h5>
                            <p class="card-text">Total</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning"><?= $stats['pendientes'] ?></h5>
                            <p class="card-text">En Espera</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-info"><?= $stats['pendiente_firma'] ?></h5>
                            <p class="card-text">Pend. Firma</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?= $stats['aprobados'] ?></h5>
                            <p class="card-text">Aprobados</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-danger"><?= $stats['rechazados'] ?></h5>
                            <p class="card-text">Rechazados</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de solicitudes -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Fecha Solicitud</th>
                            <th>Tipo Seguro</th>
                            <th>Estado</th>
                            <th>Póliza</th>
                            <th>Monto Asegurado</th>
                            <th>Documentos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result_solicitudes->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['usuario']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['cedula']) ?></small>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($row['correo']) ?><br>
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($row['telefono']) ?>
                                    </small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['fecha_solicitud'])) ?></td>
                                <td><?= htmlspecialchars($row['tipo_seguro'] ?? 'N/A') ?></td>
                                <td>
                                    <?php
                                    $badge_class = '';
                                    switch($row['estado']) {
                                        case 'En espera de aprobación':
                                            $badge_class = 'bg-warning text-dark';
                                            break;
                                        case 'Aprobado':
                                            $badge_class = 'bg-success';
                                            break;
                                        case 'Rechazado':
                                            $badge_class = 'bg-danger';
                                            break;
                                        case 'Pendiente de Firma':
                                            $badge_class = 'bg-info';
                                            break;
                                        default:
                                            $badge_class = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?> estado-badge">
                                        <?= htmlspecialchars($row['estado']) ?>
                                    </span>
                                    <br><small class="text-muted">Estado exacto: <code><?= htmlspecialchars($row['estado']) ?></code></small>
                                </td>
                                <td>
                                    <?php if ($row['numero_poliza']): ?>
                                        <strong><?= htmlspecialchars($row['numero_poliza']) ?></strong><br>
                                        <span class="badge bg-info"><?= htmlspecialchars($row['estado_poliza']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['monto_asegurado']): ?>
                                        <strong>$<?= number_format($row['monto_asegurado'], 2) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($row['tipo_pago']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    // Ver PDF
                                    $pdfPath = glob("../pdf/solicitud_seguro_{$row['usuario_id']}*.pdf");
                                    if ($pdfPath && file_exists($pdfPath[0])) {
                                        echo '<a href="' . $pdfPath[0] . '" target="_blank" class="btn btn-sm btn-primary mb-1" title="Ver solicitud PDF">
                                                <i class="fas fa-file-pdf"></i>
                                              </a><br>';
                                    }

                                    // Documentos de identificación
                                    if (!empty($row['cedula_escan_nombre'])) {
                                        echo '<a href="#" onclick="verDocumentos(\'' . $row['cedula_escan_nombre'] . '\', \'cedula\')" class="btn btn-sm btn-secondary mb-1" title="Ver documentos de identidad">
                                                <i class="fas fa-id-card"></i>
                                              </a><br>';
                                    }

                                    // Documentos adicionales
                                    if (!empty($row['documentos_adicionales_nombre'])) {
                                        echo '<a href="#" onclick="verDocumentos(\'' . $row['documentos_adicionales_nombre'] . '\', \'adicionales\')" class="btn btn-sm btn-warning mb-1" title="Ver documentos adicionales">
                                                <i class="fas fa-paperclip"></i>
                                              </a>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <!-- Debug: Mostrar condición -->
                                    <small class="text-muted d-block">
                                        Estado: "<?= htmlspecialchars($row['estado']) ?>"<br>
                                        ¿Es "En espera de aprobación"? <?= ($row['estado'] === 'En espera de aprobación') ? 'SÍ' : 'NO' ?>
                                    </small>
                                    
                                    <?php 
                                    // Condiciones más flexibles para mostrar botones
                                    $estados_pendientes = ['En espera de aprobación', 'Pendiente de Firma', 'pendiente', 'en espera'];
                                    $mostrar_botones = false;
                                    
                                    foreach ($estados_pendientes as $estado_pendiente) {
                                        if (stripos($row['estado'], $estado_pendiente) !== false) {
                                            $mostrar_botones = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($mostrar_botones): ?>
                                        <div class="action-form">
                                            <!-- Botón para aprobar -->
                                            <button type="button" class="btn btn-success btn-sm mb-2" 
                                                    onclick="mostrarModalAprobacion(<?= $row['id'] ?>, '<?= htmlspecialchars($row['usuario']) ?>')">
                                                <i class="fas fa-check"></i> Aprobar
                                            </button>
                                            
                                            <!-- Botón para rechazar -->
                                            <button type="button" class="btn btn-danger btn-sm mb-2" 
                                                    onclick="mostrarModalRechazo(<?= $row['id'] ?>, '<?= htmlspecialchars($row['usuario']) ?>')">
                                                <i class="fas fa-times"></i> Rechazar
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-check-circle"></i> Procesado
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center mt-4">
                <a href="panel_agente.php" class="btn btn-dark">
                    <i class="fas fa-arrow-left"></i> Regresar al Panel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal para aprobar solicitud -->
<div class="modal fade" id="modalAprobacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle"></i> Aprobar Solicitud
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="solicitud_id" id="aprobar_solicitud_id">
                    <input type="hidden" name="accion" value="aprobar">
                    
                    <div class="alert alert-info">
                        <strong>Cliente:</strong> <span id="aprobar_cliente_nombre"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Monto Asegurado *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" name="monto_asegurado" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Pago</label>
                        <select name="tipo_pago" class="form-select">
                            <option value="Mensual">Mensual</option>
                            <option value="Trimestral">Trimestral</option>
                            <option value="Semestral">Semestral</option>
                            <option value="Anual">Anual</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Notas adicionales sobre la aprobación..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Aprobar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para rechazar solicitud -->
<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white">
                    <i class="fas fa-times-circle"></i> Rechazar Solicitud
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="solicitud_id" id="rechazar_solicitud_id">
                    <input type="hidden" name="accion" value="rechazar">
                    
                    <div class="alert alert-warning">
                        <strong>Cliente:</strong> <span id="rechazar_cliente_nombre"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo del Rechazo *</label>
                        <select name="motivo_rechazo" class="form-select" required>
                            <option value="">Seleccione un motivo</option>
                            <option value="Documentación incompleta">Documentación incompleta</option>
                            <option value="Información inconsistente">Información inconsistente</option>
                            <option value="No cumple requisitos de edad">No cumple requisitos de edad</option>
                            <option value="Condiciones médicas preexistentes">Condiciones médicas preexistentes</option>
                            <option value="Información fraudulenta">Información fraudulenta</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Detalles adicionales</label>
                        <textarea name="motivo_rechazo_detalle" class="form-control" rows="3" placeholder="Especifique los detalles del rechazo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Rechazar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver documentos -->
<div class="modal fade" id="modalDocumentos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt"></i> Documentos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="documentos-content">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function mostrarModalAprobacion(solicitudId, clienteNombre) {
    document.getElementById('aprobar_solicitud_id').value = solicitudId;
    document.getElementById('aprobar_cliente_nombre').textContent = clienteNombre;
    new bootstrap.Modal(document.getElementById('modalAprobacion')).show();
}

function mostrarModalRechazo(solicitudId, clienteNombre) {
    document.getElementById('rechazar_solicitud_id').value = solicitudId;
    document.getElementById('rechazar_cliente_nombre').textContent = clienteNombre;
    new bootstrap.Modal(document.getElementById('modalRechazo')).show();
}

function verDocumentos(archivos, tipo) {
    const documentosArray = archivos.split(', ');
    let contenido = '<div class="row">';
    
    documentosArray.forEach((archivo, index) => {
        const ruta = '../archivos/' + archivo;
        contenido += `
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="card-title">${tipo === 'cedula' ? 'Documento de Identidad' : 'Documento Adicional'} ${index + 1}</h6>
                        <a href="${ruta}" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i> Ver Documento
                        </a>
                    </div>
                </div>
            </div>
        `;
    });
    
    contenido += '</div>';
    document.getElementById('documentos-content').innerHTML = contenido;
    new bootstrap.Modal(document.getElementById('modalDocumentos')).show();
}
</script>
</body>
</html>
