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

// Obtener la última solicitud del usuario si existe
$sql_ultima = "SELECT id FROM seguros_vida WHERE usuario_id = ? ORDER BY fecha_solicitud DESC LIMIT 1";
$stmt_ultima = $conn->prepare($sql_ultima);
$stmt_ultima->bind_param("i", $usuario['id']);
$stmt_ultima->execute();
$result_ultima = $stmt_ultima->get_result();
$ultima_solicitud_id = null;
if ($result_ultima->num_rows > 0) {
    $ultima = $result_ultima->fetch_assoc();
    $ultima_solicitud_id = $ultima['id'];
}
$stmt_ultima->close();

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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($estado_solicitud === null || $estado_solicitud === 'Rechazado')) {
    $required = ['cedula', 'tipo_identificacion', 'lugar_nacimiento', 'nacionalidad', 'sexo', 'fecha_nacimiento', 'ocupacion', 'fumador', 'peso', 'altura', 'enfermedades', 'alergias', 'seguro_id', 'estado_civil', 'tipo_sangre', 'forma_pago'];
    foreach ($required as $campo) {
        if (empty($_POST[$campo])) {
            $mensaje = "<div class='alert alert-danger'>El campo <strong>{$campo}</strong> no puede estar vacío.</div>";
            echo $mensaje;
            exit();
        }
    }

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
    $seguro_id = $_POST['seguro_id']; // Cambiamos tipo_seguro por seguro_id
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

    function procesarArchivos($inputName, $destinoBase) {
        $nombres = [];
        if (!empty($_FILES[$inputName]['name'][0])) {
            foreach ($_FILES[$inputName]['name'] as $index => $nombre) {
                $nombre_archivo = basename($nombre);
                $tmp = $_FILES[$inputName]['tmp_name'][$index];
                $ruta_destino = $destinoBase . time() . '' . $index . '' . $nombre_archivo;
                
                // Asegurarse de que el directorio existe
                $directorio = dirname($ruta_destino);
                if (!is_dir($directorio)) {
                    mkdir($directorio, 0777, true);
                }
                
                if (move_uploaded_file($tmp, $ruta_destino)) {
                    $nombres[] = basename($ruta_destino);
                }
            }
        }
        return implode(', ', $nombres);
    }

    // Asegurarse de que los directorios existen
    if (!is_dir('../archivos/cedula_')) {
        mkdir('../archivos/cedula_', 0777, true);
    }
    
    if (!is_dir('../archivos/doc_adic_')) {
        mkdir('../archivos/doc_adic_', 0777, true);
    }

    $cedula_nombre = procesarArchivos('cedula_escan', '../archivos/cedula_');
    $docs_adicionales_nombre = procesarArchivos('documentos_adicionales', '../archivos/doc_adic_');

    $firma_path = '';
    $firma_data = null;
    $estado = 'Pendiente de Firma';

    // Procesar firma dibujada en canvas
    if (isset($_POST['firma_canvas_data']) && !empty($_POST['firma_canvas_data'])) {
        // Procesar firma desde canvas
        $firma_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['firma_canvas_data']));
        
        // Crear directorio si no existe
        if (!is_dir('../firmas/')) {
            mkdir('../firmas/', 0777, true);
        }
        
        // Guardar la imagen en el servidor
        $firma_filename = 'firma_canvas_' . $usuario['id'] . '_' . time() . '.png';
        $firma_path = '../firmas/' . $firma_filename;
        file_put_contents($firma_path, $firma_data);
        
        // Generar hash de la firma
        $hash_firma = hash_file('sha256', $firma_path);
        $estado = 'En espera de aprobación';
    }
    // Procesar firma subida como imagen
    else if (isset($_FILES['firma']) && $_FILES['firma']['size'] > 0) {
        $ext = pathinfo($_FILES['firma']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['png', 'jpg', 'jpeg'])) {
            // Asegurarse de que el directorio existe
            if (!is_dir('../firmas/')) {
                mkdir('../firmas/', 0777, true);
            }
            
            $firma_filename = 'firma_upload_' . $usuario['id'] . '_' . time() . '.' . $ext;
            $firma_path = '../firmas/' . $firma_filename;
            move_uploaded_file($_FILES['firma']['tmp_name'], $firma_path);
            $firma_data = file_get_contents($firma_path);
            
            // Generar hash de la firma
            $hash_firma = hash_file('sha256', $firma_path);
            $estado = 'En espera de aprobación';
        }
    }

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
        // Asegurarse de que el directorio para PDFs existe
        if (!is_dir('../pdf')) {
            mkdir('../pdf', 0777, true);
        }
        
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, utf8_decode('Solicitud de Seguro de Vida'), 0, 1, 'C'); // Usar utf8_decode
        $pdf->SetFont('Arial', '', 12);
        $pdf->Ln(10);

        // Usa utf8_decode para los textos con caracteres especiales
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
        $pdf->Cell(0, 10, utf8_decode('Documentos Adjuntos'), 0, 1); // Usar utf8_decode
        $pdf->SetFont('Arial', '', 12);
        $pdf->AddPage();

        // Usar utf8_decode para convertir caracteres especiales
        $pdf->Cell(40, 10, utf8_decode('Cédula'));  // Aquí también
        $pdf->Cell(0, 8, utf8_decode('Documentos de identificación cargados: ' . ($cedula_nombre ?: 'Ninguno')), 0, 1);
        $pdf->Cell(0, 8, utf8_decode('Documentos adicionales cargados para revisión: ' . ($docs_adicionales_nombre ?: 'Ninguno')), 0, 1);

        if ($firma_path && file_exists($firma_path)) {
            $firma_y = $pdf->GetY() + 10;
            $pdf->Image($firma_path, 75, $firma_y, 60);
            $pdf->SetY($firma_y + 35);
            $pdf->Cell(0, 10, utf8_decode('_'), 0, 1, 'C');
            $pdf->Cell(0, 5, utf8_decode('Asegurado'), 0, 1, 'C');
        }

        $nombre_pdf = '../pdf/solicitud_seguro_' . $usuario['id'] . '_' . time() . '.pdf';
        $pdf->Output('F', $nombre_pdf);

        // Registrar la solicitud en la tabla de notificaciones
        $solicitud_id = $conn->insert_id;
        $sql_notificacion = "INSERT INTO notificaciones (usuario_id, tipo, mensaje, tipo_referencia, referencia_id) 
                            VALUES (?, 'solicitud', 'Su solicitud de seguro ha sido recibida y está en proceso.', 'seguro', ?)";
        $stmt_notif = $conn->prepare($sql_notificacion);
        $stmt_notif->bind_param("ii", $usuario['id'], $solicitud_id);
        $stmt_notif->execute();
        $stmt_notif->close();

        $mensaje = "<div class='alert alert-success mt-4' id='mensaje-exito'>Formulario enviado correctamente. <a href='$nombre_pdf' target='_blank'>Ver PDF generado</a>. <a href='panel_cliente.php'>Volver al panel</a></div>";
        
        // Obtener el ID de la solicitud recién creada
        $nueva_solicitud_id = $conn->insert_id;

        // Agregar el ID a la URL para poder usarlo en el botón de firma
        echo "<script>
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('solicitud_id', '$nueva_solicitud_id');
            window.history.replaceState({}, '', '?' + urlParams.toString());
        </script>";
        
        echo "<script>setTimeout(() => { window.location.href = 'panel_cliente.php'; }, 7000);</script>";
    } else {
        $mensaje = "<div class='alert alert-danger mt-4'>Error al guardar: " . $stmt_insert->error . "</div>";
    }
    $stmt_insert->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formulario Seguro de Vida</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background: linear-gradient(to right, #ffffff, #062D49);
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
        .nav-tabs .nav-link.active {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}
.nav-tabs .nav-link {
    cursor: pointer;
}
#firma-canvas {
    border: none;
    background-color: white;
    width: 100%;
    height: 200px;
}
.security-info {
    background-color: #f8f9fa;
    border-left: 4px solid #0d6efd;
    padding: 15px;
    margin-top: 20px;
}
.security-info h6 {
    color: #0d6efd;
    margin-bottom: 10px;
}
.security-info ul {
    padding-left: 20px;
    margin-bottom: 0;
}
    </style>
    <script src="../Cliente/firma_digital.js" defer></script>
