<?php
session_start();
include '../conexion.php';
require_once '../fpdf/fpdf.php';

// Modificamos la consulta para obtener los seguros desde la tabla tipos_seguro
$sql_seguros = "SELECT t.id, t.nombre, c.monto_base as precio, 
                CONCAT('$', FORMAT(c.monto_base, 2), ' - ', t.coberturas) as cobertura_maxima 
                FROM tipos_seguro t 
                JOIN configuraciones_seguro c ON t.id = c.tipo_seguro_id 
                WHERE t.estado = 1";
$result_seguros = $conn->query($sql_seguros);

$seguros = [];
while ($row = $result_seguros->fetch_assoc()) {
    $seguros[] = $row;
}

if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$mensaje = "";
$correo_sesion = $_SESSION['usuario'];

$sql_usuario = "SELECT * FROM usuarios WHERE correo = ?";
$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("s", $correo_sesion);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();

if ($result_usuario->num_rows === 0) {
    echo "<p>Error: usuario no encontrado.</p>";
    exit();
}

$usuario = $result_usuario->fetch_assoc();
$stmt_usuario->close();

// Validar solicitudes existentes
$sql_validar = "SELECT estado FROM seguros_vida WHERE usuario_id = ? ORDER BY fecha_solicitud DESC LIMIT 1";
$stmt_validar = $conn->prepare($sql_validar);
$stmt_validar->bind_param("i", $usuario['id']);
$stmt_validar->execute();
$result_validar = $stmt_validar->get_result();

$estado_solicitud = null;
if ($result_validar->num_rows > 0) {
    $ultima = $result_validar->fetch_assoc();
    $estado_solicitud = $ultima['estado'];

    if (in_array($estado_solicitud, ['Aprobado', 'Pendiente de Firma', 'En espera de aprobación'])) {
        $mensaje = "<div class='alert alert-warning mt-4'>No puedes enviar otra solicitud, tienes una solicitud de seguro de vida: <strong>{$estado_solicitud}</strong>.</div>";
    }
}
$stmt_validar->close();

// Función mejorada para validar archivos
function validarArchivo($archivo, $tipos_permitidos = ['pdf', 'jpg', 'jpeg', 'png'], $tamaño_max = 5242880) {
    $errores = [];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error en la carga del archivo";
        return $errores;
    }
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $tipos_permitidos)) {
        $errores[] = "Tipo de archivo no permitido. Solo se permiten: " . implode(', ', $tipos_permitidos);
    }
    
    if ($archivo['size'] > $tamaño_max) {
        $errores[] = "El archivo es demasiado grande. Máximo 5MB";
    }
    
    // Validación adicional de tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    $mimes_permitidos = [
        'application/pdf',
        'image/jpeg',
        'image/jpg', 
        'image/png'
    ];
    
    if (!in_array($mime_type, $mimes_permitidos)) {
        $errores[] = "Tipo de archivo no válido";
    }
    
    return $errores;
}

