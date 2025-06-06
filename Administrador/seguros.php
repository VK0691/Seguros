<?php
session_start();
include '../conexion.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $precio = floatval($_POST['precio']);
    $cobertura_maxima = floatval($_POST['cobertura_maxima']);
    $porcentaje_reembolso = floatval($_POST['porcentaje_reembolso']);
    $valor_cobertura = floatval($_POST['valor_cobertura']);
    $valor_accidente = floatval($_POST['valor_accidente']);

    // Verificar que ningún campo esté vacío
    if (
        empty($nombre) || empty($descripcion) || empty($precio) ||
        empty($cobertura_maxima) || empty($porcentaje_reembolso) ||
        empty($valor_cobertura) || empty($valor_accidente)
    ) {
        $mensaje = "❌ Todos los campos deben estar completos.";
    } else {
        // Verificar si ya existe un seguro con el mismo nombre
        $sql_check = "SELECT id FROM seguros WHERE nombre = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $mensaje = "⚠️ Ya existe un seguro con ese nombre.";
        } else {
            $imagen_nombre = null;
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
                $dir_subida = '../imagenes/';
                if (!is_dir($dir_subida)) {
                    mkdir($dir_subida, 0777, true);
                }

                $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                $imagen_nombre = 'seguro_' . time() . '.' . $ext;
                $ruta_imagen = $dir_subida . $imagen_nombre;

                move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_imagen);
            }

            $sql = "INSERT INTO seguros 
                    (nombre, descripcion, precio, cobertura_maxima, porcentaje_reembolso, valor_cobertura, valor_accidente, imagen_seguro) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddddds", $nombre, $descripcion, $precio, $cobertura_maxima, $porcentaje_reembolso, $valor_cobertura, $valor_accidente, $imagen_nombre);

            if ($stmt->execute()) {
                $mensaje = "✅ Seguro creado exitosamente.";
            } else {
                $mensaje = "❌ Error al crear el seguro.";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Seguro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #002147, #ffffff);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-container {
            animation: fadeInUp 1s ease-in-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .btn-view {
            background-color: #FFD700;
            color: #002147;
            font-weight: bold;
            border: none;
            margin-top: 15px;
        }
        .btn-view:hover {
            background-color: #e0c200;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center text-primary mb-4">Registro de Nuevo Seguro</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-info text-center"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="shadow p-4 bg-white rounded form-container">
        <div class="mb-3">
            <label>Nombre del Seguro</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Descripción del Seguro</label>
            <textarea name="descripcion" class="form-control" required></textarea>
        </div>

        <div class="mb-3">
            <label>Precio del Seguro</label>
            <input type="number" step="0.01" name="precio" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Valor Máximo de Cobertura</label>
            <input type="number" step="0.01" name="cobertura_maxima" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Porcentaje Máximo de Reembolso</label>
            <input type="number" step="0.01" name="porcentaje_reembolso" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Valor Máximo de Cobertura</label>
            <input type="number" step="0.01" name="valor_cobertura" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Valor en caso de Accidente</label>
            <input type="number" step="0.01" name="valor_accidente" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Imagen del Seguro</label>
            <input type="file" name="imagen" class="form-control" accept=".jpg, .jpeg, .png">
        </div>

        <button type="submit" class="btn btn-primary w-100">Crear Seguro</button>
        <a href="seguros_exist.php" class="btn btn-view w-100">Ver Seguros Existentes</a>
    </form>
</div>
</body>
</html>
