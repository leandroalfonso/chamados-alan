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

// Consulta para obter todas as avaliações com informações dos chamados e usuários
$sql = "
    SELECT 
        a.id_avaliacao,
        a.id_chamado,
        a.id_usuario,
        a.nota,
        a.comentario,
        a.data_avaliacao,
        c.titulo AS chamado_titulo,
        c.data_abertura,
        c.data_fechamento,
        u.nome AS nome_usuario,
        t.nome AS nome_tecnico
    FROM 
        avaliacoes a
    JOIN 
        chamados c ON a.id_chamado = c.id_chamado
    JOIN 
        usuarios u ON a.id_usuario = u.id_usuario
    LEFT JOIN 
        usuarios t ON c.id_tecnico = t.id_usuario
    ORDER BY 
        a.data_avaliacao DESC
";

$result = mysqli_query($conn, $sql);

// Calcular estatísticas
$total_avaliacoes = mysqli_num_rows($result);
$soma_notas = 0;
$contagem_por_nota = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

// Armazenar avaliações para reutilizar depois
$avaliacoes = [];

if ($total_avaliacoes > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $avaliacoes[] = $row;
        $soma_notas += $row['nota'];
        $contagem_por_nota[$row['nota']]++;
    }
    mysqli_data_seek($result, 0); // Resetar o cursor do resultado
}

$media_geral = $total_avaliacoes > 0 ? $soma_notas / $total_avaliacoes : 0;

// Estatísticas por técnico
$sql_tecnicos = "
    SELECT 
        t.id_usuario,
        t.nome,
        COUNT(a.id_avaliacao) AS total_avaliacoes,
        AVG(a.nota) AS media_notas
    FROM 
        usuarios t
    LEFT JOIN 
        chamados c ON t.id_usuario = c.id_tecnico
    LEFT JOIN 
        avaliacoes a ON c.id_chamado = a.id_chamado
    WHERE 
        t.cargo = 'Técnico'
    GROUP BY 
        t.id_usuario
    ORDER BY 
        media_notas DESC
";

$result_tecnicos = mysqli_query($conn, $sql_tecnicos);

// Gerar PDF se solicitado
if (isset($_POST['gerar_pdf'])) {
    require_once '../vendor/autoload.php'; // Requer TCPDF
    
    $pdf = new TCPDF();
    $pdf->SetTitle('Relatório de Avaliações');
    // ... Código para gerar PDF ...
    
    $pdf->Output('relatorio_avaliacoes.pdf', 'D');
    exit();
}

// Gerar Excel se solicitado
if (isset($_POST['gerar_excel'])) {
    require_once '../vendor/autoload.php'; // Requer PhpSpreadsheet
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    // ... Código para gerar Excel ...
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="relatorio_avaliacoes.xlsx"');
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
    <title>Relatório de Avaliações - Sistema de Chamados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .metric-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: 100%;
        }
        .chart-container {
            height: 300px;
        }
        .nota-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .nota-1 { background-color: #dc3545; }
        .nota-2 { background-color: #fd7e14; }
        .nota-3 { background-color: #ffc107; }
        .nota-4 { background-color: #20c997; }
        .nota-5 { background-color: #198754; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Relatório de Avaliações</h1>
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
        
        <!-- Resumo Geral -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <h3>Total de Avaliações</h3>
                    <div class="fs-1 fw-bold text-center"><?php echo $total_avaliacoes; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <h3>Média Geral</h3>
                    <div class="fs-1 fw-bold text-center"><?php echo number_format($media_geral, 1); ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="metric-card">
                    <h3>Distribuição por Nota</h3>
                    <div class="chart-container">
                        <canvas id="notasChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance por Técnico -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="metric-card">
                    <h3>Performance por Técnico</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Técnico</th>
                                    <th>Total de Avaliações</th>
                                    <th>Média de Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tecnico = mysqli_fetch_assoc($result_tecnicos)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tecnico['nome']); ?></td>
                                    <td><?php echo $tecnico['total_avaliacoes']; ?></td>
                                    <td>
                                        <?php 
                                            $media = $tecnico['media_notas'] ? number_format($tecnico['media_notas'], 1) : 'N/A';
                                            echo $media;
                                        ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Todas as Avaliações -->
        <div class="row">
            <div class="col-12">
                <div class="metric-card">
                    <h3>Todas as Avaliações</h3>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Chamado</th>
                                    <th>Solicitante</th>
                                    <th>Técnico</th>
                                    <th>Nota</th>
                                    <th>Comentário</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($avaliacoes as $avaliacao): ?>
                                <tr>
                                    <td>
                                        <a href="../ver_chamado.php?id=<?php echo $avaliacao['id_chamado']; ?>">
                                            #<?php echo $avaliacao['id_chamado']; ?> - <?php echo htmlspecialchars($avaliacao['chamado_titulo']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($avaliacao['nome_usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($avaliacao['nome_tecnico']); ?></td>
                                    <td>
                                        <div class="nota-circle nota-<?php echo $avaliacao['nota']; ?>">
                                            <?php echo $avaliacao['nota']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($avaliacao['comentario']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($avaliacao['data_avaliacao'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Exportar -->
        <div class="row mt-4">
            <div class="col-12">
                <form method="post" class="d-flex gap-2">
                    <button type="submit" name="gerar_pdf" class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                    </button>
                    <button type="submit" name="gerar_excel" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Gráfico de distribuição de notas
        new Chart(document.getElementById('notasChart'), {
            type: 'bar',
            data: {
                labels: ['1 estrela', '2 estrelas', '3 estrelas', '4 estrelas', '5 estrelas'],
                datasets: [{
                    label: 'Quantidade',
                    data: [
                        <?php echo $contagem_por_nota[1]; ?>,
                        <?php echo $contagem_por_nota[2]; ?>,
                        <?php echo $contagem_por_nota[3]; ?>,
                        <?php echo $contagem_por_nota[4]; ?>,
                        <?php echo $contagem_por_nota[5]; ?>
                    ],
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14',
                        '#ffc107',
                        '#20c997',
                        '#198754'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
                    }
                }
            }
        });
    </script>
</body>
</html>
