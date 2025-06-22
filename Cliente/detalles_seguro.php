<?php
// Conexión a la base de datos
include '../conexion.php';

if (isset($_GET['id'])) {
    $seguro_id = intval($_GET['id']);
    $query = "SELECT * FROM seguros WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $seguro_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $seguro = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Seguro - <?= htmlspecialchars($seguro['nombre'] ?? '') ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Estilos personalizados -->
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .card-seguro {
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 30px;
        }
        .card-seguro:hover {
            transform: translateY(-5px);
        }
        .header-seguro {
            background-color: #007bff;
            color: white;
            border-top-left-radius: 15px !important;
            border-top-right-radius: 15px !important;
        }
        .seguro-img {
            border-radius: 15px 15px 0 0;
            max-height: 300px;
            object-fit: contain;
        }
        .badge-cobertura {
            background-color: #28a745;
            font-size: 1rem;
        }
        .benefits-list {
            list-style-type: none;
            padding-left: 0;
        }
        .benefits-list li::before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }
        .btn-contract {
            background-color: #ff6b35;
            border: none;
            padding: 10px 25px;
            font-weight: bold;
        }
        .btn-contract:hover {
            background-color: #ff8c5a;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($seguro): ?>
                    <div class="card card-seguro">
                        <?php if ($seguro['imagen_seguro']): ?>
                            <img src="../assets/img/seguros/<?= htmlspecialchars($seguro['imagen_seguro']) ?>" class="card-img-top seguro-img" alt="<?= htmlspecialchars($seguro['nombre']) ?>">
                        <?php else: ?>
                            <div class="header-seguro p-4 text-center">
                                <h1><?= htmlspecialchars($seguro['nombre']) ?></h1>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h2 class="card-title mb-4"><?= htmlspecialchars($seguro['nombre']) ?></h2>
                            <p class="card-text lead"><?= htmlspecialchars($seguro['descripcion']) ?></p>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded mb-3">
                                        <h4 class="text-primary">Precio</h4>
                                        <h3 class="fw-bold">$<?= number_format($seguro['precio'], 2) ?> <small class="text-muted fs-6">/mes</small></h3>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded mb-3">
                                        <h4 class="text-primary">Cobertura Máxima</h4>
                                        <h3 class="fw-bold">$<?= number_format($seguro['cobertura_maxima'], 2) ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-success mt-4">
                                <span class="badge badge-cobertura me-2">Reembolso</span>
                                <span class="fw-bold"><?= htmlspecialchars($seguro['porcentaje_reembolso']) ?>%</span> de reembolso garantizado
                            </div>
                            
                            <h4 class="mt-4">Beneficios incluidos:</h4>
                            <ul class="benefits-list">
                                <li class="py-2">Cobertura de hasta $<?= number_format($seguro['valor_cobertura'], 2) ?></li>
                                <li class="py-2">Protección por accidentes de $<?= number_format($seguro['valor_accidente'], 2) ?></li>
                                <li class="py-2">Asistencia médica 24/7</li>
                                <li class="py-2">Ambulancia gratuita</li>
                                <li class="py-2">Cobertura nacional e internacional</li>
                            </ul>

                            <h4 class="mt-4">Requisitos para la contratación:</h4>
                            <p>Para poder contratar este seguro, es necesario cumplir con los siguientes requisitos:</p>
                            <ul class="benefits-list">
                                <li class="py-2">Ser mayor de 18 años.</li>
                                <li class="py-2">Presentar documento de identidad válido.</li>
                                <li class="py-2">Completar el formulario de solicitud.</li>
                                <li class="py-2">Realizar el pago inicial correspondiente.</li>
                            </ul>

                            <h4 class="mt-4">Condiciones del seguro:</h4>
                            <p>Este seguro está sujeto a las siguientes condiciones:</p>
                            <ul class="benefits-list">
                                <li class="py-2">La cobertura es válida solo dentro del territorio nacional.</li>
                                <li class="py-2">Los reembolsos se procesan dentro de un plazo de 30 días hábiles.</li>
                                <li class="py-2">Se aplican deducibles en ciertos casos de reclamación.</li>
                                <li class="py-2">El seguro no cubre enfermedades preexistentes.</li>
                            </ul>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                
                                <button class="btn btn-danger" onclick="window.close()">Cerrar</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger text-center">
                        <h4>No se encontraron detalles para este seguro</h4>
                        <p>El plan de seguro solicitado no existe o no está disponible.</p>
                        <button class="btn btn-danger" onclick="window.close()">Cerrar</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
