<?php
class CalculadoraPrimas {
    private $conn;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
    }
    
    public function calcularPrima($seguro_id, $edad_cliente, $antiguedad = 0, $factor_riesgo_extra = 0) {
        // Obtener configuración del seguro
        $sql = "SELECT monto_base, formula_prima, factores_riesgo 
                FROM configuraciones_seguro 
                WHERE tipo_seguro_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $seguro_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Configuración de seguro no encontrada");
        }
        
        $config = $result->fetch_assoc();
        $factores = json_decode($config['factores_riesgo'], true);
        
        // Validar edad del cliente
        if ($edad_cliente < $factores['edad_min'] || $edad_cliente > $factores['edad_max']) {
            throw new Exception("El cliente no cumple con el rango de edad para este seguro");
        }
        
        // Calcular factores
        $edad_factor = $this->calcularEdadFactor($edad_cliente, $factores);
        $riesgo_factor = $factores['riesgo_base'] + ($antiguedad * $factores['incremento_anual']) + $factor_riesgo_extra;
        
        // Preparar variables para la fórmula
        $monto_base = $config['monto_base'];
        $edad = $edad_cliente;
        
        // Evaluar fórmula segura
        $formula = $config['formula_prima'] ?? 'monto_base * (1 + (edad_factor + riesgo_factor))';
        $prima = eval("return $formula;");
        
        return round($prima, 2);
    }
    
    private function calcularEdadFactor($edad, $factores) {
        $edad_base = $factores['edad_min'];
        return ($edad - $edad_base) * $factores['edad_factor'];
    }
}
?>