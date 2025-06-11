<?php
session_start();
require_once 'db.php';

// Processar ações
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'login':
            if (!empty($_POST['username']) && !empty($_POST['password'])) {
                $username = $_POST['username'];
                $password = $_POST['password'];
                
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['usuario'] = $user;
                    header('Location: index.php');
                    exit;
                } else {
                    $loginError = 'Usuário ou senha inválidos';
                }
            }
            break;
            
        case 'logout':
            session_unset();
            session_destroy();
            header('Location: index.php');
            exit;
            
        case 'add_gasto':
            if (isset($_SESSION['usuario'])) {
                $data = $_POST['data'];
                $categoria = $_POST['categoria'];
                $descricao = $_POST['descricao'];
                $valor = $_POST['valor'];
                $forma_pagamento = $_POST['forma_pagamento'];
                $observacoes = $_POST['observacoes'] ?? '';
                
                $stmt = $pdo->prepare("INSERT INTO gastos (usuario_id, data, categoria, descricao, valor, forma_pagamento, observacoes) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['usuario']['id'],
                    $data,
                    $categoria,
                    $descricao,
                    $valor,
                    $forma_pagamento,
                    $observacoes
                ]);
                header('Location: index.php?tab=gastos');
                exit;
            }
            break;
            
        case 'add_parcelamento':
            if (isset($_SESSION['usuario'])) {
                $descricao = $_POST['descricao'];
                $valor_total = $_POST['valor_total'];
                $qtd_parcelas = $_POST['qtd_parcelas'];
                $parcela_atual = $_POST['parcela_atual'] ?? 1;
                $data_inicio = $_POST['data_inicio'];
                
                $stmt = $pdo->prepare("INSERT INTO parcelamentos (usuario_id, descricao, valor_total, qtd_parcelas, parcela_atual, data_inicio) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['usuario']['id'],
                    $descricao,
                    $valor_total,
                    $qtd_parcelas,
                    $parcela_atual,
                    $data_inicio
                ]);
                header('Location: index.php?tab=parcelamentos');
                exit;
            }
            break;
    }
}