</head>
<body>
<div class="container py-5">
    <div class="form-container mx-auto col-lg-8 col-md-10">
        <h2 class="mb-4 text-center text-primary">Formulario Seguro de Vida</h2>
        <?php echo $mensaje ?? ''; ?>
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" id="form-firma" novalidate>
            <div class="row g-3">
                <!-- Datos del usuario -->
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
                    <label>Tipo de identificación</label>
                    <select name="tipo_identificacion" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Cédula">Cédula</option>
                        <option value="Pasaporte">Pasaporte</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label>Nro. Identificación</label>
                    <input type="text" name="cedula" class="form-control" required maxlength="10" pattern="\d{10}">
                </div>
                <div class="col-md-6">
                    <label>Documentos escaneados</label>
                    <input type="file" name="cedula_escan[]" class="form-control" accept=".pdf,.jpg,.png" multiple>
                </div>
                <div class="col-md-6">
                    <label>Sexo</label>
                    <select name="sexo" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Femenino">Femenino</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Lugar de nacimiento</label>
                    <input type="text" name="lugar_nacimiento" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Nacionalidad</label>
                    <input type="text" name="nacionalidad" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Estado civil</label>
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
                    <label>Ocupación</label>
                    <input type="text" name="ocupacion" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>¿Fumador?</label>
                    <select name="fumador" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Sí">Sí</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Peso (kg)</label>
                    <input type="number" name="peso" class="form-control" min="30" max="250" required>
                </div>

                <div class="col-md-6">
                    <label>Altura (cm)</label>
                    <input type="number" name="altura" class="form-control" min="100" max="250" required>
                </div>

                <div class="col-md-6">
                    <label>Tipo de sangre</label>
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
                    <label>Forma de pago</label>
                    <select name="forma_pago" class="form-select" required>
                        <option value="">Seleccione</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Tarjeta">Tarjeta</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>

                <div class="col-12">
                    <label>Enfermedades previas</label>
                    <textarea name="enfermedades" class="form-control" required></textarea>
                </div>
                <div class="col-12">
                    <label>Alergias conocidas</label>
                    <textarea name="alergias" class="form-control" required></textarea>
                </div>
                
                <!-- Sección de selección de seguros -->
                <div class="col-12 mb-4">
                    <label class="form-label fw-bold fs-5 mb-3">Seleccionar Plan de Seguro</label>
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

                <div class="col-12" id="campo_num_hijos">
                    <label>Dependientes</label>
                    <input type="number" id="num_hijos" name="num_hijos" min="0" max="10" class="form-control" value="0">
                    <div id="advertencia_hijos" class="text-danger mt-1" style="display:none">No es aplicable para el seguro</div>
                </div>

                <div class="col-12" id="datos_hijos"></div>
                <div class="col-md-6">
                    <label>Subir documentos adicionales</label>
                    <input type="file" name="documentos_adicionales[]" class="form-control" accept=".pdf,.jpg,.png" multiple>
                    <small class="text-muted">Los documentos se guardarán en la carpeta archivos/doc_adic_</small>
                </div>
                <!-- Sección de firma digital mejorada -->
