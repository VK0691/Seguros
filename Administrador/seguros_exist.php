<?php
include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $precio = $_POST['precio'];
    $cobertura = $_POST['cobertura_maxima'];
    $reembolso = $_POST['porcentaje_reembolso'];
    $valor_cobertura = $_POST['valor_cobertura'];
    $valor_accidente = $_POST['valor_accidente'];

    $sql = "UPDATE seguros SET nombre=?, descripcion=?, precio=?, cobertura_maxima=?, porcentaje_reembolso=?, valor_cobertura=?, valor_accidente=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdddddi", $nombre, $descripcion, $precio, $cobertura, $reembolso, $valor_cobertura, $valor_accidente, $id);
    $stmt->execute();
}

$result = $conn->query("SELECT * FROM seguros");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado y Edición de Seguros</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to right, #002147, #a1b1c9);
      font-family: 'Segoe UI', sans-serif;
    }
    .tarjeta {
      background-color: #fff;
      border-radius: 10px;
      padding: 20px;
      margin: 15px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .tarjeta:hover {
      transform: translateY(-5px);
    }
    .btn-editar {
      background-color: #002147;
      color: #fff;
    }
    .btn-guardar {
      background-color: #FFD700;
      color: #000;
      font-weight: bold;
    }
    .form-control[disabled] {
      background-color: #f9f9f9;
    }
  </style>
</head>
<body>
  <div class="container py-5">
    <h2 class="text-center text-light mb-4">Listado y Edición de Seguros</h2>
    <div class="row justify-content-center">

      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-md-4">
          <form method="POST" class="tarjeta" id="form-<?= $row['id'] ?>">
            <?php if ($row['imagen_seguro']): ?>
              <img src="../imagenes/<?= htmlspecialchars($row['imagen_seguro']) ?>" class="img-fluid mb-3" style="max-height: 100px;">
            <?php endif; ?>
            <input type="hidden" name="id" value="<?= $row['id'] ?>">

            <label>Nombre:</label>
            <input type="text" name="nombre" class="form-control mb-2" value="<?= htmlspecialchars($row['nombre']) ?>" disabled>

            <label>Descripción:</label>
            <textarea name="descripcion" class="form-control mb-2" disabled><?= htmlspecialchars($row['descripcion']) ?></textarea>

            <label>Precio:</label>
            <input type="number" step="0.01" name="precio" class="form-control mb-2" value="<?= $row['precio'] ?>" disabled>

            <label>Cobertura Máxima:</label>
            <input type="number" step="0.01" name="cobertura_maxima" class="form-control mb-2" value="<?= $row['cobertura_maxima'] ?>" disabled>

            <label>% Reembolso:</label>
            <input type="number" step="0.01" name="porcentaje_reembolso" class="form-control mb-2" value="<?= $row['porcentaje_reembolso'] ?>" disabled>

            <label>Valor Cobertura:</label>
            <input type="number" step="0.01" name="valor_cobertura" class="form-control mb-2" value="<?= $row['valor_cobertura'] ?>" disabled>

            <label>Valor por Accidente:</label>
            <input type="number" step="0.01" name="valor_accidente" class="form-control mb-2" value="<?= $row['valor_accidente'] ?>" disabled>

            <button type="button" class="btn btn-editar w-100" onclick="habilitarEdicion(<?= $row['id'] ?>)">Editar</button>
            <button type="submit" class="btn btn-guardar w-100 mt-2 d-none" id="guardar-<?= $row['id'] ?>">Guardar Cambios</button>
          </form>
        </div>
      <?php endwhile; ?>

    </div>
  </div>

  <script>
    function habilitarEdicion(id) {
      const form = document.getElementById('form-' + id);
      const inputs = form.querySelectorAll('input, textarea');
      inputs.forEach(input => input.disabled = false);

      // Mostrar el botón de guardar y ocultar editar
      form.querySelector('.btn-editar').classList.add('d-none');
      form.querySelector('.btn-guardar').classList.remove('d-none');
    }
  </script>
</body>
</html>
