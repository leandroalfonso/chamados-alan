<?php

function verificar_sessao() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function verificar_permissao_admin() {
    if (!isset($_SESSION['cargo']) || $_SESSION['cargo'] !== 'Administrador') {
        header('Location: dashboard.php');
        exit();
    }
}

function verificar_permissao_tecnico() {
    if (!isset($_SESSION['cargo']) || ($_SESSION['cargo'] !== 'Administrador' && $_SESSION['cargo'] !== 'Técnico')) {
        header('Location: dashboard.php');
        exit();
    }
}

function formatar_data($data) {
    return date('d/m/Y H:i', strtotime($data));
}

function obter_status_classe($status) {
    switch ($status) {
        case 'Aberto':
            return 'status-aberto';
        case 'Em Andamento':
            return 'status-andamento';
        case 'Fechado':
            return 'status-fechado';
        default:
            return 'status-default';
    }
}

function obter_status_badge($status) {
    switch ($status) {
        case 'Aberto':
            return '<span class="badge bg-danger">Aberto</span>';
        case 'Em Andamento':
            return '<span class="badge bg-warning">Em Andamento</span>';
        case 'Fechado':
            return '<span class="badge bg-success">Fechado</span>';
        default:
            return '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
    }
}

function sanitizar_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function gerar_token() {
    return bin2hex(random_bytes(32));
}

function validar_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
