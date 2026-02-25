<?php
// PDF Viewer with source navigation and note creation
// Parameters: file, page, search, case_id

require_once __DIR__ . '/../src/php/auth.php';
require_once __DIR__ . '/../src/php/socket_client.php';

$file = $_GET['file'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = $_GET['search'] ?? '';
$case_id = $_GET['case_id'] ?? '';

// Get case info if case_id provided
$case = null;
if ($case_id) {
    $db = new SQLite3(__DIR__ . '/../database/kapjus.db');
    $stmt = $db->prepare("SELECT id, name FROM cases WHERE id = :id");
    $stmt->bindValue(':id', $case_id, SQLITE3_INTEGER);
    $case = $stmt->execute()->fetchArray();
}

// Get sources from session if available (passed from case_detail)
$sourcesJson = $_GET['sources'] ?? '[]';
$sources = json_decode(urldecode($sourcesJson), true) ?: [];

// Current user
$user = current_user();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizador PDF - <?= htmlspecialchars($file) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        /* PDF Container */
        #pdf-container {
            height: 100vh;
            overflow: auto;
            background: #1e293b;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        
        #pdf-wrapper {
            position: relative;
            background: white;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        
        canvas { display: block; }
        
        /* Floating Panel */
        #source-panel {
            position: fixed;
            right: 20px;
            top: 80px;
            width: 320px;
            max-height: calc(100vh - 120px);
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        #source-panel.collapsed {
            width: 48px;
            height: 48px;
            top: auto;
            bottom: 20px;
            right: 20px;
            border-radius: 24px;
            overflow: hidden;
        }
        
        #source-panel.collapsed .panel-content {
            display: none;
        }
        
        .panel-header {
            padding: 16px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .panel-content {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }
        
        /* Source Items */
        .source-item {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .source-item:hover {
            background: #f1f5f9;
        }
        
        .source-item.active {
            background: #eef2ff;
            border-color: #6366f1;
        }
        
        .source-filename {
            font-weight: 600;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .source-pages {
            font-size: 11px;
            color: #64748b;
        }
        
        .source-page-badge {
            display: inline-block;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 4px;
            margin-bottom: 4px;
            font-size: 11px;
            color: #475569;
        }
        
        .source-page-badge.active {
            background: #6366f1;
            color: white;
        }
        
        /* Note Form */
        .note-form {
            padding: 12px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .note-form textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            resize: none;
            font-family: inherit;
        }
        
        .note-form textarea:focus {
            outline: none;
            border-color: #6366f1;
        }
        
        .note-form button {
            margin-top: 8px;
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .note-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }
        
        /* Search Highlight */
        .highlight {
            background: yellow;
            color: black;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        /* Toolbar */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            padding: 0 20px;
            gap: 16px;
            z-index: 50;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .toolbar-title {
            font-weight: 600;
            color: #1e293b;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .toolbar-search {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 10px;
        }
        
        .toolbar-search input {
            border: none;
            background: transparent;
            outline: none;
            width: 200px;
            font-size: 14px;
        }
        
        .toolbar-btn {
            padding: 8px 16px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: #475569;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        
        .toolbar-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .toolbar-btn.primary {
            background: #6366f1;
            color: white;
        }
        
        .toolbar-btn.primary:hover {
            background: #4f46e5;
        }
        
        /* Navigation */
        .page-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #475569;
        }
        
        .page-nav input {
            width: 60px;
            padding: 6px 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
        }
        
        .page-nav input:focus {
            outline: none;
            border-color: #6366f1;
        }
        
        /* Toast */
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #10b981;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 500;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s;
            z-index: 200;
        }
        
        .toast.show {
            transform: translateX(-50%) translateY(0);
        }
        
        /* Source count badge */
        .source-count {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Toolbar -->
    <div class="toolbar">
        <a href="/case/<?= $case_id ?>" class="toolbar-btn" style="text-decoration: none;">
            <i class="fas fa-arrow-left"></i>
            Voltar
        </a>
        
        <div class="toolbar-title">
            <?= htmlspecialchars($file) ?>
            <?php if ($page > 1): ?>
                <span style="color: #64748b; font-weight: normal;">— p. <?= $page ?></span>
            <?php endif; ?>
        </div>
        
        <div class="page-nav">
            <button onclick="changePage(-1)" class="toolbar-btn"><i class="fas fa-chevron-left"></i></button>
            <input type="number" id="page-input" value="<?= $page ?>" min="1" onchange="goToPage(this.value)">
            <span>/ <span id="total-pages">-</span></span>
            <button onclick="changePage(1)" class="toolbar-btn"><i class="fas fa-chevron-right"></i></button>
        </div>
        
        <div class="toolbar-search">
            <i class="fas fa-search" style="color: #94a3b8;"></i>
            <input type="text" id="search-input" placeholder="Buscar no documento..." value="<?= htmlspecialchars($search) ?>">
            <button onclick="searchInPdf()" class="toolbar-btn primary" style="padding: 6px 12px;">
                <i class="fas fa-search"></i>
            </button>
        </div>
        
        <button onclick="togglePanel()" class="toolbar-btn" id="toggle-panel-btn">
            <i class="fas fa-list-ul"></i>
            Fontes
            <span class="source-count"><?= count($sources) ?></span>
        </button>
    </div>
    
    <!-- PDF Container -->
    <div id="pdf-container">
        <div id="pdf-wrapper">
            <canvas id="pdf-canvas"></canvas>
        </div>
    </div>
    
    <!-- Floating Source Panel -->
    <div id="source-panel" class="<?= empty($sources) ? 'collapsed' : '' ?>">
        <div class="panel-header">
            <div>
                <i class="fas fa-list-ul mr-2"></i>
                <span class="font-semibold">Fontes</span>
            </div>
            <button onclick="togglePanel()" style="background: none; border: none; color: white; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="panel-content">
            <?php if (!empty($sources)): ?>
                <?php 
                // Group sources by filename
                $grouped = [];
                foreach ($sources as $src) {
                    $fn = $src['filename'] ?? 'unknown';
                    if (!isset($grouped[$fn])) {
                        $grouped[$fn] = [];
                    }
                    $grouped[$fn][] = $src;
                }
                ?>
                
                <?php foreach ($grouped as $filename => $srcs): ?>
                    <div class="source-item" data-filename="<?= htmlspecialchars($filename) ?>">
                        <div class="source-filename">
                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                            <?= htmlspecialchars($filename) ?>
                        </div>
                        <div class="source-pages">
                            <?php foreach ($srcs as $src): ?>
                                <?php 
                                $srcPage = $src['page'] ?? 1;
                                $srcSnippet = $src['snippet'] ?? '';
                                $isActive = ($filename === $file && $srcPage === $page);
                                ?>
                                <span class="source-page-badge <?= $isActive ? 'active' : '' ?>"
                                      onclick="goToSource('<?= htmlspecialchars(urlencode($filename)) ?>', <?= $srcPage ?>, '<?= htmlspecialchars(urlencode($srcSnippet)) ?>')">
                                    p. <?= $srcPage ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <div style="text-align: center; color: #94a3b8; padding: 20px;">
                    <i class="fas fa-info-circle text-2xl mb-2"></i>
                    <p>Nenhuma fonte disponível</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Note Form -->
        <div class="note-form">
            <textarea id="note-text" rows="2" placeholder="Criar nota sobre esta página..."></textarea>
            <button onclick="createNote()">
                <i class="fas fa-sticky-note mr-2"></i>
                Criar Nota
            </button>
        </div>
    </div>
    
    <!-- Toast -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle mr-2"></i>
        Nota salva com sucesso!
    </div>
    
    <script>
        // Configuration
        const pdfFile = '/storage/uploads/<?= urlencode($file) ?>';
        const PHP_CASE_ID = <?= $case_id ? $case_id : 'null' ?>;
        const PHP_USER_ID = <?= isset($user['id']) ? $user['id'] : 'null' ?>;
        let pdfDoc = null;
        let currentPage = <?= $page ?>;
        let totalPages = 0;
        let searchTerm = '<?= htmlspecialchars($search) ?>';
        let searchResults = [];
        let currentMatch = 0;
        
        // Current source info for notes
        let currentSource = {
            file: '<?= htmlspecialchars($file) ?>',
            page: <?= $page ?>,
            snippet: '<?= htmlspecialchars($search) ?>'
        };
        
        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            await loadPdf();
            
            // Auto-search if term provided
            if (searchTerm) {
                setTimeout(searchInPdf, 1000);
            }
        });
        
        async function loadPdf() {
            const loadingTask = pdfjsLib.getDocument(pdfFile);
            pdfDoc = await loadingTask.promise;
            totalPages = pdfDoc.numPages;
            
            document.getElementById('total-pages').textContent = totalPages;
            document.getElementById('page-input').max = totalPages;
            
            await renderPage(currentPage);
        }
        
        async function renderPage(pageNum) {
            if (!pdfDoc || pageNum < 1 || pageNum > totalPages) return;
            
            const page = await pdfDoc.getPage(pageNum);
            const canvas = document.getElementById('pdf-canvas');
            const ctx = canvas.getContext('2d');
            
            // Calculate scale for responsive display
            const container = document.getElementById('pdf-container');
            const viewport = page.getViewport({ scale: 1.5 });
            
            // Fit to container width
            const containerWidth = container.clientWidth - 40;
            const scale = containerWidth / viewport.width;
            const scaledViewport = page.getViewport({ scale: scale });
            
            canvas.height = scaledViewport.height;
            canvas.width = scaledViewport.width;
            
            // Add wrapper sizing
            const wrapper = document.getElementById('pdf-wrapper');
            wrapper.style.width = scaledViewport.width + 'px';
            wrapper.style.height = scaledViewport.height + 'px';
            
            const renderContext = {
                canvasContext: ctx,
                viewport: scaledViewport
            };
            
            await page.render(renderContext).promise;
            
            currentPage = pageNum;
            document.getElementById('page-input').value = pageNum;
            
            // Update current source
            currentSource.page = pageNum;
            
            // Highlight active source in panel
            updateActiveSource();
            
            // Re-search if we have a term (page change clears highlights)
            if (searchTerm) {
                setTimeout(searchInPdf, 500);
            }
        }
        
        function changePage(delta) {
            const newPage = currentPage + delta;
            if (newPage >= 1 && newPage <= totalPages) {
                renderPage(newPage);
            }
        }
        
        function goToPage(pageNum) {
            const num = parseInt(pageNum);
            if (num >= 1 && num <= totalPages) {
                renderPage(num);
            } else {
                document.getElementById('page-input').value = currentPage;
            }
        }
        
        function goToSource(filename, page, snippet) {
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('file', filename);
            url.searchParams.set('page', page);
            if (snippet) {
                url.searchParams.set('search', snippet);
            }
            window.history.pushState({}, '', url);
            
            // Update current source
            currentSource.file = decodeURIComponent(filename);
            currentSource.page = page;
            currentSource.snippet = snippet || '';
            
            // Update toolbar
            document.querySelector('.toolbar-title').innerHTML = 
                decodeURIComponent(filename) + 
                (page > 1 ? `<span style="color: #64748b; font-weight: normal;">— p. ${page}</span>` : '');
            
            // Update search input
            document.getElementById('search-input').value = currentSource.snippet;
            searchTerm = currentSource.snippet;
            
            // Render page
            renderPage(page);
        }
        
        function updateActiveSource() {
            document.querySelectorAll('.source-page-badge').forEach(badge => {
                badge.classList.remove('active');
            });
            
            // Find and activate current source
            const activeFile = document.querySelector(`.source-item[data-filename="${currentSource.file}"]`);
            if (activeFile) {
                const badges = activeFile.querySelectorAll('.source-page-badge');
                badges.forEach(badge => {
                    const pageText = badge.textContent.trim();
                    if (pageText === `p. ${currentSource.page}`) {
                        badge.classList.add('active');
                    }
                });
            }
        }
        
        async function searchInPdf() {
            const input = document.getElementById('search-input');
            searchTerm = input.value.trim();
            
            if (!searchTerm || !pdfDoc) return;
            
            searchResults = [];
            
            // Search through all pages
            for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
                const page = await pdfDoc.getPage(pageNum);
                const textContent = await page.getTextContent();
                const text = textContent.items.map(item => item.str).join(' ');
                
                // Find all occurrences on this page
                let index = text.toLowerCase().indexOf(searchTerm.toLowerCase());
                while (index !== -1) {
                    searchResults.push({ page: pageNum, index: index });
                    index = text.toLowerCase().indexOf(searchTerm.toLowerCase(), index + 1);
                }
            }
            
            if (searchResults.length > 0) {
                currentMatch = 0;
                goToMatch(currentMatch);
                showToast(`Encontrado(s) ${searchResults.length} resultado(s)`);
            } else {
                showToast('Nenhum resultado encontrado');
            }
        }
        
        function goToMatch(matchIndex) {
            if (matchIndex >= 0 && matchIndex < searchResults.length) {
                const result = searchResults[matchIndex];
                renderPage(result.page);
            }
        }
        
        function togglePanel() {
            const panel = document.getElementById('source-panel');
            panel.classList.toggle('collapsed');
        }
        
        async function createNote() {
            const noteText = document.getElementById('note-text').value.trim();
            if (!noteText) {
                showToast('Digite uma nota primeiro');
                return;
            }
            
            const noteData = {
                case_id: PHP_CASE_ID,
                text: noteText,
                source_file: currentSource.file,
                source_page: currentSource.page,
                source_snippet: currentSource.snippet,
                
                created_at: new Date().toISOString()
            };
            
            try {
                const response = await fetch('/api/create_note', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(noteData)
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    document.getElementById('note-text').value = '';
                    showToast('Nota salva com sucesso!');
                } else {
                    showToast('Erro ao salvar nota: ' + (result.message || ''));
                }
            } catch (e) {
                console.error('Error creating note:', e);
                showToast('Erro ao salvar nota');
            }
        }
        
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.innerHTML = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                changePage(-1);
            } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                changePage(1);
            } else if (e.key === 'f' && e.ctrlKey) {
                e.preventDefault();
                document.getElementById('search-input').focus();
            }
        });
        
        // Search on Enter
        document.getElementById('search-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                searchInPdf();
            }
        });
    </script>
</body>
</html>
