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

// Verificar si se proporcionó un ID de solicitud
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: notificaciones.php");
    exit();
}

$solicitud_id = intval($_GET['id']);

// Verificar que la solicitud pertenezca al usuario
$sql_solicitud = "SELECT sv.*, 
                 u_aprobador.usuario as aprobado_por_nombre,
                 u_rechazador.usuario as rechazado_por_nombre
                 FROM seguros_vida sv
                 LEFT JOIN usuarios u_aprobador ON sv.aprobado_por = u_aprobador.id
                 LEFT JOIN usuarios u_rechazador ON sv.rechazado_por = u_rechazador.id
                 WHERE sv.id = ? AND sv.usuario_id = ?";
$stmt_solicitud = $conn->prepare($sql_solicitud);
$stmt_solicitud->bind_param("ii", $solicitud_id, $usuario['id']);
$stmt_solicitud->execute();
$result_solicitud = $stmt_solicitud->get_result();

if ($result_solicitud->num_rows === 0) {
    header("Location: notificaciones.php");
    exit();
}

$solicitud = $result_solicitud->fetch_assoc();
$stmt_solicitud->close();

// Obtener documentos de validación
$sql_docs = "SELECT * FROM documentos_validacion WHERE solicitud_id = ? ORDER BY fecha_subida DESC";
$stmt_docs = $conn->prepare($sql_docs);
$stmt_docs->bind_param("i", $solicitud_id);
$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();
$documentos = [];
while ($doc = $result_docs->fetch_assoc()) {
    $documentos[] = $doc;
}
$stmt_docs->close();

// Obtener logs de firmas
$sql_firmas = "SELECT * FROM firmas_digitales_log WHERE solicitud_id = ? ORDER BY timestamp DESC";
$stmt_firmas = $conn->prepare($sql_firmas);
$stmt_firmas->bind_param("i", $solicitud_id);
$stmt_firmas->execute();
$result_firmas = $stmt_firmas->get_result();
$firmas = [];
while ($firma = $result_firmas->fetch_assoc()) {
    $firmas[] = $firma;
}
$stmt_firmas->close();

// Marcar notificaciones relacionadas como leídas
$sql_marcar = "UPDATE notificaciones SET leida = 1, fecha_lectura = NOW() 
              WHERE usuario_id = ? AND tipo_referencia = 'seguro' AND referencia_id = ? AND leida = 0";
$stmt_marcar = $conn->prepare($sql_marcar);
$stmt_marcar->bind_param("ii", $usuario['id'], $solicitud_id);
$stmt_marcar->execute();
$stmt_marcar->close();

// Procesar la subida de documentos adicionales si se envió el formulario
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_documentos'])) {
    if (isset($_FILES['documentos_adicionales']) && $_FILES['documentos_adicionales']['size'] > 0) {
        $archivo = $_FILES['documentos_adicionales'];
        $nombre_archivo = $archivo['name'];
        $tipo_archivo = $archivo['type'];
        $tamano_archivo = $archivo['size'];
        $temp_archivo = $archivo['tmp_name'];
        $error_archivo = $archivo['error'];
        
        // Validar el archivo
        $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $mensaje = "<div class='alert alert-danger'>Solo se permiten archivos PDF, JPG, JPEG o PNG.</div>";
        } elseif ($tamano_archivo > 5242880) { // 5MB
            $mensaje = "<div class='alert alert-danger'>El archivo es demasiado grande. Máximo 5MB.</div>";
        } elseif ($error_archivo !== 0) {
            $mensaje = "<div class='alert alert-danger'>Error al subir el archivo. Por favor, inténtalo de nuevo.</div>";
        } else {
            // Probar diferentes directorios
            $directorios = [
                "../archivos/",
                
            ];
            
            $directorio_usado = null;
            foreach ($directorios as $dir) {
                if (is_dir($dir) || mkdir($dir, 0755, true)) {
                    $directorio_usado = $dir;
                    break;
                }
            }
            
            if ($directorio_usado === null) {
                $mensaje = "<div class='alert alert-danger'>Error: No se pudo encontrar o crear un directorio para guardar el archivo.</div>";
            } else {
                // Generar nombre único para el archivo
                $nombre_unico = uniqid() . '_' . $nombre_archivo;
                $ruta_destino = $directorio_usado . $nombre_unico;
                
                if (move_uploaded_file($temp_archivo, $ruta_destino)) {
                    // Actualizar la solicitud con el nuevo documento
                    $nuevo_nombre = $solicitud['documentos_adicionales_nombre'] 
                        ? $solicitud['documentos_adicionales_nombre'] . ', ' . $nombre_unico 
                        : $nombre_unico;
                    
                    $sql_update = "UPDATE seguros_vida SET documentos_adicionales_nombre = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $nuevo_nombre, $solicitud_id);
                    
                    if ($stmt_update->execute()) {
                        $mensaje = "<div class='alert alert-success'>Documento adicional subido correctamente a {$directorio_usado}.</div>";
                        // Actualizar la información de la solicitud
                        $solicitud['documentos_adicionales_nombre'] = $nuevo_nombre;
                    } else {
                        $mensaje = "<div class='alert alert-danger'>Error al actualizar la base de datos.</div>";
                    }
                    
                    $stmt_update->close();
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al mover el archivo subido. Verifique los permisos del directorio.</div>";
                }
            }
        }
    } else {
        $mensaje = "<div class='alert alert-danger'>No se ha seleccionado ningún archivo.</div>";
    }
}

