<?php
session_start();
require_once '../conexion.php';

// Verificar permisos
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

$seguro_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos del seguro
$seguro = $conn->query("SELECT * FROM tipos_seguro WHERE id = $seguro_id")->fetch_assoc();
$config = $conn->query("SELECT * FROM configuraciones_seguro WHERE tipo_seguro_id = $seguro_id")->fetch_assoc();

if (!$seguro) {
    header("Location: gestion_seguros.php?error=seguro_no_encontrado");
    exit();
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Limpieza de datos
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $coberturas = $conn->real_escape_string($_POST['coberturas']);
    $beneficios = $conn->real_escape_string($_POST['beneficios']);
    $requisitos = $conn->real_escape_string($_POST['requisitos']);
    $estado = isset($_POST['estado']) ? 1 : 0;
    $periodo_pago = $conn->real_escape_string($_POST['periodo_pago']);
    $monto_base = floatval($_POST['monto_base']);
    
    // Generar fórmula automática basada en factores de riesgo
    $formula_prima = "monto_base * (1 + edad_factor + riesgo_factor)";
    $factores_riesgo = $conn->real_escape_string(json_encode($_POST['factores']));
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Actualizar tipo de seguro
        $sql_seguro = "UPDATE tipos_seguro SET 
                      nombre = '$nombre',
                      descripcion = '$descripcion',
                      coberturas = '$coberturas',
                      beneficios = '$beneficios',
                      requisitos = '$requisitos',
                      estado = $estado
                      WHERE id = $seguro_id";
        
        if (!$conn->query($sql_seguro)) {
            throw new Exception("Error al actualizar seguro: " . $conn->error);
        }
        
        // Actualizar configuración
        $sql_config = "UPDATE configuraciones_seguro SET 
                      periodo_pago = '$periodo_pago',
                      monto_base = $monto_base,
                      formula_prima = '$formula_prima',
                      factores_riesgo = '$factores_riesgo'
                      WHERE tipo_seguro_id = $seguro_id";
        
        if (!$conn->query($sql_config)) {
            throw new Exception("Error al actualizar configuración: " . $conn->error);
        }
        
        $conn->commit();
        $_SESSION['success'] = "Seguro actualizado correctamente";
        header("Location: gestion_seguros.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Decodificar factores de riesgo
$factores = json_decode($config['factores_riesgo'] ?? '{}', true);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Seguro - Panel Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: 0.5rem;
            background-color: #f8f9fa;
        }
        .form-section h5 {
            color: #0d6efd;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .calculator-section {
            background-color: #e3f2fd;
            border: 2px solid #2196f3;
        }
        .result-display {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        .premium-result {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        @media (max-width: 768px) {
            .form-section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-edit"></i> Editar Seguro: <?= htmlspecialchars($seguro['nombre']) ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="seguroForm">
                            <div class="form-section">
                                <h5><i class="fas fa-info-circle"></i> Información Básica</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre del Seguro</label>
                                        <input type="text" name="nombre" class="form-control" 
                                               value="<?= htmlspecialchars($seguro['nombre']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Estado</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="estado" id="estado" 
                                                   <?= $seguro['estado'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="estado">Activo</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Descripción</label>
                                        <textarea name="descripcion" class="form-control" rows="3" required><?= htmlspecialchars($seguro['descripcion']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5><i class="fas fa-shield-alt"></i> Coberturas y Beneficios</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Coberturas Principales</label>
                                        <textarea name="coberturas" class="form-control" rows="5" required><?= htmlspecialchars($seguro['coberturas']) ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Beneficios Adicionales</label>
                                        <textarea name="beneficios" class="form-control" rows="5" required><?= htmlspecialchars($seguro['beneficios']) ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Requisitos para Contratación</label>
                                        <textarea name="requisitos" class="form-control" rows="3" required><?= htmlspecialchars($seguro['requisitos']) ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5><i class="fas fa-calculator"></i> Configuración Financiera</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Periodo de Pago</label>
                                        <select name="periodo_pago" class="form-select" required>
                                            <option value="mensual" <?= $config['periodo_pago'] == 'mensual' ? 'selected' : '' ?>>Mensual</option>
                                            <option value="trimestral" <?= $config['periodo_pago'] == 'trimestral' ? 'selected' : '' ?>>Trimestral</option>
                                            <option value="anual" <?= $config['periodo_pago'] == 'anual' ? 'selected' : '' ?>>Anual</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Monto Base</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" name="monto_base" id="monto_base" class="form-control" 
                                                   value="<?= number_format($config['monto_base'] ?? 0, 2, '.', '') ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h5><i class="fas fa-chart-line"></i> Factores de Riesgo</h5>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Edad Mínima</label>
                                        <input type="number" name="factores[edad_min]" id="edad_min" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['edad_min'] ?? '18') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Edad Máxima</label>
                                        <input type="number" name="factores[edad_max]" id="edad_max" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['edad_max'] ?? '65') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Factor por Edad (%)</label>
                                        <input type="number" step="0.01" name="factores[edad_factor]" id="edad_factor" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['edad_factor'] ?? '0.05') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Riesgo Base (%)</label>
                                        <input type="number" step="0.01" name="factores[riesgo_base]" id="riesgo_base" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['riesgo_base'] ?? '0.1') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Incremento Anual (%)</label>
                                        <input type="number" step="0.01" name="factores[incremento_anual]" id="incremento_anual" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['incremento_anual'] ?? '0.02') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Máximo Riesgo (%)</label>
                                        <input type="number" step="0.01" name="factores[max_riesgo]" id="max_riesgo" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['max_riesgo'] ?? '0.5') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Descuento Lealtad (%)</label>
                                        <input type="number" step="0.01" name="factores[descuento_lealtad]" id="descuento_lealtad" class="form-control factor-input" 
                                               value="<?= htmlspecialchars($factores['descuento_lealtad'] ?? '0.01') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Nueva sección de calculadora de primas -->
                            <div class="form-section calculator-section">
                                <h5><i class="fas fa-calculator"></i> Calculadora de Primas</h5>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Edad del Cliente (para prueba)</label>
                                        <input type="number" id="edad_cliente" class="form-control" value="30" min="18" max="100">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Años de Lealtad</label>
                                        <input type="number" id="anos_lealtad" class="form-control" value="0" min="0">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="button" id="calcularPrima" class="btn btn-success w-100">
                                            <i class="fas fa-calculator"></i> Calcular Prima
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="result-display" id="resultadoCalculo" style="display: none;">
                                    <h6><i class="fas fa-chart-bar"></i> Resultado del Cálculo</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <small class="text-muted">Monto Base:</small>
                                            <div id="montoBaseResult">$0.00</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Factor Edad:</small>
                                            <div id="factorEdadResult">0%</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Factor Riesgo:</small>
                                            <div id="factorRiesgoResult">0%</div>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Descuento:</small>
                                            <div id="descuentoResult">0%</div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="text-center">
                                        <small class="text-muted">Prima Total Calculada:</small>
                                        <div class="premium-result" id="primaTotal">$0.00</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="gestion_seguros.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para calcular la prima automáticamente
        function calcularPrimaAutomatica() {
            // Obtener valores de los campos
            const montoBase = parseFloat(document.getElementById('monto_base').value) || 0;
            const edadCliente = parseInt(document.getElementById('edad_cliente').value) || 18;
            const anosLealtad = parseInt(document.getElementById('anos_lealtad').value) || 0;
            
            // Obtener factores de riesgo
            const edadMin = parseInt(document.getElementById('edad_min').value) || 18;
            const edadMax = parseInt(document.getElementById('edad_max').value) || 65;
            const edadFactor = parseFloat(document.getElementById('edad_factor').value) || 0.05;
            const riesgoBase = parseFloat(document.getElementById('riesgo_base').value) || 0.1;
            const incrementoAnual = parseFloat(document.getElementById('incremento_anual').value) || 0.02;
            const maxRiesgo = parseFloat(document.getElementById('max_riesgo').value) || 0.5;
            const descuentoLealtad = parseFloat(document.getElementById('descuento_lealtad').value) || 0.01;
            
            // Validar edad dentro del rango
            if (edadCliente < edadMin || edadCliente > edadMax) {
                alert(`La edad debe estar entre ${edadMin} y ${edadMax} años`);
                return;
            }
            
            // Calcular factor por edad (incrementa con la edad)
            const factorEdadCalculado = ((edadCliente - edadMin) / (edadMax - edadMin)) * edadFactor;
            
            // Calcular factor de riesgo (base + incremento por edad)
            let factorRiesgo = riesgoBase + (factorEdadCalculado * incrementoAnual);
            factorRiesgo = Math.min(factorRiesgo, maxRiesgo); // No exceder el máximo
            
            // Calcular descuento por lealtad
            const descuentoTotal = Math.min(anosLealtad * descuentoLealtad, 0.2); // Máximo 20% descuento
            
            // Calcular prima total
            const primaBase = montoBase * (1 + factorEdadCalculado + factorRiesgo);
            const primaFinal = primaBase * (1 - descuentoTotal);
            
            // Mostrar resultados
            document.getElementById('montoBaseResult').textContent = `$${montoBase.toFixed(2)}`;
            document.getElementById('factorEdadResult').textContent = `${(factorEdadCalculado * 100).toFixed(2)}%`;
            document.getElementById('factorRiesgoResult').textContent = `${(factorRiesgo * 100).toFixed(2)}%`;
            document.getElementById('descuentoResult').textContent = `${(descuentoTotal * 100).toFixed(2)}%`;
            document.getElementById('primaTotal').textContent = `$${primaFinal.toFixed(2)}`;
            
            // Mostrar la sección de resultados
            document.getElementById('resultadoCalculo').style.display = 'block';
        }
        
        // Event listener para el botón de calcular
        document.getElementById('calcularPrima').addEventListener('click', calcularPrimaAutomatica);
        
        // Recalcular automáticamente cuando cambien los factores
        document.querySelectorAll('.factor-input, #monto_base').forEach(input => {
            input.addEventListener('input', function() {
                if (document.getElementById('resultadoCalculo').style.display !== 'none') {
                    calcularPrimaAutomatica();
                }
            });
        });
        
        // Validación del formulario
        document.getElementById('seguroForm').addEventListener('submit', function(e) {
            const edadMin = parseInt(document.getElementById('edad_min').value);
            const edadMax = parseInt(document.getElementById('edad_max').value);
            
            if (edadMin >= edadMax) {
                alert('La edad mínima debe ser menor que la edad máxima');
                e.preventDefault();
                return false;
            }
            
            const montoBase = parseFloat(document.getElementById('monto_base').value);
            if (montoBase <= 0) {
                alert('El monto base debe ser mayor a 0');
                e.preventDefault();
                return false;
            }
        });
        
        // Calcular prima inicial si hay datos
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('monto_base').value > 0) {
                calcularPrimaAutomatica();
            }
        });
    </script>
</body>
</html>
