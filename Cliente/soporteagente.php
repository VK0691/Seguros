<?php
session_start();
require_once '../conexion.php';

// Verificar autenticación
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// Obtener el ID del usuario
$usuario_id = $_SESSION['id'];

// Buscar el contrato PDF generado por el agente
$contrato_pdf = '';
$pdf_dir = '../pdf/';
if ($usuario_id > 0 && is_dir($pdf_dir)) {
    $archivos = glob($pdf_dir . "contrato_seguro_*{$usuario_id}*.pdf");
    if ($archivos && count($archivos) > 0) {
        usort($archivos, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $contrato_pdf = $archivos[0];
    }
}

// Si no se encuentra, muestra mensaje
if (!$contrato_pdf) {
    $contrato_pdf = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firma_canvas_data'])) {
    $firma_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $_POST['firma_canvas_data']));
    if (!is_dir('../firmas/')) {
        mkdir('../firmas/', 0777, true);
    }
    $firma_filename = 'firma_canvas_' . $_SESSION['id'] . '_' . time() . '.png';
    $firma_path = '../firmas/' . $firma_filename;
    file_put_contents($firma_path, $firma_data);

    // Guardar la firma en la base de datos (en la columna 'firma' de seguros_vida)
    require_once '../conexion.php';
    $usuario_id = $_SESSION['id'];
    // Busca la última solicitud aprobada de este usuario
    $sql = "UPDATE seguros_vida SET firma = ? WHERE usuario_id = ? ORDER BY fecha_solicitud DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $firma_filename, $usuario_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // Generar el PDF firmado automáticamente
    $url = "http://localhost/Seguros/Agente/generar_contrato_firmado.php?usuario_id=$usuario_id";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    echo 'OK';
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte Agente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        #firma-canvas {
            border: 1px solid #ccc;
            cursor: crosshair;
            background: #fff;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2>Notificación de Aprobación</h2>
        <p>Su solicitud de reembolso ha sido aprobada. Por favor, firme el contrato a continuación:</p>
        
        <h5>Contrato</h5>
        <?php if ($contrato_pdf): ?>
            <iframe src="<?= htmlspecialchars($contrato_pdf) ?>" width="100%" height="500px"></iframe>
        <?php else: ?>
            <div class="alert alert-warning">No se ha encontrado el contrato PDF generado por el agente.</div>
        <?php endif; ?>
        
        <div id="content-dibujar" style="display: block;">
            <p>Dibuja tu firma en el recuadro:</p>
            <div class="border border-2 border-primary rounded mb-3" style="border-style: dashed !important;">
                <canvas id="firma-canvas" width="400" height="200"></canvas>
            </div>
            <button type="button" id="limpiar-firma" class="btn btn-outline-secondary">
                <i class="fas fa-eraser me-2"></i>Limpiar
            </button>
            <button type="button" id="deshacer-firma" class="btn btn-outline-warning ms-2">
                <i class="fas fa-undo"></i> Deshacer
            </button>
            <input type="hidden" name="firma_canvas_data" id="firma_canvas_data">
        </div>
        
        <button type="button" id="enviar-contrato" class="btn btn-success mt-3" disabled>
            <i class="fas fa-paper-plane me-2"></i>Aceptar y Enviar Contrato Firmado
        </button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('firma-canvas');
        const ctx = canvas.getContext('2d');
        let dibujando = false;
        let hayFirma = false;
        let lastX = 0, lastY = 0;
        let trazos = [];
        let trazoActual = [];

        ctx.lineWidth = 2;
        ctx.lineCap = "round";
        ctx.lineJoin = "round";
        ctx.strokeStyle = "#000";

        // Dibujo con mouse
        canvas.addEventListener('mousedown', e => {
            dibujando = true;
            trazoActual = [];
            lastX = e.offsetX;
            lastY = e.offsetY;
            trazoActual.push([lastX, lastY]);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        });
        canvas.addEventListener('mousemove', e => {
            if (!dibujando) return;
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.stroke();
            trazoActual.push([e.offsetX, e.offsetY]);
            hayFirma = true;
            document.getElementById('enviar-contrato').disabled = false;
        });
        canvas.addEventListener('mouseup', () => {
            if (dibujando) {
                dibujando = false;
                trazos.push(trazoActual);
            }
        });
        canvas.addEventListener('mouseleave', () => {
            if (dibujando) {
                dibujando = false;
                trazos.push(trazoActual);
            }
        });

        // Dibujo con touch (móvil/tablet)
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            dibujando = true;
            trazoActual = [];
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            lastX = touch.clientX - rect.left;
            lastY = touch.clientY - rect.top;
            trazoActual.push([lastX, lastY]);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
        });
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (!dibujando) return;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            const x = touch.clientX - rect.left;
            const y = touch.clientY - rect.top;
            ctx.lineTo(x, y);
            ctx.stroke();
            trazoActual.push([x, y]);
            hayFirma = true;
            document.getElementById('enviar-contrato').disabled = false;
        });
        canvas.addEventListener('touchend', function(e) {
            if (dibujando) {
                dibujando = false;
                trazos.push(trazoActual);
            }
        });

        // Limpiar firma
        document.getElementById('limpiar-firma').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            trazos = [];
            hayFirma = false;
            document.getElementById('enviar-contrato').disabled = true;
        });

        // Deshacer último trazo (opcional, agrega un botón con id="deshacer-firma" si lo quieres)
        const btnDeshacer = document.getElementById('deshacer-firma');
        if (btnDeshacer) {
            btnDeshacer.addEventListener('click', () => {
                trazos.pop();
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                trazos.forEach(trazo => {
                    ctx.beginPath();
                    for (let i = 0; i < trazo.length; i++) {
                        const [x, y] = trazo[i];
                        if (i === 0) ctx.moveTo(x, y);
                        else ctx.lineTo(x, y);
                    }
                    ctx.stroke();
                });
                hayFirma = trazos.length > 0;
                document.getElementById('enviar-contrato').disabled = !hayFirma;
            });
        }

        // Enviar firma
        document.getElementById('enviar-contrato').addEventListener('click', () => {
            if (!hayFirma) {
                alert('Por favor, dibuje su firma antes de enviar el contrato.');
                return;
            }
            const firma_data = canvas.toDataURL("image/png");
            const formData = new FormData();
            formData.append('firma_canvas_data', firma_data);

            fetch('soporteagente.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.trim() === 'OK') {
                    alert('Contrato firmado enviado correctamente.');
                    window.location.href = 'panel_cliente.php';
                } else {
                    alert('Error al guardar la firma. Respuesta: ' + data);
                }
            })
            .catch(error => {
                alert('Error al guardar la firma.');
            });
        });
    });
    </script>
</body>
</html>
