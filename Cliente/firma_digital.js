document.addEventListener("DOMContentLoaded", function() {
    // Configuración del canvas para firma
    const canvas = document.getElementById("firma-canvas");
    if (!canvas) return;

    // Ajustar el tamaño del canvas para que coincida con su contenedor
    function resizeCanvas() {
        const container = canvas.parentElement;
        canvas.width = container.clientWidth;
        canvas.height = 200; // Altura fija
    }
    
    // Llamar a resize al cargar y cuando cambie el tamaño de la ventana
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    const ctx = canvas.getContext("2d");
    let dibujando = false;
    let lastX = 0;
    let lastY = 0;

    // Configurar canvas
    ctx.lineWidth = 2;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";
    ctx.strokeStyle = "#000000";

    // Eventos para dibujar con mouse
    canvas.addEventListener("mousedown", function(e) {
        dibujando = true;
        lastX = e.offsetX;
        lastY = e.offsetY;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
    });

    canvas.addEventListener("mousemove", function(e) {
        if (!dibujando) return;
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(e.offsetX, e.offsetY);
        ctx.stroke();
        lastX = e.offsetX;
        lastY = e.offsetY;
    });

    // Eventos para dispositivos táctiles
    canvas.addEventListener("touchstart", function(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const touch = e.touches[0];
        const offsetX = touch.clientX - rect.left;
        const offsetY = touch.clientY - rect.top;
        
        dibujando = true;
        lastX = offsetX;
        lastY = offsetY;
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
        
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(offsetX, offsetY);
        ctx.stroke();
        lastX = offsetX;
        lastY = offsetY;
    });

    function finalizarDibujo() {
        dibujando = false;
    }

    canvas.addEventListener("mouseup", finalizarDibujo);
    canvas.addEventListener("mouseout", finalizarDibujo);
    canvas.addEventListener("touchend", finalizarDibujo);

    // Limpiar canvas
    const btnLimpiar = document.getElementById("limpiar-firma");
    if (btnLimpiar) {
        btnLimpiar.addEventListener("click", function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });
    }

    // Guardar firma del canvas
    const formFirma = document.getElementById("form-firma");
    if (formFirma) {
        formFirma.addEventListener("submit", function(e) {
            // Verificar si hay firma en el canvas
            const pixeles = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
            let vacio = true;
            
            for (let i = 0; i < pixeles.length; i += 4) {
                if (pixeles[i + 3] !== 0) {
                    vacio = false;
                    break;
                }
            }
            
            // Si estamos en la pestaña de dibujar y el canvas está vacío
            if (document.getElementById("content-dibujar").style.display === "block" && vacio) {
                const firmaUpload = document.getElementById("firma_upload");
                // Si no hay firma dibujada ni subida, mostrar error
                if (!firmaUpload || !firmaUpload.files.length) {
                    e.preventDefault();
                    alert("Por favor, dibuja tu firma o sube una imagen antes de continuar.");
                    return;
                }
            }
            
            // Si hay firma dibujada, guardarla
            if (!vacio) {
                document.getElementById("firma_canvas_data").value = canvas.toDataURL("image/png");
            }
        });
    }

    // Preview de firma subida
    const inputFirma = document.getElementById("firma_upload");
    if (inputFirma) {
        inputFirma.addEventListener("change", function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById("firma-preview");
            const previewContainer = document.getElementById("firma-preview-container");

            if (file) {
                // Validar tamaño
                if (file.size > 2097152) { // 2MB
                    alert("La imagen de la firma es demasiado grande. Máximo 2MB");
                    this.value = "";
                    previewContainer.style.display = "none";
                    return;
                }

                // Validar tipo
                if (!["image/png", "image/jpeg", "image/jpg"].includes(file.type)) {
                    alert("Solo se permiten imágenes PNG, JPG o JPEG para la firma");
                    this.value = "";
                    previewContainer.style.display = "none";
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = "block";
                }
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = "none";
            }
        });
    }

    // Cambiar entre pestañas
    const tabDibujar = document.getElementById("tab-dibujar");
    const tabSubir = document.getElementById("tab-subir");
    const contentDibujar = document.getElementById("content-dibujar");
    const contentSubir = document.getElementById("content-subir");

    if (tabDibujar && tabSubir) {
        tabDibujar.addEventListener("click", function() {
            tabDibujar.classList.add("active");
            tabSubir.classList.remove("active");
            contentDibujar.style.display = "block";
            contentSubir.style.display = "none";
        });

        tabSubir.addEventListener("click", function() {
            tabSubir.classList.add("active");
            tabDibujar.classList.remove("active");
            contentSubir.style.display = "block";
            contentDibujar.style.display = "none";
        });
    }
});
