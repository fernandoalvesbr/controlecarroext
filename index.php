<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();

// ==========================================
// 1. CONFIGURAÇÃO E MANIPULAÇÃO DE JSON
// ==========================================
$arquivos = array(
    'veiculos' => 'veiculos.json',
    'abastecimentos' => 'abastecimentos.json',
    'utilizacao' => 'utilizacao.json',
    'tecnicos' => 'tecnicos.json',
    'usuarios' => 'usuarios.json',
    'cartoes' => 'cartoes.json',
    'lavagens' => 'lavagens.json'
);

function lerDados($arquivo) {
    if (!file_exists($arquivo)) { file_put_contents($arquivo, '[]'); }
    $conteudo = file_get_contents($arquivo);
    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : array();
}

function salvarDados($arquivo, $dados) {
    file_put_contents($arquivo, json_encode(array_values($dados), JSON_PRETTY_PRINT));
}

// Otimização e Compressão de Imagens para WEBP
function fazerUpload($fileArray) {
    if (isset($fileArray) && $fileArray['error'] === 0) {
        $dir = 'uploads/';
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
        $nomeBase = $dir . 'doc_' . uniqid();
        $nomeFinal = $nomeBase . '.' . $ext;
        
        if (function_exists('mime_content_type') && function_exists('imagewebp')) {
            $mime = @mime_content_type($fileArray['tmp_name']);
            $img = false;
            
            if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                $img = @imagecreatefromjpeg($fileArray['tmp_name']);
            } elseif ($mime == 'image/png') {
                $img = @imagecreatefrompng($fileArray['tmp_name']);
                if ($img !== false) {
                    imagepalettetotruecolor($img);
                    imagealphablending($img, true); 
                    imagesavealpha($img, true);
                }
            }
            
            if ($img !== false) {
                $nomeWebp = $nomeBase . '.webp';
                if (imagewebp($img, $nomeWebp, 70)) {
                    imagedestroy($img);
                    return $nomeWebp; 
                }
                imagedestroy($img);
            }
        }
        
        if (move_uploaded_file($fileArray['tmp_name'], $nomeFinal)) {
            return $nomeFinal;
        }
    }
    return '';
}

// Helper para uploads de múltiplos ficheiros
function reArrayFiles(&$file_post) {
    $file_ary = array();
    if (!isset($file_post['name']) || !is_array($file_post['name'])) return $file_ary;
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);
    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $file_ary[$i][$key] = $file_post[$key][$i];
        }
    }
    return $file_ary;
}

// Helper para Gerar Texto de Auditoria (Quem criou e quem editou)
function getTooltipAuditoria($item) {
    $criado = isset($item['criado_por']) && !empty($item['criado_por']) ? $item['criado_por'] : 'Sistema/Desconhecido';
    $edit = isset($item['editado_por']) && !empty($item['editado_por']) ? ' | Editado por: ' . $item['editado_por'] : '';
    return htmlspecialchars("Adicionado por: $criado" . $edit);
}

// ==========================================
// 2. LÓGICA DE AUTENTICAÇÃO E LOGIN
// ==========================================
$usuarios = lerDados($arquivos['usuarios']);
if (empty($usuarios)) {
    $usuarios[] = array('id' => uniqid(), 'username' => 'admin', 'password' => password_hash('admin', PASSWORD_DEFAULT), 'role' => 'admin');
    salvarDados($arquivos['usuarios'], $usuarios);
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username']); $password = $_POST['password']; $user_found = null;
    foreach ($usuarios as $u) { if ($u['username'] === $username) { $user_found = $u; break; } }
    
    if ($user_found && password_verify($password, $user_found['password'])) {
        $_SESSION['user_id'] = $user_found['id']; $_SESSION['username'] = $user_found['username']; $_SESSION['user_role'] = $user_found['role'];
        header("Location: index.php"); exit;
    } else { $login_error = 'Utilizador ou senha inválidos.'; }
}

if (!isset($_SESSION['user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - MLS Frotas</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <style>
            body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; }
            .login-card { width: 100%; max-width: 400px; padding: 30px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); background: #fff; }
            .login-card .brand { color: #0b5ed7; text-align: center; margin-bottom: 30px; }
            body.dark-mode { background-color: #121212; color: #e0e0e0; }
            body.dark-mode .login-card { background-color: #1e1e1e; border: 1px solid #333; }
        </style>
    </head>
    <body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">
        <div class="login-card"><h2 class="brand"><i class="bi bi-car-front-fill"></i> MLS Frotas</h2>
            <?php if($login_error): ?><div class="alert alert-danger py-2 text-center"><small><?php echo $login_error; ?></small></div><?php endif; ?>
            <form method="POST"><div class="mb-3"><label>Utilizador</label><input type="text" name="username" class="form-control" required autofocus></div><div class="mb-4"><label>Senha</label><input type="password" name="password" class="form-control" required></div><button type="submit" name="login_submit" class="btn btn-primary w-100 py-2 fw-bold">Entrar</button></form>
        </div>
    </body></html>
    <?php exit;
}

$isAdmin = ($_SESSION['user_role'] === 'admin');

// ==========================================
// 3. CARREGAMENTO DOS RESTANTES DADOS
// ==========================================
$veiculos = lerDados($arquivos['veiculos']);
$abastecimentos = lerDados($arquivos['abastecimentos']);
$utilizacao = lerDados($arquivos['utilizacao']);
$tecnicos = lerDados($arquivos['tecnicos']);
$cartoes = lerDados($arquivos['cartoes']);
$lavagens = lerDados($arquivos['lavagens']);

usort($tecnicos, function($a, $b) { return strcasecmp(isset($a['nome']) ? $a['nome'] : '', isset($b['nome']) ? $b['nome'] : ''); });

if(empty($cartoes)) {
    $cartoes = [['id' => uniqid(), 'nome' => 'Dinheiro'], ['id' => uniqid(), 'nome' => 'Cartão Combustível']];
    salvarDados($arquivos['cartoes'], $cartoes);
}

// ==========================================
// 4. LÓGICA DE PROCESSAMENTO (POST & GET)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    
    if ($acao === 'mudar_senha') {
        $senha_atual = $_POST['senha_atual']; $nova_senha = $_POST['nova_senha']; $confirma_senha = $_POST['confirma_senha']; $tab = 'perfil';
        if ($nova_senha !== $confirma_senha) { $_SESSION['msg_erro'] = "As senhas não coincidem!"; } else {
            foreach ($usuarios as $k => $u) {
                if ($u['id'] === $_SESSION['user_id']) {
                    if (password_verify($senha_atual, $u['password'])) {
                        $usuarios[$k]['password'] = password_hash($nova_senha, PASSWORD_DEFAULT);
                        salvarDados($arquivos['usuarios'], $usuarios); $_SESSION['msg'] = "Senha atualizada!";
                    } else { $_SESSION['msg_erro'] = "A senha atual está incorreta!"; } break;
                }
            }
        }
        header('Location: ?tab=' . $tab); exit;
    }
    
    if ($isAdmin) {
        if ($acao === 'salvar_usuario') {
            $existe = false; foreach ($usuarios as $u) { if (strtolower($u['username']) === strtolower(trim($_POST['username']))) { $existe = true; break; } }
            if ($existe) { $_SESSION['msg_erro'] = "Este nome de utilizador já existe!"; } else {
                $usuarios[] = array('id' => uniqid(), 'username' => trim($_POST['username']), 'password' => password_hash($_POST['password'], PASSWORD_DEFAULT), 'role' => $_POST['role']);
                salvarDados($arquivos['usuarios'], $usuarios); $_SESSION['msg'] = "Utilizador criado!";
            } $tab = 'usuarios';
        }
        elseif ($acao === 'salvar_tecnico') {
            $id = !empty($_POST['tecnico_id']) ? $_POST['tecnico_id'] : uniqid();
            
            $anexoAtual = '';
            $criado_por = $_SESSION['username'];
            $editado_por = '';
            
            if (!empty($_POST['tecnico_id'])) { foreach ($tecnicos as $t) { if ($t['id'] === $id) { $anexoAtual = isset($t['anexo']) ? $t['anexo'] : ''; $criado_por = isset($t['criado_por']) ? $t['criado_por'] : 'Desconhecido'; $editado_por = $_SESSION['username']; break; } } }
            if (isset($_POST['remover_anexo']) && $_POST['remover_anexo'] == '1') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = ''; }
            $novoAnexo = fazerUpload($_FILES['anexo']);
            if ($novoAnexo !== '') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = $novoAnexo; }

            $novo = array('id' => $id, 'nome' => trim($_POST['nome']), 'anexo' => $anexoAtual, 'criado_por' => $criado_por, 'editado_por' => $editado_por);
            if (!empty($_POST['tecnico_id'])) { foreach ($tecnicos as $k => $v) { if ($v['id'] === $id) { $tecnicos[$k] = $novo; break; } } $_SESSION['msg'] = "Técnico atualizado!"; } 
            else { $tecnicos[] = $novo; $_SESSION['msg'] = "Técnico registado!"; }
            salvarDados($arquivos['tecnicos'], $tecnicos); $tab = 'tecnicos';
        }
        elseif ($acao === 'salvar_cartao') {
            $id = !empty($_POST['cartao_id']) ? $_POST['cartao_id'] : uniqid();
            
            $anexoAtual = '';
            $criado_por = $_SESSION['username'];
            $editado_por = '';
            
            if (!empty($_POST['cartao_id'])) { foreach ($cartoes as $c) { if ($c['id'] === $id) { $anexoAtual = isset($c['anexo']) ? $c['anexo'] : ''; $criado_por = isset($c['criado_por']) ? $c['criado_por'] : 'Desconhecido'; $editado_por = $_SESSION['username']; break; } } }
            if (isset($_POST['remover_anexo']) && $_POST['remover_anexo'] == '1') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = ''; }
            $novoAnexo = fazerUpload($_FILES['anexo']);
            if ($novoAnexo !== '') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = $novoAnexo; }

            $novo = array(
                'id' => $id, 
                'nome' => trim($_POST['nome']),
                'numero_cartao' => trim($_POST['numero_cartao']),
                'senha' => trim($_POST['senha']),
                'anexo' => $anexoAtual,
                'criado_por' => $criado_por,
                'editado_por' => $editado_por
            );
            
            if (!empty($_POST['cartao_id'])) { foreach ($cartoes as $k => $v) { if ($v['id'] === $id) { $cartoes[$k] = $novo; break; } } $_SESSION['msg'] = "Cartão atualizado!"; } 
            else { $cartoes[] = $novo; $_SESSION['msg'] = "Cartão registado!"; }
            salvarDados($arquivos['cartoes'], $cartoes); $tab = 'cartoes';
        }
        elseif ($acao === 'salvar_veiculo') {
            $id = !empty($_POST['veiculo_id']) ? $_POST['veiculo_id'] : uniqid();
            
            $km_inicial = (int)$_POST['km_inicial'];
            $km_revisao = !empty($_POST['km_revisao']) ? (int)$_POST['km_revisao'] : $km_inicial;
            $data_revisao = !empty($_POST['data_revisao']) ? $_POST['data_revisao'] : date('Y-m-d');
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            $anexosAtuais = array();
            $criado_por = $_SESSION['username'];
            $editado_por = '';
            
            if (!empty($_POST['veiculo_id'])) { foreach ($veiculos as $v) { if ($v['id'] === $id) { $anexosAtuais = isset($v['anexos']) && is_array($v['anexos']) ? $v['anexos'] : array(); $criado_por = isset($v['criado_por']) ? $v['criado_por'] : 'Desconhecido'; $editado_por = $_SESSION['username']; break; } } }
            if (isset($_POST['remover_anexos_veiculo']) && is_array($_POST['remover_anexos_veiculo'])) {
                foreach ($_POST['remover_anexos_veiculo'] as $anexoRemover) { if (file_exists($anexoRemover)) { unlink($anexoRemover); } $anexosAtuais = array_diff($anexosAtuais, array($anexoRemover)); }
                $anexosAtuais = array_values($anexosAtuais); 
            }
            if (isset($_FILES['anexos_novos']) && !empty($_FILES['anexos_novos']['name'][0])) {
                $file_ary = reArrayFiles($_FILES['anexos_novos']); foreach ($file_ary as $file) { $novoAnexo = fazerUpload($file); if ($novoAnexo !== '') { $anexosAtuais[] = $novoAnexo; } }
            }

            $novo = array(
                'id' => $id, 'placa' => strtoupper(trim($_POST['placa'])), 'modelo' => trim($_POST['modelo']), 'tecnico' => trim($_POST['tecnico']),
                'km_inicial' => $km_inicial, 'km_revisao' => $km_revisao, 'data_revisao' => $data_revisao,
                'data_ipva' => $_POST['data_ipva'], 'data_seguro' => $_POST['data_seguro'], 'ativo' => $ativo, 'anexos' => $anexosAtuais,
                'criado_por' => $criado_por, 'editado_por' => $editado_por
            );
            if (!empty($_POST['veiculo_id'])) { foreach ($veiculos as $k => $v) { if ($v['id'] === $id) { $veiculos[$k] = $novo; break; } } $_SESSION['msg'] = "Veículo atualizado!"; } 
            else { $veiculos[] = $novo; $_SESSION['msg'] = "Veículo registado!"; }
            salvarDados($arquivos['veiculos'], $veiculos); $tab = 'veiculos';
        } 
        elseif ($acao === 'salvar_abastecimento') {
            $id = !empty($_POST['abastecimento_id']) ? $_POST['abastecimento_id'] : uniqid();
            
            $anexoAtual = '';
            $criado_por = $_SESSION['username'];
            $editado_por = '';
            
            if (!empty($_POST['abastecimento_id'])) { foreach ($abastecimentos as $a) { if ($a['id'] === $id) { $anexoAtual = isset($a['anexo']) ? $a['anexo'] : ''; $criado_por = isset($a['criado_por']) ? $a['criado_por'] : 'Desconhecido'; $editado_por = $_SESSION['username']; break; } } }
            if (isset($_POST['remover_anexo']) && $_POST['remover_anexo'] == '1') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = ''; }
            $novoAnexo = fazerUpload($_FILES['anexo']);
            if ($novoAnexo !== '') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = $novoAnexo; }

            $novo = array(
                'id' => $id, 'data' => $_POST['data'], 'condutor' => trim($_POST['condutor']), 'placa' => $_POST['placa'],
                'km' => (int)$_POST['km'], 'litros' => (float)str_replace(',', '.', $_POST['litros']),
                'valor' => (float)str_replace(',', '.', $_POST['valor']), 'cartao' => $_POST['cartao'], 'anexo' => $anexoAtual,
                'criado_por' => $criado_por, 'editado_por' => $editado_por
            );
            if (!empty($_POST['abastecimento_id'])) { foreach ($abastecimentos as $k => $v) { if ($v['id'] === $id) { $abastecimentos[$k] = $novo; break; } } $_SESSION['msg'] = "Abastecimento atualizado!"; } 
            else { $abastecimentos[] = $novo; $_SESSION['msg'] = "Abastecimento registado!"; }
            salvarDados($arquivos['abastecimentos'], $abastecimentos); $tab = 'abastecimentos';
        }
        elseif ($acao === 'salvar_lavagem') {
            $id = !empty($_POST['lavagem_id']) ? $_POST['lavagem_id'] : uniqid();
            
            $anexoAtual = '';
            $criado_por = $_SESSION['username'];
            $editado_por = '';
            
            if (!empty($_POST['lavagem_id'])) { foreach ($lavagens as $l) { if ($l['id'] === $id) { $anexoAtual = isset($l['anexo']) ? $l['anexo'] : ''; $criado_por = isset($l['criado_por']) ? $l['criado_por'] : 'Desconhecido'; $editado_por = $_SESSION['username']; break; } } }
            if (isset($_POST['remover_anexo']) && $_POST['remover_anexo'] == '1') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = ''; }
            $novoAnexo = fazerUpload($_FILES['anexo']);
            if ($novoAnexo !== '') { if ($anexoAtual && file_exists($anexoAtual)) { unlink($anexoAtual); } $anexoAtual = $novoAnexo; }

            // Lavagem registra o gasto e a quilometragem, mas não possui litros.
            // Por isso, nunca entra nos cálculos de consumo (KM/L).
            $novo = array(
                'id' => $id, 'data' => $_POST['data'], 'condutor' => trim($_POST['condutor']), 'placa' => $_POST['placa'],
                'km' => (int)$_POST['km'], 'valor' => (float)str_replace(',', '.', $_POST['valor']),
                'cartao' => $_POST['cartao'], 'anexo' => $anexoAtual,
                'criado_por' => $criado_por, 'editado_por' => $editado_por
            );
            if (!empty($_POST['lavagem_id'])) { foreach ($lavagens as $k => $v) { if ($v['id'] === $id) { $lavagens[$k] = $novo; break; } } $_SESSION['msg'] = "Lavagem atualizada!"; } 
            else { $lavagens[] = $novo; $_SESSION['msg'] = "Lavagem registada!"; }
            salvarDados($arquivos['lavagens'], $lavagens); $tab = 'lavagens';
        }
        elseif ($acao === 'salvar_utilizacao') {
            $id = !empty($_POST['utilizacao_id']) ? $_POST['utilizacao_id'] : uniqid();
            
            $criado_por = $_SESSION['username'];
            $editado_por = '';
            
            if (!empty($_POST['utilizacao_id'])) { foreach ($utilizacao as $u) { if ($u['id'] === $id) { $criado_por = isset($u['criado_por']) ? $u['criado_por'] : 'Desconhecido'; $editado_por = $_SESSION['username']; break; } } }
            
            $novo = array(
                'id' => $id, 'data' => $_POST['data'], 'condutor' => trim($_POST['condutor']), 'placa' => $_POST['placa'],
                'rota' => trim($_POST['rota']), 'km_inicial' => (int)$_POST['km_inicial'], 'km_final' => (int)$_POST['km_final'],
                'criado_por' => $criado_por, 'editado_por' => $editado_por
            );
            if (!empty($_POST['utilizacao_id'])) { foreach ($utilizacao as $k => $v) { if ($v['id'] === $id) { $utilizacao[$k] = $novo; break; } } $_SESSION['msg'] = "Rota atualizada!"; } 
            else { $utilizacao[] = $novo; $_SESSION['msg'] = "Rota registada!"; }
            salvarDados($arquivos['utilizacao'], $utilizacao); $tab = 'utilizacao';
        }
        elseif ($acao === 'importar_csv') {
            if (isset($_FILES['arquivo_csv']) && $_FILES['arquivo_csv']['error'] == 0) {
                $tmpName = $_FILES['arquivo_csv']['tmp_name'];
                $handle = fopen($tmpName, "r");
                if ($handle !== FALSE) {
                    $primeiraLinha = fgets($handle); $delimitador = strpos($primeiraLinha, ';') !== false ? ';' : ','; rewind($handle);
                    $ultima_km = array();
                    foreach ($veiculos as $v) { $ultima_km[$v['placa']] = isset($v['km_inicial']) && $v['km_inicial'] > 0 ? $v['km_inicial'] : $v['km_revisao']; }
                    foreach ($utilizacao as $u) { if (!isset($ultima_km[$u['placa']]) || $u['km_final'] > $ultima_km[$u['placa']]) { $ultima_km[$u['placa']] = $u['km_final']; } }
                    foreach ($abastecimentos as $a) { if (!isset($ultima_km[$a['placa']]) || $a['km'] > $ultima_km[$a['placa']]) { $ultima_km[$a['placa']] = $a['km']; } }

                    while (($dados_csv = fgetcsv($handle, 1000, $delimitador)) !== FALSE) {
                        if (count($dados_csv) < 7 || trim($dados_csv[0]) == '' || strtolower(trim($dados_csv[0])) == 'data') { continue; }
                        $d = explode('/', trim($dados_csv[0])); $data_formatada = (count($d) == 3) ? $d[2].'-'.$d[1].'-'.$d[0] : trim($dados_csv[0]);
                        $condutor = trim($dados_csv[1]); $rota = trim($dados_csv[2]); $placa = strtoupper(str_replace(' ', '', trim($dados_csv[3])));
                        $valor_str = trim($dados_csv[4]); $cartao = trim($dados_csv[5]); $km = (int)preg_replace('/[^0-9]/', '', $dados_csv[6]);

                        if (stripos($rota, 'abastecimento') !== false || $valor_str !== '') {
                            $valor = str_replace(['R$', '.', ' '], ['', '', ''], $valor_str); $valor = (float)str_replace(',', '.', $valor);
                            $abastecimentos[] = array('id' => uniqid(), 'data' => $data_formatada, 'condutor' => $condutor, 'placa' => $placa, 'km' => $km, 'litros' => 0, 'valor' => $valor, 'cartao' => $cartao, 'anexo' => '', 'criado_por' => $_SESSION['username'], 'editado_por' => '');
                            $ultima_km[$placa] = $km; 
                        } else {
                            $km_inicial = isset($ultima_km[$placa]) ? $ultima_km[$placa] : $km;
                            $utilizacao[] = array('id' => uniqid(), 'data' => $data_formatada, 'condutor' => $condutor, 'placa' => $placa, 'rota' => $rota, 'km_inicial' => $km_inicial, 'km_final' => $km, 'criado_por' => $_SESSION['username'], 'editado_por' => '');
                            $ultima_km[$placa] = $km; 
                        }
                    }
                    fclose($handle); salvarDados($arquivos['abastecimentos'], $abastecimentos); salvarDados($arquivos['utilizacao'], $utilizacao); $_SESSION['msg'] = "Planilha importada com sucesso!";
                } else { $_SESSION['msg_erro'] = "Erro ao ler ficheiro CSV."; }
            } else { $_SESSION['msg_erro'] = "Por favor, envie um ficheiro válido."; }
            $tab = 'importar';
        }
        header('Location: ?tab=' . $tab); exit;
    } else {
        $_SESSION['msg_erro'] = "Acesso Negado."; header('Location: index.php'); exit;
    }
}

