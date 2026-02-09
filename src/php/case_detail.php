<?php
$stmt = $db->prepare("SELECT * FROM cases WHERE id = :id");
$stmt->bindValue(':id', $case_id, SQLITE3_INTEGER);
$case = $stmt->execute()->fetchArray();
if (!$case) { echo "Caso não encontrado."; exit; }
?>

<!-- PDF Viewer Modal -->
<div id="pdf-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="pdf-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="closePdfModal()"></div>
    <div class="fixed inset-0 z-10 overflow-hidden">
        <div class="flex h-full">
            <!-- PDF Viewer -->
            <div class="flex-1 flex flex-col bg-slate-900">
                <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700">
                    <div class="flex items-center space-x-3">
                        <button onclick="closePdfModal()" class="text-white hover:text-red-400 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                        <span id="pdf-filename" class="text-white font-medium truncate max-w-[400px]"></span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <input type="text" id="pdf-search-input" placeholder="Buscar no PDF..." class="pl-10 pr-4 py-2 bg-slate-700 text-white text-sm rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        </div>
                        <button onclick="findNext()" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <button onclick="findPrev()" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <span id="pdf-search-count" class="text-slate-400 text-sm hidden"></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="downloadPdf()" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-download mr-1"></i> Baixar
                        </button>
                        <a id="pdf-open-tab" href="" target="_blank" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors">
                            <i class="fas fa-external-link-alt mr-1"></i> Abrir
                        </a>
                    </div>
                </div>
                <div class="flex-1 overflow-auto">
                    <iframe id="pdf-viewer" class="w-full h-full" src=""></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Search Modal -->
