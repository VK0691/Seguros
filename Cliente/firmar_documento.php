<?php
session_start();
include '../conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$correo_sesion = $_SESSION['usuario'];
$mensaje = "";

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
$sql_solicitud = "SELECT * FROM seguros_vida WHERE id = ? AND usuario_id = ?";
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

// Procesar la firma digital
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si se subió una firma
    if (isset($_FILES['firma']) && $_FILES['firma']['size'] > 0) {
        $ext = pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
        
        // Validar tipo de archivo
        if (!in_array(strtolower($ext), ['png', 'jpg', 'jpeg'])) {
            $mensaje = "<div class='alert alert-danger d-flex align-items-center'>
                        <i class='fas fa-exclamation-circle me-3 fa-lg'></i>
                        <div>
                            <h5 class='alert-heading mb-1'>Error en formato</h5>
                            <p class='mb-0'>La firma debe ser una imagen PNG, JPG o JPEG</p>
                        </div>
                    </div>";
        } 
        // Validar tamaño
        else if ($_FILES['firma']['size'] > 2097152) { // 2MB
            $mensaje = "<div class='alert alert-danger d-flex align-items-center'>
                        <i class='fas fa-exclamation-circle me-3 fa-lg'></i>
                        <div>
                            <h5 class='alert-heading mb-1'>Archivo demasiado grande</h5>
                            <p class='mb-0'>La imagen de la firma es demasiado grande. Máximo 2MB</p>
                        </div>
                    </div>";
        } 
        else {
            // Crear directorio si no existe
            if (!is_dir('../firmas/')) {
                mkdir('../firmas/', 0755, true);
            }
            
            // Generar nombre único para la firma
            $firma_filename = 'firma_' . $usuario['id'] . '_' . time() . '.' . $ext;
            $firma_path = '../firmas/' . $firma_filename;
            
            if (move_uploaded_file($_FILES['firma']['tmp_name'], $firma_path)) {
                // Leer la firma como datos binarios
                $firma_data = file_get_contents($firma_path);
                
                // Actualizar la solicitud con la firma
                $sql_update = "UPDATE seguros_vida SET 
                              firma = ?, 
                              estado = 'En espera de aprobación',
                              hash_firma = ?,
                              ip_firma = ?,
                              user_agent_firma = ?,
                              timestamp_firma = NOW()
                              WHERE id = ?";
                              
                $stmt_update = $conn->prepare($sql_update);
                $hash_firma = hash_file('sha256', $firma_path);
                $ip_firma = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                
                $stmt_update->bind_param("bsssi", $firma_data, $hash_firma, $ip_firma, $user_agent, $solicitud_id);
                
                if ($stmt_update->execute()) {
                    // Registrar en el log de firmas
                    $sql_log = "INSERT INTO firmas_digitales_log 
                               (usuario_id, solicitud_id, archivo_firma, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?)";
                    $stmt_log = $conn->prepare($sql_log);
                    $stmt_log->bind_param("iisss", $usuario['id'], $solicitud_id, $firma_filename, $ip_firma, $user_agent);
                    $stmt_log->execute();
                    
                    $mensaje = "<div class='alert alert-success d-flex align-items-center'>
    <i class='fas fa-check-circle me-3 fa-lg'></i>
    <div>
        <h5 class='alert-heading mb-1'>Firma registrada correctamente</h5>
        <p class='mb-0'>Tu solicitud ha sido actualizada a 'En espera de aprobación'. Serás redirigido en 3 segundos...</p>
    </div>
</div>";
                    
                    // Redirigir después de 3 segundos
                    header("refresh:3;url=ver_solicitud.php?id=" . $solicitud_id);
                } else {
                    $mensaje = "<div class='alert alert-danger d-flex align-items-center'>
                                <i class='fas fa-exclamation-circle me-3 fa-lg'></i>
                                <div>
                                    <h5 class='alert-heading mb-1'>Error al guardar la firma</h5>
                                    <p class='mb-0'>" . $stmt_update->error . "</p>
                                </div>
                            </div>";
                }
                
                $stmt_update->close();
            } else {
                $mensaje = "<div class='alert alert-danger d-flex align-items-center'>
                            <i class='fas fa-exclamation-circle me-3 fa-lg'></i>
                            <div>
                                <h5 class='alert-heading mb-1'>Error al subir la firma</h5>
                                <p class='mb-0'>Por favor, intenta nuevamente</p>
                            </div>
                        </div>";
            }
        }
    } else if (isset($_POST['firma_canvas']) && !empty($_POST['firma_canvas'])) {
        // Procesar firma desde canvas
        $firma_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['firma_canvas']));
        
        // Crear directorio si no existe
        if (!is_dir('../firmas/')) {
            mkdir('../firmas/', 0755, true);
        }
        
        // Guardar la imagen en el servidor
        $firma_filename = 'firma_canvas_' . $usuario['id'] . '_' . time() . '.png';
        $firma_path = '../firmas/' . $firma_filename;
        file_put_contents($firma_path, $firma_data);
        
        // Actualizar la solicitud con la firma
        $sql_update = "UPDATE seguros_vida SET 
                      firma = ?, 
                      estado = 'En espera de aprobación',
                      hash_firma = ?,
                      ip_firma = ?,
                      user_agent_firma = ?,
                      timestamp_firma = NOW()
                      WHERE id = ?";
                      
        $stmt_update = $conn->prepare($sql_update);
        $hash_firma = hash_file('sha256', $firma_path);
        $ip_firma = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt_update->bind_param("bsssi", $firma_data, $hash_firma, $ip_firma, $user_agent, $solicitud_id);
        
        if ($stmt_update->execute()) {
            // Registrar en el log de firmas
            $sql_log = "INSERT INTO firmas_digitales_log 
                       (usuario_id, solicitud_id, archivo_firma, ip_address, user_agent) 
                       VALUES (?, ?, ?, ?, ?)";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("iisss", $usuario['id'], $solicitud_id, $firma_filename, $ip_firma, $user_agent);
            $stmt_log->execute();
            
            $mensaje = "<div class='alert alert-success d-flex align-items-center'>
    <i class='fas fa-check-circle me-3 fa-lg'></i>
    <div>
        <h5 class='alert-heading mb-1'>Firma registrada correctamente</h5>
        <p class='mb-0'>Tu solicitud ha sido actualizada a 'En espera de aprobación'. Serás redirigido en 3 segundos...</p>
    </div>
</div>";
            
            // Redirigir después de 3 segundos
            header("refresh:3;url=ver_solicitud.php?id=" . $solicitud_id);
        } else {
            $mensaje = "<div class='alert alert-danger d-flex align-items-center'>
                        <i class='fas fa-exclamation-circle me-3 fa-lg'></i>
                        <div>
                            <h5 class='alert-heading mb-1'>Error al guardar la firma</h5>
                            <p class='mb-0'>" . $stmt_update->error . "</p>
                        </div>
                    </div>";
        }
        
        $stmt_update->close();
    } else {
        $mensaje = "<div class='alert alert-danger d-flex align-items-center'>
                    <i class='fas fa-exclamation-circle me-3 fa-lg'></i>
                    <div>
                        <h5 class='alert-heading mb-1'>Firma requerida</h5>
                        <p class='mb-0'>No se ha proporcionado una firma</p>
                    </div>
                </div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Firma Digital - Sistema de Seguros</title>
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
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            border: none;
        }
        
        .card-header {
            background-color: var(--secondary-color);
            color: white;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0 !important;
            padding: 15px 20px;
        }
        
        .page-title {
            color: var(--secondary-color);
            margin-bottom: 25px;
            font-weight: 600;
        }
        
        #firma-canvas {
            border: 2px dashed var(--primary-color);
            border-radius: var(--border-radius-lg);
            cursor: crosshair;
            background-color: white;
            width: 100%;
            height: 200px;
            touch-action: none;
        }
        
        .firma-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .firma-tab {
            padding: 12px 24px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-right: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .firma-tab:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .firma-tab.active {
            background-color: var(--primary-color);
            color: white;
            border-color: #dee2e6;
        }
        
        .firma-tab-content {
            display: none;
            padding: 20px;
            background: white;
            border-radius: 0 0 var(--border-radius-lg) var(--border-radius-lg);
        }
        
        .firma-tab-content.active {
            display: block;
        }
        
        .firma-preview {
            max-width: 100%;
            max-height: 200px;
            border: 1px solid #dee2e6;
            border-radius: var(--border-radius);
            margin-top: 10px;
            display: none;
        }
        
        .btn-signature {
            padding: 10px 20px;
            font-weight: 500;
            border-radius: var(--border-radius);
        }
        
        .signature-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .signature-info {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 30px;
        }
        
        .signature-info h6 {
            color: var(--secondary-color);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .signature-info ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .signature-info li {
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .firma-tabs {
                flex-direction: column;
                border-bottom: none;
            }
            
            .firma-tab {
                border-radius: 0;
                margin-right: 0;
                border: 1px solid #dee2e6;
                border-bottom: none;
            }
            
            .firma-tab:first-child {
                border-radius: var(--border-radius) var(--border-radius) 0 0;
            }
            
            .firma-tab:last-child {
                border-bottom: 1px solid #dee2e6;
                border-radius: 0 0 var(--border-radius) var(--border-radius);
            }
            
            .signature-actions {
                flex-direction: column;
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
                    <i class="fas fa-signature me-2"></i>Firma Digital
                </h1>
                <a href="notificaciones.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Volver
                </a>
            </div>
        </header>
        
        <?= $mensaje ?>
        
        <!-- Información de la solicitud -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-file-contract me-2"></i>Solicitud #<?= $solicitud_id ?></h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h5><i class="fas fa-shield-alt me-2"></i>Detalles del Seguro</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Tipo:</span>
                                    <strong><?= htmlspecialchars($solicitud['tipo_seguro']) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Estado:</span>
                                    <span class="badge bg-<?= $solicitud['estado'] === 'Pendiente de Firma' ? 'warning' : ($solicitud['estado'] === 'Aprobado' ? 'success' : ($solicitud['estado'] === 'Rechazado' ? 'danger' : 'info')) ?>">
                                        <?= htmlspecialchars($solicitud['estado']) ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Fecha:</span>
                                    <strong><?= date('d/m/Y', strtotime($solicitud['fecha_solicitud'])) ?></strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <h5><i class="fas fa-user me-2"></i>Información del Cliente</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Nombre:</span>
                                    <strong><?= htmlspecialchars($solicitud['nombres_completos']) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Identificación:</span>
                                    <strong><?= htmlspecialchars($solicitud['cedula']) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Monto:</span>
                                    <strong>$<?= number_format($solicitud['monto_seguro'], 2) ?></strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panel de firma digital -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-pen-fancy me-2"></i>Registrar Firma Digital</h4>
            </div>
            <div class="card-body p-0">
                <div class="alert alert-info m-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fa-2x"></i>
                        <div>
                            <h5 class="alert-heading mb-1">Validez Legal de la Firma</h5>
                            <p class="mb-0">Tu firma digital tiene validez legal y será utilizada para certificar tu solicitud de seguro.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Pestañas de firma -->
                <div class="firma-tabs">
                    <div class="firma-tab active" data-tab="dibujar">
                        <i class="fas fa-pen me-2"></i> Dibujar Firma
                    </div>
                    <div class="firma-tab" data-tab="subir">
                        <i class="fas fa-upload me-2"></i> Subir Imagen
                    </div>
                </div>
                
                <!-- Contenido de pestaña: Dibujar firma -->
                <div class="firma-tab-content active" id="tab-dibujar">
                    <form method="POST" id="form-firma-canvas">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Dibuja tu firma en el recuadro:</label>
                            <canvas id="firma-canvas" width="600" height="200"></canvas>
                            <input type="hidden" name="firma_canvas" id="firma_canvas_data">
                        </div>
                        
                        <div class="signature-actions">
                            <button type="button" class="btn btn-outline-danger" id="limpiar-firma">
                                <i class="fas fa-eraser me-1"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary" id="guardar-firma-canvas">
                                <i class="fas fa-save me-1"></i> Guardar Firma
                            </button>
                        </div>
                        
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="acepto_firma_canvas" required>
                            <label class="form-check-label" for="acepto_firma_canvas">
                                Acepto que esta firma tiene validez legal y autorizo su uso en mi solicitud de seguro
                            </label>
                        </div>
                    </form>
                </div>
                
                <!-- Contenido de pestaña: Subir firma -->
                <div class="firma-tab-content" id="tab-subir">
                    <form method="POST" enctype="multipart/form-data" id="form-firma-upload">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Sube una imagen de tu firma:</label>
                            <input type="file" name="firma" id="firma_upload" class="form-control" accept="image/png,image/jpg,image/jpeg" required>
                            <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG. Tamaño máximo: 2MB</small>
                            
                            <div class="mt-3 text-center">
                                <img id="firma-preview" class="firma-preview">
                            </div>
                        </div>
                        
                        <div class="signature-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar Firma
                            </button>
                        </div>
                        
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="acepto_firma_upload" required>
                            <label class="form-check-label" for="acepto_firma_upload">
                                Acepto que esta firma tiene validez legal y autorizo su uso en mi solicitud de seguro
                            </label>
                        </div>
                    </form>
                </div>
                
                <!-- Información de seguridad -->
                <div class="signature-info m-4">
                    <h6><i class="fas fa-shield-alt me-2"></i>Seguridad y Validez Legal</h6>
                    <ul>
                        <li>Tu firma se almacena de forma segura y encriptada</li>
                        <li>Se registran metadatos (fecha, hora, IP) para garantizar la validez legal</li>
                        <li>El documento final incluirá un sello de tiempo certificado</li>
                        <li>Cumplimos con la normativa de firmas electrónicas y protección de datos</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Configuración del canvas para firma
        const canvas = document.getElementById('firma-canvas');
        const ctx = canvas.getContext('2d');
        let dibujando = false;
        
        // Ajustar tamaño del canvas para dispositivos móviles
        function resizeCanvas() {
            const container = canvas.parentElement;
            canvas.width = container.offsetWidth - 40; // Margen
            canvas.height = 200;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#002147';
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        
        // Inicializar canvas
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // Eventos para dibujar (ratón)
        canvas.addEventListener('mousedown', iniciarDibujo);
        canvas.addEventListener('mousemove', dibujar);
        canvas.addEventListener('mouseup', finalizarDibujo);
        canvas.addEventListener('mouseout', finalizarDibujo);
        
        // Eventos para dibujar (táctil)
        canvas.addEventListener('touchstart', iniciarDibujoTouch);
        canvas.addEventListener('touchmove', dibujarTouch);
        canvas.addEventListener('touchend', finalizarDibujo);
        
        // Prevenir desplazamiento en dispositivos táctiles
        document.body.addEventListener('touchmove', function(e) {
            if (dibujando) {
                e.preventDefault();
            }
        }, { passive: false });
        
        function iniciarDibujo(e) {
            dibujando = true;
            ctx.beginPath();
            ctx.moveTo(e.offsetX, e.offsetY);
            e.preventDefault();
        }
        
        function dibujar(e) {
            if (!dibujando) return;
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.stroke();
            e.preventDefault();
        }
        
        function iniciarDibujoTouch(e) {
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            const offsetX = touch.clientX - rect.left;
            const offsetY = touch.clientY - rect.top;
            
            dibujando = true;
            ctx.beginPath();
            ctx.moveTo(offsetX, offsetY);
            e.preventDefault();
        }
        
        function dibujarTouch(e) {
            if (!dibujando) return;
            
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            const offsetX = touch.clientX - rect.left;
            const offsetY = touch.clientY - rect.top;
            
            ctx.lineTo(offsetX, offsetY);
            ctx.stroke();
            e.preventDefault();
        }
        
        function finalizarDibujo() {
            dibujando = false;
        }
        
        // Limpiar canvas
        document.getElementById('limpiar-firma').addEventListener('click', function() {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#002147';
        });
        
        // Validar y guardar firma del canvas
        document.getElementById('form-firma-canvas').addEventListener('submit', function(e) {
            // Verificar si el canvas está vacío
            const pixeles = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            let vacio = true;
            
            for (let i = 0; i < pixeles.length; i += 4) {
                if (pixeles[i + 3] !== 0) {
                    vacio = false;
                    break;
                }
            }
            
            if (vacio) {
                e.preventDefault();
                alert('Por favor, dibuja tu firma antes de guardar.');
                return;
            }
            
            if (!document.getElementById('acepto_firma_canvas').checked) {
                e.preventDefault();
                alert('Debes aceptar los términos para continuar.');
                return;
            }
            
            // Guardar datos del canvas
            document.getElementById('firma_canvas_data').value = canvas.toDataURL('image/png');
        });
        
        // Preview de firma subida
        document.getElementById('firma_upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('firma-preview');
            
            if (file) {
                // Validar tamaño
                if (file.size > 2097152) { // 2MB
                    alert('La imagen de la firma es demasiado grande. Máximo 2MB');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // Validar tipo
                if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
                    alert('Solo se permiten imágenes PNG, JPG o JPEG para la firma');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
        
        // Validar formulario de subida
        document.getElementById('form-firma-upload').addEventListener('submit', function(e) {
            if (!document.getElementById('acepto_firma_upload').checked) {
                e.preventDefault();
                alert('Debes aceptar los términos para continuar.');
            }
        });
        
        // Cambiar entre pestañas
        document.querySelectorAll('.firma-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Desactivar todas las pestañas
                document.querySelectorAll('.firma-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.firma-tab-content').forEach(c => c.classList.remove('active'));
                
                // Activar la pestaña seleccionada
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                
                // Redimensionar canvas al cambiar pestaña
                if (this.dataset.tab === 'dibujar') {
                    resizeCanvas();
                }
            });
        });
    </script>
</body>
</html>