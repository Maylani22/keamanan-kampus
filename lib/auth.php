<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

class Auth {
    private $db;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
        $this->userModel = new User($this->db);
    }

    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Check if user is admin
    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_ADMIN;
    }

    // Get current user data
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }

    // Login function
    public function login($email, $password) {
        $user = $this->userModel->getByEmail($email);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }
        return false;
    }

    // Register function
    public function register($name, $email, $password, $role = ROLE_MAHASISWA) {
        // Check if email exists
        if ($this->userModel->getByEmail($email)) {
            return ['error' => 'Email sudah terdaftar'];
        }

        // Create new user
        $this->userModel->name = $name;
        $this->userModel->email = $email;
        $this->userModel->password = password_hash($password, PASSWORD_DEFAULT);
        $this->userModel->role = $role;

        if ($this->userModel->create()) {
            return ['success' => true];
        }
        
        return ['error' => 'Gagal mendaftar'];
    }

    // Logout
    public function logout() {
        session_destroy();
    }
}

// Helper functions
function checkLogin() {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function checkAdmin() {
    $auth = new Auth();
    if (!$auth->isAdmin()) {
        header('Location: dashboard_mahasiswa.php');
        exit;
    }
}
?>