// ==========================================
// PROCESSAMENTO GET (EXCLUIR / STATUS / BACKUP)
// ==========================================
if (isset($_GET['excluir']) && isset($_GET['tipo']) && $isAdmin) {
    $id = $_GET['excluir']; $tipo = $_GET['tipo']; $tab = '';
    if ($tipo === 'usuario') {
        $is_superadmin = false; foreach ($usuarios as $u) { if ($u['id'] === $id && $u['username'] === 'admin') { $is_superadmin = true; break; } }
        if ($id === $_SESSION['user_id']) { $_SESSION['msg_erro'] = "Não pode excluir a si mesmo!"; }
        elseif ($is_superadmin) { $_SESSION['msg_erro'] = "Ação bloqueada: O utilizador 'admin' não pode ser excluído!"; }
        else { foreach ($usuarios as $k => $v) { if ($v['id'] === $id) unset($usuarios[$k]); } salvarDados($arquivos['usuarios'], $usuarios); $_SESSION['msg'] = "Utilizador excluído!"; }
        $tab = 'usuarios';
    } elseif ($tipo === 'tecnico') {
        foreach ($tecnicos as $k => $v) { if ($v['id'] === $id) { if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); } unset($tecnicos[$k]); } } salvarDados($arquivos['tecnicos'], $tecnicos); $_SESSION['msg'] = "Registo excluído!"; $tab = 'tecnicos';
    } elseif ($tipo === 'cartao') {
        foreach ($cartoes as $k => $v) { if ($v['id'] === $id) { if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); } unset($cartoes[$k]); } } salvarDados($arquivos['cartoes'], $cartoes); $_SESSION['msg'] = "Cartão excluído!"; $tab = 'cartoes';
    } elseif ($tipo === 'veiculo') {
        foreach ($veiculos as $k => $v) { if ($v['id'] === $id) { if(!empty($v['anexos']) && is_array($v['anexos'])) { foreach($v['anexos'] as $anx) { if(file_exists($anx)) unlink($anx); } } unset($veiculos[$k]); } } salvarDados($arquivos['veiculos'], $veiculos); $_SESSION['msg'] = "Registo excluído!"; $tab = 'veiculos';
    } elseif ($tipo === 'abastecimento') {
        foreach ($abastecimentos as $k => $v) { if ($v['id'] === $id) { if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); } unset($abastecimentos[$k]); } } salvarDados($arquivos['abastecimentos'], $abastecimentos); $_SESSION['msg'] = "Registo excluído!"; $tab = 'abastecimentos';
    } elseif ($tipo === 'lavagem') {
        foreach ($lavagens as $k => $v) { if ($v['id'] === $id) { if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); } unset($lavagens[$k]); } } salvarDados($arquivos['lavagens'], $lavagens); $_SESSION['msg'] = "Lavagem excluída!"; $tab = 'lavagens';
    } elseif ($tipo === 'utilizacao') {
        foreach ($utilizacao as $k => $v) { if ($v['id'] === $id) unset($utilizacao[$k]); } salvarDados($arquivos['utilizacao'], $utilizacao); $_SESSION['msg'] = "Registo excluído!"; $tab = 'utilizacao';
    }
    header('Location: ?tab=' . $tab); exit;
}

if (isset($_GET['excluir_anexo']) && $isAdmin) {
    $id = $_GET['excluir_anexo'];
    foreach ($abastecimentos as $k => $v) {
        if ($v['id'] === $id) {
            if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); }
            $abastecimentos[$k]['anexo'] = ''; salvarDados($arquivos['abastecimentos'], $abastecimentos); $_SESSION['msg'] = "Anexo removido com sucesso!"; break;
        }
    } header('Location: ?tab=abastecimentos'); exit;
}

if (isset($_GET['excluir_anexo_lavagem']) && $isAdmin) {
    $id = $_GET['excluir_anexo_lavagem'];
    foreach ($lavagens as $k => $v) {
        if ($v['id'] === $id) {
            if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); }
            $lavagens[$k]['anexo'] = ''; salvarDados($arquivos['lavagens'], $lavagens); $_SESSION['msg'] = "Anexo removido com sucesso!"; break;
        }
    } header('Location: ?tab=lavagens'); exit;
}