<div class="col-12 mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-signature"></i> Registrar Firma Digital</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fs-4 me-3"></i>
                    <div>
                        <h6 class="mb-1">Validez Legal de la Firma</h6>
                        <p class="mb-0">Tu firma digital tiene validez legal y será utilizada para certificar tu solicitud de seguro.</p>
                    </div>
                </div>
            </div>
            
            <!-- Pestañas de firma -->
            <ul class="nav nav-tabs mb-3" id="firma-tabs">
                <li class="nav-item">
                    <button class="nav-link active" id="tab-dibujar" type="button">
                        <i class="fas fa-pen me-2"></i>Dibujar Firma
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab-subir" type="button">
                        <i class="fas fa-upload me-2"></i>Subir Imagen
                    </button>
                </li>
            </ul>
            
            <!-- Contenido de las pestañas -->
            <div id="content-dibujar" style="display: block;">
                <p>Dibuja tu firma en el recuadro:</p>
                <div class="border border-2 border-primary rounded mb-3" style="border-style: dashed !important;">
                    <canvas id="firma-canvas" width="100%" height="200" style="width: 100%; cursor: crosshair;"></canvas>
                </div>
                <button type="button" id="limpiar-firma" class="btn btn-outline-secondary">
                    <i class="fas fa-eraser me-2"></i>Limpiar
                </button>
                <input type="hidden" name="firma_canvas_data" id="firma_canvas_data">
            </div>
            
            <div id="content-subir" style="display: none;">
                <div class="mb-3">
                    <label class="form-label">Sube una imagen de tu firma:</label>
                    <input type="file" name="firma" id="firma_upload" class="form-control" accept="image/png,image/jpg,image/jpeg">
                    <small class="text-muted">Formatos permitidos: PNG, JPG, JPEG. Tamaño máximo: 2MB</small>
                </div>
                
                <div class="mb-3" id="firma-preview-container" style="display: none;">
                    <label class="form-label">Vista previa:</label>
                    <div class="border rounded p-2 bg-light">
                        <img id="firma-preview" class="img-fluid" style="max-height: 150px;" alt="Vista previa de la firma">
                    </div>
                </div>
            </div>
            
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="acepto_firma" name="acepto_firma" required>
                <label class="form-check-label" for="acepto_firma">
                    Acepto que esta firma tiene validez legal y autorizo su uso en mi solicitud de seguro
                </label>
            </div>
        </div>
    </div>