<div id="interactive-search-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="search-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeInteractiveSearchModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl transform transition-all">
                <!-- Modal Header -->
                <div class="px-8 py-6 border-b border-slate-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-sliders-h text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-900">BUSCA INTERATIVA</h3>
                                <p class="text-sm text-slate-500">Construa sua pesquisa com operadores lógicos</p>
                            </div>
                        </div>
                        <button onclick="closeInteractiveSearchModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="px-8 py-6 space-y-6">
                    <!-- Query Preview -->
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200">
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 block">Consulta Construída</label>
                        <div id="query-preview" class="text-lg font-mono text-slate-800 break-all"></div>
                    </div>
                    
                    <!-- Main Input -->
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 block">Termo de Busca</label>
                        <div class="relative">
                            <input type="text" id="interactive-search-input" placeholder="Digite um termo..." 
                                class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl text-lg font-medium text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all"
                                onkeydown="handleInteractiveSearchKeydown(event)">
                            <i class="fas fa-search absolute right-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        </div>
                    </div>
                    
                    <!-- Operator Buttons -->
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 block">Operadores</label>
                        <div class="flex flex-wrap gap-3">
                            <button onclick="addOperator('OR')" class="group flex items-center space-x-2 px-5 py-3 bg-orange-50 border-2 border-orange-200 rounded-xl hover:bg-orange-100 hover:border-orange-300 transition-all">
                                <span class="w-8 h-8 bg-orange-500 text-white rounded-lg flex items-center justify-center font-bold text-sm group-hover:scale-110 transition-transform">OR</span>
                                <span class="font-medium text-orange-700">Inclui qualquer termo</span>
                            </button>
                            <button onclick="addOperator('AND')" class="group flex items-center space-x-2 px-5 py-3 bg-green-50 border-2 border-green-200 rounded-xl hover:bg-green-100 hover:border-green-300 transition-all">
                                <span class="w-8 h-8 bg-green-500 text-white rounded-lg flex items-center justify-center font-bold text-sm group-hover:scale-110 transition-transform">AND</span>
                                <span class="font-medium text-green-700">Inclui todos os termos</span>
                            </button>
                            <button onclick="addOperator('NOT')" class="group flex items-center space-x-2 px-5 py-3 bg-red-50 border-2 border-red-200 rounded-xl hover:bg-red-100 hover:border-red-300 transition-all">
                                <span class="w-8 h-8 bg-red-500 text-white rounded-lg flex items-center justify-center font-bold text-sm group-hover:scale-110 transition-transform">NOT</span>
                                <span class="font-medium text-red-700">Exclui o termo</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Query History / Suggestions -->
                    <div class="bg-indigo-50 rounded-2xl p-4 border border-indigo-100">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-xs font-bold text-indigo-500 uppercase tracking-widest">Dicas</label>
                            <button onclick="clearQuery()" class="text-xs text-indigo-400 hover:text-indigo-600">Limpar tudo</button>
                        </div>
                        <div class="text-sm text-indigo-700 space-y-2">
                            <p><i class="fas fa-info-circle mr-2"></i>Use operadores para refinar sua busca</p>
                            <p><i class="fas fa-lightbulb mr-2"></i>Exemplo: <code class="bg-white px-2 py-1 rounded">termo1 AND termo2</code></p>
                            <p><i class="fas fa-lightbulb mr-2"></i>Para excluir: <code class="bg-white px-2 py-1 rounded">termo NOT exclusão</code></p>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-8 py-6 border-t border-slate-100 flex justify-end space-x-4">
                    <button onclick="closeInteractiveSearchModal()" class="px-6 py-3 text-slate-600 font-medium hover:text-slate-800 transition-colors">
                        Cancelar
                    </button>
                    <button onclick="performInteractiveSearch()" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-xl hover:bg-indigo-600 transition-all shadow-lg shadow-slate-200">
                        <i class="fas fa-search mr-2"></i>EXECUTAR BUSCA
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="min-h-screen bg-[#f8fafc]">
    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-3">
                    <a href="/" class="flex items-center space-x-3 group">
                        <img src="https://kaponline.com.br/logo.jpeg" alt="KapOnline" class="h-8 w-8 rounded-lg shadow-sm group-hover:opacity-80 transition-opacity">
                        <span class="text-xl font-black tracking-tighter text-slate-900 uppercase">KAP<span class="text-indigo-600">JUS</span></span>
                    </a>
                    <span class="text-slate-300 mx-2">/</span>
                    <span class="font-bold text-slate-600 truncate max-w-[200px]"><?php echo htmlspecialchars($case['name']); ?></span>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="inline-flex items-center px-4 py-2 border border-indigo-100 text-sm font-bold rounded-xl text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                        <i class="fas fa-user-plus mr-2"></i> Convidar Advogado
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Sidebar (4 colunas) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Upload Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-black text-slate-900 mb-4 flex items-center">
                        <span class="w-8 h-8 bg-indigo-600 text-white rounded-lg flex items-center justify-center mr-3 text-xs">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </span>
                        ENVIAR DOCUMENTOS
                    </h3>
                    <div class="group relative border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center hover:border-indigo-400 hover:bg-indigo-50/30 transition-all cursor-pointer" onclick="document.getElementById('pdf-input').click()">
                        <div class="space-y-2">
                            <i class="fas fa-file-pdf text-4xl text-slate-300 group-hover:text-indigo-400 transition-colors"></i>
                            <p class="text-sm font-bold text-slate-500 group-hover:text-indigo-600">Arraste seus PDFs aqui</p>
                            <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">ou clique para selecionar</p>
                        </div>
                        <input type="file" id="pdf-input" class="hidden" multiple onchange="handleFiles(this.files)">
                    </div>
                    
                    <!-- Chunked Upload Progress Container -->
                    <div id="upload-progress-container" class="mt-4 space-y-2"></div>
                    
                    <!-- Global Status -->
                    <div id="upload-status" class="mt-4 hidden p-3 bg-indigo-50 rounded-xl text-xs font-bold text-indigo-700 animate-pulse">
                        <i class="fas fa-sync fa-spin mr-2"></i> PROCESSANDO DOCUMENTOS...
                    </div>
                </div>

                <!-- Document List Card -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-lg font-black text-slate-900 mb-4 flex items-center">
                        <span class="w-8 h-8 bg-slate-900 text-white rounded-lg flex items-center justify-center mr-3 text-xs">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        ARQUIVOS DO CASO
                    </h3>
                    <div id="doc-list" class="space-y-2 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        <p class="text-sm text-slate-400 font-medium italic">Nenhum documento processado.</p>
                    </div>
                </div>
            </div>

            <!-- Main Content (8 colunas) -->
            <div class="lg:col-span-8 space-y-6">
                <!-- Search Bar -->
                <div class="bg-white p-3 rounded-3xl shadow-xl border border-slate-200 flex gap-3 items-center">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="search-input" placeholder="Pesquise fatos, nomes ou termos jurídicos..." class="w-full pl-14 pr-6 py-4 bg-slate-50 border-0 rounded-2xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                    <button onclick="performSearch()" class="bg-slate-900 text-white px-6 py-4 rounded-2xl font-bold text-sm tracking-widest hover:bg-indigo-600 transition-all shadow-lg shadow-slate-200 whitespace-nowrap">
                        <i class="fas fa-search mr-2"></i>BUSCAR
                    </button>
                    <button onclick="openInteractiveSearchModal()" class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white px-6 py-4 rounded-2xl font-bold text-sm tracking-wider hover:from-indigo-600 hover:to-purple-700 transition-all shadow-lg shadow-indigo-200 whitespace-nowrap">
                        <i class="fas fa-sliders-h mr-2"></i>INTERATIVO
                    </button>
                </div>

                <!-- Search Results & AI Chat -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Results -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 flex flex-col h-[600px]">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                            <h3 class="text-sm font-black text-slate-900 tracking-widest uppercase">RESULTADOS</h3>
                            <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded">HÍBRIDO</span>
                        </div>
                        <div id="search-results" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
                            <div class="flex flex-col items-center justify-center h-full text-center space-y-4">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-200">
                                    <i class="fas fa-search text-3xl"></i>
                                </div>
                                <p class="text-sm font-bold text-slate-400 uppercase tracking-widest">Aguardando pesquisa...</p>
                            </div>
                        </div>
                    </div>

                    <!-- AI Chat -->
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 flex flex-col h-[600px] overflow-hidden">
                        <div class="p-6 bg-slate-900 text-white flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-indigo-500 rounded-lg flex items-center justify-center text-xs">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <h3 class="text-sm font-black tracking-widest uppercase">ANALISTA JUS</h3>
                            </div>
                            <span class="flex h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        </div>
                        <div id="chat-box" class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50 custom-scrollbar">
                            <div class="bg-white p-4 rounded-2xl rounded-tl-none shadow-sm border border-slate-100">
                                <p class="text-sm text-slate-700 leading-relaxed">Olá, Osvaldo. Sou o <strong>Analista JUS</strong>. Posso analisar os documentos deste caso e extrair informações críticas. Como posso ajudar?</p>
                            </div>
                        </div>
                        <div class="p-4 bg-white border-t border-slate-100">
                            <div class="relative flex items-center">
                                <input type="text" id="chat-input" placeholder="Pergunte ao JUS..." class="w-full pl-5 pr-14 py-4 bg-slate-50 border-0 rounded-2xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                <button onclick="askIA()" class="absolute right-2 p-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-all">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- PDF.js for embedded viewing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