// Función para verificar si un archivo existe y es accesible
function verificarArchivo($ruta) {
    if (file_exists($ruta) && is_readable($ruta)) {
        return true;
    }
    return false;
}

// Función para obtener la ruta correcta de los archivos
function obtenerRutaArchivo($nombre, $tipo) {
    // Rutas posibles donde pueden estar los archivos
    $posibles_rutas = [
        'cedula' => [
            "../archivos/cedulas/$nombre",
            "../archivos/$nombre"
        ],
        'adicionales' => [
            "../archivos/$nombre"  // Buscar directamente en archivos/
        ]
    ];
    
    // Verificar cada posible ruta
    foreach ($posibles_rutas[$tipo] as $ruta) {
        if (verificarArchivo($ruta)) {
            return $ruta;
        }
    }
    
    // Si no se encuentra, devolver la primera ruta (para mostrar el error)
    return $posibles_rutas[$tipo][0];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de Solicitud - Sistema de Seguros</title>
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
        
        .firma-preview {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .process-step {
            text-align: center;
            padding: 20px;
            position: relative;
        }
        
        .process-step .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .process-step.completed .step-icon {
            background-color: var(--success-color);
            color: white;
        }
        
        .process-step.pending .step-icon {
            background-color: #e9ecef;
            color: #6c757d;
        }
        
        .process-step.current .step-icon {
            background-color: var(--primary-color);
            color: white;
        }
        
        
        .process-step.completed .process-connector,
        .process-step.current .process-connector {
            background-color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .process-connector {
                display: none;
            }
            
            .process-step {
                margin-bottom: 20px;
            }
        }
        
        .document-card {
            border-left: 4px solid var(--primary-color);
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .document-card .card-body {
            padding: 15px;
        }
        
        .modal-content {
            border-radius: 10px;
        }
        
        .modal-header {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .modal-body img, .modal-body embed {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline:before {
            content: '';
            position: absolute;
            left: 0px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 30px;
            top: 5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
        }
        
        .timeline-item .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .btn {
            background-color: #002147;
            color: #fff;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background-color: #00112a;
        }
        
        /* Estilos para el modal de documentos */
        .documento-error {
            padding: 20px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            color: #721c24;
            margin: 15px 0;
        }
        .documento-error h4 {
            margin-top: 0;
        }
        .documento-error pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow: auto;
        }
        
        /* Estilos para el botón de cerrar del modal */
        .btn-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
        }
        
        /* Estilos para el modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-dialog {
            margin: 30px auto;
            max-width: 800px;
        }
        
        .modal-content {
            position: relative;
            background-color: #fefefe;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            text-align: right;
        }
        
        .close {
            color: white;
            float: right;
            font-size: 28px;
            font-weight: bold;
            background: none;
            border: none;
        }
        
        .close:hover,
        .close:focus {
            color: #aaa;
            text-decoration: none;
            cursor: pointer;
        }
        
        .hash-code {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="page-title">
                    <i class="fas fa-file-alt me-2"></i>Detalles de la Solicitud #<?= $solicitud_id ?>
                </h1>
                <a href="notificaciones.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Volver
                </a>
            </div>
        </header>
        
        <?php if (!empty($mensaje)): ?>
            <?= $mensaje ?>
        <?php endif; ?>
        
        <!-- Resumen de estado -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Tipo de Seguro</h6>
                                <h4 class="mb-0"><?= htmlspecialchars($solicitud['tipo_seguro']) ?></h4>
                            </div>
                            <i class="fas fa-shield-alt fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-<?= $solicitud['estado'] === 'Aprobado' ? 'success' : ($solicitud['estado'] === 'Rechazado' ? 'danger' : 'warning') ?> text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Estado</h6>
                                <h4 class="mb-0"><?= htmlspecialchars($solicitud['estado']) ?></h4>
                            </div>
                            <i class="fas fa-<?= $solicitud['estado'] === 'Aprobado' ? 'check-circle' : ($solicitud['estado'] === 'Rechazado' ? 'times-circle' : 'clock') ?> fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Monto del Seguro</h6>
                                <h4 class="mb-0">$<?= number_format($solicitud['monto_seguro'], 2) ?></h4>
                            </div>
                            <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información general -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información General</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h5><i class="fas fa-calendar-alt me-2"></i>Fechas</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Solicitud:</span>
                                    <strong><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></strong>
                                </li>
                                <?php if ($solicitud['estado'] === 'Aprobado'): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Aprobación:</span>
                                        <strong><?= date('d/m/Y', strtotime($solicitud['fecha_aprobacion'])) ?></strong>
                                    </li>
                                <?php elseif ($solicitud['estado'] === 'Rechazado'): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Rechazo:</span>
                                        <strong><?= date('d/m/Y', strtotime($solicitud['fecha_rechazo'])) ?></strong>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h5><i class="fas fa-money-bill-wave me-2"></i>Pago</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Forma de Pago:</span>
                                    <strong><?= htmlspecialchars($solicitud['forma_pago'] ?? 'No especificada') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Frecuencia:</span>
                                    <strong><?= htmlspecialchars($solicitud['frecuencia_pago'] ?? 'No especificada') ?></strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if ($solicitud['estado'] === 'Aprobado'): ?>
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle fa-2x me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Solicitud Aprobada</h5>
                                        <p class="mb-0">Número de Póliza: <strong><?= htmlspecialchars($solicitud['numero_poliza']) ?></strong></p>
                                        <p class="mb-0">Aprobado por: <strong><?= htmlspecialchars($solicitud['aprobado_por_nombre']) ?></strong></p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($solicitud['estado'] === 'Rechazado'): ?>
                            <div class="alert alert-danger">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-times-circle fa-2x me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Solicitud Rechazada</h5>
                                        <p class="mb-0">Rechazado por: <strong><?= htmlspecialchars($solicitud['rechazado_por_nombre']) ?></strong></p>
                                        <p class="mb-0">Motivo: <strong><?= htmlspecialchars($solicitud['motivo_rechazo']) ?></strong></p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($solicitud['estado'] === 'Pendiente de Firma' && empty($solicitud['firma'])): ?>
                            <div class="alert alert-warning">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Firma Pendiente</h5>
                                        <p class="mb-2">Esta solicitud requiere tu firma digital para continuar con el proceso.</p>
                                        <a href="firmar_documento.php?id=<?= $solicitud_id ?>" class="btn btn-primary">
                                            <i class="fas fa-signature me-1"></i> Firmar Documento
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">En Proceso</h5>
                                        <p class="mb-0">Tu solicitud está siendo revisada por nuestro equipo.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($solicitud['estado'] === 'Aprobado'): ?>
                            <div class="alert alert-info mt-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-pdf fa-2x me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Documento Final</h5>
                                        <?php
                                        $pdfPath = glob("../pdf/solicitud_seguro_{$solicitud['usuario_id']}*.pdf");
                                        if ($pdfPath && file_exists($pdfPath[0])):
                                        ?>
                                            <a href="<?= $pdfPath[0] ?>" target="_blank" class="btn btn-danger">
                                                <i class="fas fa-download me-1"></i> Descargar Póliza
                                            </a>
                                        <?php else: ?>
                                            <p class="mb-0">El documento final estará disponible pronto.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Proceso de documentación -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-project-diagram me-2"></i>Proceso de Documentación</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 process-step <?= !empty($solicitud['cedula_escan_nombre']) ? 'completed' : 'pending' ?>">
                        <div class="process-connector"></div>
                        <div class="step-icon">
                            <i class="fas fa-upload"></i>
                        </div>
                        <h5>Documentos</h5>
                        <p><?= !empty($solicitud['cedula_escan_nombre']) ? 'Completado' : 'Pendiente' ?></p>
                    </div>
                    
                    <div class="col-md-3 process-step <?= $solicitud['firma'] ? 'completed' : ($solicitud['estado'] === 'Pendiente de Firma' ? 'current' : 'pending') ?>">
                        <div class="process-connector"></div>
                        <div class="step-icon">
                            <i class="fas fa-signature"></i>
                        </div>
                        <h5>Firma Digital</h5>
                        <p><?= $solicitud['firma'] ? 'Completado' : ($solicitud['estado'] === 'Pendiente de Firma' ? 'Pendiente' : 'Pendiente') ?></p>
                    </div>
                    
                    <div class="col-md-3 process-step <?= $solicitud['estado'] === 'Aprobado' || $solicitud['estado'] === 'Rechazado' ? 'completed' : 'pending' ?>">
                        <div class="process-connector"></div>
                        <div class="step-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h5>Verificación</h5>
                        <p><?= $solicitud['estado'] === 'Aprobado' || $solicitud['estado'] === 'Rechazado' ? 'Completado' : 'Pendiente' ?></p>
                    </div>
                    
                    <div class="col-md-3 process-step <?= $solicitud['estado'] === 'Aprobado' ? 'completed' : 'pending' ?>">
                        <div class="step-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h5>Certificación</h5>
                        <p><?= $solicitud['estado'] === 'Aprobado' ? 'Completado' : 'Pendiente' ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Documentos -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-file me-2"></i>Documentos</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="document-card card mb-4">
                            <div class="card-body">
                                <h5><i class="fas fa-id-card me-2"></i>Documentos de Identificación</h5>
                                <?php if (!empty($solicitud['cedula_escan_nombre'])): ?>
                                    <p class="mb-2"><strong>Archivos:</strong> <?= htmlspecialchars($solicitud['cedula_escan_nombre']) ?></p>
                                    <div class="action-buttons mt-3">
                                        <button class="btn btn-primary" onclick="verDocumentos('<?= htmlspecialchars($solicitud['cedula_escan_nombre']) ?>', 'cedula')">
                                            <i class="fas fa-eye me-1"></i> Ver Documentos
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-circle me-1"></i> No hay documentos de identificación cargados.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="document-card card mb-4">
                            <div class="card-body">
                                <h5><i class="fas fa-paperclip me-2"></i>Documentos Adicionales</h5>
                                <?php if (!empty($solicitud['documentos_adicionales_nombre'])): ?>
                                    <p class="mb-2"><strong>Archivos:</strong> <?= htmlspecialchars($solicitud['documentos_adicionales_nombre']) ?></p>
                                    <div class="action-buttons mt-3">
                                        <button type="button" class="btn btn-primary" onclick="verDocumentosSimple('<?= htmlspecialchars($solicitud['documentos_adicionales_nombre']) ?>', 'adicionales')">
                                            <i class="fas fa-eye me-1"></i> Ver Documentos
                                        </button>
                                        
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-circle me-1"></i> No hay documentos adicionales cargados.
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#subirDocumentosModal">
                                                <i class="fas fa-upload me-1"></i> Subir Documentos
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PDF de la solicitud -->
                <div class="document-card card">
                    <div class="card-body">
                        <h5><i class="fas fa-file-pdf me-2"></i>Documento Final</h5>
                        <?php
                        $pdfPath = glob("../pdf/solicitud_seguro_{$solicitud['usuario_id']}*.pdf");
                        if ($pdfPath && file_exists($pdfPath[0])):
                        ?>
                            <p class="mb-2"><strong>Archivo:</strong> <?= basename($pdfPath[0]) ?></p>
                            <div class="action-buttons mt-3">
                                <a href="<?= $pdfPath[0] ?>" target="_blank" class="btn btn-danger">
                                    <i class="fas fa-file-pdf me-1"></i> Ver PDF
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-circle me-1"></i> No se encontró el PDF de la solicitud.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Firma Digital -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-signature me-2"></i>Firma Digital</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($solicitud['firma'])): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle fa-2x me-3"></i>
                                    <div>
                                        <h5 class="alert-heading mb-1">Firma Digital Registrada</h5>
                                        <p class="mb-0">Tu firma ha sido validada y almacenada de forma segura.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5><i class="fas fa-info-circle me-2"></i>Detalles de la Firma</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Fecha de firma:</span>
                                        <strong><?= $solicitud['timestamp_firma'] ? date('d/m/Y H:i:s', strtotime($solicitud['timestamp_firma'])) : 'No disponible' ?></strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Hash de verificación:</span>
                                        <?php if (!empty($solicitud['hash_firma'])): ?>
                                            <span class="hash-code"><?= substr($solicitud['hash_firma'], 0, 16) ?>...</span>
                                            <button class="btn btn-sm btn-info ms-2" 
                                                    onclick="mostrarHashCompleto('<?= htmlspecialchars($solicitud['hash_firma']) ?>')"
                                                    title="Ver hash completo">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-warning">No disponible</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="text-center">
                                <h5><i class="fas fa-signature me-2"></i>Tu Firma Digital</h5>
                                <?php
                                $firma_base64 = base64_encode($solicitud['firma']);
                                ?>
                                <img src="data:image/png;base64,<?= $firma_base64 ?>" alt="Firma Digital" class="firma-preview img-fluid" style="max-width: 300px;">
                                <p class="text-muted mt-2">Esta es la imagen de tu firma digital registrada en el sistema.</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($firmas)): ?>
                        <div class="mt-4">
                            <h5><i class="fas fa-history me-2"></i>Historial de Firmas</h5>
                            <div class="timeline">
                                <?php foreach ($firmas as $firma): ?>
                                    <div class="timeline-item">
                                        <div class="card mb-2">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($firma['archivo_firma']) ?></h6>
                                                    <span class="timeline-date"><?= date('d/m/Y H:i', strtotime($firma['timestamp'])) ?></span>
                                                </div>
                                                <p class="mb-1">Dirección IP: <?= htmlspecialchars($firma['ip_address']) ?></p>
                                                <span class="badge <?= $firma['validada'] ? 'bg-success' : 'bg-warning' ?>">
                                                    <?= $firma['validada'] ? 'Validada' : 'Pendiente' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-warning">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <h5 class="alert-heading mb-1">Firma Digital Pendiente</h5>
                                <p class="mb-2">Esta solicitud requiere tu firma digital para continuar con el proceso.</p>
                                <a href="firmar_documento.php?id=<?= $solicitud_id ?>" class="btn btn-primary">
                                    <i class="fas fa-signature me-1"></i> Firmar Documento
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal para ver documentos -->
    <div class="modal fade" id="documentosModal" tabindex="-1" aria-labelledby="documentosModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="documentosModalLabel">Visualización de Documentos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="documentosModalBody">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para subir documentos adicionales -->
    <div class="modal fade" id="subirDocumentosModal" tabindex="-1" aria-labelledby="subirDocumentosModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subirDocumentosModalLabel">Subir Documentos Adicionales</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="documentos_adicionales" class="form-label">Selecciona el documento:</label>
                            <input type="file" class="form-control" id="documentos_adicionales" name="documentos_adicionales" required>
                            <div class="form-text">Formatos permitidos: PDF, JPG, JPEG, PNG. Tamaño máximo: 5MB.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="subir_documentos" class="btn btn-primary">Subir Documento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal para mostrar hash completo -->
    <div class="modal fade" id="hashModal" tabindex="-1" aria-labelledby="hashModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hashModalLabel">Hash de Verificación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <p>El hash de verificación es una huella digital única que garantiza la integridad de tu firma digital.</p>
                    </div>
                    <div class="p-3 bg-light border rounded">
                        <code id="hashCompleto" style="word-break: break-all;"></code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="copiarHash()">
                        <i class="fas fa-copy me-1"></i> Copiar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Versión simplificada para ver documentos (evita problemas de carga)
       // Versión simplificada para ver documentos (evita problemas de carga)
function verDocumentosSimple(nombreArchivos, tipo) {
    const rutasBase = tipo === 'cedula' 
        ? ['../archivos/cedulas/', '../archivos/'] 
        : ['../archivos/'];  // Documentos adicionales directo en archivos/
    
    const archivos = nombreArchivos.split(',');
    
    archivos.forEach(archivo => {
        const archivoTrim = archivo.trim();
        if (!archivoTrim) return;
        
        // Intentar con cada ruta posible
        rutasBase.forEach(ruta => {
            const rutaCompleta = ruta + archivoTrim;
            window.open(rutaCompleta, '_blank');
        });
    });
}
        
        // Función para mostrar documentos en modal (versión mejorada)
       // Función para mostrar documentos en modal (versión mejorada)
function verDocumentos(nombreArchivos, tipo) {
    const modalElement = document.getElementById('documentosModal');
    const modalBody = document.getElementById('documentosModalBody');
    modalBody.innerHTML = '';
    
    const archivos = nombreArchivos.split(',');
    
    // Configurar rutas según el tipo de documento
    const rutasBase = tipo === 'cedula' 
        ? ['../archivos/cedulas/', '../archivos/'] 
        : ['../archivos/'];  // Documentos adicionales van directo a archivos/
    
    archivos.forEach(archivo => {
        const archivoTrim = archivo.trim();
        if (!archivoTrim) return;
        
        const extension = archivoTrim.split('.').pop().toLowerCase();
        const fileContainer = document.createElement('div');
        fileContainer.className = 'mb-4';
        
        // Título del archivo
        const fileTitle = document.createElement('h5');
        fileTitle.textContent = archivoTrim;
        fileContainer.appendChild(fileTitle);
        
        // Intentar cargar desde cada ruta posible
        rutasBase.forEach((ruta, index) => {
            const rutaCompleta = ruta + archivoTrim;
            const contentDiv = document.createElement('div');
            contentDiv.className = 'mb-3';
            contentDiv.innerHTML = `<p class="small text-muted">Ubicación: ${rutaCompleta}</p>`;
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                const img = document.createElement('img');
                img.src = rutaCompleta;
                img.className = 'img-fluid rounded';
                img.alt = archivoTrim;
                img.onerror = function() {
                    this.parentNode.innerHTML += '<div class="alert alert-danger mt-2">No se pudo cargar la imagen.</div>';
                };
                contentDiv.appendChild(img);
            } else if (extension === 'pdf') {
                const embed = document.createElement('embed');
                embed.src = rutaCompleta;
                embed.type = 'application/pdf';
                embed.width = '100%';
                embed.height = '500px';
                embed.className = 'rounded border';
                embed.onerror = function() {
                    this.parentNode.innerHTML += '<div class="alert alert-danger mt-2">No se pudo cargar el PDF.</div>';
                };
                contentDiv.appendChild(embed);
                
                const pdfLink = document.createElement('a');
                pdfLink.href = rutaCompleta;
                pdfLink.className = 'btn btn-sm btn-outline-primary mt-2';
                pdfLink.textContent = 'Abrir en nueva pestaña';
                pdfLink.target = '_blank';
                contentDiv.appendChild(pdfLink);
            } else {
                const downloadLink = document.createElement('a');
                downloadLink.href = rutaCompleta;
                downloadLink.className = 'btn btn-primary';
                downloadLink.textContent = 'Descargar archivo';
                downloadLink.target = '_blank';
                contentDiv.appendChild(downloadLink);
            }
            
            fileContainer.appendChild(contentDiv);
        });
        
        modalBody.appendChild(fileContainer);
    });
    
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}
        
        // Función para mostrar el hash completo
        function mostrarHashCompleto(hash) {
            const hashElement = document.getElementById('hashCompleto');
            if (hashElement) {
                hashElement.textContent = hash;
                const modal = new bootstrap.Modal(document.getElementById('hashModal'));
                modal.show();
            }
        }
        
        // Función para copiar el hash al portapapeles
        function copiarHash() {
            const hashText = document.getElementById('hashCompleto').textContent;
            navigator.clipboard.writeText(hashText).then(() => {
                alert('Hash copiado al portapapeles');
            }).catch(err => {
                console.error('Error al copiar: ', err);
            });
        }
    </script>
</body>
</html>