// Obter dados para as visualizações
function getGastos($pdo, $usuario_id = null) {
    $params = [];
    $sql = "SELECT * FROM gastos";
    
    if ($usuario_id) {
        $sql .= " WHERE usuario_id = ?";
        $params[] = $usuario_id;
    } elseif (isset($_SESSION['usuario']) && $_SESSION['usuario']['role'] !== 'admin') {
        $sql .= " WHERE usuario_id = ?";
        $params[] = $_SESSION['usuario']['id'];
    }
    
    $sql .= " ORDER BY data DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getParcelamentos($pdo, $usuario_id = null) {
    $params = [];
    $sql = "SELECT * FROM parcelamentos";
    
    if ($usuario_id) {
        $sql .= " WHERE usuario_id = ?";
        $params[] = $usuario_id;
    } elseif (isset($_SESSION['usuario']) && $_SESSION['usuario']['role'] !== 'admin') {
        $sql .= " WHERE usuario_id = ?";
        $params[] = $_SESSION['usuario']['id'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Determinar aba ativa
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#5e35b1">
    <title>Controle de Gastos</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos do projeto anterior mantidos */
        :root {
            --primary: #5e35b1;
            --primary-dark: #4527a0;
            --secondary: #26a69a;
            --accent: #ff4081;
            --light: #f5f5f5;
            --dark: #333;
            --gray: #9e9e9e;
            --success: #4caf50;
            --warning: #ff9800;
            --danger: #f44336;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Tela de Login */
        .login-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 400px;
            padding: 30px;
            text-align: center;
            transform: translateY(0);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .app-logo {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .app-title {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .app-subtitle {
            color: var(--gray);
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .input-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .input-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(94, 53, 177, 0.2);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: var(--secondary);
        }

        .btn-secondary:hover {
            background-color: #00897b;
        }

        .error {
            color: var(--danger);
            margin-bottom: 15px;
        }

        /* Interface Principal */
        .app-container {
            display: none;
            min-height: 100vh;
        }

        .app-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: white;
            color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
        }

        .nav-tabs {
            display: flex;
            background-color: white;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 70px;
            z-index: 90;
        }

        .tab {
            padding: 15px 20px;
            text-align: center;
            font-weight: 500;
            cursor: pointer;
            flex: 1;
            min-width: 100px;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }

        .tab-content {
            display: none;
            padding: 20px;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        .section-title {
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .positive {
            color: var(--success);
        }

        .negative {
            color: var(--danger);
        }

        .chart-container {
            height: 250px;
            margin: 20px 0;
            position: relative;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-details {
            flex: 1;
        }

        .item-title {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .item-subtitle {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .item-value {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .progress-bar {
            height: 8px;
            background-color: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress {
            height: 100%;
            background-color: var(--secondary);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .actions .btn {
            flex: 1;
        }

        .install-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            cursor: pointer;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(94, 53, 177, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(94, 53, 177, 0); }
            100% { box-shadow: 0 0 0 0 rgba(94, 53, 177, 0); }
        }

        /* Responsividade */
        @media (min-width: 768px) {
            .chart-container {
                height: 300px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .nav-tabs {
                justify-content: center;
            }
            
            .tab {
                min-width: 120px;
            }
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['usuario'])): ?>
    <!-- Tela de Login -->
    <div class="login-container">
        <div class="login-card">
            <div class="app-logo">
                <i class="fas fa-wallet"></i>
            </div>
            <h1 class="app-title">Controle de Gastos</h1>
            <p class="app-subtitle">Gerencie suas finanças de forma simples</p>
            
            <?php if (isset($loginError)): ?>
                <div class="error"><?= $loginError ?></div>
            <?php endif; ?>
            
            <form method="POST" action="?action=login">
                <div class="input-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" placeholder="Digite seu usuário" required>
                </div>
                
                <div class="input-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
                </div>
                
                <button type="submit" class="btn">Entrar</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Interface Principal -->
    <div class="app-container">
        <header class="app-header">
            <h1><i class="fas fa-wallet"></i> Controle de Gastos</h1>
            <div class="user-info">
                <div class="user-avatar"><?= substr($_SESSION['usuario']['nome'], 0, 1) ?></div>
                <span><?= $_SESSION['usuario']['nome'] ?></span>
                <a href="?action=logout" style="color: white; margin-left: 15px;"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div class="nav-tabs">
            <div class="tab <?= $activeTab == 'dashboard' ? 'active' : '' ?>" data-tab="dashboard">Dashboard</div>
            <div class="tab <?= $activeTab == 'gastos' ? 'active' : '' ?>" data-tab="gastos">Gastos</div>
            <div class="tab <?= $activeTab == 'parcelamentos' ? 'active' : '' ?>" data-tab="parcelamentos">Parcelamentos</div>
            <div class="tab <?= $activeTab == 'resumo' ? 'active' : '' ?>" data-tab="resumo">Resumo</div>
        </div>

        <!-- Dashboard -->
        <div id="dashboardTab" class="tab-content <?= $activeTab == 'dashboard' ? 'active' : '' ?>">
            <div class="container">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Dashboard</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value positive">R$ 4.200</div>
                        <div class="stat-label">Salário</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value positive">R$ 1.850</div>
                        <div class="stat-label">Saldo Livre</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">3</div>
                        <div class="stat-label">Parcelas Restantes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value negative">R$ 2.350</div>
                        <div class="stat-label">Gastos Totais</div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Distribuição de Gastos</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="gastosChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Evolução Mensal</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="evolucaoChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Gastos Parcelados vs Não Parcelados</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="parceladosChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gastos -->
        <div id="gastosTab" class="tab-content <?= $activeTab == 'gastos' ? 'active' : '' ?>">
            <div class="container">
                <h2 class="section-title"><i class="fas fa-receipt"></i> Gastos</h2>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Adicionar Novo Gasto</h3>
                    </div>
                    <form id="gastoForm" action="?action=add_gasto" method="POST">
                        <div class="form-group">
                            <label for="gastoData">Data</label>
                            <input type="date" id="gastoData" name="data" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gastoCategoria">Categoria</label>
                            <select id="gastoCategoria" name="categoria" class="form-control" required>
                                <option value="">Selecione uma categoria</option>
                                <option value="alimentacao">Alimentação</option>
                                <option value="transporte">Transporte</option>
                                <option value="moradia">Moradia</option>
                                <option value="lazer">Lazer</option>
                                <option value="saude">Saúde</option>
                                <option value="educacao">Educação</option>
                                <option value="outros">Outros</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="gastoDescricao">Descrição</label>
                            <input type="text" id="gastoDescricao" name="descricao" class="form-control" placeholder="Descrição do gasto" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gastoValor">Valor (R$)</label>
                            <input type="number" id="gastoValor" name="valor" class="form-control" placeholder="0.00" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gastoFormaPagamento">Forma de Pagamento</label>
                            <select id="gastoFormaPagamento" name="forma_pagamento" class="form-control" required>
                                <option value="">Selecione a forma de pagamento</option>
                                <option value="dinheiro">Dinheiro</option>
                                <option value="debito">Cartão de Débito</option>
                                <option value="credito">Cartão de Crédito</option>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="gastoObservacoes">Observações</label>
                            <textarea id="gastoObservacoes" name="observacoes" class="form-control" rows="3" placeholder="Observações adicionais"></textarea>
                        </div>
                        
                        <div class="actions">
                            <button type="reset" class="btn btn-secondary">Limpar</button>
                            <button type="submit" class="btn">Adicionar Gasto</button>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Últimos Gastos</h3>
                    </div>
                    <div id="listaGastos">
                        <?php 
                        $gastos = getGastos($pdo);
                        foreach ($gastos as $gasto): 
                        ?>
                        <div class="list-item">
                            <div class="item-details">
                                <div class="item-title"><?= htmlspecialchars($gasto['descricao']) ?></div>
                                <div class="item-subtitle">
                                    <?= ucfirst($gasto['categoria']) ?> • 
                                    <?= date('d/m/Y', strtotime($gasto['data'])) ?> • 
                                    <?= ucfirst($gasto['forma_pagamento']) ?>
                                </div>
                                <?php if (!empty($gasto['observacoes'])): ?>
                                <div class="item-subtitle"><?= htmlspecialchars($gasto['observacoes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="item-value negative">R$ <?= number_format($gasto['valor'], 2, ',', '.') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parcelamentos -->
        <div id="parcelamentosTab" class="tab-content <?= $activeTab == 'parcelamentos' ? 'active' : '' ?>">
            <div class="container">
                <h2 class="section-title"><i class="fas fa-credit-card"></i> Parcelamentos</h2>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Adicionar Parcelamento</h3>
                    </div>
                    <form id="parcelamentoForm" action="?action=add_parcelamento" method="POST">
                        <div class="form-group">
                            <label for="parcelaDescricao">Descrição</label>
                            <input type="text" id="parcelaDescricao" name="descricao" class="form-control" placeholder="Descrição do parcelamento" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="parcelaValor">Valor Total (R$)</label>
                            <input type="number" id="parcelaValor" name="valor_total" class="form-control" placeholder="0.00" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="parcelaQtd">Quantidade de Parcelas</label>
                            <input type="number" id="parcelaQtd" name="qtd_parcelas" class="form-control" placeholder="Número de parcelas" min="2" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="parcelaAtual">Parcela Atual</label>
                            <input type="number" id="parcelaAtual" name="parcela_atual" class="form-control" placeholder="Parcela atual" min="1" required value="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="parcelaData">Data Início</label>
                            <input type="date" id="parcelaData" name="data_inicio" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="actions">
                            <button type="reset" class="btn btn-secondary">Limpar</button>
                            <button type="submit" class="btn">Adicionar Parcelamento</button>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Parcelamentos Ativos</h3>
                    </div>
                    <div id="listaParcelamentos">
                        <?php 
                        $parcelamentos = getParcelamentos($pdo);
                        foreach ($parcelamentos as $parcela): 
                            $progresso = ($parcela['parcela_atual'] / $parcela['qtd_parcelas']) * 100;
                            $valor_parcela = $parcela['valor_total'] / $parcela['qtd_parcelas'];
                        ?>
                        <div class="list-item">
                            <div class="item-details">
                                <div class="item-title"><?= htmlspecialchars($parcela['descricao']) ?></div>
                                <div class="item-subtitle">
                                    R$ <?= number_format($parcela['valor_total'], 2, ',', '.') ?> • 
                                    <?= $parcela['qtd_parcelas'] ?>x • Parcela <?= $parcela['parcela_atual'] ?>/<?= $parcela['qtd_parcelas'] ?>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?= $progresso ?>%"></div>
                                </div>
                                <div class="item-subtitle">
                                    <?= number_format($progresso, 0) ?>% concluído • 
                                    Restam <?= $parcela['qtd_parcelas'] - $parcela['parcela_atual'] ?> parcelas
                                </div>
                            </div>
                            <div class="item-value">R$ <?= number_format($valor_parcela, 2, ',', '.') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resumo -->
        <div id="resumoTab" class="tab-content <?= $activeTab == 'resumo' ? 'active' : '' ?>">
            <div class="container">
                <h2 class="section-title"><i class="fas fa-file-invoice-dollar"></i> Resumo Mensal</h2>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?= date('F Y') ?></h3>
                    </div>
                    <?php
                    // Calcular valores para o resumo (exemplo simplificado)
                    $salario = 4200.00;
                    $gastos_fixos = 1850.00;
                    $parcelamentos = 750.00;
                    $gastos_variaveis = 600.00;
                    $saldo_livre = $salario - $gastos_fixos - $parcelamentos - $gastos_variaveis;
                    ?>
                    <div class="list-item">
                        <div class="item-details">
                            <div class="item-title">Salário</div>
                        </div>
                        <div class="item-value positive">R$ <?= number_format($salario, 2, ',', '.') ?></div>
                    </div>
                    <div class="list-item">
                        <div class="item-details">
                            <div class="item-title">Gastos Fixos</div>
                        </div>
                        <div class="item-value negative">R$ <?= number_format($gastos_fixos, 2, ',', '.') ?></div>
                    </div>
                    <div class="list-item">
                        <div class="item-details">
                            <div class="item-title">Parcelamentos</div>
                        </div>
                        <div class="item-value negative">R$ <?= number_format($parcelamentos, 2, ',', '.') ?></div>
                    </div>
                    <div class="list-item">
                        <div class="item-details">
                            <div class="item-title">Gastos Variáveis</div>
                        </div>
                        <div class="item-value negative">R$ <?= number_format($gastos_variaveis, 2, ',', '.') ?></div>
                    </div>
                    <div class="list-item" style="border-top: 2px solid #eee; padding-top: 15px; margin-top: 10px;">
                        <div class="item-details">
                            <div class="item-title" style="font-weight: 700;">Saldo Livre</div>
                        </div>
                        <div class="item-value" style="font-weight: 700; color: var(--success);">R$ <?= number_format($saldo_livre, 2, ',', '.') ?></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status de Parcelamentos</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusParcelamentosChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botão de instalação PWA -->
        <div id="installButton" class="install-btn" title="Instalar aplicativo">
            <i class="fas fa-download"></i>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Mostrar a interface principal se o usuário estiver logado
        <?php if (isset($_SESSION['usuario'])): ?>
            document.querySelector('.app-container').style.display = 'block';
        <?php endif; ?>

        // Navegação entre abas
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remover classe ativa de todas as abas
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                
                // Adicionar classe ativa à aba selecionada
                this.classList.add('active');
                document.getElementById(`${tabId}Tab`).classList.add('active');
                
                // Atualizar a URL
                const url = new URL(window.location);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);
            });
        });

        // Inicialização de gráficos
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de distribuição de gastos
            const gastosCtx = document.getElementById('gastosChart')?.getContext('2d');
            if (gastosCtx) {
                new Chart(gastosCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Alimentação', 'Transporte', 'Moradia', 'Lazer', 'Saúde', 'Educação', 'Outros'],
                        datasets: [{
                            data: [25, 15, 30, 10, 8, 7, 5],
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        }
                    }
                });
            }

            // Gráfico de evolução mensal
            const evolucaoCtx = document.getElementById('evolucaoChart')?.getContext('2d');
            if (evolucaoCtx) {
                new Chart(evolucaoCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                        datasets: [{
                            label: 'Gastos',
                            data: [2200, 2400, 2000, 2500, 2300, 2350],
                            borderColor: '#FF6384',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            fill: true,
                            tension: 0.3
                        }, {
                            label: 'Saldo Livre',
                            data: [1800, 1600, 2000, 1500, 1700, 1850],
                            borderColor: '#36A2EB',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Gráfico de parcelados vs não parcelados
            const parceladosCtx = document.getElementById('parceladosChart')?.getContext('2d');
            if (parceladosCtx) {
                new Chart(parceladosCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                        datasets: [{
                            label: 'Parcelados',
                            data: [600, 550, 700, 800, 750, 750],
                            backgroundColor: '#FFCE56'
                        }, {
                            label: 'Não Parcelados',
                            data: [1600, 1850, 1300, 1700, 1550, 1600],
                            backgroundColor: '#4BC0C0'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: false,
                            },
                            y: {
                                stacked: false,
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Gráfico de status de parcelamentos
            const statusCtx = document.getElementById('statusParcelamentosChart')?.getContext('2d');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'polarArea',
                    data: {
                        labels: ['Pagos', 'Em aberto', 'Atrasados'],
                        datasets: [{
                            data: [5, 3, 1],
                            backgroundColor: [
                                '#4BC0C0',
                                '#FFCE56',
                                '#FF6384'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true
                        }
                    }
                });
            }
        });

        // PWA Installation
        let deferredPrompt;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('installButton').style.display = 'flex';
        });

        document.getElementById('installButton').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response: ${outcome}`);
            document.getElementById('installButton').style.display = 'none';
            deferredPrompt = null;
        });

        window.addEventListener('appinstalled', () => {
            document.getElementById('installButton').style.display = 'none';
            deferredPrompt = null;
            console.log('PWA installed');
        });

        // Service Worker para PWA
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js').then(function(registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>