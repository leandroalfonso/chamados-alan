<?php
// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurações do banco de dados
define('DB_HOST', 'problemas-bd.mysql.uhserver.com');  // Host do banco que funciona
define('DB_USER', 'leandroalfonso');  // Usuário que funciona
define('DB_PASS', 'Leandro171716@');  // Senha que funciona
define('DB_NAME', 'problemas_bd');  // Nome do banco que funciona

// URLs e caminhos
define('BASE_URL', isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '');
define('ROOT_PATH', dirname(__DIR__));

// Configurações de fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Configurações de exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Funções úteis
function redirect($url) {
    header("Location: " . BASE_URL . "/" . $url);
    exit();
}

function isAdmin() {
    return isset($_SESSION['cargo']) && $_SESSION['cargo'] === 'Administrador';
}

function isTecnico() {
    return isset($_SESSION['cargo']) && $_SESSION['cargo'] === 'Técnico';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Carregar funções comuns
require_once ROOT_PATH . '/includes/functions.php';