</div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Enviar Solicitud</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('estado_civil').addEventListener('change', function() {
        var estadoCivil = this.value;
        var campoConyugeNombre = document.getElementById('campo_conyugue_nombre');
        var campoConyugeCedula = document.getElementById('campo_conyugue_cedula');

        if (estadoCivil === 'Casado') {
            campoConyugeNombre.style.display = 'block';
            campoConyugeCedula.style.display = 'block';
        } else {
            campoConyugeNombre.style.display = 'none';
            campoConyugeCedula.style.display = 'none';
        }
    });

    document.getElementById('num_hijos').addEventListener('change', function() {
        var numHijos = parseInt(this.value);
        var datosHijosDiv = document.getElementById('datos_hijos');
        datosHijosDiv.innerHTML = ''; // Limpiar contenido anterior

        if (numHijos > 0) {
            for (let i = 1; i <= numHijos; i++) {
                let hijoDiv = document.createElement('div');
                hijoDiv.innerHTML = `
                    <h5 class="mt-3">Datos del hijo ${i}</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Nombre completo</label>
                            <input type="text" name="dep_nombre_${i}" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Cédula</label>
                            <input type="text" name="dep_cedula_${i}" class="form-control" required maxlength="10" pattern="\\d{10}">
                        </div>
                        <div class="col-md-6">
                            <label>Lugar de nacimiento</label>
                            <input type="text" name="dep_lugar_${i}" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Nacionalidad</label>
                            <input type="text" name="dep_nacionalidad_${i}" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Edad</label>
                            <input type="number" name="dep_edad_${i}" class="form-control" min="0" max="30" required>
                        </div>
                        <div class="col-md-6">
                            <label>Parentesco</label>
                            <input type="text" name="dep_parentesco_${i}" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Sexo</label>
                            <select name="dep_sexo_${i}" class="form-select" required>
                                <option value="">Seleccione</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                    </div>
                `;
                datosHijosDiv.appendChild(hijoDiv);
            }
        }
    });

    function seleccionarSeguro(element) {
        // Remover la clase 'selected' de todos los elementos
        document.querySelectorAll('.seguro-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Añadir la clase 'selected' al elemento actual
        element.classList.add('selected');

        // Obtener el ID del seguro y asignarlo al campo oculto
        var seguroId = element.getAttribute('data-id');
        document.getElementById('seguro_id').value = seguroId;
    }

    // Inicializar el preview de la firma al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        const firmaInput = document.getElementById('firma');
        const firmaPreviewContainer = document.getElementById('firma-preview-container');
        const firmaPreview = document.getElementById('firma-preview');

        if (firmaInput) {
            firmaInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        firmaPreview.src = e.target.result;
                        firmaPreviewContainer.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    firmaPreview.src = '';
                    firmaPreviewContainer.style.display = 'none';
                }
            });
        }
    });
</script>
</body>
</html>