if (isset($_GET['excluir_anexo_cartao']) && $isAdmin) {
    $id = $_GET['excluir_anexo_cartao'];
    foreach ($cartoes as $k => $v) {
        if ($v['id'] === $id) {
            if(!empty($v['anexo']) && file_exists($v['anexo'])) { unlink($v['anexo']); }
            $cartoes[$k]['anexo'] = ''; salvarDados($arquivos['cartoes'], $cartoes); $_SESSION['msg'] = "Anexo removido com sucesso!"; break;
        }
    } header('Location: ?tab=cartoes'); exit;
}

if (isset($_GET['toggle_ativo_veiculo']) && $isAdmin) {
    $id = $_GET['toggle_ativo_veiculo'];
    foreach ($veiculos as $k => $v) {
        if ($v['id'] === $id) {
            $veiculos[$k]['ativo'] = (!isset($v['ativo']) || $v['ativo'] == 1) ? 0 : 1;
            salvarDados($arquivos['veiculos'], $veiculos); $_SESSION['msg'] = "Status do veículo atualizado com sucesso!"; break;
        }
    } header('Location: ?tab=veiculos'); exit;
}

if (isset($_GET['backup_db']) && $isAdmin) {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $filename = "backup_mls_frotas_" . date('Ymd_His') . ".zip";
        if ($zip->open($filename, ZipArchive::CREATE) === TRUE) {
            foreach ($arquivos as $key => $file) {
                if (file_exists($file)) {
                    $zip->addFile($file, $file);
                }
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filename));
            readfile($filename);
            unlink($filename); // Apaga o ficheiro temporário após o download
            exit;
        } else {
            $_SESSION['msg_erro'] = "Erro ao criar o ficheiro ZIP de backup.";
        }
    } else {
        $_SESSION['msg_erro'] = "A extensão ZipArchive não está ativa no seu servidor PHP.";
    }
    header('Location: ?tab=importar'); exit;
}

// ==========================================
// 5. LÓGICA DE FILTROS E ORDENAÇÃO
// ==========================================
$tab_ativa = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

if (isset($_GET['clear_filters'])) {
    setcookie('filtro_data_inicio', '', time() - 3600, "/"); setcookie('filtro_data_fim', '', time() - 3600, "/"); setcookie('filtro_placa', '', time() - 3600, "/");
    header("Location: ?tab=" . $tab_ativa); exit;
}

if (isset($_GET['data_inicio'])) { $data_inicio = $_GET['data_inicio']; setcookie('filtro_data_inicio', $data_inicio, time() + (86400 * 30), "/"); } 
else { $data_inicio = isset($_COOKIE['filtro_data_inicio']) ? $_COOKIE['filtro_data_inicio'] : date('Y-m-01', strtotime('-3 months')); }

if (isset($_GET['data_fim'])) { $data_fim = $_GET['data_fim']; setcookie('filtro_data_fim', $data_fim, time() + (86400 * 30), "/"); } 
else { $data_fim = isset($_COOKIE['filtro_data_fim']) ? $_COOKIE['filtro_data_fim'] : date('Y-m-t'); }

if (isset($_GET['filtro_placa'])) { $filtro_placa = $_GET['filtro_placa']; setcookie('filtro_placa', $filtro_placa, time() + (86400 * 30), "/"); } 
else { $filtro_placa = isset($_COOKIE['filtro_placa']) ? $_COOKIE['filtro_placa'] : ''; }

$veiculo_edit = null; $abastecimento_edit = null; $lavagem_edit = null; $utilizacao_edit = null; $cartao_edit = null; $tecnico_edit = null;
if ($isAdmin) {
    if (isset($_GET['edit_veiculo'])) { foreach ($veiculos as $v) { if ($v['id'] === $_GET['edit_veiculo']) { $veiculo_edit = $v; $tab_ativa = 'veiculos'; break; } } }
    if (isset($_GET['edit_abastecimento'])) { foreach ($abastecimentos as $a) { if ($a['id'] === $_GET['edit_abastecimento']) { $abastecimento_edit = $a; $tab_ativa = 'abastecimentos'; break; } } }
    if (isset($_GET['edit_lavagem'])) { foreach ($lavagens as $l) { if ($l['id'] === $_GET['edit_lavagem']) { $lavagem_edit = $l; $tab_ativa = 'lavagens'; break; } } }
    if (isset($_GET['edit_utilizacao'])) { foreach ($utilizacao as $u) { if ($u['id'] === $_GET['edit_utilizacao']) { $utilizacao_edit = $u; $tab_ativa = 'utilizacao'; break; } } }
    if (isset($_GET['edit_cartao'])) { foreach ($cartoes as $c) { if ($c['id'] === $_GET['edit_cartao']) { $cartao_edit = $c; $tab_ativa = 'cartoes'; break; } } }
    if (isset($_GET['edit_tecnico'])) { foreach ($tecnicos as $t) { if ($t['id'] === $_GET['edit_tecnico']) { $tecnico_edit = $t; $tab_ativa = 'tecnicos'; break; } } }
}

usort($abastecimentos, function($a, $b) { return strcmp($a['data'], $b['data']); });
foreach ($abastecimentos as $idx => $abs) {
    $abastecimentos[$idx]['kml_calc'] = '-';
    if (isset($abs['litros']) && $abs['litros'] > 0) {
        $km_ant = 0;
        for ($i = $idx - 1; $i >= 0; $i--) { if ($abastecimentos[$i]['placa'] === $abs['placa']) { $km_ant = $abastecimentos[$i]['km']; break; } }
        if ($km_ant > 0 && $abs['km'] > $km_ant) { $abastecimentos[$idx]['kml_calc'] = number_format(($abs['km'] - $km_ant) / $abs['litros'], 1, ',', '') . ' km/l'; }
    }
}

function aplicarFiltros($dados, $dt_ini, $dt_fim, $placa) {
    $resultado = array();
    foreach ($dados as $item) {
        $passou_dt_ini = ($dt_ini === '' || $item['data'] >= $dt_ini);
        $passou_dt_fim = ($dt_fim === '' || $item['data'] <= $dt_fim);
        $passou_placa = ($placa === '' || $item['placa'] === $placa);
        if ($passou_dt_ini && $passou_dt_fim && $passou_placa) { $resultado[] = $item; }
    }
    return $resultado;
}
$abastecimentos_filtrados = aplicarFiltros($abastecimentos, $data_inicio, $data_fim, $filtro_placa);
$lavagens_filtradas = aplicarFiltros($lavagens, $data_inicio, $data_fim, $filtro_placa);
$utilizacao_filtrada = aplicarFiltros($utilizacao, $data_inicio, $data_fim, $filtro_placa);

$sort_col = isset($_GET['sort_col']) ? $_GET['sort_col'] : (isset($_COOKIE['sort_col']) ? $_COOKIE['sort_col'] : 'data');
$sort_dir = isset($_GET['sort_dir']) ? $_GET['sort_dir'] : (isset($_COOKIE['sort_dir']) ? $_COOKIE['sort_dir'] : 'desc');
if (isset($_GET['sort_col'])) { setcookie('sort_col', $sort_col, time() + (86400 * 30), "/"); setcookie('sort_dir', $sort_dir, time() + (86400 * 30), "/"); }

