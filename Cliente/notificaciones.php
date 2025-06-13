<?php
session_start();
include '../conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$correo_sesion = $_SESSION['usuario'];

// Obtener datos del usuario
$sql_usuario = "SELECT * FROM usuarios WHERE correo = ?";
$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("s", $correo_sesion);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();
$usuario = $result_usuario->fetch_assoc();
$stmt_usuario->close();

// Obtener notificaciones del usuario
$sql_notificaciones = "SELECT n.*, s.tipo_seguro, s.estado as estado_seguro 
                      FROM notificaciones n 
                      LEFT JOIN seguros_vida s ON n.referencia_id = s.id AND n.tipo_referencia = 'seguro'
                      WHERE n.usuario_id = ? 
                      ORDER BY n.fecha_creacion DESC";
$stmt_notificaciones = $conn->prepare($sql_notificaciones);
$stmt_notificaciones->bind_param("i", $usuario['id']);
$stmt_notificaciones->execute();
$result_notificaciones = $stmt_notificaciones->get_result();

// Marcar notificaciones como leídas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_leidas'])) {
    $sql_marcar = "UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0";
    $stmt_marcar = $conn->prepare($sql_marcar);
    $stmt_marcar->bind_param("i", $usuario['id']);
    $stmt_marcar->execute();
    header("Location: notificaciones.php");
    exit();
}

// Contar notificaciones no leídas
$sql_count = "SELECT COUNT(*) as total FROM notificaciones WHERE usuario_id = ? AND leida = 0";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $usuario['id']);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$count = $result_count->fetch_assoc()['total'];
$stmt_count->close();

// Obtener estado de documentos y firmas
$sql_documentos = "SELECT sv.id, sv.tipo_seguro, sv.estado, sv.cedula_escan_nombre, 
                  sv.documentos_adicionales_nombre, sv.firma IS NOT NULL as tiene_firma,
                  sv.fecha_solicitud, sv.fecha_aprobacion, sv.fecha_rechazo
                  FROM seguros_vida sv 
                  WHERE sv.usuario_id = ? 
                  ORDER BY sv.fecha_solicitud DESC";
