<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

// Verificación de sesión y permisos
if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../login.php");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "seguros");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['crear_seguro'])) {
        // Procesar creación de nuevo seguro
        $nombre = $conexion->real_escape_string($_POST['nombre']);
        $tipo = $conexion->real_escape_string($_POST['tipo']);
        $descripcion = $conexion->real_escape_string($_POST['descripcion']);
        $beneficios = $conexion->real_escape_string($_POST['beneficios']);
        $monto_maximo = floatval($_POST['monto_maximo']);
        $prima_mensual = floatval($_POST['prima_mensual']);
        $edad_minima = intval($_POST['edad_minima']);
        $edad_maxima = intval($_POST['edad_maxima']);
        $duracion = $conexion->real_escape_string($_POST['duracion']);
        $estado = isset($_POST['estado']) ? 1 : 0;
        
        $sql = "INSERT INTO seguros_planes (nombre, tipo, descripcion, beneficios, monto_maximo, prima_mensual, edad_minima, edad_maxima, duracion, estado) 
                VALUES ('$nombre', '$tipo', '$descripcion', '$beneficios', $monto_maximo, $prima_mensual, $edad_minima, $edad_maxima, '$duracion', $estado)";
        
        if ($conexion->query($sql)) {
            $mensaje = "Seguro creado exitosamente";
        } else {
            $error = "Error al crear seguro: " . $conexion->error;
        }
    }
}

// Obtener listado de seguros
$seguros = $conexion->query("SELECT * FROM seguros_planes ORDER BY fecha_creacion DESC");
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
    .tab-content { padding: 20px; border-left: 1px solid #ddd; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; }
    .nav-tabs .nav-link.active { font-weight: bold; }
    .cobertura-item { margin-bottom: 15px; padding: 10px; border: 1px solid #eee; border-radius: 5px; }
  </style>
</head>
<body>
  <?php include 'admin_header.php'; ?>

  <div class="main-content" style="padding-top: 80px">
    <div class="container-fluid">
      <h2 class="mb-4"><i class="fas fa-shield-alt"></i> Gestión de Seguros</h2>
      
      <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="planes-tab" data-bs-toggle="tab" data-bs-target="#planes" type="button">Planes de Seguro</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="nuevo-tab" data-bs-toggle="tab" data-bs-target="#nuevo" type="button">Crear Nuevo Plan</button>
        </li>
      </ul>
      
      <div class="tab-content" id="myTabContent">
        <!-- TAB 1: Listado de Planes -->
        <div class="tab-pane fade show active" id="planes" role="tabpanel">
          <?php if(isset($mensaje)): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
          <?php endif; ?>
          
          <div class="table-responsive">
            <table class="table table-striped">
              <thead class="table-dark">
                <tr>
                  <th>Nombre</th>
                  <th>Tipo</th>
                  <th>Monto Máximo</th>
                  <th>Prima Mensual</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php while($seguro = $seguros->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($seguro['nombre']); ?></td>
                  <td><?php echo htmlspecialchars($seguro['tipo']); ?></td>
                  <td>$<?php echo number_format($seguro['monto_maximo'], 2); ?></td>
                  <td>$<?php echo number_format($seguro['prima_mensual'], 2); ?></td>
                  <td>
                    <span class="badge bg-<?php echo $seguro['estado'] ? 'success' : 'danger'; ?>">
                      <?php echo $seguro['estado'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                  </td>
                  <td>
                    <a href="editar_seguro.php?id=<?php echo $seguro['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                    <a href="eliminar_seguro.php?id=<?php echo $seguro['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este plan?')"><i class="fas fa-trash"></i></a>
                    <a href="detalles_seguro.php?id=<?php echo $seguro['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                  </td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <!-- TAB 2: Formulario para nuevo plan -->
        <div class="tab-pane fade" id="nuevo" role="tabpanel">
          <h4>Crear Nuevo Plan de Seguro</h4>
          <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nombre del Plan</label>
                <input type="text" name="nombre" class="form-control" required>
              </div>
              
              <div class="col-md-6">
                <label class="form-label">Tipo de Seguro</label>
                <select name="tipo" class="form-select" required>
                  <option value="Vida">Vida</option>
                  <option value="Salud">Salud</option>
                  <option value="Vehicular">Vehicular</option>
                  <option value="Hogar">Hogar</option>
                </select>
              </div>
              
              <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="2" required></textarea>
              </div>
              
              <div class="col-12">
                <label class="form-label">Beneficios Principales</label>
                <textarea name="beneficios" class="form-control" rows="3" required></textarea>
              </div>
              
              <div class="col-md-4">
                <label class="form-label">Monto Máximo Asegurado</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" name="monto_maximo" class="form-control" required>
                </div>
              </div>
              
              <div class="col-md-4">
                <label class="form-label">Prima Mensual</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" name="prima_mensual" class="form-control" required>
                </div>
              </div>
              
              <div class="col-md-2">
                <label class="form-label">Edad Mínima</label>
                <input type="number" name="edad_minima" class="form-control" required>
              </div>
              
              <div class="col-md-2">
                <label class="form-label">Edad Máxima</label>
                <input type="number" name="edad_maxima" class="form-control" required>
              </div>
              
              <div class="col-md-6">
                <label class="form-label">Duración del Seguro</label>
                <select name="duracion" class="form-select" required>
                  <option value="1 año">1 año</option>
                  <option value="2 años">2 años</option>
                  <option value="5 años">5 años</option>
                  <option value="10 años">10 años</option>
                  <option value="Vitalicio">Vitalicio</option>
                </select>
              </div>
              
              <div class="col-md-6">
                <label class="form-label">Estado del Plan</label>
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="estado" id="estado" checked>
                  <label class="form-check-label" for="estado">Activo</label>
                </div>
              </div>
              
              <div class="col-12">
                <label class="form-label">Documento PDF del Contrato</label>
                <input type="file" name="contrato_pdf" class="form-control" accept=".pdf">
              </div>
              
              <div class="col-12 mt-4">
                <button type="submit" name="crear_seguro" class="btn btn-success">
                  <i class="fas fa-save"></i> Guardar Plan
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>