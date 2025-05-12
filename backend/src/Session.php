<?php
class Session {
    private static $baseUrl = '/employee-leave-management-system';

    /**
     * Start a new session if one isn't already active with secure settings.
     *
     * @throws RuntimeException If session fails to start
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session cookie parameters before starting
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie expires when browser closes
                'path' => '/',
                'secure' => true, // Only send over HTTPS
                'httponly' => true, // Prevent JavaScript access
                'samesite' => 'Strict' // Prevent CSRF via cross-site requests
            ]);
            if (!session_start()) {
                throw new RuntimeException('Failed to start session');
            }
        }
    }

    /**
     * Regenerate the session ID to prevent session fixation.
     *
     * @param bool $deleteOldSession Whether to delete the old session data
     */
    public static function regenerate($deleteOldSession = false) {
        self::start();
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Set a session value.
     *
     * @param string $key The session key
     * @param mixed $value The value to set
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     *
     * @param string $key The session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The session value or default
     */
    public static function get($key, $default = null) {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * Unset a specific session key.
     *
     * @param string $key The session key to remove
     */
    public static function unset($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Destroy the current session.
     */
    public static function destroy() {
        self::start();
        session_unset();
        session_destroy();
        // Clear the session array to prevent reuse
        $_SESSION = [];
    }

    /**
     * Check if a user is logged in.
     *
     * @return bool True if logged in, false otherwise
     */
    public static function isLoggedIn() {
        return self::get('user_id') !== null;
    }

    /**
     * Get the role of the logged-in user.
     *
     * @return string|null The user's role or null if not set
     */
    public static function getRole() {
        return self::get('role');
    }

    /**
     * Redirect to login page if user is not logged in.
     *
     * @param string|null $redirectUrl Custom redirect URL (optional)
     */
    public static function requireLogin($redirectUrl = null) {
        if (!self::isLoggedIn()) {
            $url = $redirectUrl ?? self::$baseUrl . '/frontend/views/login.php';
            self::set('message', 'Please log in to access this page.');
            self::set('message_type', 'error');
            header("Location: $url");
            exit;
        }
    }

    /**
     * Validate the session to mitigate hijacking.
     *
     * @return bool True if session is valid, false otherwise
     */
    public static function validate() {
        self::start();
        $storedUserAgent = self::get('user_agent');
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (!$storedUserAgent) {
            self::set('user_agent', $currentUserAgent);
            return true;
        }

        return $storedUserAgent === $currentUserAgent;
    }
}
?>