function urlOrdenacao($coluna, $colAtual, $dirAtual, $tab = 'utilizacao') {
    $params = $_GET; $params['tab'] = $tab; $params['sort_col'] = $coluna;
    $params['sort_dir'] = ($colAtual === $coluna && $dirAtual === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

$funcaoOrdenacao = function($a, $b) use ($sort_col, $sort_dir) {
    $valA = isset($a[$sort_col]) ? $a[$sort_col] : ''; $valB = isset($b[$sort_col]) ? $b[$sort_col] : '';
    if (is_numeric($valA) && is_numeric($valB)) { $cmp = ($valA == $valB) ? 0 : (($valA < $valB) ? -1 : 1); } 
    else { $cmp = strcasecmp($valA, $valB); }
    return ($sort_dir === 'asc') ? $cmp : -$cmp;
};

usort($utilizacao_filtrada, $funcaoOrdenacao);
usort($abastecimentos_filtrados, $funcaoOrdenacao);
usort($lavagens_filtradas, $funcaoOrdenacao);

// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv' && in_array($tab_ativa, array('utilizacao', 'abastecimentos', 'lavagens'))) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=relatorio_' . $tab_ativa . '_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w'); fputs($output, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
    if ($tab_ativa === 'abastecimentos') {
        fputcsv($output, array('Data', 'Condutor', 'Placa', 'KM', 'Litros', 'Valor (R$)', 'Pagamento', 'Num Cartao'), ';');
        foreach ($abastecimentos_filtrados as $linha) {
            $litros = isset($linha['litros']) ? number_format($linha['litros'], 2, ',', '') : '0,00';
            
            $num_c = ''; foreach($cartoes as $c) { if($c['nome'] === $linha['cartao']) { $num_c = isset($c['numero_cartao']) ? $c['numero_cartao'] : ''; break; } }
            fputcsv($output, array(date('d/m/Y', strtotime($linha['data'])), $linha['condutor'], $linha['placa'], $linha['km'], $litros, number_format($linha['valor'], 2, ',', ''), $linha['cartao'], $num_c), ';');
        }
    } elseif ($tab_ativa === 'lavagens') {
        fputcsv($output, array('Data', 'Condutor', 'Placa', 'KM', 'Valor (R$)', 'Pagamento', 'Num Cartao'), ';');
        foreach ($lavagens_filtradas as $linha) {
            $num_c = ''; foreach($cartoes as $c) { if($c['nome'] === $linha['cartao']) { $num_c = isset($c['numero_cartao']) ? $c['numero_cartao'] : ''; break; } }
            fputcsv($output, array(date('d/m/Y', strtotime($linha['data'])), $linha['condutor'], $linha['placa'], $linha['km'], number_format($linha['valor'], 2, ',', ''), $linha['cartao'], $num_c), ';');
        }
    } elseif ($tab_ativa === 'utilizacao') {
        fputcsv($output, array('Data', 'Condutor', 'Rota', 'Placa', 'KM Inicial', 'KM Final', 'Total KM'), ';');
        foreach ($utilizacao_filtrada as $linha) {
            fputcsv($output, array(date('d/m/Y', strtotime($linha['data'])), $linha['condutor'], $linha['rota'], $linha['placa'], $linha['km_inicial'], $linha['km_final'], ($linha['km_final'] - $linha['km_inicial'])), ';');
        }
    } fclose($output); exit;
}

$total_gasto = 0; $total_abastecimento = 0; $total_lavagens = 0;
$qtd_abastecimentos = count($abastecimentos_filtrados); $qtd_lavagens = count($lavagens_filtradas);
$gastos_por_mes = array(); $consumo_por_veiculo = array();
$relatorio_frota = array();

$gastos_individuais_labels = array(); $gastos_individuais_data = array();
$abs_para_grafico = $abastecimentos_filtrados;
usort($abs_para_grafico, function($a, $b) { return strcmp($a['data'], $b['data']); });

// Combustível entra em gastos e também no cálculo de consumo.
foreach ($abs_para_grafico as $abs) { 
    $total_gasto += $abs['valor'];
    $total_abastecimento += $abs['valor'];
    $mes = date('Y-m', strtotime($abs['data']));
    if(!isset($gastos_por_mes[$mes])) $gastos_por_mes[$mes] = 0;
    $gastos_por_mes[$mes] += $abs['valor'];
    
    $p = strtoupper(trim($abs['placa']));
    if(!isset($consumo_por_veiculo[$p])) { $consumo_por_veiculo[$p] = array('litros' => 0, 'kms' => array()); }
    if(isset($abs['litros'])) { $consumo_por_veiculo[$p]['litros'] += (float)$abs['litros']; }
    if(isset($abs['km']) && $abs['km'] > 0) { $consumo_por_veiculo[$p]['kms'][] = (int)$abs['km']; }
    
    if(!isset($relatorio_frota[$p])) { $relatorio_frota[$p] = array('gasto' => 0, 'km_rodado' => 0, 'abs_count' => 0, 'lav_count' => 0); }
    $relatorio_frota[$p]['gasto'] += $abs['valor'];
    $relatorio_frota[$p]['abs_count']++;
    
    $gastos_individuais_labels[] = date('d/m/y', strtotime($abs['data'])) . ' (' . $p . ') - Abast.';
    $gastos_individuais_data[] = $abs['valor'];
}

// Lavagens entram exclusivamente nos gastos e nunca recebem litros/KM-L.
$lavagens_para_grafico = $lavagens_filtradas;
usort($lavagens_para_grafico, function($a, $b) { return strcmp($a['data'], $b['data']); });
foreach ($lavagens_para_grafico as $lavagem) {
    $total_gasto += $lavagem['valor'];
    $total_lavagens += $lavagem['valor'];
    $mes = date('Y-m', strtotime($lavagem['data']));
    if(!isset($gastos_por_mes[$mes])) $gastos_por_mes[$mes] = 0;
    $gastos_por_mes[$mes] += $lavagem['valor'];

    $p = strtoupper(trim($lavagem['placa']));
    if(!isset($relatorio_frota[$p])) { $relatorio_frota[$p] = array('gasto' => 0, 'km_rodado' => 0, 'abs_count' => 0, 'lav_count' => 0); }
    $relatorio_frota[$p]['gasto'] += $lavagem['valor'];
    $relatorio_frota[$p]['lav_count']++;

    $gastos_individuais_labels[] = date('d/m/y', strtotime($lavagem['data'])) . ' (' . $p . ') - Lavagem';
    $gastos_individuais_data[] = $lavagem['valor'];
}
ksort($gastos_por_mes);

$km_rodado_total = 0;
foreach ($utilizacao_filtrada as $uso) { 
    if ($uso['km_final'] > $uso['km_inicial']) { 
        $dist = $uso['km_final'] - $uso['km_inicial'];
        $km_rodado_total += $dist; 
        
        $p = strtoupper(trim($uso['placa']));
        if(!isset($relatorio_frota[$p])) { $relatorio_frota[$p] = array('gasto' => 0, 'km_rodado' => 0, 'abs_count' => 0, 'lav_count' => 0); }
        $relatorio_frota[$p]['km_rodado'] += $dist;
    } 
}

$km_atual_veiculos = array();
foreach ($veiculos as $v) { $p = strtoupper(trim($v['placa'])); $km_atual_veiculos[$p] = isset($v['km_inicial']) && $v['km_inicial'] > 0 ? (int)$v['km_inicial'] : (int)$v['km_revisao']; }
foreach ($abastecimentos as $abs) { $p = strtoupper(trim($abs['placa'])); if (!isset($km_atual_veiculos[$p])) $km_atual_veiculos[$p] = 0; if ((int)$abs['km'] > $km_atual_veiculos[$p]) { $km_atual_veiculos[$p] = (int)$abs['km']; } }
// A quilometragem da lavagem atualiza o painel/manutenção, mas não é usada no KM/L.
foreach ($lavagens as $lavagem) { $p = strtoupper(trim($lavagem['placa'])); if (!isset($km_atual_veiculos[$p])) $km_atual_veiculos[$p] = 0; if ((int)$lavagem['km'] > $km_atual_veiculos[$p]) { $km_atual_veiculos[$p] = (int)$lavagem['km']; } }
foreach ($utilizacao as $uso) { $p = strtoupper(trim($uso['placa'])); if (!isset($km_atual_veiculos[$p])) $km_atual_veiculos[$p] = 0; if ((int)$uso['km_final'] > $km_atual_veiculos[$p]) { $km_atual_veiculos[$p] = (int)$uso['km_final']; }
    if(isset($consumo_por_veiculo[$p])) { $consumo_por_veiculo[$p]['kms'][] = (int)$uso['km_final']; $consumo_por_veiculo[$p]['kms'][] = (int)$uso['km_inicial']; }
}

$kml_chart_data = array('labels' => array(), 'data' => array());
foreach ($consumo_por_veiculo as $placa => $dados) {
    if ($dados['litros'] > 0 && count($dados['kms']) > 0) {
        $km_min = min($dados['kms']); $km_max = max($dados['kms']);
        $distancia = $km_max - $km_min;
        if ($distancia > 0) {
            $kml = $distancia / $dados['litros'];
            $kml_chart_data['labels'][] = $placa; $kml_chart_data['data'][] = round($kml, 1);
        }
    }
}
if(empty($kml_chart_data['labels'])) { $kml_chart_data['labels'][] = "Faltam Litros"; $kml_chart_data['data'][] = 0; }

$alertas_docs = array(); $hoje = date('Y-m-d'); $trinta_dias = date('Y-m-d', strtotime('+30 days'));
foreach ($veiculos as $v) {
    if (isset($v['ativo']) && $v['ativo'] == 0) continue;
    if (!empty($v['data_ipva'])) { if ($v['data_ipva'] < $hoje) { $alertas_docs[] = "IPVA do {$v['placa']} está VENCIDO!"; } elseif ($v['data_ipva'] <= $trinta_dias) { $alertas_docs[] = "IPVA do {$v['placa']} vence dia " . date('d/m/Y', strtotime($v['data_ipva'])); } }
    if (!empty($v['data_seguro'])) { if ($v['data_seguro'] < $hoje) { $alertas_docs[] = "Seguro do {$v['placa']} está VENCIDO!"; } elseif ($v['data_seguro'] <= $trinta_dias) { $alertas_docs[] = "Seguro do {$v['placa']} vence dia " . date('d/m/Y', strtotime($v['data_seguro'])); } }
}

// Relatório Especial de Abastecimentos com Excel layout
$relatorio_abast = array();
$total_abast_km = 0;
$abs_ordenado = $abastecimentos;
usort($abs_ordenado, function($a, $b) { return strcmp($a['data'], $b['data']); });

foreach ($abastecimentos_filtrados as $abs) {
    $p = strtoupper(trim($abs['placa']));
    if (!isset($relatorio_abast[$p])) { $relatorio_abast[$p] = ['valor' => 0, 'cartao' => '', 'km_rodado' => 0]; }
    
    $relatorio_abast[$p]['valor'] += $abs['valor'];
    
    $num_cartao = '';
    foreach ($cartoes as $c) {
        if ($c['nome'] === $abs['cartao']) {
            $num_cartao = isset($c['numero_cartao']) ? trim($c['numero_cartao']) : '';
            break;
        }
    }
    
    if (!empty($num_cartao)) {
        $relatorio_abast[$p]['cartao'] = $num_cartao;
    } elseif (empty($relatorio_abast[$p]['cartao'])) {
        $relatorio_abast[$p]['cartao'] = $abs['cartao']; 
    }

    $km_ant = 0;
    foreach ($abs_ordenado as $a) {
        if ($a['placa'] === $abs['placa']) {
            if ($a['id'] === $abs['id']) break;
            $km_ant = $a['km'];
        }
    }
    
    if ($km_ant > 0 && $abs['km'] > $km_ant) {
        $km_diff = $abs['km'] - $km_ant;
        $relatorio_abast[$p]['km_rodado'] += $km_diff;
        $total_abast_km += $km_diff;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MLS - Controle de Frotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .navbar-brand { font-weight: bold; color: #0d6efd !important; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; margin-bottom: 20px; }
        .card-header { background-color: #fff; border-bottom: 2px solid #f4f6f9; font-weight: 600; }
        .stat-card { text-align: center; padding: 20px; border-radius: 8px; color: white; }
        .bg-primary-dark { background-color: #0b5ed7; }
        .bg-success-dark { background-color: #157347; }
        .bg-warning-dark { background-color: #ffca2c; color: #000; }
        
        .table-responsive { max-height: 500px; overflow-y: auto; }
        .table-responsive thead th { position: sticky; top: -1px; z-index: 10; }
        
        .user-badge { font-size: 0.95rem; font-weight: 500; color: #555; }
        body.dark-mode .user-badge { color: #f8f9fa !important; }
        
        /* Dark Mode */
        body.dark-mode { background-color: #121212; color: #e0e0e0; }
        body.dark-mode .navbar.bg-white { background-color: #1e1e1e !important; border-bottom: 1px solid #333; }
        body.dark-mode .card { background-color: #1e1e1e; border: 1px solid #333; }
        body.dark-mode .card-header { background-color: #2c2c2c; border-bottom: 1px solid #444; color: #fff; }
        body.dark-mode .bg-light { background-color: #2c2c2c !important; }
        body.dark-mode label, body.dark-mode .form-label { color: #ccc; }
        body.dark-mode .form-control, body.dark-mode .form-select { background-color: #2a2a2a; border-color: #444; color: #fff; }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus { background-color: #333; color: #fff; border-color: #5dade2; }
        body.dark-mode .nav-tabs .nav-link.active { background-color: #1e1e1e; color: #5dade2; border-color: #444 #444 #1e1e1e; }
        body.dark-mode .table { --bs-table-bg: #1e1e1e; --bs-table-color: #e0e0e0; border-color: #444 !important; color: #e0e0e0 !important; }
        body.dark-mode .table > :not(caption) > * > * { background-color: var(--bs-table-bg) !important; color: var(--bs-table-color) !important; border-bottom-color: #444 !important; }
        body.dark-mode .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #2a2a2a !important; color: #e0e0e0 !important; }
        body.dark-mode .table-light th { background-color: #2c2c2c !important; color: #fff !important; }
        body.dark-mode .table-dark th { background-color: #111 !important; color: #fff !important; }
        body.dark-mode .text-danger { color: #ff6b6b !important; }
        
        .theme-switch-wrapper { display: flex; align-items: center; }
        .theme-switch { display: inline-block; height: 24px; position: relative; width: 50px; margin-bottom: 0; }
        .theme-switch input { display: none; }
        .slider { background-color: #ccc; bottom: 0; cursor: pointer; left: 0; position: absolute; right: 0; top: 0; transition: .4s; border-radius: 34px; }
        .slider:before { background-color: #fff; bottom: 4px; content: ""; height: 16px; left: 4px; position: absolute; transition: .4s; width: 16px; border-radius: 50%; }
        input:checked + .slider { background-color: #0b5ed7; }
        input:checked + .slider:before { transform: translateX(26px); }
        .theme-switch-icon { font-size: 1.2rem; margin: 0 8px; color: #555; }
        body.dark-mode .theme-switch-icon.bi-moon-fill { color: #f1c40f; }
        
        /* Relatórios para Impressão */
        .print-only-diretoria, .print-only-abastecimentos { display: none; }
        
        @media print {
            body { background: #fff !important; color: #000 !important; font-size: 11pt; }
            .print-hide, .navbar, .nav-tabs, form, .theme-switch-wrapper, .alert { display: none !important; }
            
            body.print-mode-diretoria .print-only-diretoria { display: block !important; padding: 20px; }
            body.print-mode-diretoria .tab-content { display: none !important; }
            
            body.print-mode-abastecimentos .print-only-abastecimentos { display: block !important; padding: 20px; }
            body.print-mode-abastecimentos .tab-content { display: none !important; }
            
            .card, .card-body { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; background: transparent !important; }
            .col-md-4 { width: 33.333% !important; float: left !important; }
            .col-12 { width: 100% !important; float: none !important; clear: both !important; }
            .table { border: 1px solid #ddd !important; border-collapse: collapse !important; width: 100% !important; margin-bottom: 20px !important; }
            .table th, .table td { border: 1px solid #000 !important; color: #000 !important; padding: 6px !important; font-size: 10pt; }
            .table th { background-color: #eee !important; -webkit-print-color-adjust: exact; font-weight: bold; }
            .badge { border: 1px solid #000; color: #000 !important; background: #fff !important; -webkit-print-color-adjust: exact; padding: 2px 4px; }
            .report-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 15px; margin-bottom: 25px; }
            .report-signature { margin-top: 50px; text-align: center; }
            .report-signature .line { border-top: 1px solid #000; width: 300px; margin: 0 auto 10px auto; }
            tr { page-break-inside: avoid; }
            h4 { page-break-after: avoid; margin-top: 30px; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 5px; }
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

<!-- HEADER IMPRESSÃO (RELATÓRIO DIRETORIA) -->
<div class="print-only-diretoria">
    <div class="report-header">
        <h1>RELATÓRIO EXECUTIVO DE FROTA</h1>
        <p><b>Período Analisado:</b> <?php echo date('d/m/Y', strtotime($data_inicio)) . ' até ' . date('d/m/Y', strtotime($data_fim)); ?></p>
        <p><b>Data de Emissão:</b> <?php echo date('d/m/Y H:i'); ?></p>
    </div>
    
    <div style="display: flex; margin-bottom: 30px; border: 1px solid #000; padding: 15px; background: #f9f9f9; -webkit-print-color-adjust: exact;">
        <div style="flex: 1; text-align: center;">
            <p style="margin: 0; color: #555;">Investimento Total</p>
            <h3 style="margin: 0;">R$ <?php echo number_format($total_gasto, 2, ',', '.'); ?></h3>
        </div>
        <div style="flex: 1; text-align: center; border-left: 1px solid #ccc; border-right: 1px solid #ccc;">
            <p style="margin: 0; color: #555;">Distância Total</p>
            <h3 style="margin: 0;"><?php echo number_format($km_rodado_total, 0, '', '.'); ?> km</h3>
        </div>
        <div style="flex: 1; text-align: center;">
            <p style="margin: 0; color: #555;">Lançamentos</p>
            <h3 style="margin: 0;"><?php echo ($qtd_abastecimentos + $qtd_lavagens); ?></h3>
        </div>
    </div>
    
    <h4>1. Desempenho Operacional por Veículo</h4>
    <table class="table">
        <thead><tr><th>Placa</th><th>Distância Rodada</th><th>Custo Total (R$)</th><th>Abastecimentos</th><th>Lavagens</th></tr></thead>
        <tbody>
            <?php foreach($relatorio_frota as $placa => $r): ?>
            <tr>
                <td><b><?php echo $placa; ?></b></td>
                <td><?php echo number_format($r['km_rodado'], 0, '', '.'); ?> km</td>
                <td>R$ <?php echo number_format($r['gasto'], 2, ',', '.'); ?></td>
                <td><?php echo $r['abs_count']; ?></td>
                <td><?php echo isset($r['lav_count']) ? $r['lav_count'] : 0; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h4>2. Detalhamento de Rotas no Período</h4>
    <table class="table">
        <thead>
            <tr><th>Data</th><th>Condutor</th><th>Placa</th><th>Rota Registada</th><th>KM Inic.</th><th>KM Final</th><th>Total KM</th></tr>
        </thead>
        <tbody>
            <?php foreach($utilizacao_filtrada as $uso): ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($uso['data'])); ?></td>
                <td><?php echo htmlspecialchars($uso['condutor']); ?></td>
                <td><b><?php echo htmlspecialchars($uso['placa']); ?></b></td>
                <td><?php echo nl2br(htmlspecialchars($uso['rota'])); ?></td>
                <td><?php echo $uso['km_inicial']; ?></td>
                <td><?php echo $uso['km_final']; ?></td>
                <td><?php echo ($uso['km_final'] - $uso['km_inicial']); ?> km</td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($utilizacao_filtrada)): ?>
            <tr><td colspan="7" style="text-align: center;">Nenhuma rota registada neste período.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="report-signature">
        <div class="line"></div>
        <p><b>Assinatura do Gestor Responsável</b></p>
    </div>
</div>

<!-- HEADER IMPRESSÃO (RELATÓRIO DE ABASTECIMENTOS) -->
<div class="print-only-abastecimentos" style="font-family: Arial, sans-serif;">
    <h2 style="text-align: center; margin-bottom: 25px; font-weight: normal; font-size: 24px;">Relatório de abastecimento de veículos</h2>
    
    <div style="display: flex; gap: 40px; margin-bottom: 25px;">
        <div style="flex: 1;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                <tr><th style="border: 1px solid #000; padding: 4px; font-weight: normal;">Período</th></tr>
            </table>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                <tr>
                    <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 4px 8px; font-weight: bold; width: 40%; -webkit-print-color-adjust: exact;">Data início:</td>
                    <td style="border: 1px solid #000; padding: 4px 8px; text-align: center;"><?php echo date('d/m/Y', strtotime($data_inicio)); ?></td>
                </tr>
                <tr>
                    <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 4px 8px; font-weight: bold; -webkit-print-color-adjust: exact;">Data fim:</td>
                    <td style="border: 1px solid #000; padding: 4px 8px; text-align: center;"><?php echo date('d/m/Y', strtotime($data_fim)); ?></td>
                </tr>
            </table>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 4px 8px; font-weight: bold; width: 40%; -webkit-print-color-adjust: exact;">Departamento:</td>
                    <td style="border: 1px solid #000; padding: 4px 8px; text-align: center;">Redes</td>
                </tr>
            </table>
        </div>

        <div style="flex: 1;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                <tr><th style="border: 1px solid #000; padding: 4px; font-weight: normal;">Valor total</th></tr>
            </table>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 4px 8px; font-weight: bold; width: 50%; -webkit-print-color-adjust: exact;">Valor Abastecimento:</td>
                    <td style="border: 1px solid #000; padding: 4px 8px; text-align: center;">R$ <?php echo number_format($total_abastecimento, 2, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 4px 8px; font-weight: bold; -webkit-print-color-adjust: exact;">Quilometragem:</td>
                    <td style="border: 1px solid #000; padding: 4px 8px; text-align: center;"><?php echo number_format($total_abast_km, 0, '', '.'); ?></td>
                </tr>
            </table>
        </div>
        <div style="flex: 0.5;"></div>
    </div>

    <table style="width: 100%; border-collapse: collapse; text-align: center;">
        <thead>
            <tr>
                <th style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;">Placa</th>
                <th style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;">Valor abastecimento</th>
                <th style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;">Cartão</th>
                <th style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;">Quilometragem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($relatorio_abast as $placa => $dados): ?>
            <tr>
                <td style="border: 1px solid #000; padding: 6px; font-weight: bold;"><?php echo htmlspecialchars($placa); ?></td>
                <td style="border: 1px solid #000; padding: 6px;">R$ <?php echo number_format($dados['valor'], 2, ',', '.'); ?></td>
                <td style="border: 1px solid #000; padding: 6px;"><?php echo htmlspecialchars($dados['cartao']); ?></td>
                <td style="border: 1px solid #000; padding: 6px;"><?php echo $dados['km_rodado']; ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr>
                <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;">TOTAL</td>
                <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;">R$ <?php echo number_format($total_abastecimento, 2, ',', '.'); ?></td>
                <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; -webkit-print-color-adjust: exact;"></td>
                <td style="background-color: #003366; color: #fff; border: 1px solid #000; padding: 6px; font-weight: bold; -webkit-print-color-adjust: exact;"><?php echo number_format($total_abast_km, 0, '', '.'); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 print-hide">
    <div class="container-fluid">
        <a class="navbar-brand" href="?"><i class="bi bi-car-front-fill"></i> Sistema de Frotas</a>
        
        <div class="d-flex align-items-center ms-auto">
            <span class="user-badge me-3">
                <i class="bi bi-person-circle"></i> Olá, <b><?php echo htmlspecialchars($_SESSION['username']); ?></b>
                <span class="badge <?php echo $isAdmin ? 'bg-danger' : 'bg-secondary'; ?> ms-1"><?php echo ucfirst($_SESSION['user_role']); ?></span>
            </span>
            <div class="theme-switch-wrapper me-3 border-end pe-3 border-secondary">
                <i class="bi bi-sun-fill theme-switch-icon"></i>
                <label class="theme-switch" for="checkbox_theme">
                    <input type="checkbox" id="checkbox_theme" <?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'checked' : ''; ?> />
                    <div class="slider round"></div>
                </label>
                <i class="bi bi-moon-fill theme-switch-icon"></i>
            </div>
            <a href="?logout=true" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Sair</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 print-hide">

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle-fill"></i> <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['msg_erro'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $_SESSION['msg_erro']; unset($_SESSION['msg_erro']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body bg-light rounded">
            <form method="GET" class="row g-3 align-items-center">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_ativa); ?>">
                <div class="col-auto"><label class="col-form-label fw-bold">Período:</label></div>
                <div class="col-auto"><input type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($data_inicio); ?>"></div>
                <div class="col-auto"><label class="col-form-label">até</label></div>
                <div class="col-auto"><input type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($data_fim); ?>"></div>
                <div class="col-auto">
                    <select name="filtro_placa" class="form-select">
                        <option value="">Todas as Placas</option>
                        <?php foreach($veiculos as $v): ?><option value="<?php echo htmlspecialchars($v['placa']); ?>" <?php if($filtro_placa === $v['placa']) echo 'selected'; ?>><?php echo htmlspecialchars($v['placa'] . ' - ' . $v['modelo']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                    <a href="?clear_filters=1&tab=<?php echo htmlspecialchars($tab_ativa); ?>" class="btn btn-secondary" title="Apagar memória de filtro">Limpar</a>
                    <?php if(in_array($tab_ativa, array('utilizacao', 'abastecimentos', 'lavagens'))): ?>
                        <button type="submit" name="export" value="csv" class="btn btn-success ms-2"><i class="bi bi-file-earmark-excel"></i> Exportar CSV</button>
                    <?php endif; ?>
                    <?php if($tab_ativa === 'dashboard'): ?>
                        <button type="button" onclick="printReport('diretoria')" class="btn btn-dark ms-2"><i class="bi bi-briefcase-fill"></i> Relatório Diretoria</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'dashboard' ? 'active' : ''; ?>" href="?tab=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'utilizacao' ? 'active' : ''; ?>" href="?tab=utilizacao"><i class="bi bi-map"></i> Rotas</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'abastecimentos' ? 'active' : ''; ?>" href="?tab=abastecimentos"><i class="bi bi-fuel-pump"></i> Abastecimentos</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'lavagens' ? 'active' : ''; ?>" href="?tab=lavagens"><i class="bi bi-droplet-half"></i> Lavagens</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'veiculos' ? 'active' : ''; ?>" href="?tab=veiculos"><i class="bi bi-car-front"></i> Veículos</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'tecnicos' ? 'active' : ''; ?>" href="?tab=tecnicos"><i class="bi bi-person-badge"></i> Técnicos</a></li>
        <?php if($isAdmin): ?>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'importar' ? 'active' : ''; ?>" href="?tab=importar"><i class="bi bi-database"></i> Dados & Backup</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'cartoes' ? 'active' : ''; ?>" href="?tab=cartoes"><i class="bi bi-credit-card"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab_ativa === 'usuarios' ? 'active' : ''; ?> text-danger" href="?tab=usuarios"><i class="bi bi-shield-lock"></i> Acessos</a></li>
        <?php endif; ?>
        <li class="nav-item ms-auto"><a class="nav-link <?php echo $tab_ativa === 'perfil' ? 'active' : ''; ?>" href="?tab=perfil"><i class="bi bi-key"></i> Senha</a></li>
    </ul>

    <div class="tab-content">
        <!-- ABA: DASHBOARD -->
        <?php if ($tab_ativa === 'dashboard'): ?>
        <div class="tab-pane active fade show">
            <?php if(!empty($alertas_docs)): ?>
            <div class="alert alert-warning"><h5 class="alert-heading"><i class="bi bi-bell-fill"></i> Alertas de Documentação!</h5><ul class="mb-0"><?php foreach($alertas_docs as $al): ?><li><b><?php echo $al; ?></b></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-3"><div class="stat-card bg-primary-dark shadow-sm"><h5>Total Gasto</h5><h2>R$ <?php echo number_format($total_gasto, 2, ',', '.'); ?></h2></div></div>
                <div class="col-md-3"><div class="stat-card bg-success-dark shadow-sm"><h5>KM Total Rodado</h5><h2><?php echo number_format($km_rodado_total, 0, '', '.'); ?> km</h2></div></div>
                <div class="col-md-3"><div class="stat-card bg-warning-dark shadow-sm"><h5>Abastecimentos</h5><h2><?php echo $qtd_abastecimentos; ?></h2></div></div>
                <div class="col-md-3"><div class="stat-card bg-info text-dark shadow-sm"><h5>Lavagens</h5><h2><?php echo $qtd_lavagens; ?></h2></div></div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Evolução de Gastos</span>
                            <select id="tipoGraficoGastos" class="form-select form-select-sm" style="width: auto; font-size: 0.85rem;">
                                <option value="mes">Soma por Mês</option>
                                <option value="abastecimento">Por Lançamento</option>
                            </select>
                        </div>
                        <div class="card-body"><div style="position: relative; height: 280px; width: 100%;"><canvas id="chartGastos"></canvas></div></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header fw-bold">Eficiência (KM/L) por Veículo</div>
                        <div class="card-body"><div style="position: relative; height: 280px; width: 100%;"><canvas id="chartKML"></canvas></div></div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-tools"></i> Manutenção Preventiva</div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>Placa</th><th>Modelo</th><th>Técnico</th><th>Última Rev.</th><th>KM Atual</th><th>Diferença</th><th>Status Próxima Vistoria</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($veiculos as $v): 
                                            if (isset($v['ativo']) && $v['ativo'] == 0) continue; 
                                            
                                            $km_atual = isset($km_atual_veiculos[strtoupper(trim($v['placa']))]) ? $km_atual_veiculos[strtoupper(trim($v['placa']))] : $v['km_revisao'];
                                            $diferenca = $km_atual - $v['km_revisao'];
                                            $limite_revisao = 10000;
                                            $percentagem = ($diferenca / $limite_revisao) * 100;
                                            if ($percentagem < 0) $percentagem = 0; if ($percentagem > 100) $percentagem = 100;
                                            
                                            $falta = $limite_revisao - $diferenca; $status_class = "bg-success"; $status_text = "OK"; $barra_class = "bg-success";
                                            if ($falta < 0) { $status_class = "bg-danger"; $status_text = "Atrasada!"; $barra_class = "bg-danger"; } 
                                            elseif ($falta <= 1000) { $status_class = "bg-warning text-dark"; $status_text = "Atenção ($falta km)"; $barra_class = "bg-warning"; }
                                        ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($v); ?>">
                                            <td class="fw-bold"><?php echo htmlspecialchars($v['placa']); ?></td>
                                            <td><?php echo htmlspecialchars($v['modelo']); ?></td>
                                            <td><?php echo isset($v['tecnico']) ? htmlspecialchars($v['tecnico']) : '-'; ?></td>
                                            <td><?php echo number_format($v['km_revisao'], 0, '', '.'); ?></td>
                                            <td><?php echo number_format($km_atual, 0, '', '.'); ?></td>
                                            <td><?php echo number_format($diferenca, 0, '', '.'); ?> km</td>
                                            <td style="min-width: 150px;">
                                                <div class="d-flex justify-content-between align-items-center mb-1"><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span><small class="text-muted"><?php echo round($percentagem); ?>%</small></div>
                                                <div class="progress" style="height: 6px;"><div class="progress-bar <?php echo $barra_class; ?>" role="progressbar" style="width: <?php echo $percentagem; ?>%;"></div></div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var isDark = document.body.classList.contains('dark-mode'); 
                    var textColor = isDark ? '#e0e0e0' : '#666';
                    
                    var labelsMes = <?php echo json_encode(array_keys($gastos_por_mes)); ?>;
                    var dataMes = <?php echo json_encode(array_values($gastos_por_mes)); ?>;
                    var labelsAbs = <?php echo json_encode($gastos_individuais_labels); ?>;
                    var dataAbs = <?php echo json_encode($gastos_individuais_data); ?>;
                    
                    if (labelsMes.length === 0) { labelsMes = ['Sem dados no período']; dataMes = [0]; }
                    if (labelsAbs.length === 0) { labelsAbs = ['Sem dados no período']; dataAbs = [0]; }
                    
                    var ctxG = document.getElementById('chartGastos').getContext('2d');
                    var chartGastos = new Chart(ctxG, { 
                        type: 'line', 
                        data: { labels: labelsMes, datasets: [{ label: 'Gastos (R$)', data: dataMes, borderColor: '#0b5ed7', backgroundColor: 'rgba(11, 94, 215, 0.2)', fill: true, tension: 0.3 }] }, 
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: textColor } } }, scales: { x: { ticks: { color: textColor } }, y: { ticks: { color: textColor } } } } 
                    });
                    
                    var selectGrafico = document.getElementById('tipoGraficoGastos');
                    if (selectGrafico) {
                        var savedPref = localStorage.getItem('pref_grafico_gastos');
                        if (savedPref) {
                            selectGrafico.value = savedPref;
                            if (savedPref === 'abastecimento') {
                                chartGastos.data.labels = labelsAbs;
                                chartGastos.data.datasets[0].data = dataAbs;
                                chartGastos.update();
                            }
                        }
                        selectGrafico.addEventListener('change', function() {
                            localStorage.setItem('pref_grafico_gastos', this.value);
                            if (this.value === 'mes') {
                                chartGastos.data.labels = labelsMes;
                                chartGastos.data.datasets[0].data = dataMes;
                            } else {
                                chartGastos.data.labels = labelsAbs;
                                chartGastos.data.datasets[0].data = dataAbs;
                            }
                            chartGastos.update();
                        });
                    }

                    var ctxK = document.getElementById('chartKML').getContext('2d');
                    new Chart(ctxK, { 
                        type: 'bar', 
                        data: { labels: <?php echo json_encode($kml_chart_data['labels']); ?>, datasets: [{ label: 'KM por Litro', data: <?php echo json_encode($kml_chart_data['data']); ?>, backgroundColor: '#157347' }] }, 
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: textColor } } }, scales: { x: { ticks: { color: textColor } }, y: { ticks: { color: textColor } } } } 
                    });
                });
            </script>
        </div>
        <?php endif; ?>

        <!-- ABA: UTILIZAÇÃO -->
        <?php if ($tab_ativa === 'utilizacao'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <?php if($isAdmin): ?>
                <div class="col-md-3">
                    <div class="card <?php echo $utilizacao_edit ? 'border-primary' : ''; ?>">
                        <div class="card-header <?php echo $utilizacao_edit ? 'bg-primary text-white' : 'bg-light'; ?>"><b><?php echo $utilizacao_edit ? 'Editar Rota' : 'Nova Rota'; ?></b></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="acao" value="salvar_utilizacao"><input type="hidden" name="utilizacao_id" value="<?php echo $utilizacao_edit ? $utilizacao_edit['id'] : ''; ?>">
                                <div class="mb-2"><label>Data</label><input type="date" name="data" class="form-control" required value="<?php echo $utilizacao_edit ? $utilizacao_edit['data'] : date('Y-m-d'); ?>"></div>
                                <div class="mb-2"><label>Condutor</label><select name="condutor" class="form-select" required><option value="">Selecione...</option><?php foreach($tecnicos as $t): ?><option value="<?php echo htmlspecialchars($t['nome']); ?>" <?php echo ($utilizacao_edit && $utilizacao_edit['condutor'] === $t['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nome']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label>Veículo</label><select name="placa" id="select_placa_rota" class="form-select" required><option value="">Selecione...</option><?php foreach($veiculos as $v): if((isset($v['ativo']) && $v['ativo'] == 0) && (!$utilizacao_edit || $utilizacao_edit['placa'] !== $v['placa'])) continue; ?><option value="<?php echo htmlspecialchars($v['placa']); ?>" <?php echo ($utilizacao_edit && $utilizacao_edit['placa'] === $v['placa']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['placa']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label>Rota</label><textarea name="rota" class="form-control" rows="2" required><?php echo $utilizacao_edit ? htmlspecialchars($utilizacao_edit['rota']) : ''; ?></textarea></div>
                                <div class="mb-2"><label>KM Inic</label><input type="number" name="km_inicial" id="input_km_inicial_rota" class="form-control" value="<?php echo $utilizacao_edit ? $utilizacao_edit['km_inicial'] : ''; ?>" required></div>
                                <div class="mb-3"><label>KM Final</label><input type="number" name="km_final" class="form-control" value="<?php echo $utilizacao_edit ? $utilizacao_edit['km_final'] : ''; ?>" required></div>
                                <button type="submit" class="btn btn-primary w-100 mb-2">Registar</button>
                                <?php if($utilizacao_edit): ?><a href="?tab=utilizacao" class="btn btn-outline-secondary w-100">Cancelar</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="<?php echo $isAdmin ? 'col-md-9' : 'col-md-12'; ?>">
                    <div class="card">
                        <div class="card-header bg-light"><b>Histórico de Rotas</b></div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0" style="font-size: 0.9em;">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><a href="<?php echo urlOrdenacao('data', $sort_col, $sort_dir, 'utilizacao'); ?>" class="text-white text-decoration-none">Data <?php echo $sort_col == 'data' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th><a href="<?php echo urlOrdenacao('condutor', $sort_col, $sort_dir, 'utilizacao'); ?>" class="text-white text-decoration-none">Condutor <?php echo $sort_col == 'condutor' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th>Rota</th><th>Placa</th><th>KM Inic.</th><th>KM Final</th><th>Total KM</th>
                                            <?php if($isAdmin): ?><th>Ação</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($utilizacao_filtrada as $uso): ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($uso); ?>">
                                            <td><?php echo date('d/m/Y', strtotime($uso['data'])); ?></td><td><?php echo htmlspecialchars($uso['condutor']); ?></td><td><?php echo nl2br(htmlspecialchars($uso['rota'])); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($uso['placa']); ?></span></td><td><?php echo $uso['km_inicial']; ?></td><td><?php echo $uso['km_final']; ?></td>
                                            <td><span class="badge bg-info text-dark"><?php echo ($uso['km_final'] - $uso['km_inicial']); ?> km</span></td>
                                            <?php if($isAdmin): ?><td><a href="?tab=utilizacao&edit_utilizacao=<?php echo $uso['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a> <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $uso['id']; ?>&tipo=utilizacao')"><i class="bi bi-trash"></i></button></td><?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABA: ABASTECIMENTOS -->
        <?php if ($tab_ativa === 'abastecimentos'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <?php if($isAdmin): ?>
                <div class="col-md-3">
                    <div class="card <?php echo $abastecimento_edit ? 'border-primary' : ''; ?>">
                        <div class="card-header <?php echo $abastecimento_edit ? 'bg-primary text-white' : 'bg-light'; ?>"><b><?php echo $abastecimento_edit ? 'Editar Abast.' : 'Novo Abastecimento'; ?></b></div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="salvar_abastecimento"><input type="hidden" name="abastecimento_id" value="<?php echo $abastecimento_edit ? $abastecimento_edit['id'] : ''; ?>">
                                <div class="mb-2"><label>Data</label><input type="date" name="data" class="form-control" required value="<?php echo $abastecimento_edit ? $abastecimento_edit['data'] : date('Y-m-d'); ?>"></div>
                                <div class="mb-2"><label>Condutor</label><select name="condutor" class="form-select" required><option value="">Selecione...</option><?php foreach($tecnicos as $t): ?><option value="<?php echo htmlspecialchars($t['nome']); ?>" <?php echo ($abastecimento_edit && $abastecimento_edit['condutor'] === $t['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nome']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label>Veículo</label><select name="placa" class="form-select" required><option value="">Selecione...</option><?php foreach($veiculos as $v): if((isset($v['ativo']) && $v['ativo'] == 0) && (!$abastecimento_edit || $abastecimento_edit['placa'] !== $v['placa'])) continue; ?><option value="<?php echo htmlspecialchars($v['placa']); ?>" <?php echo ($abastecimento_edit && $abastecimento_edit['placa'] === $v['placa']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['placa']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label>KM Painel</label><input type="number" name="km" class="form-control" value="<?php echo $abastecimento_edit ? $abastecimento_edit['km'] : ''; ?>" required></div>
                                <div class="mb-2"><label>Qtd. Litros</label><input type="text" name="litros" class="form-control" placeholder="0,00" value="<?php echo $abastecimento_edit && isset($abastecimento_edit['litros']) ? number_format($abastecimento_edit['litros'], 2, ',', '') : ''; ?>" required></div>
                                <div class="mb-2"><label>Valor (R$)</label><input type="text" name="valor" class="form-control" value="<?php echo $abastecimento_edit ? number_format($abastecimento_edit['valor'], 2, ',', '') : ''; ?>" required></div>
                                <div class="mb-2"><label>Pagamento</label><select name="cartao" class="form-select"><?php foreach($cartoes as $c): ?><option value="<?php echo htmlspecialchars($c['nome']); ?>" <?php echo ($abastecimento_edit && $abastecimento_edit['cartao'] === $c['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-3"><label>Anexo do Talão (Foto/PDF)</label><input type="file" name="anexo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf"><?php if($abastecimento_edit && !empty($abastecimento_edit['anexo'])): ?><div class="mt-1 d-flex justify-content-between align-items-center"><small><a href="<?php echo $abastecimento_edit['anexo']; ?>" target="_blank">Ver anexo atual</a></small><label class="small text-danger"><input type="checkbox" name="remover_anexo" value="1"> Remover</label></div><?php endif; ?></div>
                                <button type="submit" class="btn btn-primary w-100 mb-2">Registar</button><?php if($abastecimento_edit): ?><a href="?tab=abastecimentos" class="btn btn-outline-secondary w-100">Cancelar</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="<?php echo $isAdmin ? 'col-md-9' : 'col-md-12'; ?>">
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <b>Histórico de Abastecimentos</b>
                            <button type="button" class="btn btn-dark btn-sm print-hide" onclick="printReport('abastecimentos')"><i class="bi bi-printer"></i> Imprimir Relatório Excel</button>
                        </div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><a href="<?php echo urlOrdenacao('data', $sort_col, $sort_dir, 'abastecimentos'); ?>" class="text-white text-decoration-none">Data <?php echo $sort_col == 'data' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th><a href="<?php echo urlOrdenacao('condutor', $sort_col, $sort_dir, 'abastecimentos'); ?>" class="text-white text-decoration-none">Condutor <?php echo $sort_col == 'condutor' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th><a href="<?php echo urlOrdenacao('placa', $sort_col, $sort_dir, 'abastecimentos'); ?>" class="text-white text-decoration-none">Placa <?php echo $sort_col == 'placa' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th>KM</th><th>Lts</th><th>KM/L</th>
                                            <th><a href="<?php echo urlOrdenacao('valor', $sort_col, $sort_dir, 'abastecimentos'); ?>" class="text-white text-decoration-none">Valor <?php echo $sort_col == 'valor' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th>Pagamento</th><th>Nf</th>
                                            <?php if($isAdmin): ?><th>Ação</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($abastecimentos_filtrados as $abs): ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($abs); ?>">
                                            <td><?php echo date('d/m/Y', strtotime($abs['data'])); ?></td><td><?php echo htmlspecialchars($abs['condutor']); ?></td><td><span class="badge bg-secondary"><?php echo htmlspecialchars($abs['placa']); ?></span></td>
                                            <td><?php echo $abs['km']; ?></td><td><?php echo isset($abs['litros']) ? $abs['litros'] : '-'; ?></td><td><span class="badge bg-info text-dark"><?php echo $abs['kml_calc']; ?></span></td>
                                            <td class="text-danger fw-bold">R$ <?php echo number_format($abs['valor'], 2, ',', '.'); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($abs['cartao']); ?>
                                                <?php 
                                                    $num_c_ui = ''; foreach($cartoes as $c) { if($c['nome'] === $abs['cartao']) { $num_c_ui = isset($c['numero_cartao']) ? $c['numero_cartao'] : ''; break; } }
                                                    if(!empty($num_c_ui)): ?><br><small class="text-muted"><?php echo htmlspecialchars($num_c_ui); ?></small><?php endif; 
                                                ?>
                                            </td>
                                            <td><?php if(!empty($abs['anexo'])): ?><a href="<?php echo $abs['anexo']; ?>" target="_blank" class="badge bg-success text-decoration-none">Ver</a><?php if($isAdmin): ?> <a href="?excluir_anexo=<?php echo $abs['id']; ?>" class="badge bg-danger text-decoration-none" title="Remover" onclick="return confirm('Apagar anexo?');">×</a><?php endif; ?><?php else: ?><span class="badge bg-light text-muted">-</span><?php endif; ?></td>
                                            <?php if($isAdmin): ?><td><a href="?tab=abastecimentos&edit_abastecimento=<?php echo $abs['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a> <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $abs['id']; ?>&tipo=abastecimento')"><i class="bi bi-trash"></i></button></td><?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABA: LAVAGENS -->
        <?php if ($tab_ativa === 'lavagens'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <?php if($isAdmin): ?>
                <div class="col-md-3">
                    <div class="card <?php echo $lavagem_edit ? 'border-info' : ''; ?>">
                        <div class="card-header <?php echo $lavagem_edit ? 'bg-info text-dark' : 'bg-light'; ?>"><b><?php echo $lavagem_edit ? 'Editar Lavagem' : 'Nova Lavagem'; ?></b></div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="salvar_lavagem"><input type="hidden" name="lavagem_id" value="<?php echo $lavagem_edit ? $lavagem_edit['id'] : ''; ?>">
                                <div class="mb-2"><label>Data</label><input type="date" name="data" class="form-control" required value="<?php echo $lavagem_edit ? $lavagem_edit['data'] : date('Y-m-d'); ?>"></div>
                                <div class="mb-2"><label>Condutor</label><select name="condutor" class="form-select" required><option value="">Selecione...</option><?php foreach($tecnicos as $t): ?><option value="<?php echo htmlspecialchars($t['nome']); ?>" <?php echo ($lavagem_edit && $lavagem_edit['condutor'] === $t['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nome']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label>Veículo</label><select name="placa" class="form-select" required><option value="">Selecione...</option><?php foreach($veiculos as $v): if((isset($v['ativo']) && $v['ativo'] == 0) && (!$lavagem_edit || $lavagem_edit['placa'] !== $v['placa'])) continue; ?><option value="<?php echo htmlspecialchars($v['placa']); ?>" <?php echo ($lavagem_edit && $lavagem_edit['placa'] === $v['placa']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['placa']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label>KM Painel</label><input type="number" name="km" class="form-control" value="<?php echo $lavagem_edit ? $lavagem_edit['km'] : ''; ?>" required></div>
                                <div class="mb-2"><label>Valor (R$)</label><input type="text" name="valor" class="form-control" value="<?php echo $lavagem_edit ? number_format($lavagem_edit['valor'], 2, ',', '') : ''; ?>" required></div>
                                <div class="mb-2"><label>Pagamento</label><select name="cartao" class="form-select"><?php foreach($cartoes as $c): ?><option value="<?php echo htmlspecialchars($c['nome']); ?>" <?php echo ($lavagem_edit && $lavagem_edit['cartao'] === $c['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-3"><label>Anexo do Talão (Foto/PDF)</label><input type="file" name="anexo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf"><?php if($lavagem_edit && !empty($lavagem_edit['anexo'])): ?><div class="mt-1 d-flex justify-content-between align-items-center"><small><a href="<?php echo $lavagem_edit['anexo']; ?>" target="_blank">Ver anexo atual</a></small><label class="small text-danger"><input type="checkbox" name="remover_anexo" value="1"> Remover</label></div><?php endif; ?></div>
                                <button type="submit" class="btn btn-info text-dark w-100 mb-2">Registar</button><?php if($lavagem_edit): ?><a href="?tab=lavagens" class="btn btn-outline-secondary w-100">Cancelar</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="<?php echo $isAdmin ? 'col-md-9' : 'col-md-12'; ?>">
                    <div class="card">
                        <div class="card-header bg-light"><b>Histórico de Lavagens</b></div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th><a href="<?php echo urlOrdenacao('data', $sort_col, $sort_dir, 'lavagens'); ?>" class="text-white text-decoration-none">Data <?php echo $sort_col == 'data' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th><a href="<?php echo urlOrdenacao('condutor', $sort_col, $sort_dir, 'lavagens'); ?>" class="text-white text-decoration-none">Condutor <?php echo $sort_col == 'condutor' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th><a href="<?php echo urlOrdenacao('placa', $sort_col, $sort_dir, 'lavagens'); ?>" class="text-white text-decoration-none">Placa <?php echo $sort_col == 'placa' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th>KM</th>
                                            <th><a href="<?php echo urlOrdenacao('valor', $sort_col, $sort_dir, 'lavagens'); ?>" class="text-white text-decoration-none">Valor <?php echo $sort_col == 'valor' ? ($sort_dir == 'asc' ? '↑' : '↓') : ''; ?></a></th>
                                            <th>Pagamento</th><th>Nf</th>
                                            <?php if($isAdmin): ?><th>Ação</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($lavagens_filtradas as $lavagem): ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($lavagem); ?>">
                                            <td><?php echo date('d/m/Y', strtotime($lavagem['data'])); ?></td><td><?php echo htmlspecialchars($lavagem['condutor']); ?></td><td><span class="badge bg-secondary"><?php echo htmlspecialchars($lavagem['placa']); ?></span></td>
                                            <td><?php echo $lavagem['km']; ?></td>
                                            <td class="text-danger fw-bold">R$ <?php echo number_format($lavagem['valor'], 2, ',', '.'); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($lavagem['cartao']); ?>
                                                <?php $num_c_ui = ''; foreach($cartoes as $c) { if($c['nome'] === $lavagem['cartao']) { $num_c_ui = isset($c['numero_cartao']) ? $c['numero_cartao'] : ''; break; } } if(!empty($num_c_ui)): ?><br><small class="text-muted"><?php echo htmlspecialchars($num_c_ui); ?></small><?php endif; ?>
                                            </td>
                                            <td><?php if(!empty($lavagem['anexo'])): ?><a href="<?php echo $lavagem['anexo']; ?>" target="_blank" class="badge bg-success text-decoration-none">Ver</a><?php if($isAdmin): ?> <a href="?excluir_anexo_lavagem=<?php echo $lavagem['id']; ?>" class="badge bg-danger text-decoration-none" title="Remover" onclick="return confirm('Apagar anexo?');">×</a><?php endif; ?><?php else: ?><span class="badge bg-light text-muted">-</span><?php endif; ?></td>
                                            <?php if($isAdmin): ?><td><a href="?tab=lavagens&edit_lavagem=<?php echo $lavagem['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a> <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $lavagem['id']; ?>&tipo=lavagem')"><i class="bi bi-trash"></i></button></td><?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABA: VEÍCULOS -->
        <?php if ($tab_ativa === 'veiculos'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <?php if($isAdmin): ?>
                <div class="col-md-4">
                    <div class="card <?php echo $veiculo_edit ? 'border-primary' : ''; ?>">
                        <div class="card-header <?php echo $veiculo_edit ? 'bg-primary text-white' : 'bg-light'; ?>"><b><?php echo $veiculo_edit ? 'Editar Veículo' : 'Registar Veículo'; ?></b></div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="salvar_veiculo"><input type="hidden" name="veiculo_id" value="<?php echo $veiculo_edit ? $veiculo_edit['id'] : ''; ?>">
                                <div class="mb-2"><label>Placa</label><input type="text" name="placa" class="form-control" value="<?php echo $veiculo_edit ? htmlspecialchars($veiculo_edit['placa']) : ''; ?>" required></div>
                                <div class="mb-2"><label>Modelo</label><input type="text" name="modelo" class="form-control" value="<?php echo $veiculo_edit ? htmlspecialchars($veiculo_edit['modelo']) : ''; ?>" required></div>
                                <div class="mb-2"><label>Técnico</label><select name="tecnico" class="form-select" required><option value="">Selecione...</option><?php foreach($tecnicos as $t): ?><option value="<?php echo htmlspecialchars($t['nome']); ?>" <?php echo ($veiculo_edit && isset($veiculo_edit['tecnico']) && $veiculo_edit['tecnico'] === $t['nome']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['nome']); ?></option><?php endforeach; ?></select></div>
                                <div class="row">
                                    <div class="col-6 mb-2"><label>KM Inicial</label><input type="number" name="km_inicial" class="form-control" value="<?php echo $veiculo_edit && isset($veiculo_edit['km_inicial']) ? $veiculo_edit['km_inicial'] : ''; ?>" required></div>
                                    <div class="col-6 mb-2"><label>KM Revisão</label><input type="number" name="km_revisao" class="form-control" placeholder="Igual ao Inicial" value="<?php echo $veiculo_edit ? $veiculo_edit['km_revisao'] : ''; ?>"></div>
                                </div>
                                <div class="mb-2"><label>Data Revisão</label><input type="date" name="data_revisao" class="form-control" value="<?php echo $veiculo_edit ? $veiculo_edit['data_revisao'] : ''; ?>"><small class="text-muted d-block" style="font-size: 0.75rem; margin-top: -3px;">Se em branco, assume hoje.</small></div>
                                <hr>
                                <div class="mb-2"><label>Vencimento IPVA</label><input type="date" name="data_ipva" class="form-control" value="<?php echo $veiculo_edit && isset($veiculo_edit['data_ipva']) ? $veiculo_edit['data_ipva'] : ''; ?>"></div>
                                <div class="mb-3"><label>Vencimento Seguro</label><input type="date" name="data_seguro" class="form-control" value="<?php echo $veiculo_edit && isset($veiculo_edit['data_seguro']) ? $veiculo_edit['data_seguro'] : ''; ?>"></div>
                                
                                <div class="mb-3 p-2 border rounded">
                                    <label class="fw-bold mb-1 d-block"><i class="bi bi-file-earmark-plus"></i> Docs. Veículo (Múltiplos)</label>
                                    <input type="file" name="anexos_novos[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf" multiple>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Selecione vários ficheiros ao mesmo tempo.</small>
                                    
                                    <?php if($veiculo_edit && !empty($veiculo_edit['anexos']) && is_array($veiculo_edit['anexos'])): ?>
                                        <div class="mt-2 pt-2 border-top">
                                            <label class="small fw-bold text-danger mb-1 d-block">Marque para apagar:</label>
                                            <?php foreach($veiculo_edit['anexos'] as $i => $anx): ?>
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input" type="checkbox" name="remover_anexos_veiculo[]" value="<?php echo htmlspecialchars($anx); ?>" id="rm_anx_<?php echo $i; ?>">
                                                    <label class="form-check-label small" for="rm_anx_<?php echo $i; ?>">
                                                        <a href="<?php echo $anx; ?>" target="_blank" class="text-decoration-none">Documento <?php echo $i+1; ?></a>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="checkAtivo" name="ativo" value="1" <?php echo (!$veiculo_edit || !isset($veiculo_edit['ativo']) || $veiculo_edit['ativo'] == 1) ? 'checked' : ''; ?>><label class="form-check-label fw-bold" for="checkAtivo">Veículo Ativo na Frota</label></div>
                                <button type="submit" class="btn btn-primary w-100 mb-2">Salvar</button><?php if($veiculo_edit): ?><a href="?tab=veiculos" class="btn btn-outline-secondary w-100">Cancelar</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="<?php echo $isAdmin ? 'col-md-8' : 'col-md-12'; ?>">
                    <div class="card">
                        <div class="card-header bg-light"><b>Frota Registada</b></div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-dark"><tr><th class="text-center">Status</th><th>Placa</th><th>Modelo</th><th>Técnico</th><th>Data Rev</th><th>Docs</th><?php if($isAdmin): ?><th>Ações</th><?php endif; ?></tr></thead>
                                    <tbody>
                                        <?php foreach($veiculos as $v): ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($v); ?>">
                                            <td class="text-center">
                                                <?php if($isAdmin): ?>
                                                    <a href="?toggle_ativo_veiculo=<?php echo $v['id']; ?>" class="text-decoration-none">
                                                        <?php if(!isset($v['ativo']) || $v['ativo'] == 1): ?>
                                                            <i class="bi bi-check-circle-fill text-success fs-5" title="Ativo - Clique para desativar"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-x-circle-fill text-danger fs-5" title="Inativo - Clique para ativar"></i>
                                                        <?php endif; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php if(!isset($v['ativo']) || $v['ativo'] == 1): ?><i class="bi bi-check-circle-fill text-success fs-5" title="Ativo"></i><?php else: ?><i class="bi bi-x-circle-fill text-danger fs-5" title="Inativo"></i><?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($v['placa']); ?></td><td><?php echo htmlspecialchars($v['modelo']); ?></td><td><span class="badge bg-secondary"><?php echo isset($v['tecnico']) ? htmlspecialchars($v['tecnico']) : '-'; ?></span></td>
                                            <td><?php echo date('d/m/Y', strtotime($v['data_revisao'])); ?></td>
                                            <td>
                                                <?php if(!empty($v['anexos']) && is_array($v['anexos'])): ?>
                                                    <?php foreach($v['anexos'] as $i => $anx): ?>
                                                        <a href="<?php echo $anx; ?>" target="_blank" class="badge bg-success text-decoration-none mb-1" title="Visualizar Doc <?php echo $i+1; ?>"><i class="bi bi-file-earmark-text"></i> <?php echo $i+1; ?></a>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if($isAdmin): ?><td><a href="?tab=veiculos&edit_veiculo=<?php echo $v['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a> <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $v['id']; ?>&tipo=veiculo')"><i class="bi bi-trash"></i></button></td><?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABA: TÉCNICOS -->
        <?php if ($tab_ativa === 'tecnicos'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <?php if($isAdmin): ?>
                <div class="col-md-4">
                    <div class="card <?php echo $tecnico_edit ? 'border-primary' : ''; ?>">
                        <div class="card-header <?php echo $tecnico_edit ? 'bg-primary text-white' : 'bg-light'; ?>"><b><?php echo $tecnico_edit ? 'Editar Técnico' : 'Registar Técnico'; ?></b></div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="salvar_tecnico">
                                <input type="hidden" name="tecnico_id" value="<?php echo $tecnico_edit ? $tecnico_edit['id'] : ''; ?>">
                                
                                <div class="mb-3">
                                    <label>Nome Completo</label>
                                    <input type="text" name="nome" class="form-control" value="<?php echo $tecnico_edit ? htmlspecialchars($tecnico_edit['nome']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label><i class="bi bi-person-vcard"></i> CNH / Documento</label>
                                    <input type="file" name="anexo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                                    <?php if($tecnico_edit && !empty($tecnico_edit['anexo'])): ?>
                                        <div class="mt-2 d-flex justify-content-between align-items-center">
                                            <small><a href="<?php echo $tecnico_edit['anexo']; ?>" target="_blank" class="fw-bold text-success">Ver Documento Atual</a></small>
                                            <label class="small text-danger fw-bold"><input type="checkbox" name="remover_anexo" value="1"> Remover</label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Salvar</button>
                                <?php if($tecnico_edit): ?><a href="?tab=tecnicos" class="btn btn-outline-secondary w-100 mt-2">Cancelar</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="<?php echo $isAdmin ? 'col-md-8' : 'col-md-12'; ?>">
                    <div class="card">
                        <div class="card-header bg-light"><b>Técnicos Registados</b></div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-dark"><tr><th>Nome</th><th>CNH / Doc</th><?php if($isAdmin): ?><th style="width: 150px;">Ação</th><?php endif; ?></tr></thead>
                                    <tbody>
                                        <?php foreach($tecnicos as $t): ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($t); ?>">
                                            <td class="fw-bold"><?php echo htmlspecialchars($t['nome']); ?></td>
                                            <td>
                                                <?php if(!empty($t['anexo'])): ?>
                                                    <a href="<?php echo $t['anexo']; ?>" target="_blank" class="badge bg-success text-decoration-none"><i class="bi bi-check-circle-fill"></i> Ver Doc</a>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if($isAdmin): ?>
                                            <td>
                                                <a href="?tab=tecnicos&edit_tecnico=<?php echo $t['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a> 
                                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $t['id']; ?>&tipo=tecnico')"><i class="bi bi-trash"></i></button>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ABA: IMPORTAR CSV E BACKUP (ADMIN ONLY) -->
        <?php if ($isAdmin && $tab_ativa === 'importar'): ?>
        <div class="tab-pane active fade show">
            <div class="row justify-content-center">
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-bold"><i class="bi bi-database-down"></i> Backup do Sistema (Base de Dados)</div>
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <p class="text-muted mb-4">Descarregue uma cópia de segurança completa de toda a sua base de dados (todos os ficheiros JSON com os registos do sistema).</p>
                            <a href="?backup_db=1" class="btn btn-dark w-100 fs-5 mt-auto"><i class="bi bi-download"></i> Baixar Backup (.ZIP)</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-cloud-arrow-up"></i> Importar CSV</div>
                        <div class="card-body">
                            <div class="alert alert-info">Mude o ficheiro Excel para <b>CSV (Separado por vírgulas)</b> ou <b>CSV UTF-8</b> e faça o upload abaixo. A quilometragem inicial será preenchida automaticamente de forma inteligente.</div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="importar_csv">
                                <div class="mb-3 mt-4"><label class="form-label fw-bold">Ficheiro (.csv)</label><input class="form-control" type="file" name="arquivo_csv" accept=".csv" required></div>
                                <button type="submit" class="btn btn-success w-100 fs-5"><i class="bi bi-upload"></i> Importar Histórico</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABA: CARTÕES (ADMIN ONLY) -->
        <?php if ($isAdmin && $tab_ativa === 'cartoes'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <div class="col-md-4">
                    <div class="card <?php echo $cartao_edit ? 'border-primary' : ''; ?>">
                        <div class="card-header <?php echo $cartao_edit ? 'bg-primary text-white' : 'bg-light'; ?>"><b><?php echo $cartao_edit ? 'Editar Cartão' : 'Novo Cartão'; ?></b></div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="acao" value="salvar_cartao">
                                <input type="hidden" name="cartao_id" value="<?php echo $cartao_edit ? $cartao_edit['id'] : ''; ?>">
                                
                                <div class="mb-3">
                                    <label>Nome / Referência</label>
                                    <input type="text" name="nome" class="form-control" placeholder="Ex: Cartão Master Frota" value="<?php echo $cartao_edit ? htmlspecialchars($cartao_edit['nome']) : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label>Nº do Cartão (Usado no Relatório)</label>
                                    <input type="text" name="numero_cartao" class="form-control" placeholder="Apenas números" value="<?php echo $cartao_edit && isset($cartao_edit['numero_cartao']) ? htmlspecialchars($cartao_edit['numero_cartao']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label>Senha do Cartão</label>
                                    <div class="input-group">
                                        <input type="password" name="senha" id="inputSenhaCartao" class="form-control" value="<?php echo $cartao_edit && isset($cartao_edit['senha']) ? htmlspecialchars($cartao_edit['senha']) : ''; ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="var p = document.getElementById('inputSenhaCartao'); p.type = p.type === 'password' ? 'text' : 'password';"><i class="bi bi-eye"></i></button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label>Foto do Cartão</label>
                                    <input type="file" name="anexo" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                                    <?php if($cartao_edit && !empty($cartao_edit['anexo'])): ?>
                                        <div class="mt-1 d-flex justify-content-between align-items-center">
                                            <small><a href="<?php echo $cartao_edit['anexo']; ?>" target="_blank" class="fw-bold text-success">Ver foto atual</a></small>
                                            <label class="small text-danger fw-bold"><input type="checkbox" name="remover_anexo" value="1"> Remover</label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">Salvar Cartão</button>
                                <?php if($cartao_edit): ?><a href="?tab=cartoes" class="btn btn-outline-secondary w-100 mt-2">Cancelar Edição</a><?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-light"><b>Meios de Pagamento Disponíveis</b></div>
                        <div class="card-body p-3">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr><th>Referência</th><th>Nº Cartão</th><th>Senha</th><th>Foto</th><th style="width: 150px;">Ação</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($cartoes as $c): ?>
                                        <tr data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo getTooltipAuditoria($c); ?>">
                                            <td class="fw-bold"><?php echo htmlspecialchars($c['nome']); ?></td>
                                            <td><?php echo isset($c['numero_cartao']) && !empty($c['numero_cartao']) ? htmlspecialchars($c['numero_cartao']) : '<span class="text-muted">-</span>'; ?></td>
                                            <td>
                                                <?php if(isset($c['senha']) && !empty($c['senha'])): ?>
                                                    <div class="input-group input-group-sm" style="width: 120px;">
                                                        <input type="password" class="form-control" value="<?php echo htmlspecialchars($c['senha']); ?>" readonly id="pwd_<?php echo $c['id']; ?>" style="background-color: transparent; border: none; padding-left: 0;">
                                                        <button class="btn btn-sm btn-outline-secondary border-0" type="button" onclick="var p = document.getElementById('pwd_<?php echo $c['id']; ?>'); p.type = p.type === 'password' ? 'text' : 'password';"><i class="bi bi-eye"></i></button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if(!empty($c['anexo'])): ?>
                                                    <a href="<?php echo $c['anexo']; ?>" target="_blank" class="badge bg-success text-decoration-none">Ver</a>
                                                    <a href="?excluir_anexo_cartao=<?php echo $c['id']; ?>" class="badge bg-danger text-decoration-none" title="Remover" onclick="return confirm('Apagar anexo?');">×</a>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?tab=cartoes&edit_cartao=<?php echo $c['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i></a> 
                                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $c['id']; ?>&tipo=cartao')"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ABA: GESTÃO DE UTILIZADORES (ADMIN ONLY) -->
        <?php if ($isAdmin && $tab_ativa === 'usuarios'): ?>
        <div class="tab-pane active fade show">
            <div class="row">
                <div class="col-md-4">
                    <div class="card border-danger"><div class="card-header bg-danger text-white"><b>Novo Utilizador</b></div>
                        <div class="card-body"><form method="POST"><input type="hidden" name="acao" value="salvar_usuario"><div class="mb-2"><label>Nome de Utilizador</label><input type="text" name="username" class="form-control" placeholder="Sem espaços" required></div><div class="mb-2"><label>Senha de Acesso</label><input type="password" name="password" class="form-control" required></div><div class="mb-3"><label>Nível de Acesso</label><select name="role" class="form-select"><option value="leitura">Leitura (Pode apenas ver)</option><option value="admin">Administrador (Acesso Total)</option></select></div><button type="submit" class="btn btn-danger w-100">Criar Utilizador</button></form></div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card"><div class="card-header bg-light"><b>Utilizadores do Sistema</b></div>
                        <div class="card-body p-3"><div class="table-responsive"><table class="table table-striped table-hover align-middle mb-0"><thead class="table-dark"><tr><th>Utilizador</th><th>Permissão</th><th>Ação</th></tr></thead><tbody><?php foreach($usuarios as $u): ?><tr><td class="fw-bold"><?php echo htmlspecialchars($u['username']); ?></td><td><?php if($u['role'] == 'admin'): ?><span class="badge bg-danger">Admin</span><?php else: ?><span class="badge bg-secondary">Leitura</span><?php endif; ?></td><td><?php if($u['id'] !== $_SESSION['user_id'] && $u['username'] !== 'admin'): ?><button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete('?excluir=<?php echo $u['id']; ?>&tipo=usuario')">Excluir</button><?php elseif($u['username'] === 'admin'): ?><span class="text-muted small text-danger"><i class="bi bi-shield-lock-fill"></i> Protegido</span><?php else: ?><span class="text-muted small">Você</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ABA: MUDAR SENHA -->
        <?php if ($tab_ativa === 'perfil'): ?>
        <div class="tab-pane active fade show">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card shadow-sm"><div class="card-header bg-secondary text-white"><b>Mudar Senha Pessoal</b></div>
                        <div class="card-body"><form method="POST"><input type="hidden" name="acao" value="mudar_senha"><div class="mb-3"><label>Senha Atual</label><input type="password" name="senha_atual" class="form-control" required></div><div class="mb-3"><label>Nova Senha</label><input type="password" name="nova_senha" class="form-control" required></div><div class="mb-4"><label>Confirme a Nova Senha</label><input type="password" name="confirma_senha" class="form-control" required></div><button type="submit" class="btn btn-secondary w-100">Atualizar Senha</button></form></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Atenção</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center mt-2"><b>Deseja excluir este registo?</b><br><small class="text-muted">Ação irreversível.</small></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><a href="#" id="confirmDeleteBtn" class="btn btn-danger">Sim, Excluir</a></div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Lógica de Impressão Direcionada
function printReport(type) {
    document.body.classList.add('print-mode-' + type);
    window.print();
    setTimeout(function() {
        document.body.classList.remove('print-mode-' + type);
    }, 2000);
}

window.addEventListener('afterprint', function() {
    document.body.classList.remove('print-mode-diretoria', 'print-mode-abastecimentos');
});

function confirmDelete(url) { document.getElementById('confirmDeleteBtn').href = url; new bootstrap.Modal(document.getElementById('deleteModal')).show(); }

document.addEventListener('DOMContentLoaded', function() {
    // ATIVAÇÃO DOS TOOLTIPS DO BOOTSTRAP PARA A AUDITORIA (QUEM CRIOU/EDITOU)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            delay: { "show": 100, "hide": 100 }
        });
    });

    var kmAtual = <?php echo empty($km_atual_veiculos) ? '{}' : json_encode($km_atual_veiculos); ?>;
    var selectPlaca = document.getElementById('select_placa_rota'); var inputKmInicial = document.getElementById('input_km_inicial_rota');
    if (selectPlaca && inputKmInicial) {
        var inputIdRota = document.querySelector('input[name="utilizacao_id"]'); var isEditing = inputIdRota && inputIdRota.value !== '';
        function atualizarKmInicial(event) {
            var placaSelecionada = selectPlaca.value.trim().toUpperCase();
            if (isEditing && (!event || event.type !== 'change')) return; 
            if (placaSelecionada !== '' && kmAtual.hasOwnProperty(placaSelecionada)) { inputKmInicial.value = kmAtual[placaSelecionada]; } else { inputKmInicial.value = ''; }
        }
        selectPlaca.addEventListener('change', atualizarKmInicial);
        var tabRotas = document.querySelector('a[href="?tab=utilizacao"]'); if(tabRotas) { tabRotas.addEventListener('shown.bs.tab', atualizarKmInicial); }
    }
    
    var toggleSwitch = document.querySelector('#checkbox_theme');
    function switchTheme(e) {
        if (e.target.checked) { document.body.classList.add('dark-mode'); document.cookie = "theme=dark; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/"; } 
        else { document.body.classList.remove('dark-mode'); document.cookie = "theme=light; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/"; }    
    }
    if(toggleSwitch) { toggleSwitch.addEventListener('change', switchTheme, false); }
});
</script>

</body>
</html>