<?php
session_start();

require_once '../conexion.php';

// Verificar autenticación
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['id'];

// Obtener seguros disponibles
$seguros_disponibles = [];
$query = "SELECT id, nombre, precio, cobertura_maxima FROM seguros";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $seguros_disponibles[] = $row;
}

// Configuración de archivos
$upload_dir = '../uploads/reembolsos/';
$allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$max_size = 5 * 1024 * 1024; // 5MB

// Crear directorio si no existe
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seguro_id = intval($_POST['seguro_id']);
    $tipo = $_POST['tipo_reembolso'];
    $monto = floatval($_POST['monto']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    
    // Validar monto positivo
    if ($monto <= 0) {
        $error = "❌ El monto debe ser mayor a cero";
    } else {
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Insertar reembolso
            $query = "INSERT INTO reembolsos (usuario_id, seguro_id, tipo_reembolso, monto_solicitado, descripcion, estado) 
                      VALUES (?, ?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iisds", $usuario_id, $seguro_id, $tipo, $monto, $descripcion);
            $stmt->execute();
            $reembolso_id = $conn->insert_id;
            
            // Procesar archivos subidos
            if (!empty($_FILES['documentos']['name'][0])) {
                foreach ($_FILES['documentos']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['documentos']['name'][$key];
                    $file_size = $_FILES['documentos']['size'][$key];
                    $file_tmp = $_FILES['documentos']['tmp_name'][$key];
                    $file_type = $_FILES['documentos']['type'][$key];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Validar tipo de archivo
                    if (!in_array($file_ext, $allowed_types)) {
                        throw new Exception("Tipo de archivo no permitido: $file_name");
                    }
                    
                    // Validar tamaño
                    if ($file_size > $max_size) {
                        throw new Exception("Archivo demasiado grande: $file_name (Máximo 5MB)");
                    }
                    
                    // Generar nombre único
                    $new_file_name = "reembolso_{$reembolso_id}_" . uniqid() . ".$file_ext";
                    $file_path = $upload_dir . $new_file_name;
                    
                    // Mover archivo
                    if (!move_uploaded_file($file_tmp, $file_path)) {
                        throw new Exception("Error al subir el archivo: $file_name");
                    }
                    
                    // Guardar en base de datos
                    $query = "INSERT INTO documentos_reembolso (reembolso_id, nombre_original, nombre_archivo, ruta, tipo, tamaño) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("issssi", $reembolso_id, $file_name, $new_file_name, $file_path, $file_type, $file_size);
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            $mensaje = "✅ Solicitud de reembolso #$reembolso_id enviada correctamente";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ Error: " . $e->getMessage();
        }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card-reembolso {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: none;
        }
        .seguro-option {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .seguro-name {
            font-weight: 500;
            color: #0d6efd;
        }
        .seguro-details {
            font-size: 0.9em;
            color: #6c757d;
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .file-upload-area:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .file-preview {
            margin-top: 15px;
        }
        .file-item {
            display: flex;
            align-items: center;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .file-icon {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        .file-info {
            flex-grow: 1;
        }
        .file-size {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .remove-file {
            color: #dc3545;
            cursor: pointer;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            color: #6c757d;
        }
        .step.active .step-number {
            background-color: #0d6efd;
            color: white;
        }
        .step-title {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .step.active .step-title {
            color: #0d6efd;
            font-weight: 500;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 60%;
            right: -40%;
            height: 2px;
            background-color: #e9ecef;
            z-index: -1;
        }
        .step.active:not(:last-child)::after {
            background-color: #0d6efd;
        }
        @media (max-width: 768px) {
            .step-title {
                font-size: 0.7rem;
            }
            .step:not(:last-child)::after {
                left: 55%;
                right: -45%;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card card-reembolso p-4 mb-4">
                    <!-- Indicador de pasos -->
                    <div class="step-indicator">
                        <div class="step active">
                            <div class="step-number">1</div>
                            <div class="step-title">Datos del Reembolso</div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-title">Documentación</div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-title">Confirmación</div>
                        </div>
                    </div>
                    
                    <h2 class="text-center mb-4">
                        <i class="bi bi-arrow-repeat me-2"></i>Solicitud de Reembolso
                    </h2>
                    
                    <?php if (isset($mensaje)): ?>
                        <div class="alert alert-success d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div><?= $mensaje ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div><?= $error ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-card-checklist me-2"></i>Información del Seguro</h5>
                            <div class="mb-3">
                                <label class="form-label">Plan de Seguro:</label>
                                <select class="form-select" name="seguro_id" required>
                                    <option value="" disabled selected>Seleccione su plan</option>
                                    <?php foreach ($seguros_disponibles as $seguro): ?>
                                        <option value="<?= $seguro['id'] ?>">
                                            <div class="seguro-option">
                                                <span class="seguro-name"><?= htmlspecialchars($seguro['nombre']) ?></span>
                                                <span class="seguro-details">
                                                    $<?= number_format($seguro['precio'], 2) ?> | 
                                                    Cobertura: $<?= number_format($seguro['cobertura_maxima'], 2) ?>
                                                </span>
                                            </div>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tipo de Reembolso:</label>
                                <select class="form-select" name="tipo_reembolso" required>
                                    <option value="" disabled selected>Seleccione el tipo</option>
                                    <option value="medicina">Medicinas y recetas</option>
                                    <option value="cirugia">Procedimientos quirúrgicos</option>
                                    <option value="hospitalizacion">Gastos de hospitalización</option>
                                    <option value="consulta">Consultas médicas</option>
                                    <option value="analisis">Análisis clínicos</option>
                                    <option value="otros">Otros gastos médicos</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-currency-dollar me-2"></i>Detalles del Reembolso</h5>
                            <div class="mb-3">
                                <label class="form-label">Monto a Reembolsar (USD):</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="monto" 
                                           step="0.01" min="0.01" placeholder="Ej: 150.50" required>
                                </div>
                                <div class="form-text">Ingrese el monto total que desea reclamar</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Descripción Detallada:</label>
                                <textarea class="form-control" name="descripcion" rows="4" 
                                          placeholder="Describa los gastos médicos incurridos, incluyendo fechas, procedimientos y cualquier información relevante..." required></textarea>
                                <div class="form-text">Sea lo más específico posible para agilizar el proceso</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-up me-2"></i>Documentación de Soporte</h5>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Suba facturas, recetas médicas o documentos que respalden su solicitud. Formatos aceptados: PDF, JPG, PNG, Word (Máx. 5MB por archivo)
                            </div>
                            
                            <div class="file-upload-area" id="dropArea">
                                <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: #0d6efd;"></i>
                                <h5 class="my-3">Arrastre y suelte archivos aquí</h5>
                                <p class="text-muted">o haga clic para seleccionar</p>
                                <input type="file" name="documentos[]" id="fileInput" class="d-none" multiple 
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" data-max-size="<?= $max_size ?>">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('fileInput').click()">
                                    <i class="bi bi-folder2-open me-2"></i>Seleccionar Archivos
                                </button>
                            </div>
                            
                            <div class="file-preview" id="filePreview">
                                <!-- Los archivos seleccionados aparecerán aquí -->
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="clientedash.php" class="btn btn-outline-primary me-md-2">
                                <i class="bi bi-arrow-left-circle me-2"></i>Volver al Panel
                            </a>
                            <button type="reset" class="btn btn-outline-secondary me-md-2">
                                <i class="bi bi-x-circle me-2"></i>Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send-check me-2"></i>Enviar Solicitud
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card card-reembolso p-4">
                    <h5 class="fw-bold"><i class="bi bi-question-circle-fill me-2"></i>¿Necesita ayuda?</h5>
                    <p>Si tiene preguntas sobre el proceso de reembolso, por favor contacte a nuestro equipo de soporte:</p>
                    <ul>
                        <li><i class="bi bi-telephone me-2"></i> Teléfono: 1-800-555-1000</li>
                        <li><i class="bi bi-envelope me-2"></i> Email: reembolsos@aseguradora.com</li>
                        <li><i class="bi bi-clock me-2"></i> Horario: Lunes a Viernes, 8am - 5pm</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejo de la subida de archivos
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const maxSize = <?= $max_size ?>;
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        
        // Iconos por tipo de archivo
        const fileIcons = {
            'pdf': 'bi-file-earmark-pdf',
            'jpg': 'bi-file-image',
            'jpeg': 'bi-file-image',
            'png': 'bi-file-image',
            'doc': 'bi-file-earmark-word',
            'docx': 'bi-file-earmark-word'
        };
        
        // Prevenir comportamientos por defecto en el área de drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Resaltar el área de drop
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('bg-light');
            dropArea.style.borderColor = '#0d6efd';
        }
        
        function unhighlight() {
            dropArea.classList.remove('bg-light');
            dropArea.style.borderColor = '#dee2e6';
        }
        
        // Manejar archivos soltados
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        // Manejar archivos seleccionados
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        function handleFiles(files) {
            filePreview.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                // Validar tipo de archivo
                if (!allowedTypes.includes(file.type) && !file.name.match(/\.(pdf|jpe?g|png|docx?)$/i)) {
                    alert(`El archivo ${file.name} no es de un tipo permitido.`);
                    continue;
                }
                
                // Validar tamaño
                if (file.size > maxSize) {
                    alert(`El archivo ${file.name} excede el tamaño máximo de 5MB.`);
                    continue;
                }
                
                // Mostrar previsualización
                const fileExt = file.name.split('.').pop().toLowerCase();
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `
                    <i class="file-icon bi ${fileIcons[fileExt] || 'bi-file-earmark'}"></i>
                    <div class="file-info">
                        <div>${file.name}</div>
                        <div class="file-size">${formatFileSize(file.size)}</div>
                    </div>
                    <i class="remove-file bi bi-x-circle" onclick="removeFile(this)"></i>
                `;
                filePreview.appendChild(fileItem);
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]);
        }
        
        function removeFile(element) {
            element.parentElement.remove();
            // También deberíamos eliminar el archivo del input file
            // Esto requiere un enfoque más complejo ya que no podemos modificar directamente FileList
            // Una solución sería mantener un array de archivos aceptados y reconstruir el DataTransfer
        }
        
        // Validación antes de enviar el formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const monto = parseFloat(document.querySelector('input[name="monto"]').value);
            if (monto <= 0) {
                alert('El monto debe ser mayor a cero');
                e.preventDefault();
                return false;
            }
            
            // Podrías añadir más validaciones aquí si es necesario
            return true;
        });
    </script>
</body>
</html>