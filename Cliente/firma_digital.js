document.addEventListener("DOMContentLoaded", function() {
    const canvas = document.getElementById("firma-canvas");
    const btnLimpiar = document.getElementById("limpiar-firma");
    const btnEnviar = document.getElementById("enviar-contrato");
    const inputFirma = document.getElementById("firma_canvas_data");

    if (!canvas || !btnLimpiar || !btnEnviar || !inputFirma) return;

    const ctx = canvas.getContext("2d");
    let dibujando = false;
    let hayFirma = false;
    let lastX = 0;
    let lastY = 0;

    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = "#000000";

    // Ajustar el tamaño del canvas para que coincida con su contenedor
    function resizeCanvas() {
        const container = canvas.parentElement;
        canvas.width = container.clientWidth;
        canvas.height = 200; // Altura fija
    }
    
    // Llamar a resize al cargar y cuando cambie el tamaño de la ventana
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Mouse events
    canvas.addEventListener("mousedown", function(e) {
        dibujando = true;
        lastX = e.offsetX;
        lastY = e.offsetY;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
    });

    canvas.addEventListener("mousemove", function(e) {
        if (!dibujando) return;
        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.stroke();
        lastX = e.offsetX;
        lastY = e.offsetY;
        hayFirma = true;
        btnEnviar.disabled = false;
    });

    canvas.addEventListener("mouseup", () => dibujando = false);
    canvas.addEventListener("mouseout", () => dibujando = false);

    // Touch events
    canvas.addEventListener("touchstart", function(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches[0];
        lastX = touch.clientX - rect.left;
        lastY = touch.clientY - rect.top;
        dibujando = true;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
    });

    canvas.addEventListener("touchmove", function(e) {
        e.preventDefault();
        if (!dibujando) return;
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches[0];
        const offsetX = touch.clientX - rect.left;
        const offsetY = touch.clientY - rect.top;
        ctx.lineTo(offsetX, offsetY);
        ctx.stroke();
        lastX = offsetX;
        lastY = offsetY;
        hayFirma = true;
        btnEnviar.disabled = false;
    });

    canvas.addEventListener("touchend", () => dibujando = false);

    // Limpiar firma
    btnLimpiar.addEventListener("click", function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hayFirma = false;
        btnEnviar.disabled = true;
    });

    // Enviar firma
    btnEnviar.addEventListener("click", function() {
        if (!hayFirma) {
            alert("Por favor, dibuje su firma antes de enviar el contrato.");
            return;
        }
        const firma_data = canvas.toDataURL("image/png");
        inputFirma.value = firma_data;

        const formData = new FormData();
        formData.append('firma_canvas_data', firma_data);

        fetch('soporteagente.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === 'OK') {
                alert('Contrato firmado enviado correctamente.');
                window.location.href = 'panel_cliente.php';
            } else {
                alert('Error al guardar la firma. Respuesta: ' + data);
            }
        })
        .catch(error => {
            alert('Error al guardar la firma.');
        });
    });
});


