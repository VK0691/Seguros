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

// Obtener estadísticas
$totalSegurosVida = $conexion->query("SELECT COUNT(*) AS total FROM seguros_vida")->fetch_assoc()['total'];
$totalClientes = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Cliente'")->fetch_assoc()['total'];
$totalAgentes = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Agente' AND estado = 1")->fetch_assoc()['total'];

// Obtener listado de seguros de vida
$segurosVida = $conexion->query("SELECT sv.*, u.usuario FROM seguros_vida sv INNER JOIN usuarios u ON sv.usuario_id = u.id ORDER BY sv.fecha_solicitud DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seguros de Vida - Panel Administrador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fc; }
    .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; padding: 20px; background-color:rgb(41, 59, 113); color: white; }
    .sidebar a { color: white; text-decoration: none; display: block; padding: 10px 15px; margin-bottom: 5px; border-radius: 5px; }
    .sidebar a:hover, .sidebar a.active { background-color:rgb(15, 31, 80); }
    .main-content { margin-left: 250px; padding: 30px; }
    .card { border-radius: 0.75rem; }
    .topbar { height: 60px; background-color: white; border-bottom: 1px solid #e3e6f0; display: flex; justify-content: flex-end; align-items: center; padding: 0 30px; position: fixed; top: 0; left: 250px; right: 0; z-index: 1000; }
    .badge-vida { background-color: #dc3545; color: white; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h4><i class="fas fa-user-shield"></i> Panel Administrador</h4>
    <hr style="border-color:white">
    <a href="lista_usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
    <a href="gestion_seguros_vida.php" class="active"><i class="fas fa-heartbeat"></i> Seguros Vida</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
  </div>

  <div class="topbar">
    <span class="me-2"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
  </div>

  <div class="main-content" style="padding-top: 80px">
    <div class="container-fluid">
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card text-white bg-danger shadow p-3">
            <div class="text-white-50 small">Seguros Vida</div>
            <h5><?php echo $totalSegurosVida; ?></h5>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-white bg-success shadow p-3">
            <div class="text-white-50 small">Clientes</div>
            <h5><?php echo $totalClientes; ?></h5>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card text-white bg-info shadow p-3">
            <div class="text-white-50 small">Agentes</div>
            <h5><?php echo $totalAgentes; ?></h5>
          </div>
        </div>
      </div>

      <div class="card p-4 mb-4">
        <h3><i class="fas fa-heartbeat"></i> Gestión de Seguros de Vida</h3>
        
        <div class="table-responsive mt-4">
          <table class="table table-striped">
            <thead class="table-dark">
              <tr>
                <th>Cliente</th>
                <th>Fecha Solicitud</th>
                <th>Estado</th>
                <th>N° Póliza</th>
                <th>Monto Asegurado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while($seguro = $segurosVida->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($seguro['usuario']); ?></td>
                <td><?php echo htmlspecialchars($seguro['fecha_solicitud']); ?></td>
                <td>
                  <span class="badge bg-<?php 
                    echo $seguro['estado'] == 'Aprobado' ? 'success' : 
                         ($seguro['estado'] == 'Pendiente' ? 'warning' : 'danger'); 
                  ?>">
                    <?php echo htmlspecialchars($seguro['estado']); ?>
                  </span>
                </td>
                <td><?php echo htmlspecialchars($seguro['numero_poliza'] ?? 'N/A'); ?></td>
                <td><?php echo isset($seguro['monto_asegurado']) ? '$' . number_format($seguro['monto_asegurado'], 2) : 'N/A'; ?></td>
                <td>
                  <a href="ver_seguro_vida.php?id=<?php echo $seguro['id']; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-eye"></i> Ver
                  </a>
                  <?php if($seguro['estado'] == 'Pendiente'): ?>
                    <a href="aprobar_seguro.php?id=<?php echo $seguro['id']; ?>" class="btn btn-sm btn-success">
                      <i class="fas fa-check"></i> Aprobar
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>