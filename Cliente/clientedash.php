<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Seguro de Salud</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .card-btn img {
    width: 100px;
    height: auto;
    margin-bottom: 10px;
  }
  @media (max-width: 720px) {
    .card-btn img {
      width: 60px;
    }
    .profile-round-btn {
      width: 50px;
      height: 50px;
      font-size: 18px;
    }
  }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
    }
    .sidebar {
      height: 100vh;
      width: 220px;
      position: fixed;
      top: 0;
      left: 0;
      background-color: #063047;
      color: white;
      padding-top: 30px;
    }
    .sidebar a {
      display: block;
      color: white;
      padding: 12px 20px;
      text-decoration: none;
      transition: background 0.3s;
    }
    .sidebar a:hover {
      background-color: #084d6e;
    }
    .main {
      margin-left: 220px;
      padding: 20px;
    }
    .card-btn {
      border: 1px solid #ccc;
      border-radius: 30px;
      padding: 50px;
      text-align: center;
      transition: transform 0.2s;
      cursor: pointer;
      min-height: 150px;
    }
    .card-btn:hover {
      transform: scale(1.05);
      background-color: #f5f5f5;
    }
    .profile-round-btn {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 60px;
      height: 60px;
      background-color: #063047;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      font-size: 20px;
      z-index: 1001;
      transition: background 0.3s;
    }
    .profile-round-btn:hover {
      background-color: #084d6e;
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 100%;
        height: auto;
        position: relative;
      }
      .main {
        margin-left: 0;
      }
      .card-btn {
        font-size: 14px;
        padding: 15px;
      }
      h1 {
        font-size: 24px;
      }
    }
    @media (max-width: 720px) {
      .card-btn img {
        width: 40px;
      }
      .profile-round-btn {
        width: 50px;
        height: 50px;
        font-size: 18px;
      }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <a href="#">Pólizas Activas</a>
  <a href="#">Estado de pago al día</a>
  <a href="../login.php">Cerrar Sesión</a>
</div>

<div class="main">
  <h1 class="text-center text-primary">Seguro de Salud</h1>

  <div class="row text-center mt-5 g-4">
    <a href="contratacion.php" style="text-decoration: none; color: inherit;">
    <div class="col-sm-6 col-md-4">
      <div class="card-btn">
        <img src="../img/seguro.jpg" width="50">
        <p class="mt-2">Contratar Seguro</p>
      </div>
    </a>
    </div>
    <div class="col-sm-6 col-md-4">
      <div class="card-btn">
        <img src="../img/renovar.jpg" width="50">
        <p class="mt-2">Próximas Renovaciones</p>
      </div>
    </div>
    <div class="col-sm-6 col-md-4">
      <div class="card-btn">
        <img src="../img/reembolso.jpg" width="50">
        <p class="mt-2">Ultimos Reembolsos</p>
      </div>
    </div>
   <div class="col-sm-6 col-md-4">
  <div class="card-btn" onclick="window.location.href='notificaciones.php'">
    <img src="../img/noti.jpg" width="50">
    <p class="mt-2">Notificaciones</p>
    <small class="text-muted">Ver confirmaciones de documentos firmados</small>
  </div>
</div>

<a href="panel_cliente.php" class="profile-round-btn" title="Perfil">
  <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
    <path fill-rule="evenodd" d="M8 9a5 5 0 0 0-4.546 2.916.5.5 0 1 0 .892.448A4 4 0 0 1 8 10a4 4 0 0 1 3.654 2.364.5.5 0 1 0 .892-.448A5 5 0 0 0 8 9z"/>
  </svg>
</a>

</body>
</html>