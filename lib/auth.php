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

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_ADMIN;
    }

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

    public function register($name, $email, $password, $role = ROLE_MAHASISWA) {
        if ($this->userModel->getByEmail($email)) {
            return ['error' => 'Email sudah terdaftar'];
        }

        $this->userModel->name = $name;
        $this->userModel->email = $email;
        $this->userModel->password = password_hash($password, PASSWORD_DEFAULT);
        $this->userModel->role = $role;

        if ($this->userModel->create()) {
            return ['success' => true];
        }
        
        return ['error' => 'Gagal mendaftar'];
    }

    public function logout() {
        session_destroy();
    }
}

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