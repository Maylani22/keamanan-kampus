<?php
require_once '../config/constants.php';
require_once '../lib/auth.php';

$auth = new Auth();
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    if ($user['role'] === ROLE_ADMIN) {
        header('Location: dashboard_admin.php');
    } else {
        header('Location: dashboard_mahasiswa.php');
    }
} else {
    header('Location: login.php');
}
exit;
?>