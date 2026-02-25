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
                        <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars(current_user()['name'] ?? ''); ?></p>
                    </div>
                    <?php $initials = strtoupper(implode('', array_map(fn($w) => $w[0], array_filter(explode(' ', current_user()['name'] ?? 'U'))))); $initials = substr($initials, 0, 2); ?>
                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold border-2 border-white shadow-sm text-sm">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                    <a href="/logout" class="text-xs text-slate-400 hover:text-red-500 transition-colors hidden sm:block" title="Sair">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
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

        <!-- Search + Sort bar -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6 items-stretch sm:items-center">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="case-filter-input" placeholder="Filtrar casos por nome..." oninput="filterCases()" class="w-full pl-10 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500 outline-none shadow-sm transition-all">
            </div>
            <select id="case-sort-select" onchange="filterCases()" class="text-sm bg-white border border-slate-200 rounded-xl px-4 py-3 text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none shadow-sm font-medium">
                <option value="recent">Mais recentes</option>
                <option value="oldest">Mais antigos</option>
                <option value="az">A → Z</option>
                <option value="za">Z → A</option>
            </select>
        </div>

        <!-- Cases List - Mobile: Single line, Desktop: Grid -->
        <div id="cases-grid" class="block sm:grid sm:grid-cols-2 lg:grid-cols-3 gap-0 sm:gap-5 sm:gap-8">
            <?php 
            $has_cases = false;
            while ($row = $results->fetchArray()): 
                $has_cases = true;
            ?>
                <a href="/case/<?php echo $row['id']; ?>" class="case-card group flex sm:block items-center sm:items-start gap-4 sm:gap-0 p-4 sm:p-0 sm:bg-white sm:rounded-2xl sm:shadow-sm sm:border sm:border-slate-200 sm:hover:shadow-xl sm:hover:border-indigo-200 transition-all duration-300 relative overflow-hidden" data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>" data-date="<?php echo $row['created_at']; ?>">
                    <!-- ... existing card content ... -->
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

        <?php if (!$has_cases): ?>
            <div id="cases-empty-state" class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border-2 border-dashed border-slate-200">
                <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mb-6">
                    <i class="fas fa-folder-open text-3xl text-indigo-200"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Nenhum caso ainda</h3>
                <p class="text-slate-500 mb-8 text-center max-w-xs">Você ainda não criou nenhum caso jurídico. Comece agora para organizar seus documentos.</p>
                <button onclick="document.getElementById('modal-case').classList.remove('hidden')" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
                    <i class="fas fa-plus-circle mr-2"></i> Criar Meu Primeiro Caso
                </button>
            </div>
        <?php endif; ?>
        <p id="no-cases-msg" class="hidden text-center text-slate-400 font-bold py-12">Nenhum caso encontrado para o filtro atual.</p>
    </main>
</div>

<script>
function filterCases() {
    const q = (document.getElementById('case-filter-input').value || '').toLowerCase().trim();
    const sort = document.getElementById('case-sort-select').value;
    const grid = document.getElementById('cases-grid');
    const cards = Array.from(grid.querySelectorAll('.case-card'));

    // Filter
    let visible = cards.filter(c => {
        if (!q) return true;
        return c.dataset.name.includes(q);
    });

    // Hide all first
    cards.forEach(c => c.style.display = 'none');

    // Sort
    visible.sort((a, b) => {
        if (sort === 'recent') return b.dataset.date.localeCompare(a.dataset.date);
        if (sort === 'oldest') return a.dataset.date.localeCompare(b.dataset.date);
        if (sort === 'az') return a.dataset.name.localeCompare(b.dataset.name);
        if (sort === 'za') return b.dataset.name.localeCompare(a.dataset.name);
        return 0;
    });

    // Re-append in sorted order and show
    visible.forEach(c => {
        c.style.display = '';
        grid.appendChild(c);
    });

    document.getElementById('no-cases-msg').classList.toggle('hidden', visible.length > 0);
}
</script>

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