$stmt_documentos = $conn->prepare($sql_documentos);
$stmt_documentos->bind_param("i", $usuario['id']);
$stmt_documentos->execute();
$result_documentos = $stmt_documentos->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Notificaciones - Sistema de Seguros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard-styles.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --info-color: #1abc9c;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            border: none;
        }
        
        .card-header {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .page-title {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        .stats-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card i {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .list-group-item {
            margin-bottom: 10px;
            border-radius: 8px !important;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        
        .list-group-item.unread {
            border-left: 4px solid var(--primary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .badge {
            font-weight: 500;
            padding: 6px 10px;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .badge-danger {
            background-color: var(--danger-color);
        }
        
        .badge-info {
            background-color: var(--info-color);
        }
        
        .badge-primary {
            background-color: var(--primary-color);
        }
        
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 15px;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .notification-icon.info {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .notification-icon.success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }
        
        .notification-icon.warning {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--warning-color);
        }
        
        .notification-icon.danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .flujo-proceso {
            
            position: relative;
            padding-bottom: 20px;
        }
        
        
        @media (max-width: 992px) {
            .flujo-proceso:before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="page-title">
                    <i class="fas fa-bell me-2"></i>Centro de Notificaciones
                </h1>
                <a href="clientedash.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Panel
                </a>
            </div>
        </header>
        
        <!-- Alertas informativas -->
        <div class="alert alert-info d-flex align-items-center">
            <div class="notification-icon info">
                <i class="fas fa-info-circle"></i>
            </div>
            <div>
                <h5 class="alert-heading mb-1">Bienvenido al Centro de Notificaciones</h5>
                <p class="mb-0">Aquí encontrarás todas las actualizaciones sobre tus documentos, firmas digitales y solicitudes de seguro.</p>
            </div>
        </div>
        
        <!-- Resumen de estado -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Solicitudes Activas</h6>
                                <h3 class="mb-0"><?= $result_documentos->num_rows ?></h3>
                            </div>
                            <i class="fas fa-file-contract fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Documentos Firmados</h6>
                                <h3 class="mb-0">
                                    <?php 
                                    $firmados = 0;
                                    $result_documentos->data_seek(0);
                                    while($doc = $result_documentos->fetch_assoc()) {
                                        if($doc['tiene_firma']) $firmados++;
                                    }
                                    echo $firmados;
                                    ?>
                                </h3>
                            </div>
                            <i class="fas fa-signature fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Notificaciones Nuevas</h6>
                                <h3 class="mb-0"><?= $count ?></h3>
                            </div>
                            <i class="fas fa-bell fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Estado de documentos y firmas -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-file-signature me-2"></i>Estado de Documentos</h4>
                <span class="badge bg-light text-dark"><?= $result_documentos->num_rows ?> solicitudes</span>
            </div>
            <div class="card-body">
                <?php if ($result_documentos->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo de Seguro</th>
                                    <th>Estado</th>
                                    <th>Documentos</th>
                                    <th>Firma</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $result_documentos->data_seek(0); ?>
                                <?php while ($doc = $result_documentos->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($doc['tipo_seguro']) ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = '';
                                            switch($doc['estado']) {
                                                case 'En espera de aprobación':
                                                    $badge_class = 'bg-warning';
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
                                            <span class="badge <?= $badge_class ?>">
                                                <?= htmlspecialchars($doc['estado']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($doc['cedula_escan_nombre']) || !empty($doc['documentos_adicionales_nombre'])): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Completos
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-circle me-1"></i> Pendientes
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($doc['tiene_firma']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-signature me-1"></i> Firmado
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-pen me-1"></i> Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($doc['fecha_solicitud'])) ?>
                                        </td>
                                        <td>
                                            <div class="d-flex action-buttons">
                                                <a href="ver_solicitud.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($doc['estado'] === 'Pendiente de Firma'): ?>
                                                    <a href="firmar_documento.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-success" title="Firmar documento">
                                                        <i class="fas fa-signature"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fa-lg"></i>
                        <div>
                            <h5 class="alert-heading mb-1">No tienes solicitudes activas</h5>
                            <p class="mb-0">Puedes iniciar una nueva solicitud de seguro desde tu panel principal.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Flujo del proceso -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-project-diagram me-2"></i>Proceso de Documentación</h4>
            </div>
            <div class="card-body">
                <div class="row text-center flujo-proceso">
                    <div class="col-md-3 mb-4 mb-md-0">
                        <div class="stats-card">
                            <div class="notification-icon info mb-3">
                                <i class="fas fa-upload fa-lg"></i>
                            </div>
                            <h5>1. Subir Documentos</h5>
                            <p class="text-muted">Carga los documentos requeridos para tu solicitud</p>
                            <span class="badge bg-primary rounded-pill">Paso 1</span>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4 mb-md-0">
                        <div class="stats-card">
                            <div class="notification-icon success mb-3">
                                <i class="fas fa-signature fa-lg"></i>
                            </div>
                            <h5>2. Firma Digital</h5>
                            <p class="text-muted">Firma digitalmente tu solicitud</p>
                            <span class="badge bg-primary rounded-pill">Paso 2</span>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4 mb-md-0">
                        <div class="stats-card">
                            <div class="notification-icon warning mb-3">
                                <i class="fas fa-check-circle fa-lg"></i>
                            </div>
                            <h5>3. Confirmación</h5>
                            <p class="text-muted">Recibe confirmación por email y notificación</p>
                            <span class="badge bg-primary rounded-pill">Paso 3</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="notification-icon danger mb-3">
                                <i class="fas fa-file-pdf fa-lg"></i>
                            </div>
                            <h5>4. Documento Final</h5>
                            <p class="text-muted">Recibe el PDF certificado con todas las firmas</p>
                            <span class="badge bg-primary rounded-pill">Paso 4</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notificaciones -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-bell me-2"></i>Tus Notificaciones</h4>
                <div>
                    <?php if ($count > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="marcar_leidas" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-check-double me-1"></i> Marcar como leídas
                            </button>
                        </form>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark ms-2"><?= $result_notificaciones->num_rows ?> notificaciones</span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($result_notificaciones->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($notif = $result_notificaciones->fetch_assoc()): ?>
                            <a href="<?= !empty($notif['accion_url']) ? htmlspecialchars($notif['accion_url']) : '#' ?>" 
                               class="list-group-item list-group-item-action <?= $notif['leida'] ? '' : 'unread' ?>">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon <?= $notif['leida'] ? 'info' : 'warning' ?>">
                                        <i class="fas fa-<?= $notif['leida'] ? 'envelope-open' : 'envelope' ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="d-flex justify-content-between">
                                            <h5 class="mb-1">
                                                <?= htmlspecialchars($notif['titulo']) ?>
                                            </h5>
                                            <small class="notification-time"><?= date('d/m/Y H:i', strtotime($notif['fecha_creacion'])) ?></small>
                                        </div>
                                        <p class="mb-1"><?= htmlspecialchars($notif['mensaje']) ?></p>
                                        <?php if ($notif['tipo_referencia'] === 'seguro' && !empty($notif['tipo_seguro'])): ?>
                                            <div class="d-flex align-items-center mt-2">
                                                <span class="badge bg-light text-dark me-2">
                                                    <i class="fas fa-shield-alt me-1"></i> <?= htmlspecialchars($notif['tipo_seguro']) ?>
                                                </span>
                                                <span class="badge <?= $notif['estado_seguro'] === 'Aprobado' ? 'bg-success' : ($notif['estado_seguro'] === 'Rechazado' ? 'bg-danger' : 'bg-warning') ?>">
                                                    <?= htmlspecialchars($notif['estado_seguro']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fa-lg"></i>
                        <div>
                            <h5 class="alert-heading mb-1">No tienes notificaciones</h5>
                            <p class="mb-0">Aquí aparecerán las actualizaciones sobre tus documentos y solicitudes.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tooltip initialization
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>