// Función para validar firma digital
function validarFirmaDigital($archivo) {
    $errores = [];
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return $errores; // Firma es opcional
    }
    
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'jpg', 'jpeg'])) {
        $errores[] = "La firma debe ser una imagen PNG, JPG o JPEG";
    }
    
    if ($archivo['size'] > 2097152) { // 2MB máximo para firmas
        $errores[] = "La imagen de la firma es demasiado grande. Máximo 2MB";
    }
    
    // Validar dimensiones de la imagen
    $imagen_info = getimagesize($archivo['tmp_name']);
    if ($imagen_info) {
        $ancho = $imagen_info[0];
        $alto = $imagen_info[1];
        
        if ($ancho > 800 || $alto > 400) {
            $errores[] = "Las dimensiones de la firma son demasiado grandes. Máximo 800x400 píxeles";
        }
        
        if ($ancho < 100 || $alto < 50) {
            $errores[] = "Las dimensiones de la firma son demasiado pequeñas. Mínimo 100x50 píxeles";
        }
    }
    
    return $errores;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($estado_solicitud === null || $estado_solicitud === 'Rechazado')) {
    $errores_validacion = [];
    
    // Validar campos requeridos
    $required = ['cedula', 'tipo_identificacion', 'lugar_nacimiento', 'nacionalidad', 'sexo', 'fecha_nacimiento', 'ocupacion', 'fumador', 'peso', 'altura', 'enfermedades', 'alergias', 'seguro_id', 'estado_civil', 'tipo_sangre', 'forma_pago'];
    foreach ($required as $campo) {
        if (empty($_POST[$campo])) {
            $errores_validacion[] = "El campo <strong>{$campo}</strong> no puede estar vacío.";
        }
    }
    
    // Validar documentos de identificación
    if (!empty($_FILES['cedula_escan']['name'][0])) {
        foreach ($_FILES['cedula_escan']['name'] as $index => $nombre) {
            if (!empty($nombre)) {
                $archivo_temp = [
                    'name' => $_FILES['cedula_escan']['name'][$index],
                    'tmp_name' => $_FILES['cedula_escan']['tmp_name'][$index],
                    'error' => $_FILES['cedula_escan']['error'][$index],
                    'size' => $_FILES['cedula_escan']['size'][$index]
                ];
                $errores_archivo = validarArchivo($archivo_temp);
                $errores_validacion = array_merge($errores_validacion, $errores_archivo);
            }
        }
    }
    
    // Validar documentos adicionales
    if (!empty($_FILES['documentos_adicionales']['name'][0])) {
        foreach ($_FILES['documentos_adicionales']['name'] as $index => $nombre) {
            if (!empty($nombre)) {
                $archivo_temp = [
                    'name' => $_FILES['documentos_adicionales']['name'][$index],
                    'tmp_name' => $_FILES['documentos_adicionales']['tmp_name'][$index],
                    'error' => $_FILES['documentos_adicionales']['error'][$index],
                    'size' => $_FILES['documentos_adicionales']['size'][$index]
                ];
                $errores_archivo = validarArchivo($archivo_temp);
                $errores_validacion = array_merge($errores_validacion, $errores_archivo);
            }
        }
    }
    
    // Validar firma digital
    if (!empty($_FILES['firma']['name'])) {
        $errores_firma = validarFirmaDigital($_FILES['firma']);
        $errores_validacion = array_merge($errores_validacion, $errores_firma);
    }
    
    // Si hay errores, mostrarlos
    if (!empty($errores_validacion)) {
        $mensaje = "<div class='alert alert-danger'><ul>";
        foreach ($errores_validacion as $error) {
            $mensaje .= "<li>$error</li>";
        }
        $mensaje .= "</ul></div>";
    } else {
        // Procesar formulario si no hay errores
        $cedula = $_POST['cedula'];
        $tipo_identificacion = $_POST['tipo_identificacion'];
        $lugar_nacimiento = $_POST['lugar_nacimiento'];
        $nacionalidad = $_POST['nacionalidad'];
        $sexo = $_POST['sexo'];
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $ocupacion = $_POST['ocupacion'];
        $fumador = $_POST['fumador'];
        $peso = $_POST['peso'];
        $altura = $_POST['altura'];
        $enfermedades = $_POST['enfermedades'];
        $alergias = $_POST['alergias'];
        $seguro_id = $_POST['seguro_id'];
        $estado_civil = $_POST['estado_civil'];
        $tipo_sangre = $_POST['tipo_sangre'];
        $forma_pago = $_POST['forma_pago'];

        // Obtener información del seguro seleccionado
        $sql_seguro_info = "SELECT t.nombre, c.monto_base 
                            FROM tipos_seguro t 
                            JOIN configuraciones_seguro c ON t.id = c.tipo_seguro_id 
                            WHERE t.id = ?";
        $stmt_seguro = $conn->prepare($sql_seguro_info);
        $stmt_seguro->bind_param("i", $seguro_id);
        $stmt_seguro->execute();
        $result_seguro = $stmt_seguro->get_result();
        $seguro_info = $result_seguro->fetch_assoc();
        $tipo_seguro = $seguro_info['nombre'];
        $monto_seguro = $seguro_info['monto_base'];
        $stmt_seguro->close();

        // Procesar dependientes
        $numero_hijos = $_POST['num_hijos'] ?? 0;
        $datos_hijos = '';
        for ($i = 1; $i <= $numero_hijos; $i++) {
            $nombre = $_POST["dep_nombre_$i"] ?? '';
            $cedula_h = $_POST["dep_cedula_$i"] ?? '';
            $lugar = $_POST["dep_lugar_$i"] ?? '';
            $nacionalidad_h = $_POST["dep_nacionalidad_$i"] ?? '';
            $edad = $_POST["dep_edad_$i"] ?? '';
            $parentesco = $_POST["dep_parentesco_$i"] ?? '';
            $sexo_h = $_POST["dep_sexo_$i"] ?? '';

            if ($nombre && $cedula_h && $lugar && $nacionalidad_h && $edad && $parentesco && $sexo_h) {
                $datos_hijos .= "$nombre ($cedula_h, $lugar, $nacionalidad_h, $edad años, $parentesco, $sexo_h); ";
            }
        }
        $datos_hijos = rtrim($datos_hijos, '; ');

        $nombre_conyuge = $_POST['conyugue_nombre'] ?? '';
        $cedula_conyuge = $_POST['conyugue_cedula'] ?? '';

        // Función mejorada para procesar archivos con validación
        function procesarArchivosSeguro($inputName, $destinoBase, $usuario_id) {
            $nombres = [];
            if (!empty($_FILES[$inputName]['name'][0])) {
                foreach ($_FILES[$inputName]['name'] as $index => $nombre) {
                    if (!empty($nombre)) {
                        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                        $nombre_seguro = $destinoBase . $usuario_id . '_' . time() . '_' . $index . '.' . $extension;
                        $tmp = $_FILES[$inputName]['tmp_name'][$index];
                        
                        // Crear directorio si no existe
                        $directorio = dirname($nombre_seguro);
                        if (!is_dir($directorio)) {
                            mkdir($directorio, 0755, true);
                        }
                        
                        if (move_uploaded_file($tmp, $nombre_seguro)) {
                            $nombres[] = basename($nombre_seguro);
                        }
                    }
                }
            }
            return implode(', ', $nombres);
        }

        $cedula_nombre = procesarArchivosSeguro('cedula_escan', '../archivos/cedula_', $usuario['id']);
        $docs_adicionales_nombre = procesarArchivosSeguro('documentos_adicionales', '../archivos/doc_adic_', $usuario['id']);

        // Procesar firma digital mejorada
        $firma_path = '';
        $firma_data = null;
        $estado = 'Pendiente de Firma';
        
        if (isset($_FILES['firma']) && $_FILES['firma']['size'] > 0) {
            $ext = pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['png', 'jpg', 'jpeg'])) {
                // Crear nombre único para la firma
                $firma_filename = 'firma_' . $usuario['id'] . '_' . time() . '.' . $ext;
                $firma_path = '../firmas/' . $firma_filename;
                
                // Crear directorio de firmas si no existe
                if (!is_dir('../firmas/')) {
                    mkdir('../firmas/', 0755, true);
                }
                
                if (move_uploaded_file($_FILES['firma']['tmp_name'], $firma_path)) {
                    $firma_data = file_get_contents($firma_path);
                    $estado = 'En espera de aprobación';
                    
                    // Log de la firma para auditoría
                    $log_firma = [
                        'usuario_id' => $usuario['id'],
                        'archivo' => $firma_filename,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ];
                    file_put_contents('../logs/firmas.log', json_encode($log_firma) . "\n", FILE_APPEND);
                }
            }
        }

        // Insertar en base de datos
        $stmt_insert = $conn->prepare("INSERT INTO seguros_vida (
            usuario_id, nombres_completos, cedula, tipo_identificacion, lugar_nacimiento, nacionalidad, sexo, telefono, correo, direccion,
            fecha_nacimiento, ocupacion, fumador, peso, altura,
            enfermedades_previas, alergias, firma, estado,
            tipo_seguro, estado_civil, nombre_conyuge, cedula_conyuge,
            numero_hijos, datos_hijos, tipo_sangre, forma_pago, cedula_escan_nombre, documentos_adicionales_nombre,
            seguro_id, monto_seguro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt_insert->bind_param(
            "issssssssssssssssssssssssssssis",
            $usuario['id'], $usuario['usuario'], $cedula, $tipo_identificacion, $lugar_nacimiento, $nacionalidad, $sexo,
            $usuario['telefono'], $usuario['correo'], $usuario['direccion'],
            $fecha_nacimiento, $ocupacion, $fumador, $peso, $altura,
            $enfermedades, $alergias, $firma_data, $estado,
            $tipo_seguro, $estado_civil, $nombre_conyuge, $cedula_conyuge,
            $numero_hijos, $datos_hijos, $tipo_sangre, $forma_pago, $cedula_nombre, $docs_adicionales_nombre,
            $seguro_id, $monto_seguro
        );

        if ($stmt_insert->execute()) {
            // Generar PDF mejorado
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, utf8_decode('Solicitud de Seguro de Vida'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Ln(10);

            $pdf->MultiCell(0, 8, utf8_decode("Nombre: {$usuario['usuario']}
Cédula: {$cedula}
Tipo ID: {$tipo_identificacion}
Lugar de Nacimiento: {$lugar_nacimiento}
Nacionalidad: {$nacionalidad}
Sexo: {$sexo}
Teléfono: {$usuario['telefono']}
Correo: {$usuario['correo']}
Dirección: {$usuario['direccion']}
Fecha de Nacimiento: {$fecha_nacimiento}
Ocupación: {$ocupacion}
Fumador: {$fumador}
Peso: {$peso}
Altura: {$altura}
Enfermedades: {$enfermedades}
Alergias: {$alergias}
Tipo de seguro: {$tipo_seguro}
Monto del seguro: \${$monto_seguro}
Estado civil: {$estado_civil}
Cónyuge: {$nombre_conyuge} ({$cedula_conyuge})
Hijos: {$datos_hijos}
Tipo de Sangre: {$tipo_sangre}
Forma de Pago: {$forma_pago}"), 0);

            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, utf8_decode('Documentos Adjuntos'), 0, 1);
            $pdf->SetFont('Arial', '', 12);
            
            $pdf->Cell(0, 8, utf8_decode('Documentos de identificación: ' . ($cedula_nombre ?: 'Ninguno')), 0, 1);
            $pdf->Cell(0, 8, utf8_decode('Documentos adicionales: ' . ($docs_adicionales_nombre ?: 'Ninguno')), 0, 1);

            // Agregar firma al PDF si existe
            if ($firma_path && file_exists($firma_path)) {
                $pdf->Ln(10);
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 10, utf8_decode('Firma Digital'), 0, 1);
                
                $firma_y = $pdf->GetY() + 5;
                $pdf->Image($firma_path, 75, $firma_y, 60);
                $pdf->SetY($firma_y + 35);
                $pdf->Cell(0, 10, utf8_decode('_____________________________'), 0, 1, 'C');
                $pdf->Cell(0, 5, utf8_decode('Firma del Asegurado'), 0, 1, 'C');
                
                // Agregar información de validación
                $pdf->Ln(5);
                $pdf->SetFont('Arial', '', 8);
                $pdf->Cell(0, 5, utf8_decode('Firma digital validada el: ' . date('d/m/Y H:i:s')), 0, 1, 'C');
                $pdf->Cell(0, 5, utf8_decode('IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')), 0, 1, 'C');
            }

            $nombre_pdf = '../pdf/solicitud_seguro_' . $usuario['id'] . '_' . time() . '.pdf';
            $pdf->Output('F', $nombre_pdf);

            $mensaje = "<div class='alert alert-success mt-4' id='mensaje-exito'>
                        Formulario enviado correctamente. 
                        <a href='$nombre_pdf' target='_blank' class='btn btn-sm btn-outline-success ms-2'>
                            <i class='fas fa-file-pdf'></i> Ver PDF generado
                        </a>
                        <br><small class='mt-2 d-block'>Estado: <strong>$estado</strong></small>
                        <a href='panel_cliente.php' class='btn btn-primary mt-2'>Volver al panel</a>
                       </div>";
            echo "<script>setTimeout(() => { window.location.href = 'panel_cliente.php'; }, 10000);</script>";
        } else {
            $mensaje = "<div class='alert alert-danger mt-4'>Error al guardar: " . $stmt_insert->error . "</div>";
        }
        $stmt_insert->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formulario Seguro de Vida - Firma Digital</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        
        body {
            background: linear-gradient(135deg, #ffffff 50%,  #002147 50%);
            color: #000;
        }
        label {
            font-weight: bold;
        }
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .seguro-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .seguro-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .seguro-card.selected {
            border-color: #0d6efd;
            background-color: #f0f7ff;
        }
        .seguro-details {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        .seguro-price {
            font-weight: bold;
            color: #198754;
        }
        .firma-section {
            background-color: #f8f9fa;
            border: 2px dashed #6c757d;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .firma-preview {
            max-width: 300px;
            max-height: 150px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin: 10px auto;
            display: none;
        }
        .documento-info {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .file-upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .file-upload-area:hover {
            background-color: #e3f2fd;
            border-color: #0056b3;
        }
        .file-upload-area.dragover {
            background-color: #cce5ff;
            border-color: #004085;
        }
        .security-info {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="form-container mx-auto col-lg-8 col-md-10">
        <h2 class="mb-4 text-center text-primary">
            <i class="fas fa-file-signature"></i> Formulario Seguro de Vida con Firma Digital
        </h2>
        
        <!-- Información de seguridad -->
        <div class="security-info">
            <h6><i class="fas fa-shield-alt"></i> Información de Seguridad</h6>
            <ul class="mb-0">
                <li>Todos los documentos se almacenan de forma segura y encriptada</li>
                <li>Su firma digital tiene validez legal</li>
                <li>Los archivos permitidos son: PDF, JPG, PNG (máximo 5MB)</li>
                <li>Para firmas: PNG, JPG, JPEG (máximo 2MB, dimensiones 100x50 a 800x400 píxeles)</li>
            </ul>
        </div>
        
        <?php echo $mensaje ?? ''; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
            <div class="row g-3">
                <!-- Datos del usuario (readonly) -->
                <div class="col-md-6">
                    <label>ID de usuario en el sistema</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['id']) ?>" readonly>
                </div>
                
                <div class="col-md-6">
                    <label>Nombre</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['usuario']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label>Teléfono</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['telefono']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label>Dirección</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['direccion']) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label>Correo</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" readonly>
                </div>

                <!-- Datos del formulario -->
                <div class="col-md-6">
                    <label>Tipo de identificación *</label>
                    <select name="tipo_identificacion" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Cédula">Cédula</option>
                        <option value="Pasaporte">Pasaporte</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label>Nro. Identificación *</label>
                    <input type="text" name="cedula" class="form-control" required maxlength="10" pattern="\d{10}">
                </div>
                
                <!-- Sección de carga de documentos mejorada -->
                <div class="col-12">
                    <div class="documento-info">
                        <h5><i class="fas fa-upload"></i> Carga de Documentos de Identificación</h5>
                        <p class="mb-2">Suba sus documentos de identificación (cédula, pasaporte, etc.)</p>
                        <div class="file-upload-area" id="cedula-upload-area">
                            <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                            <p>Arrastra y suelta archivos aquí o haz clic para seleccionar</p>
                            <input type="file" name="cedula_escan[]" id="cedula_escan" class="form-control" 
                                   accept=".pdf,.jpg,.jpeg,.png" multiple style="display: none;">
                            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('cedula_escan').click()">
                                <i class="fas fa-folder-open"></i> Seleccionar Archivos
                            </button>
                        </div>
                        <div id="cedula-files-list" class="mt-2"></div>
                    </div>
                </div>

                <!-- Campos del formulario existentes -->
                <div class="col-md-6">
                    <label>Sexo *</label>
                    <select name="sexo" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Lugar de nacimiento *</label>
                    <input type="text" name="lugar_nacimiento" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Nacionalidad *</label>
                    <input type="text" name="nacionalidad" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Fecha de Nacimiento *</label>
                    <input type="date" name="fecha_nacimiento" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Estado civil *</label>
                    <select name="estado_civil" id="estado_civil" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Soltero">Soltero</option>
                        <option value="Casado">Casado</option>
                        <option value="Divorciado">Divorciado</option>
                    </select>
                </div>

                <div class="col-md-6" id="campo_conyugue_nombre" style="display:none">
                    <label>Nombre del cónyuge</label>
                    <input type="text" name="conyugue_nombre" class="form-control">
                </div>
                <div class="col-md-6" id="campo_conyugue_cedula" style="display:none">
                    <label>Cédula del cónyuge</label>
                    <input type="text" name="conyugue_cedula" class="form-control" maxlength="10" pattern="\d{10}">
                </div> 

                <div class="col-md-6">
                    <label>Ocupación *</label>
                    <input type="text" name="ocupacion" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>¿Fumador? *</label>
                    <select name="fumador" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Sí">Sí</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Peso (kg) *</label>
                    <input type="number" name="peso" class="form-control" min="30" max="250" required>
                </div>

                <div class="col-md-6">
                    <label>Altura (cm) *</label>
                    <input type="number" name="altura" class="form-control" min="100" max="250" required>
                </div>

                <div class="col-md-6">
                    <label>Tipo de sangre *</label>
                    <select name="tipo_sangre" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label>Forma de pago *</label>
                    <select name="forma_pago" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>

                <div class="col-12">
                    <label>Enfermedades previas *</label>
                    <textarea name="enfermedades" class="form-control" required></textarea>
                </div>
                <div class="col-12">
                    <label>Alergias conocidas *</label>
                    <textarea name="alergias" class="form-control" required></textarea>
                </div>
                
                <!-- Sección de selección de seguros -->
                <div class="col-12 mb-4">
                    <label class="form-label fw-bold fs-5 mb-3">Seleccionar Plan de Seguro *</label>
                    <input type="hidden" id="seguro_id" name="seguro_id" required>
                    
                    <div class="row" id="seguros_container">
                        <?php if (count($seguros) > 0): ?>
                            <?php foreach ($seguros as $seg): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="seguro-card" data-id="<?= $seg['id'] ?>" onclick="seleccionarSeguro(this)">
                                        <h5><?= htmlspecialchars($seg['nombre']) ?></h5>
                                        <div class="seguro-details">
                                            <span class="seguro-price">$<?= htmlspecialchars($seg['precio']) ?></span>
                                            <span class="badge bg-info"><?= htmlspecialchars(substr($seg['cobertura_maxima'], 0, 30)) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-warning">No hay planes de seguro disponibles en este momento.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dependientes -->
                <div class="col-12" id="campo_num_hijos">
                    <label>Dependientes</label>
                    <input type="number" id="num_hijos" name="num_hijos" min="0" max="10" class="form-control" value="0">
                    <div id="advertencia_hijos" class="text-danger mt-1" style="display:none">No es aplicable para el seguro</div>
                </div>

                <div class="col-12" id="datos_hijos"></div>
                
                <!-- Documentos adicionales -->
                <div class="col-12">
                    <div class="documento-info">
                        <h5><i class="fas fa-paperclip"></i> Documentos Adicionales (Opcional)</h5>
                        <p class="mb-2">Suba cualquier documento adicional que considere relevante</p>
                        <div class="file-upload-area" id="docs-upload-area">
                            <i class="fas fa-file-upload fa-2x text-success mb-2"></i>
                            <p>Documentos médicos, referencias, etc.</p>
                            <input type="file" name="documentos_adicionales[]" id="documentos_adicionales" class="form-control" 
                                   accept=".pdf,.jpg,.jpeg,.png" multiple style="display: none;">
                            <button type="button" class="btn btn-outline-success" onclick="document.getElementById('documentos_adicionales').click()">
                                <i class="fas fa-plus"></i> Agregar Documentos
                            </button>
                        </div>
                        <div id="docs-files-list" class="mt-2"></div>
                    </div>
                </div>
                
                <!-- Sección de firma digital mejorada -->
                <div class="col-12">
                    <div class="firma-section">
                        <h5><i class="fas fa-signature"></i> Firma Digital</h5>
                        <p class="text-muted">Su firma digital tendrá validez legal para este contrato</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Subir imagen de su firma</label>
                                <input type="file" name="firma" id="firma" class="form-control" accept="image/png,image/jpg,image/jpeg">
                                <small class="text-muted">
                                    Formatos: PNG, JPG, JPEG | Máximo: 2MB<br>
                                    Dimensiones recomendadas: 300x150 píxeles
                                </small>
                            </div>
                            <div class="col-md-6">
                                <img id="firma-preview" class="firma-preview" alt="Vista previa de la firma">
                                <div id="firma-info" style="display: none;">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle"></i> Firma cargada correctamente
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Nota:</strong> Si no proporciona una firma ahora, su solicitud quedará como "Pendiente de Firma" 
                            y deberá completar este paso posteriormente.
                        </div>
                    </div>
                </div>
                
                <!-- Términos y condiciones -->
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="acepto_terminos" name="acepto_terminos" required>
                        <label class="form-check-label" for="acepto_terminos">
                            Acepto los términos y condiciones, y autorizo el procesamiento de mis datos personales y documentos
                        </label>
                    </div>
                </div>

                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-dark px-5">
                        <i class="fas fa-file-signature"></i> Enviar Solicitud con Firma Digital
                    </button>
                    <a href="clientedash.php" class="btn btn-info ms-2">
                        <i class="fas fa-arrow-left"></i> Volver Inicio
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Variables globales
    const estadoCivil = document.getElementById('estado_civil');
    const campoConyugueNombre = document.getElementById('campo_conyugue_nombre');
    const campoConyugueCedula = document.getElementById('campo_conyugue_cedula');
    const numHijosInput = document.getElementById('num_hijos');
    const datosHijosContainer = document.getElementById('datos_hijos');
    const advertenciaHijos = document.getElementById('advertencia_hijos');

    // Función para seleccionar un seguro
    function seleccionarSeguro(element) {
        document.querySelectorAll('.seguro-card').forEach(card => {
            card.classList.remove('selected');
        });
        element.classList.add('selected');
        document.getElementById('seguro_id').value = element.getAttribute('data-id');
    }

    // Preview de firma digital
    document.getElementById('firma').addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('firma-preview');
        const info = document.getElementById('firma-info');
        
        if (file) {
            // Validar tamaño
            if (file.size > 2097152) { // 2MB
                alert('La imagen de la firma es demasiado grande. Máximo 2MB');
                this.value = '';
                return;
            }
            
            // Validar tipo
            if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
                alert('Solo se permiten imágenes PNG, JPG o JPEG para la firma');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                info.style.display = 'block';
                
                // Validar dimensiones
                preview.onload = function() {
                    if (this.naturalWidth > 800 || this.naturalHeight > 400) {
                        alert('Las dimensiones de la firma son demasiado grandes. Máximo 800x400 píxeles');
                        document.getElementById('firma').value = '';
                        preview.style.display = 'none';
                        info.style.display = 'none';
                    } else if (this.naturalWidth < 100 || this.naturalHeight < 50) {
                        alert('Las dimensiones de la firma son demasiado pequeñas. Mínimo 100x50 píxeles');
                        document.getElementById('firma').value = '';
                        preview.style.display = 'none';
                        info.style.display = 'none';
                    }
                };
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
            info.style.display = 'none';
        }
    });

    // Manejo de archivos con drag & drop
    function setupFileUpload(uploadAreaId, inputId, listId) {
        const uploadArea = document.getElementById(uploadAreaId);
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            input.files = files;
            updateFileList(files, list);
        });
        
        input.addEventListener('change', function() {
            updateFileList(this.files, list);
        });
    }
    
    function updateFileList(files, listElement) {
        listElement.innerHTML = '';
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileItem = document.createElement('div');
            fileItem.className = 'alert alert-info d-flex justify-content-between align-items-center';
            fileItem.innerHTML = `
                <span><i class="fas fa-file"></i> ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                <span class="badge bg-primary">${file.type}</span>
            `;
            listElement.appendChild(fileItem);
        }
    }
    
    // Inicializar drag & drop
    setupFileUpload('cedula-upload-area', 'cedula_escan', 'cedula-files-list');
    setupFileUpload('docs-upload-area', 'documentos_adicionales', 'docs-files-list');

    // Manejo de dependientes
    numHijosInput.addEventListener('input', () => {
        const num = parseInt(numHijosInput.value);
        datosHijosContainer.innerHTML = '';
        advertenciaHijos.style.display = 'none';

        if (isNaN(num) || num <= 0) return;
        if (num > 10) {
            advertenciaHijos.style.display = 'block';
            return;
        }

        for (let i = 1; i <= num; i++) {
            datosHijosContainer.innerHTML += `
                <div class="row g-2 mb-2 border rounded p-2">
                    <div class="col-md-4">
                        <label>Dependiente ${i}</label>
                        <input type="text" name="dep_nombre_${i}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Cédula</label>
                        <input type="text" name="dep_cedula_${i}" class="form-control" maxlength="10" pattern="\\d{10}" required>
                    </div>
                    <div class="col-md-4">
                        <label>Lugar de nacimiento</label>
                        <input type="text" name="dep_lugar_${i}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label>Nacionalidad</label>
                        <input type="text" name="dep_nacionalidad_${i}" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label>Edad</label>
                        <input type="number" name="dep_edad_${i}" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label>Parentesco</label>
                        <input type="text" name="dep_parentesco_${i}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label>Sexo</label>
                        <select name="dep_sexo_${i}" class="form-select" required>
                            <option value="">Seleccione</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Femenino">Femenino</option>
                        </select>
                    </div>
                </div>
            `;
        }
    });

    // Manejo de estado civil
    estadoCivil.addEventListener('change', () => {
        const casado = estadoCivil.value === 'Casado';
        campoConyugueNombre.style.display = casado ? 'block' : 'none';
        campoConyugueCedula.style.display = casado ? 'block' : 'none';
    });

    // Validación del formulario
    document.querySelector("form").addEventListener("submit", function(e) {
        const camposRequeridos = this.querySelectorAll("[required]");
        let valido = true;

        camposRequeridos.forEach((campo) => {
            if (!campo.value.trim()) {
                valido = false;
                campo.classList.add("is-invalid");
            } else {
                campo.classList.remove("is-invalid");
            }
        });

        const terminos = document.getElementById("acepto_terminos");
        if (!terminos.checked) {
            valido = false;
            alert("Debes aceptar los términos y condiciones para enviar la solicitud de contratación del seguro.");
        }

        const seguroId = document.getElementById("seguro_id").value;
        if (!seguroId) {
            valido = false;
            alert("Debes seleccionar un plan de seguro.");
        }

        if (!valido) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>
