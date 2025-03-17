<?php
session_start();
require_once '../db_connection.php';
require_once '../includes/functions.php';

// Verificar se é administrador
if (!isset($_SESSION['user_id']) || $_SESSION['cargo'] !== 'Administrador') {
    
    $_SESSION['error_message'] = "Você não tem permissão para acessar este recurso.";

    header('Location: ../dashboard.php');
    exit();
}

// Função para calcular métricas
function calcularMetricas($conn) {
    // Tempo médio de resolução
    $stmt = mysqli_prepare($conn, "
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, data_abertura, data_fechamento)) as tempo_medio,
            COUNT(*) as total_chamados,
            COUNT(CASE WHEN status = 'Fechado' THEN 1 END) as chamados_fechados
        FROM chamados
        WHERE data_fechamento IS NOT NULL
    ");
    mysqli_stmt_execute($stmt);
    $metricas_gerais = mysqli_stmt_get_result($stmt)->fetch_assoc();

    // Taxa de reabertura
    $stmt = mysqli_prepare($conn, "
        SELECT 
            COUNT(DISTINCT sr.id_chamado) as chamados_reabertos,
            (SELECT COUNT(*) FROM chamados) as total_chamados
        FROM solicitacoes_reabertura sr
        WHERE sr.status = 'Aprovada'
    ");
    mysqli_stmt_execute($stmt);
    $taxa_reabertura = mysqli_stmt_get_result($stmt)->fetch_assoc();

    // Performance dos técnicos
    $stmt = mysqli_prepare($conn, "
        SELECT 
            u.nome,
            COUNT(c.id_chamado) as total_chamados,
            AVG(TIMESTAMPDIFF(MINUTE, c.data_abertura, c.data_fechamento)) as tempo_medio_resolucao,
            (SELECT COUNT(*) 
             FROM solicitacoes_reabertura sr 
             WHERE sr.status = 'Aprovada' 
             AND sr.id_chamado IN (SELECT id_chamado FROM chamados WHERE id_tecnico = u.id_usuario)
            ) as total_reaberturas
        FROM usuarios u
        LEFT JOIN chamados c ON c.id_tecnico = u.id_usuario
        WHERE u.cargo = 'Técnico'
        GROUP BY u.id_usuario
    ");
    mysqli_stmt_execute($stmt);
    $performance_tecnicos = mysqli_stmt_get_result($stmt);

    return [
        'metricas_gerais' => $metricas_gerais,
        'taxa_reabertura' => $taxa_reabertura,
        'performance_tecnicos' => mysqli_fetch_all($performance_tecnicos, MYSQLI_ASSOC)
    ];
}

$metricas = calcularMetricas($conn);

// Gerar PDF se solicitado
if (isset($_POST['gerar_pdf'])) {
    require_once '../vendor/autoload.php'; // Requer TCPDF
    
    $pdf = new TCPDF();
    $pdf->SetTitle('Relatório de Métricas');
    // ... Código para gerar PDF ...
    
    $pdf->Output('relatorio_metricas.pdf', 'D');
    exit();
}

// Gerar Excel se solicitado
if (isset($_POST['gerar_excel'])) {
    require_once '../vendor/autoload.php'; // Requer PhpSpreadsheet
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    // ... Código para gerar Excel ...
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_metricas.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Métricas - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.min.css">
    <style>
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #0d6efd;
        }
        .chart-container {
            height: 300px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h1 class="mb-4">Dashboard de Métricas</h1>
        
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Indicadores do Sistema</h2>
                    <a href="relatorio_avaliacoes.php" class="btn btn-primary">
                        <i class="bi bi-star"></i> Ver Relatório de Avaliações
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="metric-card">
                    <h3>Tempo Médio de Resolução</h3>
                    <div class="metric-value">
                        <?php 
                        $horas = floor($metricas['metricas_gerais']['tempo_medio'] / 60);
                        echo $horas . 'h';
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <h3>Taxa de Resolução</h3>
                    <div class="metric-value">
                        <?php 
                        $taxa = ($metricas['metricas_gerais']['chamados_fechados'] / $metricas['metricas_gerais']['total_chamados']) * 100;
                        echo number_format($taxa, 1) . '%';
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <h3>Taxa de Reabertura</h3>
                    <div class="metric-value">
                        <?php 
                        $taxa = ($metricas['taxa_reabertura']['chamados_reabertos'] / $metricas['taxa_reabertura']['total_chamados']) * 100;
                        echo number_format($taxa, 1) . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="metric-card">
                    <h3>Performance dos Técnicos</h3>
                    <div class="chart-container">
                        <canvas id="techPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card">
                    <h3>Tendência de Chamados</h3>
                    <div class="chart-container">
                        <canvas id="ticketTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="metric-card">
                    <h3>Detalhes por Técnico</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Técnico</th>
                                    <th>Chamados Atendidos</th>
                                    <th>Tempo Médio</th>
                                    <th>Taxa de Reabertura</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($metricas['performance_tecnicos'] as $tecnico): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tecnico['nome']); ?></td>
                                    <td><?php echo $tecnico['total_chamados']; ?></td>
                                    <td><?php echo floor($tecnico['tempo_medio_resolucao'] / 60) . 'h'; ?></td>
                                    <td><?php 
                                        $taxa = $tecnico['total_chamados'] > 0 
                                            ? ($tecnico['total_reaberturas'] / $tecnico['total_chamados']) * 100 
                                            : 0;
                                        echo number_format($taxa, 1) . '%';
                                    ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <form method="post" class="d-flex gap-2">
                    <button type="submit" name="gerar_pdf" class="btn btn-danger">
                        Exportar PDF
                    </button>
                    <button type="submit" name="gerar_excel" class="btn btn-success">
                        Exportar Excel
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dados para os gráficos
        const techData = <?php echo json_encode(array_map(function($t) {
            return [
                'name' => $t['nome'],
                'tickets' => $t['total_chamados'],
                'avgTime' => floor($t['tempo_medio_resolucao'] / 60)
            ];
        }, $metricas['performance_tecnicos'])); ?>;

        // Gráfico de Performance dos Técnicos
        new Chart(document.getElementById('techPerformanceChart'), {
            type: 'bar',
            data: {
                labels: techData.map(t => t.name),
                datasets: [{
                    label: 'Chamados Atendidos',
                    data: techData.map(t => t.tickets),
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Gráfico de Tendência (exemplo com dados fictícios)
        new Chart(document.getElementById('ticketTrendChart'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Chamados Abertos',
                    data: [65, 59, 80, 81, 56, 55],
                    borderColor: '#0d6efd',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html>
