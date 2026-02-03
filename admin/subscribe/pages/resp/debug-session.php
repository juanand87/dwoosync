<?php
/**
 * Debug de sesión para identificar el problema
 */

// Habilitar logging de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug de Sesión</h2>";

// Mostrar información de sesión
echo "<h3>Información de Sesión:</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "</p>";

// Incluir configuraciones
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Asegurar que la sesión esté iniciada
startSecureSession();

echo "<h3>Después de incluir config.php:</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "</p>";

// Mostrar datos de sesión
echo "<h3>Datos de Sesión:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Verificar isLoggedIn()
echo "<h3>Función isLoggedIn():</h3>";
echo "<p><strong>Resultado:</strong> " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "</p>";

// Mostrar información del servidor
echo "<h3>Información del Servidor:</h3>";
echo "<p><strong>REQUEST_METHOD:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p><strong>HTTP_HOST:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
echo "<p><strong>REQUEST_URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";

// Mostrar headers
echo "<h3>Headers HTTP:</h3>";
echo "<pre>" . print_r(getallheaders(), true) . "</pre>";

// Mostrar cookies
echo "<h3>Cookies:</h3>";
echo "<pre>" . print_r($_COOKIE, true) . "</pre>";

// Probar conexión a BD
echo "<h3>Prueba de Conexión a BD:</h3>";
try {
    $pdo = getDatabase();
    echo "<p style='color: green;'>✅ Conexión a BD exitosa</p>";
    
    // Probar consulta simple
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subscribers");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total de suscriptores: " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error de BD: " . $e->getMessage() . "</p>";
}

// Probar crear ciclo de facturación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Procesando POST:</h3>";
    
    $plan_type = $_POST['plan_type'] ?? '';
    $subscriber_id = $_POST['subscriber_id'] ?? '';
    
    echo "<p><strong>Plan Type:</strong> " . htmlspecialchars($plan_type) . "</p>";
    echo "<p><strong>Subscriber ID:</strong> " . htmlspecialchars($subscriber_id) . "</p>";
    
    if (isLoggedIn()) {
        echo "<p style='color: green;'>✅ Usuario autenticado, procediendo con creación de ciclo...</p>";
        
        try {
            $pdo = getDatabase();
            
            // Obtener información del plan
            $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
            $plan_stmt->execute([$plan_type]);
            $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($plan_data) {
                echo "<p style='color: green;'>✅ Plan encontrado: " . $plan_data['plan_name'] . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Plan no encontrado: " . $plan_type . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Usuario NO autenticado</p>";
    }
}
?>

<form method="POST" style="margin-top: 20px; padding: 20px; border: 1px solid #ccc;">
    <h3>Probar Creación de Ciclo:</h3>
    <p>
        <label>Plan Type:</label>
        <select name="plan_type">
            <option value="free">Free</option>
            <option value="premium">Premium</option>
            <option value="enterprise">Enterprise</option>
        </select>
    </p>
    <p>
        <label>Subscriber ID:</label>
        <input type="number" name="subscriber_id" value="<?php echo $_SESSION['subscriber_id'] ?? ''; ?>" required>
    </p>
    <button type="submit">Probar Creación</button>
</form>