/* Modal animations */
#pdf-modal, #interactive-search-modal {
    animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* PDF search highlight */
.pdf-search-highlight {
    background-color: yellow;
    color: black;
}
</style>

<script>
// Interactive Search Modal Functions
let interactiveQuery = '';

function openInteractiveSearchModal() {
    document.getElementById('interactive-search-modal').classList.remove('hidden');
    updateQueryPreview();
    document.getElementById('interactive-search-input').focus();
}

function closeInteractiveSearchModal() {
    document.getElementById('interactive-search-modal').classList.add('hidden');
}

function addOperator(operator) {
    const input = document.getElementById('interactive-search-input');
    const term = input.value.trim();
    
    if (!term) return;
    
    if (interactiveQuery && interactiveQuery.trim()) {
        if (interactiveQuery.match(/(OR|AND|NOT)$/)) {
            interactiveQuery = interactiveQuery.trim() + ' ' + term;
        } else {
            interactiveQuery += ' ' + operator + ' ' + term;
        }
    } else {
        interactiveQuery = term;
    }
    
    input.value = '';
    updateQueryPreview();
    input.focus();
}

function updateQueryPreview() {
    const preview = document.getElementById('query-preview');
    const input = document.getElementById('interactive-search-input');
    const currentTerm = input.value.trim();
    
    let fullQuery = interactiveQuery;
    if (currentTerm) {
        if (fullQuery && fullQuery.match(/(OR|AND|NOT)$/)) {
            fullQuery += ' ' + currentTerm;
        } else if (fullQuery) {
            fullQuery += ' ' + currentTerm;
        } else {
            fullQuery = currentTerm;
        }
    }
    
    if (fullQuery) {
        // Highlight operators
        fullQuery = fullQuery.replace(/(OR|AND|NOT)/g, '<span class="inline-block px-2 py-0.5 rounded text-xs font-bold ml-1 $1 === \'OR\' ? \'bg-orange-100 text-orange-600\' : ($1 === \'AND\' ? \'bg-green-100 text-green-600\' : \'bg-red-100 text-red-600\')">$1</span>');
        preview.innerHTML = fullQuery;
    } else {
        preview.innerHTML = '<span class="text-slate-400 italic">Digite um termo para começar...</span>';
    }
}

