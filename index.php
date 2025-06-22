<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Salud Plus</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: Arial, sans-serif;
    }

    body {
      background-color: #ffffff;
      color: #000;
    }

    header {
      background: #003366;
      color: white;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
    }

    .logo {
      font-size: 1.8rem;
      font-weight: bold;
    }

    nav a {
      color: white;
      margin-left: 1rem;
      text-decoration: none;
      font-weight: 500;
    }

    .hero {
      background: linear-gradient(to right, #003366, #0055a5);
      color: white;
      padding: 4rem 2rem;
      text-align: center;
    }

    .hero h1 {
      font-size: 2.5rem;
    }

    .hero p {
      margin-top: 1rem;
      font-size: 1.2rem;
    }

    .btn-login {
      background: #ffcc00;
      color: #000;
      padding: 0.7rem 1.2rem;
      text-decoration: none;
      font-weight: bold;
      border-radius: 5px;
      margin-top: 1.5rem;
      display: inline-block;
    }

    section {
      padding: 2rem;
    }

    .section-title {
      color: #003366;
      margin-bottom: 1rem;
      font-size: 1.5rem;
      text-align: center;
    }

    .features {
      position: relative;
      min-height: 500px;
      overflow: hidden;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .feature-box {
      position: absolute;
      width: 100%;
      max-width: 600px;
      background: #f4f4f4;
      padding: 2rem 1rem;
      border-left: 5px solid #ffcc00;
      opacity: 0;
      transition: opacity 1s ease-in-out;
      text-align: center;
      margin: 0 auto;
    }

    .feature-box.active {
      opacity: 1;
      z-index: 1;
    }

    .feature-box img {
      margin-top: 1.5rem;
      width: 100%;
      max-width: 400px;
      height: auto;
      border-radius: 8px;
    }

    footer {
      background: #000;
      color: white;
      text-align: center;
      padding: 1rem;
    }

    @media (min-width: 768px) {
      .about, .contact {
        display: flex;
        justify-content: space-around;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">SaludPlus Seguros</div>
    <nav>
      <a href="login.php" class="btn-login">Iniciar sesión</a>
    </nav>
  </header>

  <div class="hero">
    <h1>Protege lo que más amas</h1>
    <p>Con nuestros seguros de vida, aseguras el futuro de tu familia con confianza y respaldo.</p>
    <a href="login.php" class="btn-login">Contrata Ahora</a>
  </div>

  <section id="servicios">
    <h2 class="section-title">Nuestros Servicios</h2>
    <div class="features">
      <div class="feature-box active">
        <h3>Seguro de Salud Personal</h3>
        <p>Cobertura médica completa para ti y solo para ti, incluyendo emergencias, consultas y hospitalización.</p>
        <img src="img/personal.jpg" alt="Salud Personal">
      </div>
      <div class="feature-box">
        <h3>Seguro de Salud Familiar</h3>
        <p>Protección médica integral para toda la familia y tus dependientes, con atención preventiva y beneficios ampliados.</p>
        <img src="img/familiar.jpg" alt="Salud Familiar">
      </div>
    </div>
  </section>

  <section id="nosotros">
    <h2 class="section-title">¿Quiénes Somos?</h2>
    <div class="about">
      <p>En SaludPlus llevamos más de 2 meses ayudando a las familias ecuatorianas a sentirse seguras y respaldadas. Nuestra misión es ofrecer tranquilidad a través de seguros de vida confiables, adaptables y con el respaldo de expertos.</p>
    </div>
  </section>

  <section id="contacto">
    <h2 class="section-title">Contáctanos</h2>
    <div class="contact">
      <p>Email: crissmera0114@gmail.com</p>
      <p>      Teléfono: +593985531975</p>
      <p>Dirección: Av. Los Chasquis, Ambato, Ecuador</p>
    </div>
  </section>

  <footer>
    <p>&copy; 2025 SaludPlus Seguros. Todos los derechos reservados.</p>
  </footer>

  <script>
    const features = document.querySelectorAll('.feature-box');
    let current = 0;

    setInterval(() => {
      features[current].classList.remove('active');
      current = (current + 1) % features.length;
      features[current].classList.add('active');
    }, 4000);
  </script>
</body>
</html>