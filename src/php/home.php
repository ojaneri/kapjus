<?php
if (isset($_POST['create_case'])) {
    $name = $_POST['name'];
    $desc = $_POST['description'];
    $stmt = $db->prepare("INSERT INTO cases (name, description) VALUES (:name, :desc)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':desc', $desc, SQLITE3_TEXT);
    $stmt->execute();
    header("Location: /");
    exit;
}
$results = $db->query("SELECT * FROM cases ORDER BY created_at DESC");
?>

<div class="min-h-screen bg-[#f8fafc]">
    <!-- Navbar Moderna -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-3">
                    <img src="https://kaponline.com.br/logo.jpeg" alt="KapOnline" class="h-10 w-10 rounded-lg shadow-sm">
                    <span class="text-2xl font-black tracking-tighter text-slate-900">KAP<span class="text-indigo-600">JUS</span></span>
                </div>
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Usuário</p>
                        <p class="text-sm font-semibold text-slate-700">Osvaldo J. Filho</p>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border-2 border-white shadow-sm">
                        OJ
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
        <!-- Hero Section -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 sm:mb-10 gap-4">
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">Central de Casos</h1>
                <p class="text-slate-500 mt-1 text-sm sm:text-base">Gerencie e analise seus processos com inteligência artificial.</p>
            </div>
            <button onclick="document.getElementById('modal-case').classList.remove('hidden')" class="inline-flex items-center justify-center px-5 sm:px-6 py-3 border border-transparent text-sm sm:text-base font-bold rounded-xl shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:scale-105 active:scale-95 w-full sm:w-auto">
                <i class="fas fa-plus-circle mr-2"></i> Iniciar Novo Caso
            </button>
        </div>

        <!-- Cases List - Mobile: Single line, Desktop: Grid -->
        <div class="block sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-0 sm:gap-5 sm:gap-8">
            <?php while ($row = $results->fetchArray()): ?>
                <a href="/case/<?php echo $row['id']; ?>" class="group flex sm:block items-center sm:items-start gap-4 sm:gap-0 p-4 sm:p-0 sm:bg-white sm:rounded-2xl sm:shadow-sm sm:border sm:border-slate-200 sm:hover:shadow-xl sm:hover:border-indigo-200 transition-all duration-300 relative overflow-hidden">
                    <!-- Mobile: Icon, Desktop: Hover indicator -->
                    <div class="hidden sm:block absolute top-0 left-0 w-1 h-full bg-indigo-600 transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                    <div class="flex-shrink-0 sm:hidden p-2 bg-indigo-50 rounded-lg text-indigo-600">
                        <i class="fas fa-gavel"></i>
                    </div>
                    
                    <div class="flex-1 min-w-0 sm:mb-4">
                        <h3 class="text-base sm:text-lg sm:text-xl font-bold text-slate-900 group-hover:text-indigo-600 transition-colors truncate"><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p class="text-xs text-slate-500 truncate sm:line-clamp-3 sm:min-h-[3rem]"><?php echo htmlspecialchars($row['description'] ?: 'Sem descrição'); ?></p>
                    </div>
                    
                    <div class="hidden sm:flex items-center justify-between pt-4 border-t border-slate-100">
                        <span class="text-xs font-medium text-slate-400">
                            <i class="far fa-calendar-alt mr-1"></i> <?php echo date('d M, Y', strtotime($row['created_at'])); ?>
                        </span>
                        <span class="text-indigo-600 font-bold text-xs sm:text-sm flex items-center">
                            Acessar <i class="fas fa-chevron-right ml-2 text-[10px] group-hover:translate-x-1 transition-transform"></i>
                        </span>
                    </div>
                    
                    <!-- Mobile: Chevron -->
                    <i class="fas fa-chevron-right text-slate-300 sm:hidden"></i>
                </a>
            <?php endwhile; ?>
        </div>
    </main>
</div>

<!-- Modal Novo Caso -->
<div id="modal-case" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end sm:items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" onclick="document.getElementById('modal-case').classList.add('hidden')"></div>
        
        <!-- Center trick -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-white/20">
            <div class="bg-white px-6 sm:px-8 pt-6 sm:pt-8 pb-6">
                <div class="flex items-center space-x-4 mb-6">
                    <div class="bg-indigo-100 p-3 rounded-2xl text-indigo-600">
                        <i class="fas fa-folder-plus text-2xl"></i>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Novo Caso Jurídico</h3>
                </div>
                
                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Título do Processo</label>
                        <input type="text" name="name" required placeholder="Ex: Ação Indenizatória - Silva vs Santos" class="w-full bg-slate-50 border-0 rounded-2xl px-5 py-4 text-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none transition-all placeholder:text-slate-400">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Breve Descrição</label>
                        <textarea name="description" rows="4" placeholder="Detalhes iniciais, número do processo ou partes envolvidas..." class="w-full bg-slate-50 border-0 rounded-2xl px-5 py-4 text-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none transition-all placeholder:text-slate-400 resize-none"></textarea>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3 pt-4">
                        <button type="button" onclick="document.getElementById('modal-case').classList.add('hidden')" class="flex-1 px-6 py-4 text-slate-600 font-bold hover:bg-slate-50 rounded-2xl transition-colors order-2 sm:order-1">
                            Cancelar
                        </button>
                        <button type="submit" name="create_case" class="flex-1 bg-indigo-600 text-white px-6 py-4 rounded-2xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all transform hover:scale-105 active:scale-95 order-1 sm:order-2">
                            Criar Agora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