function clearQuery() {
    interactiveQuery = '';
    document.getElementById('interactive-search-input').value = '';
    updateQueryPreview();
}

function handleInteractiveSearchKeydown(event) {
    if (event.key === 'Enter') {
        performInteractiveSearch();
    }
}

function performInteractiveSearch() {
    const input = document.getElementById('interactive-search-input');
    const currentTerm = input.value.trim();
    
    let query = interactiveQuery;
    if (currentTerm) {
        if (query && query.match(/(OR|AND|NOT)$/)) {
            query += ' ' + currentTerm;
        } else if (query) {
            query += ' ' + currentTerm;
        } else {
            query = currentTerm;
        }
    }
    
    if (!query.trim()) return;
    
    document.getElementById('search-input').value = query;
    closeInteractiveSearchModal();
    performSearch();
}

// PDF Viewer Functions
let currentPdfUrl = '';
let currentPdfFilename = '';
let pdfSearchTerm = '';
let pdfSearchResults = [];
let pdfCurrentMatch = 0;

function escapeHtml(text = '') {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function decodeFilename(encoded = '') {
    try {
        return decodeURIComponent(encoded);
    } catch (error) {
        console.warn('Falha ao decodificar nome de arquivo', error);
        return encoded;
    }
}

async function viewPdf(encodedFilename) {
    const filename = decodeFilename(encodedFilename);
    const modal = document.getElementById('pdf-modal');
    const viewer = document.getElementById('pdf-viewer');
    const filenameEl = document.getElementById('pdf-filename');
    const openTab = document.getElementById('pdf-open-tab');
    
    // Check if file is PDF
    if (!filename.toLowerCase().endsWith('.pdf')) {
        alert('Visualização disponível apenas para arquivos PDF.');
        return;
    }
    
    currentPdfFilename = filename;
    currentPdfUrl = '/storage/uploads/' + encodeURIComponent(filename);
    
    filenameEl.textContent = filename;
    openTab.href = currentPdfUrl;
    
    // Use PDF.js embedded viewer with search
    viewer.src = 'https://mozilla.github.io/pdf.js/web/viewer.html?file=' + encodeURIComponent(currentPdfUrl);
    
    modal.classList.remove('hidden');
}

function closePdfModal() {
    document.getElementById('pdf-modal').classList.add('hidden');
    document.getElementById('pdf-viewer').src = '';
    pdfSearchTerm = '';
    pdfSearchResults = [];
    document.getElementById('pdf-search-input').value = '';
    document.getElementById('pdf-search-count').classList.add('hidden');
}

function downloadPdf() {
    const link = document.createElement('a');
    link.href = currentPdfUrl;
    link.download = currentPdfFilename;
    link.click();
}

// PDF.js search functionality
async function findTextInPdf() {
    const searchInput = document.getElementById('pdf-search-input');
    const searchTerm = searchInput.value.trim().toLowerCase();
    const countEl = document.getElementById('pdf-search-count');
    
    if (!searchTerm) {
        pdfSearchResults = [];
        countEl.classList.add('hidden');
        return;
    }
    
    pdfSearchTerm = searchTerm;
    
    // Load PDF document
    const loadingTask = pdfjsLib.getDocument(currentPdfUrl);
    const pdf = await loadingTask.promise;
    
    pdfSearchResults = [];
    
    // Search through all pages
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const page = await pdf.getPage(pageNum);
        const textContent = await page.getTextContent();
        const text = textContent.items.map(item => item.str).join(' ');
        
        // Find all occurrences on this page
        let index = text.toLowerCase().indexOf(searchTerm);
        while (index !== -1) {
            pdfSearchResults.push({ page: pageNum, index: index });
            index = text.toLowerCase().indexOf(searchTerm, index + 1);
        }
    }
    
    if (pdfSearchResults.length > 0) {
        pdfCurrentMatch = 0;
        countEl.textContent = `1/${pdfSearchResults.length}`;
        countEl.classList.remove('hidden');
        highlightMatch();
    } else {
        countEl.textContent = '0/0';
        countEl.classList.remove('hidden');
        alert('Texto não encontrado no documento.');
    }
}

