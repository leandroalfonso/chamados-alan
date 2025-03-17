<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Sistema de Chamados</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <?php if (isset($_SESSION['cargo']) && ($_SESSION['cargo'] === 'Administrador' || $_SESSION['cargo'] === 'Técnico')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="chamados_tecnicos.php">Chamados Técnicos</a>
                </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['cargo']) && $_SESSION['cargo'] === 'Administrador'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="gerenciar_usuarios.php">Gerenciar Usuários</a>
                </li>
                <?php endif; ?>
            </ul>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-nav">
                <span class="nav-item nav-link text-light">
                    <span class="material-symbols-outlined align-middle">person</span>
                    <?php echo htmlspecialchars($_SESSION['nome']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <span class="material-symbols-outlined align-middle">logout</span>
                    Sair
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
