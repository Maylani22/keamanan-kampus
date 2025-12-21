<?php

function redirect($url) {
    header("Location: $url");
    exit;
}

function formatDate($date, $format = 'd M Y H:i') {
    return date($format, strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        STATUS_PENDING => 'warning',
        STATUS_PROCESSED => 'info',
        STATUS_RESOLVED => 'success'
    ];
    return $badges[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        STATUS_PENDING => 'Menunggu',
        STATUS_PROCESSED => 'Diproses',
        STATUS_RESOLVED => 'Selesai'
    ];
    return $texts[$status] ?? $status;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function showAlert($type, $message) {
    return "<div class='alert alert-{$type} alert-dismissible fade show'>
                {$message}
                <button class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}
?>