async function highlightMatch() {
    if (pdfSearchResults.length === 0) return;
    
    const result = pdfSearchResults[pdfCurrentMatch];
    const countEl = document.getElementById('pdf-search-count');
    
    countEl.textContent = `${pdfCurrentMatch + 1}/${pdfSearchResults.length}`;
    
    // Send message to PDF.js viewer to highlight and scroll to page
    const viewer = document.getElementById('pdf-viewer');
    viewer.contentWindow.postMessage({
        type: 'find',
        query: pdfSearchTerm,
        phraseSearch: true
    }, '*');
}

function findNext() {
    if (pdfSearchResults.length === 0) {
        findTextInPdf();
        return;
    }
    
    if (pdfCurrentMatch < pdfSearchResults.length - 1) {
        pdfCurrentMatch++;
        highlightMatch();
    }
}

function findPrev() {
    if (pdfSearchResults.length === 0) {
        findTextInPdf();
        return;
    }
    
    if (pdfCurrentMatch > 0) {
        pdfCurrentMatch--;
        highlightMatch();
    }
}

// Listen for PDF search input
document.getElementById('pdf-search-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        findNext();
    }
});

async function fetchDocuments() {
    const docList = document.getElementById('doc-list');
    try {
        const response = await fetch('/api/documents', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: '<?php echo $case_id; ?>' })
        });
        const docs = await response.json();
        
        if (docs.length === 0) {
            docList.innerHTML = '<p class="text-sm text-slate-400 font-medium italic">Nenhum documento processado.</p>';
            return;
        }
        
        docList.innerHTML = docs.map(d => {
            const filename = d.filename || '';
            const safeFilename = escapeHtml(filename);
            const encodedFilename = encodeURIComponent(filename);
            const isPdf = filename.toLowerCase().endsWith('.pdf');

            return `
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl hover:bg-indigo-50 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-file-pdf ${isPdf ? 'text-red-500' : 'text-slate-400'}"></i>
                        <span class="text-sm font-medium text-slate-700 truncate max-w-[120px]">${safeFilename}</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded">${d.page_count} páginas</span>
                        ${isPdf ? `
                            <button onclick="viewPdf('${encodedFilename}')" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                        ` : ''}
                        <button onclick="deleteFile('${encodedFilename}')" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    } catch (e) {
        docList.innerHTML = '<p class="text-sm text-red-400 font-medium italic">Erro ao carregar documentos.</p>';
    }
}

// Delete file functionality
async function deleteFile(encodedFilename) {
    const filename = decodeFilename(encodedFilename);
    if (!confirm(`Tem certeza que deseja excluir "${filename}"? Esta ação não pode ser desfeita.`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/delete_file', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: '<?php echo $case_id; ?>', filename: filename })
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            // Refresh document list
            fetchDocuments();
            // Show success message
            alert('Arquivo excluído com sucesso.');
        } else {
            alert('Erro ao excluir arquivo: ' + (result.message || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro ao comunicar com o servidor: ' + e.message);
    }
}

// ==================== CHUNKED FILE UPLOAD ====================
// Configuration
const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB max
const CASE_ID = '<?php echo $case_id; ?>';

// Upload manager class
class ChunkedUploadManager {
    constructor() {
        this.uploads = new Map();
        this.container = document.getElementById('upload-progress-container');
    }
    
    // Format bytes to human readable
    formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Create upload UI element
    createUploadElement(fileId, filename, fileSize) {
        const div = document.createElement('div');
        div.id = `upload-${fileId}`;
        div.className = 'p-3 bg-indigo-50 rounded-xl';
        div.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center space-x-3 flex-1 min-w-0">
                    <i class="fas fa-file-pdf text-indigo-400"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-indigo-700 truncate">${filename}</p>
                        <p class="text-[10px] text-indigo-400">${this.formatBytes(fileSize)}</p>
                    </div>
                </div>
                <button onclick="uploadManager.cancelUpload('${fileId}')" class="p-1.5 text-indigo-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Cancelar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="relative h-2 bg-indigo-100 rounded-full overflow-hidden">
                <div id="progress-${fileId}" class="absolute left-0 top-0 h-full bg-indigo-500 transition-all duration-300" style="width: 0%"></div>
            </div>
            <div class="flex items-center justify-between mt-1">
                <span id="status-${fileId}" class="text-[10px] font-bold text-indigo-500">Iniciando...</span>
                <span id="percent-${fileId}" class="text-[10px] font-bold text-indigo-400">0%</span>
            </div>
        `;
        this.container.appendChild(div);
        return div;
    }
    
    // Update upload progress
    updateProgress(fileId, percent, status) {
        const progressBar = document.getElementById(`progress-${fileId}`);
        const statusEl = document.getElementById(`status-${fileId}`);
        const percentEl = document.getElementById(`percent-${fileId}`);
        
        if (progressBar) progressBar.style.width = `${percent}%`;
        if (percentEl) percentEl.textContent = `${Math.round(percent)}%`;
        if (statusEl) {
            statusEl.textContent = status;
            statusEl.className = 'text-[10px] font-bold ' + this.getStatusClass(status);
        }
    }
    
    getStatusClass(status) {
        const statusClasses = {
            'Iniciando': 'text-indigo-500',
            'Enviando': 'text-indigo-500',
            'Resumindo': 'text-orange-500',
            'Processando': 'text-purple-500',
            'Concluído': 'text-emerald-500',
            'Erro': 'text-red-500',
            'Pausado': 'text-amber-500'
        };
        return statusClasses[status] || 'text-indigo-500';
    }
    
    // Mark upload as completed
    markCompleted(fileId) {
        const div = document.getElementById(`upload-${fileId}`);
        if (div) {
            div.classList.remove('bg-indigo-50');
            div.classList.add('bg-emerald-50');
            this.updateProgress(fileId, 100, 'Concluído');
        }
    }
    
    // Mark upload as error
    markError(fileId, message) {
        const div = document.getElementById(`upload-${fileId}`);
        if (div) {
            div.classList.remove('bg-indigo-50');
            div.classList.add('bg-red-50');
            this.updateProgress(fileId, 0, 'Erro: ' + message);
        }
    }
    
    // Cancel upload
    async cancelUpload(fileId) {
        const upload = this.uploads.get(fileId);
        if (upload) {
            if (upload.xhr) upload.xhr.abort();
            this.uploads.delete(fileId);
            const div = document.getElementById(`upload-${fileId}`);
            if (div) div.remove();
        }
    }
    
    // Upload a single file with chunking
    async uploadFile(file) {
        const fileId = Math.random().toString(36).substring(2, 11);
        
        // Validate file size
        if (file.size > MAX_FILE_SIZE) {
            alert(`Arquivo muito grande: ${file.name}. Máximo permitido: ${this.formatBytes(MAX_FILE_SIZE)}`);
            return;
        }
        
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        
        // Create UI element
        this.createUploadElement(fileId, file.name, file.size);
        this.updateProgress(fileId, 0, 'Iniciando');
        
        const upload = {
            fileId,
            file,
            uploadId: null,
            uploadedChunks: new Set(),
            totalChunks,
            currentChunk: 0,
            xhr: null
        };
        
        this.uploads.set(fileId, upload);
        
        try {
            // Initialize upload session
            const initResponse = await fetch('/api/upload_init', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    case_id: CASE_ID,
                    filename: file.name,
                    total_size: file.size,
                    total_chunks: totalChunks
                })
            });
            
            if (!initResponse.ok) throw new Error('Falha ao inicializar upload');
            
            const initData = await initResponse.json();
            console.log('[UPLOAD] upload_init response:', initData);
            
            upload.uploadId = initData.upload_id;
            console.log('[UPLOAD] uploadId set to:', upload.uploadId);
            
            if (!upload.uploadId) {
                console.error('[UPLOAD] ERROR: uploadId is null or empty!');
                throw new Error('Invalid upload_id returned from server');
            }
            
            // Check for existing progress and resume if needed
            const statusResponse = await fetch(`/api/upload_status?upload_id=${initData.upload_id}`);
            console.log('[UPLOAD] upload_status response:', statusResponse.ok ? await statusResponse.json() : 'FAILED');
            
            if (statusResponse.ok) {
                const statusData = await statusResponse.json();
                if (statusData.status === 'in_progress') {
                    statusData.missing_chunks.forEach(idx => upload.uploadedChunks.add(idx));
                    upload.currentChunk = Math.min(...Array.from(upload.uploadedChunks));
                    this.updateProgress(fileId, statusData.progress, 'Resumindo');
                }
            }
            
            // Upload chunks sequentially
            console.log('[UPLOAD] Starting uploadChunks, totalChunks:', upload.totalChunks);
            await this.uploadChunks(upload);
            console.log('[UPLOAD] uploadChunks completed');
            
            // Complete upload
            await this.completeUpload(upload);
            
            // Mark as completed
            this.markCompleted(fileId);
            
            // Refresh document list
            await fetchDocuments();
            
            // Clean up after delay
            setTimeout(() => this.cancelUpload(fileId), 3000);
            
        } catch (error) {
            console.error('[UPLOAD] Upload error caught:', error.message, error.stack);
            this.markError(fileId, error.message);
        }
    }
    
    // Upload chunks sequentially
    async uploadChunks(upload) {
        console.log('[UPLOAD] uploadChunks called with:', {
            uploadId: upload.uploadId,
            totalChunks: upload.totalChunks,
            uploadedChunksCount: upload.uploadedChunks.size
        });
        
        const { file, uploadId, uploadedChunks } = upload;
        
        for (let chunkIndex = 0; chunkIndex < upload.totalChunks; chunkIndex++) {
            // Skip already uploaded chunks
            if (uploadedChunks.has(chunkIndex)) {
                console.log('[UPLOAD] Skipping chunk', chunkIndex, '(already uploaded)');
                continue;
            }
            
            console.log('[UPLOAD] Processing chunk', chunkIndex, 'of', upload.totalChunks);
            // Skip already uploaded chunks
            if (uploadedChunks.has(chunkIndex)) continue;
            
            const start = chunkIndex * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunk = file.slice(start, end);
            
            const formData = new FormData();
            formData.append('upload_id', uploadId);
            formData.append('chunk_index', chunkIndex);
            formData.append('filename', file.name);
            formData.append('case_id', CASE_ID);
            formData.append('chunk', chunk);
            
            upload.xhr = new XMLHttpRequest();
            
            console.log('[UPLOAD] Sending XHR for chunk', chunkIndex, 'to /api/upload_chunk');
            
            const chunkPromise = new Promise((resolve, reject) => {
                upload.xhr.upload.onprogress = (event) => {
                    if (event.lengthComputable) {
                        const chunkProgress = (chunkIndex / upload.totalChunks) * 100;
                        const currentProgress = (event.loaded / event.total) / upload.totalChunks * 100;
                        const totalProgress = chunkProgress + currentProgress;
                        this.updateProgress(upload.fileId, totalProgress, 'Enviando');
                    }
                };
                
                upload.xhr.onload = () => {
                    console.log('[UPLOAD] XHR onload, status:', upload.xhr.status, 'response:', upload.xhr.responseText?.substring(0, 200));
                    if (upload.xhr.status >= 200 && upload.xhr.status < 300) {
                        uploadedChunks.add(chunkIndex);
                        resolve();
                    } else {
                        reject(new Error(`Erro no chunk ${chunkIndex}: ${upload.xhr.status}`));
                    }
                };
                
                upload.xhr.onerror = () => {
                    console.error('[UPLOAD] XHR onerror called');
                    reject(new Error('Erro de rede'));
                };
                upload.xhr.ontimeout = () => {
                    console.error('[UPLOAD] XHR ontimeout called');
                    reject(new Error('Timeout'));
                };
                
                upload.xhr.onabort = () => {
                    console.error('[UPLOAD] XHR onabort called');
                };
                
                upload.xhr.open('POST', '/api/upload_chunk');
                upload.xhr.send(formData);
                console.log('[UPLOAD] XHR sent for chunk', chunkIndex);
            });
            
            await chunkPromise;
            upload.currentChunk = chunkIndex + 1;
        }
    }
    
    // Complete upload
    async completeUpload(upload) {
        this.updateProgress(upload.fileId, 100, 'Processando');
        
        const response = await fetch('/api/upload_complete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                upload_id: upload.uploadId,
                case_id: CASE_ID
            })
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.detail || 'Falha ao completar upload');
        }
        
        return response.json();
    }
}

