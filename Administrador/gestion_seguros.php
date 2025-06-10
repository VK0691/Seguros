<?php
session_start();
require_once '../conexion.php';

// Verificación de permisos
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'crear') {
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
        $factores_riesgo = $conn->real_escape_string(json_encode($_POST['factores'] ?? [
            'edad_min' => 18,
            'edad_max' => 65,
            'edad_factor' => 0.05,
            'riesgo_base' => 0.1,
            'incremento_anual' => 0.02,
            'max_riesgo' => 0.5,
            'descuento_lealtad' => 0.01
        ]));
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Insertar tipo de seguro
            $sql = "INSERT INTO tipos_seguro (nombre, descripcion, coberturas, beneficios, requisitos, estado) 
                    VALUES ('$nombre', '$descripcion', '$coberturas', '$beneficios', '$requisitos', $estado)";
            
            if (!$conn->query($sql)) {
                throw new Exception("Error al crear seguro: " . $conn->error);
            }
            
            $seguro_id = $conn->insert_id;
            
            // Insertar configuración
            $sql_config = "INSERT INTO configuraciones_seguro 
                          (tipo_seguro_id, periodo_pago, monto_base, formula_prima, factores_riesgo) 
                          VALUES ($seguro_id, '$periodo_pago', $monto_base, '$formula_prima', '$factores_riesgo')";
            
            if (!$conn->query($sql_config)) {
                throw new Exception("Error al guardar configuración: " . $conn->error);
            }
            
            $conn->commit();
            $_SESSION['success'] = "Seguro creado exitosamente";
            header("Location: gestion_seguros.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Obtener listado de seguros con paginación
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$total_seguros = $conn->query("SELECT COUNT(*) AS total FROM tipos_seguro")->fetch_assoc()['total'];
$total_paginas = ceil($total_seguros / $por_pagina);

$seguros = $conn->query("SELECT t.*, c.periodo_pago, c.monto_base, c.formula_prima 
                        FROM tipos_seguro t
                        LEFT JOIN configuraciones_seguro c ON t.id = c.tipo_seguro_id
                        ORDER BY t.nombre
                        LIMIT $offset, $por_pagina");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Seguros</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-shadow {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .badge-active {
            background-color: #28a745;
        }
        .badge-inactive {
            background-color: #6c757d;
        }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .form-section {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
        }
        .form-section h6 {
            color: #0d6efd;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        .calculator-section {
            background-color: #e3f2fd;
            border-left-color: #2196f3;
        }
        .result-display {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-top: 0.75rem;
        }
        .premium-result {
            font-size: 1.25rem;
            font-weight: bold;
            color: #28a745;
        }
        @media (max-width: 768px) {
            .form-container {
                margin-bottom: 20px;
            }
            .table-responsive {
                font-size: 0.9rem;
            }
            .action-buttons .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            .form-section {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-4 form-container">
                <div class="card card-shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-plus-circle me-2"></i>Nuevo Seguro</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="nuevoSeguroForm">
                            <input type="hidden" name="action" value="crear">
                            
                            <!-- Información Básica -->
                            <div class="form-section">
                                <h6><i class="fas fa-info-circle me-1"></i>Información Básica</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nombre del Seguro*</label>
                                    <input type="text" name="nombre" class="form-control" required maxlength="100">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descripción*</label>
                                    <textarea name="descripcion" class="form-control" rows="2" required maxlength="500"></textarea>
                                </div>
                                
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="estado" id="estado" checked>
                                    <label class="form-check-label" for="estado">Activar este seguro</label>
                                </div>
                            </div>
                            
                            <!-- Coberturas -->
                            <div class="form-section">
                                <h6><i class="fas fa-shield-alt me-1"></i>Coberturas y Beneficios</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">Coberturas Principales*</label>
                                    <textarea name="coberturas" class="form-control" rows="2" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Beneficios*</label>
                                    <textarea name="beneficios" class="form-control" rows="2" required></textarea>
                                </div>
                                
                                <div class="mb-0">
                                    <label class="form-label">Requisitos*</label>
                                    <textarea name="requisitos" class="form-control" rows="2" required></textarea>
                                </div>
                            </div>
                            
                            <!-- Configuración Financiera -->
                            <div class="form-section">
                                <h6><i class="fas fa-dollar-sign me-1"></i>Configuración Financiera</h6>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Periodo de Pago*</label>
                                        <select name="periodo_pago" class="form-select" required>
                                            <option value="mensual">Mensual</option>
                                            <option value="trimestral">Trimestral</option>
                                            <option value="anual">Anual</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Monto Base*</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0" name="monto_base" id="monto_base_nuevo" class="form-control factor-input" required>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Factores de Riesgo -->
                            <div class="form-section">
                                <h6><i class="fas fa-chart-line me-1"></i>Factores de Riesgo</h6>
                                
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Edad Mín.</label>
                                        <input type="number" name="factores[edad_min]" id="edad_min_nuevo" class="form-control form-control-sm factor-input" value="18">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Edad Máx.</label>
                                        <input type="number" name="factores[edad_max]" id="edad_max_nuevo" class="form-control form-control-sm factor-input" value="65">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Factor Edad (%)</label>
                                        <input type="number" step="0.01" name="factores[edad_factor]" id="edad_factor_nuevo" class="form-control form-control-sm factor-input" value="0.05">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Riesgo Base (%)</label>
                                        <input type="number" step="0.01" name="factores[riesgo_base]" id="riesgo_base_nuevo" class="form-control form-control-sm factor-input" value="0.1">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Increm. Anual (%)</label>
                                        <input type="number" step="0.01" name="factores[incremento_anual]" id="incremento_anual_nuevo" class="form-control form-control-sm factor-input" value="0.02">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Máx. Riesgo (%)</label>
                                        <input type="number" step="0.01" name="factores[max_riesgo]" id="max_riesgo_nuevo" class="form-control form-control-sm factor-input" value="0.5">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Calculadora de Primas -->
                            <div class="form-section calculator-section">
                                <h6><i class="fas fa-calculator me-1"></i>Calculadora de Primas</h6>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label">Edad Cliente</label>
                                        <input type="number" id="edad_cliente_nuevo" class="form-control form-control-sm" value="30" min="18" max="100">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Años Lealtad</label>
                                        <input type="number" id="anos_lealtad_nuevo" class="form-control form-control-sm" value="0" min="0">
                                    </div>
                                </div>
                                
                                <button type="button" id="calcularPrimaNuevo" class="btn btn-success btn-sm w-100 mb-2">
                                    <i class="fas fa-calculator"></i> Calcular Prima
                                </button>
                                
                                <div class="result-display" id="resultadoCalculoNuevo" style="display: none;">
                                    <div class="row g-1 text-center">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Base:</small>
                                            <span id="montoBaseResultNuevo" class="fw-bold">$0.00</span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Prima:</small>
                                            <span class="premium-result" id="primaTotalNuevo">$0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save me-2"></i>Guardar Seguro
                            </button>
                            <div>     
                                    <a href="../Administrador/adminpanel.php" class="btn-regresar">Regresar</a>
           
                            </div>
                            
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card card-shadow">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4><i class="fas fa-list me-2"></i>Listado de Seguros</h4>
                            <span class="badge bg-light text-dark">Total: <?= $total_seguros ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Periodo</th>
                                        <th>Monto Base</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($seguros->num_rows > 0): ?>
                                        <?php while($seguro = $seguros->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($seguro['nombre']) ?></strong>
                                                <small class="d-block text-muted"><?= substr(htmlspecialchars($seguro['descripcion']), 0, 50) ?>...</small>
                                            </td>
                                            <td><?= ucfirst($seguro['periodo_pago']) ?></td>
                                            <td>$<?= number_format($seguro['monto_base'], 2) ?></td>
                                            <td>
                                                <span class="badge <?= $seguro['estado'] ? 'badge-active' : 'badge-inactive' ?>">
                                                    <?= $seguro['estado'] ? 'Activo' : 'Inactivo' ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="editar_seguro.php?id=<?= $seguro['id'] ?>" class="btn btn-sm btn-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="toggle_seguro.php?id=<?= $seguro['id'] ?>" class="btn btn-sm <?= $seguro['estado'] ? 'btn-danger' : 'btn-success' ?>" title="<?= $seguro['estado'] ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-power-off"></i>
                                                </a>
                                                
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No hay seguros registrados</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <!-- Paginación -->
                            <?php if($total_paginas > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <?php if($pagina > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $pagina-1 ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for($i = 1; $i <= $total_paginas; $i++): ?>
                                        <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if($pagina < $total_paginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $pagina+1 ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para calcular la prima automáticamente en el formulario de creación
        function calcularPrimaAutomaticaNuevo() {
            // Obtener valores de los campos
            const montoBase = parseFloat(document.getElementById('monto_base_nuevo').value) || 0;
            const edadCliente = parseInt(document.getElementById('edad_cliente_nuevo').value) || 18;
            const anosLealtad = parseInt(document.getElementById('anos_lealtad_nuevo').value) || 0;
            
            // Obtener factores de riesgo
            const edadMin = parseInt(document.getElementById('edad_min_nuevo').value) || 18;
            const edadMax = parseInt(document.getElementById('edad_max_nuevo').value) || 65;
            const edadFactor = parseFloat(document.getElementById('edad_factor_nuevo').value) || 0.05;
            const riesgoBase = parseFloat(document.getElementById('riesgo_base_nuevo').value) || 0.1;
            const incrementoAnual = parseFloat(document.getElementById('incremento_anual_nuevo').value) || 0.02;
            const maxRiesgo = parseFloat(document.getElementById('max_riesgo_nuevo').value) || 0.5;
            const descuentoLealtad = 0.01; // Valor fijo para nuevos seguros
            
            // Validar edad dentro del rango
            if (edadCliente < edadMin || edadCliente > edadMax) {
                alert(`La edad debe estar entre ${edadMin} y ${edadMax} años`);
                return;
            }
            
            // Validar monto base
            if (montoBase <= 0) {
                alert('Debe ingresar un monto base válido');
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
            document.getElementById('montoBaseResultNuevo').textContent = `$${montoBase.toFixed(2)}`;
            document.getElementById('primaTotalNuevo').textContent = `$${primaFinal.toFixed(2)}`;
            
            // Mostrar la sección de resultados
            document.getElementById('resultadoCalculoNuevo').style.display = 'block';
        }
        
        // Event listener para el botón de calcular
        document.getElementById('calcularPrimaNuevo').addEventListener('click', calcularPrimaAutomaticaNuevo);
        
        // Recalcular automáticamente cuando cambien los factores
        document.querySelectorAll('.factor-input').forEach(input => {
            input.addEventListener('input', function() {
                if (document.getElementById('resultadoCalculoNuevo').style.display !== 'none') {
                    calcularPrimaAutomaticaNuevo();
                }
            });
        });
        
        // Validación del formulario
        document.getElementById('nuevoSeguroForm').addEventListener('submit', function(e) {
            const edadMin = parseInt(document.getElementById('edad_min_nuevo').value);
            const edadMax = parseInt(document.getElementById('edad_max_nuevo').value);
            
            if (edadMin >= edadMax) {
                alert('La edad mínima debe ser menor que la edad máxima');
                e.preventDefault();
                return false;
            }
            
            const monto = document.getElementById('monto_base_nuevo').value;
            if (parseFloat(monto) <= 0) {
                alert('El monto base debe ser mayor que cero');
                e.preventDefault();
                return false;
            }
        });
        
        // Calcular prima inicial si hay datos válidos
        document.addEventListener('DOMContentLoaded', function() {
            const montoBase = document.getElementById('monto_base_nuevo');
            if (montoBase) {
                montoBase.addEventListener('blur', function() {
                    if (this.value > 0) {
                        calcularPrimaAutomaticaNuevo();
                    }
                });
            }
        });
    </script>
</body>
</html>
