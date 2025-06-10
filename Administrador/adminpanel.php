
<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['session_regenerada'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerada'] = true;
}

if (!isset($_SESSION['usuario']) || !isset($_SESSION['rol'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: ../index.html");
    exit();
}

$conexion = new mysqli("localhost", "root", "", "seguros");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_usuario'])) {
    $usuario    = $conexion->real_escape_string($_POST['usuario']);
    $telefono   = $conexion->real_escape_string($_POST['telefono']);
    $direccion  = $conexion->real_escape_string($_POST['direccion']);
    $correo     = $conexion->real_escape_string($_POST['correo']);
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol        = $conexion->real_escape_string($_POST['rol']);
    $estado     = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

    $check = $conexion->query("SELECT * FROM usuarios WHERE usuario = '$usuario' OR correo = '$correo'");
    if ($check->num_rows > 0) {
        header("Location: adminpanel.php?error=usuario_existe");
        exit();
    }

    $sql = "INSERT INTO usuarios (usuario, telefono, direccion, correo, contrasena, rol, estado) 
            VALUES ('$usuario', '$telefono', '$direccion', '$correo', '$contrasena', '$rol', $estado)";

    if ($conexion->query($sql) === TRUE) {
        header("Location: adminpanel.php?success=creado");
        exit();
    } else {
        echo "Error: " . $conexion->error;
    }
}

$totalseguros_vida = $conexion->query("SELECT COUNT(*) AS total FROM seguros_vida")->fetch_assoc()['total'];
$totalClientes = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Cliente'")->fetch_assoc()['total'];
$totalAgentes = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Agente' AND estado = 1")->fetch_assoc()['total'];
$admins = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Administrador'")->fetch_assoc()['total'];
$agentes = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Agente'")->fetch_assoc()['total'];
$clientes = $conexion->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'Cliente'")->fetch_assoc()['total'];
$usuariosTotal = $clientes;
$conSeguro = $conexion->query("SELECT COUNT(DISTINCT usuario_id) AS total FROM seguros_vida")->fetch_assoc()['total'];
$sinSeguro = $usuariosTotal - $conSeguro;
$ingresosMensual = 5000;
$ingresosTrimestral = 14500;
$ingresosAnual = 60000;
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administrador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background-color: #f8f9fc; }
    .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; padding: 20px; background-color:rgb(41, 59, 113); color: white; }
    .sidebar a { color: white; text-decoration: none; display: block; padding: 10px 15px; margin-bottom: 5px; border-radius: 5px; }
    .sidebar a:hover, .sidebar a.active { background-color:rgb(15, 31, 80); }
    .main-content { margin-left: 250px; padding: 30px; }
    .card { border-radius: 0.75rem; }
    .topbar { height: 60px; background-color: white; border-bottom: 1px solid #e3e6f0; display: flex; justify-content: flex-end; align-items: center; padding: 0 30px; position: fixed; top: 0; left: 250px; right: 0; z-index: 1000; }
    .dropdown-menu { left: auto !important; right: 0; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h4><i class="fas fa-user-shield"></i>Panel Administrador</h4>
    <hr style="border-color:white">
    <a href="lista_usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
    <a href="gestion_seguros.php"><i class="fas fa-heartbeat"></i> Seguros Vida</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
  </div>

  <div class="topbar">
    <span class="me-2"><?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
  </div>

  <div class="main-content" style="padding-top: 80px">
    <div class="container-fluid">
      <div class="row mb-4">
        <div class="col-md-3"><div class="card text-white bg-primary shadow p-3"><div class="text-white-50 small">Seguros Vida</div><h5><?php echo $totalseguros_vida; ?></h5></div></div>
        <div class="col-md-3"><div class="card text-white bg-success shadow p-3"><div class="text-white-50 small">Clientes</div><h5><?php echo $totalClientes; ?></h5></div></div>
        <div class="col-md-3"><div class="card text-white bg-info shadow p-3"><div class="text-white-50 small">Agentes</div><h5><?php echo $totalAgentes; ?></h5></div></div>
        
      </div>

      <div class="card p-4 mb-4" id="usuarios">
        <h3>Crear nuevo usuario</h3>
        <form method="POST" action="adminpanel.php">
          <div class="row g-3">
            <div class="col-md-4"><input type="text" name="usuario" class="form-control" placeholder="Usuario" required></div>
            <div class="col-md-4"><input type="text" name="telefono" class="form-control" placeholder="Teléfono" required></div>
            <div class="col-md-4"><input type="text" name="direccion" class="form-control" placeholder="Dirección" required></div>
            <div class="col-md-4"><input type="email" name="correo" class="form-control" placeholder="Correo" required></div>
            <div class="col-md-4"><input type="password" name="contrasena" class="form-control" placeholder="Contraseña" required></div>
            <div class="col-md-2"><select name="rol" class="form-select" required><option value="Administrador">Administrador</option><option value="Cliente">Cliente</option><option value="Agente">Agente</option></select></div>
            <div class="col-md-2"><select name="estado" class="form-select" required><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
          </div>
          <button type="submit" name="crear_usuario" class="btn btn-success mt-3">Crear Usuario</button>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