// Initialize upload manager
const uploadManager = new ChunkedUploadManager();

// Handle file selection
async function handleFiles(files) {
    const status = document.getElementById('upload-status');
    status.classList.remove('hidden');
    status.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i> ENVIANDO ARQUIVOS...';
    
    for (const file of files) {
        await uploadManager.uploadFile(file);
    }
    
    status.innerHTML = '<i class="fas fa-check mr-2"></i> DOCUMENTOS INDEXADOS!';
    setTimeout(() => status.classList.add('hidden'), 3000);
}

// Legacy upload handler (fallback for small files)
async function handleUpload(input) {
    const status = document.getElementById('upload-status');
    status.classList.remove('hidden');
    status.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i> PROCESSANDO DOCUMENTOS...';
    
    for (const file of input.files) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('case_id', '<?php echo $case_id; ?>');
        
        try {
            await fetch('/api/upload', { method: 'POST', body: formData });
        } catch (e) { console.error(e); }
    }
    
    status.innerHTML = '<i class="fas fa-check mr-2"></i> DOCUMENTOS INDEXADOS!';
    setTimeout(() => status.classList.add('hidden'), 3000);
    
    // Refresh document list after upload
    await fetchDocuments();
}

async function performSearch() {
    const query = document.getElementById('search-input').value;
    const resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '<div class="flex items-center justify-center h-full"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-600"></i></div>';
    
    try {
        const response = await fetch('/api/search', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: '<?php echo $case_id; ?>', query: query })
        });
        const results = await response.json();
        
        if (results.length === 0) {
            resultsDiv.innerHTML = '<p class="text-slate-400 text-center font-bold uppercase tracking-widest mt-10">Nada encontrado.</p>';
            return;
        }
        
        resultsDiv.innerHTML = results.map(r => `
            <div class="p-5 bg-white rounded-2xl border border-slate-100 shadow-sm hover:border-indigo-200 transition-all">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-1 rounded truncate max-w-[150px]">${r.filename}</span>
                    <span class="text-[10px] font-black text-slate-400">PÁG. ${r.page}</span>
                </div>
                <p class="text-sm text-slate-700 leading-relaxed">${r.snippet}</p>
            </div>
        `).join('');
    } catch (e) { resultsDiv.innerHTML = '<p class="text-red-500">Erro na busca.</p>'; }
}

async function askIA() {
    const input = document.getElementById('chat-input');
    const question = input.value;
    if (!question) return;
    
    const chatBox = document.getElementById('chat-box');
    chatBox.innerHTML += `<div class="bg-indigo-600 p-4 rounded-2xl rounded-tr-none text-white text-sm font-medium self-end ml-10 shadow-lg shadow-indigo-100">${question}</div>`;
    input.value = '';
    chatBox.scrollTop = chatBox.scrollHeight;
    
    try {
        const formData = new FormData();
        formData.append('case_id', '<?php echo $case_id; ?>');
        formData.append('question', question);
        
        const response = await fetch('/api/ask_ia', { method: 'POST', body: formData });
        const data = await response.json();
        
        let answer = data.candidates ? data.candidates[0].content.parts[0].text : "Erro ao processar resposta.";
        chatBox.innerHTML += `<div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-100 shadow-sm text-sm text-slate-700 leading-relaxed mr-10">${answer}</div>`;
        chatBox.scrollTop = chatBox.scrollHeight;
    } catch (e) { console.error(e); }
}

// Update query preview when typing
document.getElementById('interactive-search-input').addEventListener('input', updateQueryPreview);

// Load documents on page load
fetchDocuments();
</script>
