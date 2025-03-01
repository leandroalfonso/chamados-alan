<?php
session_start();
require_once 'db_connection.php';
require_once 'includes/functions.php';
verificar_sessao();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$id_chamado = $_GET['id'];
$id_usuario = $_SESSION['user_id'];
$cargo = $_SESSION['cargo'];


$stmt = mysqli_prepare($conn, "SELECT c.*, u.nome as nome_usuario 
                             FROM chamados c 
                             JOIN usuarios u ON c.id_usuario = u.id_usuario 
                             WHERE c.id_chamado = ?");
mysqli_stmt_bind_param($stmt, "i", $id_chamado);
mysqli_stmt_execute($stmt);
$chamado = mysqli_stmt_get_result($stmt)->fetch_assoc();

if (!$chamado || 
    ($cargo !== 'Administrador' && 
     $cargo !== 'Técnico' && 
     $id_usuario !== $chamado['id_usuario'])) {
    $_SESSION['error_message'] = "Você não tem permissão para ver este histórico.";
    header('Location: dashboard.php');
    exit();
}


$eventos = [];

// Adicionar evento de criação do chamado
$eventos[] = [
    'data' => $chamado['data_abertura'],
    'tipo' => 'Criação do Chamado',
    'descricao' => 'Chamado criado por ' . $chamado['nome_usuario'],
    'usuario' => $chamado['nome_usuario']
];

// Buscar comentários
$stmt = mysqli_prepare($conn, "SELECT c.*, u.nome as nome_usuario 
                             FROM comentarios c 
                             JOIN usuarios u ON c.id_usuario = u.id_usuario 
                             WHERE c.id_chamado = ?");
mysqli_stmt_bind_param($stmt, "i", $id_chamado);
mysqli_stmt_execute($stmt);
$comentarios = mysqli_stmt_get_result($stmt);

while ($comentario = mysqli_fetch_assoc($comentarios)) {
    $eventos[] = [
        'data' => $comentario['data_comentario'],
        'tipo' => 'Comentário',
        'descricao' => strip_tags($comentario['comentario']),
        'usuario' => $comentario['nome_usuario']
    ];
}

// Buscar solicitações de reabertura e suas respostas
$stmt = mysqli_prepare($conn, "SELECT sr.*, u.nome as nome_solicitante, ur.nome as nome_responsavel
                             FROM solicitacoes_reabertura sr 
                             JOIN usuarios u ON sr.id_usuario = u.id_usuario 
                             LEFT JOIN usuarios ur ON sr.id_responsavel = ur.id_usuario 
                             WHERE sr.id_chamado = ?");
mysqli_stmt_bind_param($stmt, "i", $id_chamado);
mysqli_stmt_execute($stmt);
$solicitacoes = mysqli_stmt_get_result($stmt);

while ($solicitacao = mysqli_fetch_assoc($solicitacoes)) {
   
    $eventos[] = [
        'data' => $solicitacao['data_solicitacao'],
        'tipo' => 'Solicitação de Reabertura',
        'descricao' => "Solicitação de reabertura por " . $solicitacao['nome_solicitante'] . "\nJustificativa: " . strip_tags($solicitacao['justificativa']),
        'usuario' => $solicitacao['nome_solicitante']
    ];

   
    if ($solicitacao['status'] !== 'Pendente' && $solicitacao['data_resposta']) {
        $status = $solicitacao['status'] === 'Aprovada' ? 'aprovada' : 'rejeitada';
        $eventos[] = [
            'data' => $solicitacao['data_resposta'],
            'tipo' => 'Resposta à Solicitação',
            'descricao' => "Solicitação de reabertura {$status} por " . $solicitacao['nome_responsavel'],
            'usuario' => $solicitacao['nome_responsavel']
        ];
    }
}

// Buscar anexos
$stmt = mysqli_prepare($conn, "SELECT * FROM anexos WHERE id_chamado = ? ORDER BY data_upload ASC");
mysqli_stmt_bind_param($stmt, "i", $id_chamado);
mysqli_stmt_execute($stmt);
$anexos = mysqli_stmt_get_result($stmt);

while ($anexo = mysqli_fetch_assoc($anexos)) {
    $eventos[] = [
        'data' => $anexo['data_upload'],
        'tipo' => 'Anexo',
        'descricao' => 'Arquivo anexado ao chamado',
        'usuario' => 'Sistema'
    ];
}


if ($chamado['status'] === 'Fechado' && $chamado['data_fechamento']) {
    // Buscar nome do técnico que fechou
    $stmt = mysqli_prepare($conn, "SELECT nome FROM usuarios WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "i", $chamado['id_tecnico']);
    mysqli_stmt_execute($stmt);
    $tecnico = mysqli_stmt_get_result($stmt)->fetch_assoc();
    
    $eventos[] = [
        'data' => $chamado['data_fechamento'],
        'tipo' => 'Fechamento',
        'descricao' => 'Chamado encerrado',
        'usuario' => $tecnico['nome']
    ];
}

// Ordenar eventos por data (mais recente primeiro)
usort($eventos, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico #<?php echo $id_chamado; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .header {
            background-color: #0d6efd;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
            margin: 0;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .logout-btn {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover {
            color: #f0f0f0;
        }

        .timeline {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 0;
        }

        .timeline::after {
            content: '';
            position: absolute;
            width: 3px;
            background: linear-gradient(to bottom, #007bff, #00ff88);
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -1px;
            border-radius: 3px;
        }

        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
            box-sizing: border-box;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            right: -12px;
            top: 15px;
            border-radius: 50%;
            z-index: 1;
            box-shadow: 0 0 0 4px #fff;
            transition: all 0.3s ease;
        }

        .timeline-item.criacao-do-chamado::after {
            background-color: #28a745;
        }

        .timeline-item.comentario::after {
            background-color: #007bff;
        }

        .timeline-item.anexo::after {
            background-color: #6610f2;
        }

        .timeline-item.fechamento::after {
            background-color: #dc3545;
        }

        .timeline-item.solicitacao-de-reabertura::after {
            background-color: #ff9900;
        }

        .timeline-item.resposta-a-solicitacao::after {
            background-color: #33cc33;
        }

        .timeline-item:nth-child(odd) {
            left: 0;
        }

        .timeline-item:nth-child(even) {
            left: 50%;
        }

        .timeline-item:nth-child(even)::after {
            left: -12px;
        }

        .timeline-content {
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .timeline-content:hover {
            transform: translateY(-5px);
        }

        .timeline-date {
            color: #6c757d;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .timeline-type {
            color: #007bff;
            font-weight: 600;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .timeline-description {
            margin-top: 10px;
            color: #212529;
            line-height: 1.5;
        }

        .timeline-user {
            margin-top: 10px;
            font-size: 0.9em;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .chamado-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .info-item {
            flex: 1;
            min-width: 200px;
        }

        .info-item .label {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-weight: 500;
            color: #212529;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .btn-voltar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 15px 25px;
            background: #007bff;
            color: white;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,123,255,0.3);
        }

        .btn-voltar:hover {
            background: #0056b3;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,123,255,0.4);
        }

        @media screen and (max-width: 768px) {
            .timeline::after {
                left: 31px;
            }

            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 25px;
            }

            .timeline-item::after {
                left: 21px;
            }

            .timeline-item:nth-child(even) {
                left: 0;
            }

            .timeline-item:nth-child(even)::after {
                left: 21px;
            }

            .chamado-info {
                flex-direction: column;
            }

            .info-item {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1 class="header-title">
            <span class="material-symbols-outlined">history</span>
            Histórico #<?php echo $id_chamado; ?>
        </h1>
        <div class="header-user">
            <div class="user-info">
                <span class="material-symbols-outlined">person</span>
                <?php echo htmlspecialchars($_SESSION['nome']); ?>
            </div>
            <a href="logout.php" class="logout-btn">
                <span class="material-symbols-outlined">logout</span>
                Sair
            </a>
        </div>
    </header>

    <div class="container mt-4">
        <div class="chamado-info">
            <div class="info-item">
                <div class="label">Título</div>
                <div class="value"><?php echo htmlspecialchars($chamado['titulo']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Status</div>
                <div class="value"><?php echo htmlspecialchars($chamado['status']); ?></div>
            </div>
            <div class="info-item">
                <div class="label">Data de Abertura</div>
                <div class="value"><?php echo date('d/m/Y H:i', strtotime($chamado['data_abertura'])); ?></div>
            </div>
            <?php if ($chamado['status'] === 'Fechado'): ?>
            <div class="info-item">
                <div class="label">Data de Fechamento</div>
                <div class="value"><?php echo date('d/m/Y H:i', strtotime($chamado['data_fechamento'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="timeline">
            <?php foreach ($eventos as $evento): ?>
                <div class="timeline-item <?php echo str_replace(' ', '-', strtolower($evento['tipo'])); ?>">
                    <div class="timeline-content">
                        <div class="timeline-date">
                            <span class="material-symbols-outlined">schedule</span>
                            <?php echo date('d/m/Y H:i:s', strtotime($evento['data'])); ?>
                        </div>
                        <div class="timeline-type">
                            <span class="material-symbols-outlined">
                                <?php
                                switch($evento['tipo']) {
                                    case 'Criação do Chamado':
                                        echo 'add_circle';
                                        break;
                                    case 'Comentário':
                                        echo 'comment';
                                        break;
                                    case 'Anexo':
                                        echo 'attach_file';
                                        break;
                                    case 'Fechamento':
                                        echo 'task_alt';
                                        break;
                                    case 'Solicitação de Reabertura':
                                        echo 'refresh';
                                        break;
                                    case 'Resposta à Solicitação':
                                        echo 'check_circle';
                                        break;
                                }
                                ?>
                            </span>
                            <?php echo htmlspecialchars($evento['tipo']); ?>
                        </div>
                        <div class="timeline-description">
                            <?php echo htmlspecialchars($evento['descricao']); ?>
                        </div>
                        <div class="timeline-user">
                            <span class="material-symbols-outlined">person</span>
                            <?php echo htmlspecialchars($evento['usuario']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="nav-buttons">
            <a href="ver_chamado.php?id=<?php echo $id_chamado; ?>" class="btn-voltar">
                <span class="material-symbols-outlined">arrow_back</span>
                Voltar ao Chamado
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
