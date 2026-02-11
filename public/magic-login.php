<?php
/**
 * KAPJUS RAG - Magic Login Page
 * Landing page for lawyer invitation magic links
 * Accessed via: /magic-login?token=XXX&case_id=YYY
 */

// Include socket client for API communication
require_once __DIR__ . '/../src/php/socket_client.php';

$error = null;
$success = null;
$invitation = null;
$case_id = isset($_GET['case_id']) ? $_GET['case_id'] : null;
$token = isset($_GET['token']) ? $_GET['token'] : null;

// If no token or case_id, show form
if (!$token || !$case_id) {
    $error = "Link de convite inválido ou expirado.";
}

// Verify invitation if token and case_id are provided
if ($token && $case_id && !$error) {
    $result = verify_invitation($token, $case_id);
    
    if (isset($result['status']) && $result['status'] === 'valid') {
        $success = true;
        $invitation = $result;
    } else {
        $error = $result['detail'] ?? $result['message'] ?? "Convite não encontrado ou expirado.";
    }
}

// Handle form submission (accept invitation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation) {
    // In a real implementation, you might want to create a session here
    // For now, just show success and redirect to the case
    
    // Store session data (simplified - in production use proper session management)
    $session_data = json_encode([
        'lawyer_email' => $invitation['lawyer_email'],
        'lawyer_name' => $invitation['lawyer_name'],
        'role' => $invitation['role'],
        'case_id' => $invitation['case_id'],
        'session_token' => $invitation['session_token'],
        'expires_at' => $invitation['expires_at']
    ]);
    
    // Set cookie for 24 hours
    setcookie('kapjus_session', $session_data, time() + 86400, '/', 'kapjus.kaponline.com.br', true, true);
    
    // Redirect to case detail
    header("Location: /case/" . $invitation['case_id']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KAPJUS - Login Mágico</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center space-x-3 mb-4">
                <img src="https://kaponline.com.br/logo.jpeg" alt="KapOnline" class="h-12 w-12 rounded-xl shadow-lg">
                <span class="text-3xl font-black tracking-tighter text-white uppercase">KAP<span class="text-indigo-300">JUS</span></span>
            </div>
            <p class="text-indigo-200">Sistema de Gestão Jurídica</p>
        </div>
        
        <!-- Error Card -->
        <?php if ($error): ?>
        <div class="bg-white rounded-3xl shadow-2xl p-8 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-2xl text-red-500"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-900 mb-2">Erro de Acesso</h2>
            <p class="text-slate-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
            <a href="/" class="inline-flex items-center justify-center px-6 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-indigo-600 transition-all">
                <i class="fas fa-home mr-2"></i> Voltar ao Início
            </a>
        </div>
        
        <!-- Success Card -->
        <?php elseif ($invitation): ?>
        <div class="bg-white rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-2xl text-emerald-500"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-900">Convite Aceito!</h2>
                <p class="text-slate-600">Você foi convidado para acessar este caso.</p>
            </div>
            
            <!-- Invitation Details -->
            <div class="bg-slate-50 rounded-2xl p-4 mb-6">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Nome:</span>
                        <span class="font-medium text-slate-900"><?php echo htmlspecialchars($invitation['lawyer_name'] ?? 'Não informado'); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">E-mail:</span>
                        <span class="font-medium text-slate-900"><?php echo htmlspecialchars($invitation['lawyer_email']); ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Permissão:</span>
                        <span class="font-medium text-indigo-600">
                            <?php 
                                $roles = [
                                    'viewer' => 'Visualizador',
                                    'commenter' => 'Colaborador',
                                    'editor' => 'Editor'
                                ];
                                echo $roles[$invitation['role']] ?? ucfirst($invitation['role']);
                            ?>
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Válido até:</span>
                        <span class="font-medium text-slate-900"><?php echo date('d/m/Y H:i', strtotime($invitation['expires_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Accept Form -->
            <form method="POST">
                <button type="submit" class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-200">
                    <i class="fas fa-sign-in-alt mr-2"></i> Acessar Caso
                </button>
            </form>
            
            <p class="text-xs text-slate-400 text-center mt-4">
                Ao clicar em "Acessar Caso", você concorda com os termos de uso do sistema.
            </p>
        </div>
        
        <!-- Form Card (no token) -->
        <?php else: ?>
        <div class="bg-white rounded-3xl shadow-2xl p-8 text-center">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-user-plus text-2xl text-indigo-500"></i>
            </div>
            <h2 class="text-xl font-bold text-slate-900 mb-2">Convite de Acesso</h2>
            <p class="text-slate-600 mb-6">Por favor, clique no link mágico enviado por e-mail para acessar o caso.</p>
            <a href="/" class="inline-flex items-center justify-center px-6 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-indigo-600 transition-all">
                <i class="fas fa-home mr-2"></i> Voltar ao Início
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="text-center mt-6 text-indigo-200 text-sm">
            <p>&copy; 2024 KAPJUS. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
