<?php
/**
 * Sistema de autenticación para el panel de administración
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

class AdminAuth {
    private $db;
    private $sessionName = 'admin_session';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }

    /**
     * Iniciar sesión
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Autenticar usuario
     */
    public function login($username, $password) {
        try {
            $sql = "SELECT id, username, email, password_hash, full_name, is_active 
                    FROM admin_users 
                    WHERE username = :username AND is_active = 1";
            
            $user = $this->db->fetch($sql, ['username' => $username]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado'];
            }
            
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Contraseña incorrecta'];
            }
            
            // Crear sesión
            $sessionToken = $this->generateSessionToken();
            $this->createSession($user['id'], $sessionToken);
            
            // Actualizar último login
            $this->updateLastLogin($user['id']);
            
            // Guardar en sesión
            $_SESSION[$this->sessionName] = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'session_token' => $sessionToken
            ];
            
            return ['success' => true, 'message' => 'Login exitoso'];
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout() {
        if (isset($_SESSION[$this->sessionName])) {
            $sessionToken = $_SESSION[$this->sessionName]['session_token'];
            $this->destroySession($sessionToken);
        }
        
        unset($_SESSION[$this->sessionName]);
        session_destroy();
        
        return ['success' => true, 'message' => 'Sesión cerrada'];
    }

    /**
     * Verificar si está autenticado
     */
    public function isAuthenticated() {
        if (!isset($_SESSION[$this->sessionName])) {
            return false;
        }
        
        $sessionData = $_SESSION[$this->sessionName];
        $sessionToken = $sessionData['session_token'];
        
        // Verificar sesión en base de datos
        $sql = "SELECT s.*, u.is_active 
                FROM admin_sessions s 
                JOIN admin_users u ON s.user_id = u.id 
                WHERE s.session_token = :token AND s.expires_at > NOW() AND u.is_active = 1";
        
        $session = $this->db->fetch($sql, ['token' => $sessionToken]);
        
        if (!$session) {
            $this->logout();
            return false;
        }
        
        return true;
    }

    /**
     * Obtener datos del usuario actual
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return $_SESSION[$this->sessionName];
    }

    /**
     * Crear sesión en base de datos
     */
    private function createSession($userId, $sessionToken) {
        $sql = "INSERT INTO admin_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
                VALUES (:user_id, :token, :ip, :user_agent, :expires_at)";
        
        $this->db->query($sql, [
            'user_id' => $userId,
            'token' => $sessionToken,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
        ]);
    }

    /**
     * Destruir sesión
     */
    private function destroySession($sessionToken) {
        $sql = "DELETE FROM admin_sessions WHERE session_token = :token";
        $this->db->query($sql, ['token' => $sessionToken]);
    }

    /**
     * Actualizar último login
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE admin_users SET last_login = NOW() WHERE id = :id";
        $this->db->query($sql, ['id' => $userId]);
    }

    /**
     * Generar token de sesión
     */
    private function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Limpiar sesiones expiradas
     */
    public function cleanExpiredSessions() {
        $sql = "DELETE FROM admin_sessions WHERE expires_at < NOW()";
        $this->db->query($sql);
    }
}
