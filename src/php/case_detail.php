<?php
$stmt = $db->prepare("SELECT * FROM cases WHERE id = :id");
$stmt->bindValue(':id', $case_id, SQLITE3_INTEGER);
$case = $stmt->execute()->fetchArray();
if (!$case) { echo "Caso não encontrado."; exit; }
?>

<!-- Confirm Dialog Modal (reusable) -->
<div id="confirm-modal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="_confirmReject()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-6 space-y-4 z-10">
        <div class="flex items-start gap-4">
            <div id="confirm-icon-wrap" class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 bg-red-100">
                <i id="confirm-icon" class="fas fa-trash text-red-600 text-lg"></i>
            </div>
            <div>
                <h3 id="confirm-title" class="font-bold text-slate-900 text-base">Confirmar exclusão</h3>
                <p id="confirm-message" class="text-sm text-slate-500 mt-1 leading-relaxed"></p>
            </div>
        </div>
        <div class="flex gap-3 pt-2">
            <button onclick="_confirmReject()" class="flex-1 px-4 py-3 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">
                Cancelar
            </button>
            <button id="confirm-ok-btn" onclick="_confirmResolve()" class="flex-1 px-4 py-3 text-sm font-bold text-white bg-red-600 rounded-xl hover:bg-red-700 transition-colors">
                Excluir
            </button>
        </div>
    </div>
</div>

<!-- PDF Viewer Split View Panel -->
<div id="pdf-panel" class="fixed inset-y-0 right-0 z-[90] hidden w-full max-w-2xl bg-slate-900 shadow-2xl transform transition-transform duration-300" aria-labelledby="pdf-modal-title" role="dialog" aria-modal="true">
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700 flex-shrink-0">
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
                        <button onclick="togglePdfFullscreen()" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors" title="Tela cheia">
                            <i class="fas fa-expand"></i>
                        </button>
                        <button onclick="closePdfPanel()" class="ml-2 p-2 text-slate-400 hover:text-white transition-colors">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto bg-slate-800">
                    <iframe id="pdf-viewer" class="w-full h-full" src=""></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen PDF Modal (for toggle) -->
<div id="pdf-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="pdf-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity" onclick="closePdfModal()"></div>
    <div class="fixed inset-0 z-10 overflow-hidden">
        <div class="flex h-full">
            <div class="flex-1 flex flex-col bg-slate-900">
                <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700">
                    <div class="flex items-center space-x-3">
                        <button onclick="closePdfModal()" class="text-white hover:text-red-400 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                        <span id="pdf-filename-modal" class="text-white font-medium truncate max-w-[400px]"></span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <input type="text" id="pdf-search-input-modal" placeholder="Buscar no PDF..." class="pl-10 pr-4 py-2 bg-slate-700 text-white text-sm rounded-lg border-0 focus:ring-2 focus:ring-indigo-500">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        </div>
                        <button onclick="findNext()" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <button onclick="findPrev()" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                        <span id="pdf-search-count-modal" class="text-slate-400 text-sm hidden"></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="downloadPdf()" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-download mr-1"></i> Baixar
                        </button>
                        <a id="pdf-open-tab-modal" href="" target="_blank" class="px-3 py-2 bg-slate-700 text-white text-sm rounded-lg hover:bg-slate-600 transition-colors">
                            <i class="fas fa-external-link-alt mr-1"></i> Abrir
                        </a>
                    </div>
                </div>
                <div class="flex-1 overflow-auto">
                    <iframe id="pdf-viewer-modal" class="w-full h-full" src=""></iframe>
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
                                <h3 class="text-xl font-bold text-slate-900">Busca interativa</h3>
                                <p class="text-sm text-slate-500">Construa sua pesquisa com operadores lógicos</p>
                            </div>
                        </div>
                        <button onclick="closeInteractiveSearchModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body — simplified natural-language builder -->
                <div class="px-8 py-6 space-y-5">
                    <!-- ALL words (AND) -->
                    <div>
                        <label class="text-xs font-bold text-green-700 uppercase tracking-widest mb-2 block flex items-center gap-2">
                            <span class="w-6 h-6 bg-green-500 text-white rounded-md flex items-center justify-center text-[9px] font-black">E</span>
                            Contém TODAS as palavras
                        </label>
                        <input type="text" id="isb-all" placeholder="Ex: recurso sentença" class="w-full px-4 py-3 bg-slate-50 border-2 border-green-100 focus:border-green-400 rounded-xl text-sm font-medium text-slate-900 outline-none transition-all">
                        <p class="text-xs text-slate-400 mt-1">Separe por espaço — todos os termos devem aparecer</p>
                    </div>
                    <!-- ANY word (OR) -->
                    <div>
                        <label class="text-xs font-bold text-orange-700 uppercase tracking-widest mb-2 block flex items-center gap-2">
                            <span class="w-6 h-6 bg-orange-500 text-white rounded-md flex items-center justify-center text-[9px] font-black">OU</span>
                            Contém QUALQUER palavra
                        </label>
                        <input type="text" id="isb-any" placeholder="Ex: petição requerimento" class="w-full px-4 py-3 bg-slate-50 border-2 border-orange-100 focus:border-orange-400 rounded-xl text-sm font-medium text-slate-900 outline-none transition-all">
                        <p class="text-xs text-slate-400 mt-1">Separe por espaço — ao menos um termo deve aparecer</p>
                    </div>
                    <!-- NOT words -->
                    <div>
                        <label class="text-xs font-bold text-red-700 uppercase tracking-widest mb-2 block flex items-center gap-2">
                            <span class="w-6 h-6 bg-red-500 text-white rounded-md flex items-center justify-center text-[9px] font-black">NÃO</span>
                            Não contém
                        </label>
                        <input type="text" id="isb-not" placeholder="Ex: certidão despacho" class="w-full px-4 py-3 bg-slate-50 border-2 border-red-100 focus:border-red-400 rounded-xl text-sm font-medium text-slate-900 outline-none transition-all">
                        <p class="text-xs text-slate-400 mt-1">Separe por espaço — nenhum destes termos deve aparecer</p>
                    </div>
                    <!-- Preview (collapsible) -->
                    <details class="group">
                        <summary class="text-xs font-bold text-slate-400 cursor-pointer select-none hover:text-slate-600 transition-colors">
                            <i class="fas fa-code mr-1"></i> Ver consulta técnica gerada
                        </summary>
                        <div class="mt-2 bg-slate-50 rounded-xl p-3 border border-slate-200">
                            <div id="query-preview" class="text-sm font-mono text-slate-700 break-all"></div>
                        </div>
                    </details>
                    <!-- Hidden legacy input kept for JS compatibility -->
                    <input type="hidden" id="interactive-search-input">
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

<!-- Lawyer Invitation Modal -->
<div id="invitation-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="invitation-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeInvitationModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl transform transition-all">
                <!-- Modal Header -->
                <div class="px-8 py-6 border-b border-slate-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-user-plus text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-slate-900">Convidar advogado</h3>
                                <p class="text-sm text-slate-500">Compartilhe este caso com um colega advogado</p>
                            </div>
                        </div>
                        <button onclick="closeInvitationModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="px-8 py-6 space-y-6">
                    <!-- Invite Form -->
                    <div id="invite-form-section">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 block">Nome do Advogado</label>
                                <input type="text" id="invitee-name" placeholder="Dr. João Silva" 
                                    class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl text-lg font-medium text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 block">E-mail</label>
                                <input type="email" id="invitee-email" placeholder="joao@email.com" 
                                    class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl text-lg font-medium text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2 block">Cargo/Permissão</label>
                            <select id="invitee-role" class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl text-lg font-medium text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition-all">
                                <option value="viewer">Visualizador - Pode apenas visualizar documentos</option>
                                <option value="commenter">Colaborador - Pode visualizar e comentar</option>
                                <option value="editor">Editor - Pode gerenciar documentos</option>
                            </select>
                        </div>
                        
                        <div class="mt-4 p-4 bg-amber-50 rounded-xl border border-amber-200">
                            <p class="text-sm text-amber-700"><i class="fas fa-info-circle mr-2"></i>O advogado receberá um link mágico por e-mail com validade de 48 horas.</p>
                        </div>
                    </div>
                    
                    <!-- Invitations List -->
                    <div id="invitations-list-section" class="hidden">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-bold text-slate-900">Convites Enviados</h4>
                            <button onclick="showInviteForm()" class="px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Novo Convite
                            </button>
                        </div>
                        <div id="invitations-list" class="space-y-3 max-h-[300px] overflow-y-auto">
                            <!-- Invitations will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Access History -->
                    <div id="access-history-section" class="hidden">
                        <h4 class="text-lg font-bold text-slate-900 mb-4">Histórico de Acessos</h4>
                        <div id="access-history-list" class="space-y-3 max-h-[300px] overflow-y-auto">
                            <!-- Access logs will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-8 py-6 border-t border-slate-100 flex justify-between items-center">
                    <div class="flex space-x-3">
                        <button onclick="showInvitationsList()" class="px-4 py-2 text-slate-600 font-medium hover:text-slate-800 transition-colors">
                            <i class="fas fa-list mr-2"></i>Convites
                        </button>
                        <button onclick="showAccessHistory()" class="px-4 py-2 text-slate-600 font-medium hover:text-slate-800 transition-colors">
                            <i class="fas fa-history mr-2"></i>Histórico
                        </button>
                    </div>
                    <div class="flex space-x-4">
                        <button onclick="closeInvitationModal()" class="px-6 py-3 text-slate-600 font-medium hover:text-slate-800 transition-colors">
                            Fechar
                        </button>
                        <button onclick="sendInvitation()" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">
                            <i class="fas fa-paper-plane mr-2"></i>ENVIAR CONVITE
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notes Modal -->
<div id="notes-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="notes-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeNotesModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl transform transition-all max-h-[90vh] flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-sticky-note"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">Notas do Caso</h3>
                                <p class="text-xs text-slate-500">Gerencie suas anotações</p>
                            </div>
                        </div>
                        <button onclick="closeNotesModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-2">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Note Creation Form -->
                    <form id="note-form-modal" onsubmit="createNote(event)" class="bg-slate-50 p-4 rounded-2xl space-y-3">
                        <textarea id="note-text-modal" rows="2" placeholder="Nova nota..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none resize-none" required></textarea>
                        <div class="flex items-center justify-between">
                            <div class="flex gap-1">
                                <button type="button" onclick="setNoteColor('red')" class="note-color-btn w-6 h-6 rounded-full bg-red-500 border-2 border-white ring-2 ring-transparent hover:ring-slate-300 transition-all" data-color="red" title="Vermelho"></button>
                                <button type="button" onclick="setNoteColor('yellow')" class="note-color-btn w-6 h-6 rounded-full bg-yellow-400 border-2 border-white ring-2 ring-transparent hover:ring-slate-300 transition-all" data-color="yellow" title="Amarelo"></button>
                                <button type="button" onclick="setNoteColor('green')" class="note-color-btn w-6 h-6 rounded-full bg-emerald-500 border-2 border-white ring-2 ring-transparent hover:ring-slate-300 transition-all" data-color="green" title="Verde"></button>
                                <button type="button" onclick="setNoteColor('blue')" class="note-color-btn w-6 h-6 rounded-full bg-blue-500 border-2 border-white ring-2 ring-transparent hover:ring-slate-300 transition-all" data-color="blue" title="Azul"></button>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 transition-colors">
                                <i class="fas fa-plus mr-1"></i>Adicionar
                            </button>
                        </div>
                    </form>
                    
                    <!-- Notes List -->
                    <div id="notes-list-modal" class="space-y-3">
                        <!-- Notes will be rendered here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="h-screen overflow-hidden flex flex-col bg-[#f8fafc]">
    <!-- Navbar -->
    <nav class="bg-white border-b border-slate-200 flex-shrink-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center gap-2">
                <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                    <a href="/" class="flex items-center space-x-2 sm:space-x-3 group flex-shrink-0">
                        <img src="https://kaponline.com.br/logo.jpeg" alt="KapOnline" class="h-8 w-8 rounded-lg shadow-sm group-hover:opacity-80 transition-opacity">
                        <span class="text-lg sm:text-xl font-black tracking-tighter text-slate-900 uppercase hidden sm:inline">KAP<span class="text-indigo-600">JUS</span></span>
                    </a>
                    <!-- Breadcrumb -->
                    <nav class="hidden sm:flex items-center gap-1 text-sm text-slate-400 flex-shrink-0" aria-label="Breadcrumb">
                        <a href="/" class="hover:text-indigo-600 transition-colors font-medium">Casos</a>
                        <i class="fas fa-chevron-right text-[9px]"></i>
                    </nav>
                    <!-- Inline editable case title -->
                    <span id="case-title-display" class="font-bold text-slate-600 truncate text-sm sm:text-base cursor-pointer hover:text-indigo-600 transition-colors group flex items-center gap-2" onclick="startEditCaseTitle()" title="Clique para editar">
                        <span class="border-b border-dashed border-slate-300 group-hover:border-indigo-400 transition-colors"><?php echo htmlspecialchars($case['name']); ?></span>
                        <i class="fas fa-pencil-alt text-[10px] text-slate-300 group-hover:text-indigo-600 transition-all"></i>
                    </span>
                    <form id="case-title-form" class="hidden items-center gap-2 flex-1 min-w-0" onsubmit="saveCaseTitle(event)">
                        <input type="text" id="case-title-input" value="<?php echo htmlspecialchars($case['name']); ?>" class="flex-1 min-w-0 text-sm font-semibold bg-slate-50 border border-indigo-300 rounded-lg px-3 py-1 text-slate-900 focus:ring-2 focus:ring-indigo-400 outline-none">
                        <button type="submit" class="text-xs bg-indigo-600 text-white px-3 py-1 rounded-lg font-bold hover:bg-indigo-700 transition-colors whitespace-nowrap">Salvar</button>
                        <button type="button" onclick="cancelEditCaseTitle()" class="text-xs text-slate-400 hover:text-slate-600 px-2 py-1">Cancelar</button>
                    </form>
                </div>
                <div class="flex items-center space-x-2 sm:space-x-4 flex-shrink-0">
                    <!-- Notes Icon Button -->
                    <button onclick="openNotesModal()" class="inline-flex items-center px-3 sm:px-4 py-2 border border-amber-100 text-xs sm:text-sm font-semibold rounded-xl text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors" title="Notas do caso">
                        <i class="fas fa-sticky-note sm:mr-2"></i> <span class="hidden sm:inline">Notas</span>
                    </button>
                    <button onclick="openInvitationModal()" class="inline-flex items-center px-3 sm:px-4 py-2 border border-indigo-100 text-xs sm:text-sm font-semibold rounded-xl text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                        <i class="fas fa-user-plus sm:mr-2"></i> <span class="hidden sm:inline">Convidar advogado</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 overflow-hidden">
        <div class="h-full">
            <div class="grid grid-cols-12 gap-4 h-full p-4">
            
            <!-- Sidebar (Documentos) - Desktop always visible, Mobile via tabs -->
            <div class="col-span-3 flex flex-col gap-4 overflow-hidden hidden md:flex">
                
                <!-- Sumário Executivo Card -->
                <div class="mobile-tab-panel" data-mobile-section="arquivos">
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-sm border border-slate-200 p-4 sm:p-6">
                    <h3 class="text-xs font-black text-slate-900 mb-4 flex items-center tracking-widest uppercase">
                        <span class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-lg flex items-center justify-center mr-3 text-xs">
                            <i class="fas fa-gavel"></i>
                        </span>
                        Sumário Executivo
                    </h3>
                    
                    <!-- Fatos Relevantes -->
                    <div id="executive-facts" class="mb-4">
                        <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-indigo-400"></i>
                            Fatos Relevantes
                        </h4>
                        <div class="bg-indigo-50 rounded-xl p-3 space-y-2">
                            <p class="text-xs text-indigo-600 font-medium animate-pulse">
                                <i class="fas fa-circle-notch fa-spin mr-1"></i>Analisando documentos...
                            </p>
                        </div>
                    </div>
                    
                    <!-- Partes Envolvidas -->
                    <div id="executive-parties" class="mb-4">
                        <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i class="fas fa-users text-purple-400"></i>
                            Partes Envolvidas
                        </h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
                                <span class="text-xs font-medium text-slate-600">Autor</span>
                                <span class="text-[10px] text-slate-400">Não identificado</span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
                                <span class="text-xs font-medium text-slate-600">Réu</span>
                                <span class="text-[10px] text-slate-400">Não identificado</span>
                            </div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
                                <span class="text-xs font-medium text-slate-600">Juiz</span>
                                <span class="text-[10px] text-slate-400">Não identificado</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status da Prova -->
                    <div id="executive-proof-status">
                        <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                            <i class="fas fa-file-signature text-emerald-400"></i>
                            Status da Prova
                        </h4>
                        <div id="proof-status-indicator" class="flex items-center gap-2 p-3 bg-emerald-50 rounded-xl">
                            <div class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></div>
                            <span class="text-xs font-bold text-emerald-700">Aguardando documentos</span>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Upload Card - Mobile: show when 'arquivos' tab active -->
                <div class="mobile-tab-panel" data-mobile-section="arquivos">
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-sm border border-slate-200 p-4 sm:p-6">
                    <h3 class="text-xs font-black text-slate-900 mb-4 flex items-center tracking-widest uppercase">
                        <span class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center mr-3 text-xs">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </span>
                        Enviar documentos
                    </h3>
                    <div class="group relative border-2 border-dashed border-slate-200 rounded-2xl p-6 sm:p-8 text-center bg-slate-50/50 hover:border-indigo-400 hover:bg-indigo-50/30 transition-all cursor-pointer" onclick="document.getElementById('pdf-input').click()">
                        <div class="space-y-2">
                            <i class="fas fa-file-pdf text-3xl sm:text-4xl text-slate-300 group-hover:text-indigo-400 transition-colors"></i>
                            <p class="text-xs sm:text-sm font-bold text-slate-500 group-hover:text-indigo-600">Arraste seus PDFs aqui</p>
                            <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">ou clique para selecionar</p>
                        </div>
                        <input type="file" id="pdf-input" class="hidden" multiple onchange="handleFiles(this.files)" accept=".pdf">
                    </div>
                    
                    <!-- Chunked Upload Progress Container -->
                    <div id="upload-progress-container" class="mt-4 space-y-2"></div>
                    
                    <!-- Global Status -->
                    <div id="upload-status" class="mt-4 hidden p-3 bg-indigo-50 rounded-xl text-xs font-bold text-indigo-700 animate-pulse">
                        <i class="fas fa-sync fa-spin mr-2"></i> PROCESSANDO DOCUMENTOS...
                    </div>
                </div>
                </div>

                <!-- Document List Card -->
                <div class="mobile-tab-panel flex-1 overflow-hidden" data-mobile-section="arquivos">
                <div class="bg-white rounded-2xl sm:rounded-3xl shadow-sm border border-slate-200 p-4 sm:p-6 h-full flex flex-col">
                    <h3 class="text-xs font-black text-slate-900 mb-4 flex items-center tracking-widest uppercase flex-shrink-0">
                        <span class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center mr-3 text-xs">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        Arquivos do caso
                    </h3>
                    <div id="doc-list" class="flex-1 overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                        <!-- empty state shown until fetchDocuments() runs -->
                        <div id="doc-empty-state" class="flex flex-col items-center justify-center py-8 text-center space-y-3">
                            <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-file-upload text-2xl text-slate-200"></i>
                            </div>
                            <p class="text-sm font-bold text-slate-400">Nenhum documento ainda</p>
                            <p class="text-xs text-slate-300">Arraste um PDF acima para começar</p>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Centro (Chat/Busca) -->
            <div class="col-span-12 md:col-span-9 flex flex-col overflow-hidden">
                <div class="mobile-tab-panel active flex-1 flex flex-col overflow-hidden" data-mobile-section="busca">
                    <div class="space-y-4 flex-1 flex flex-col overflow-hidden">
                        <!-- Filtros de metadados (apenas modo busca) -->
                        <div id="search-filters-bar" class="bg-white rounded-2xl border border-slate-100 px-5 py-3 flex flex-wrap gap-4 items-center shadow-sm">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest shrink-0">Filtrar arquivos</span>
                            <div class="flex-1 min-w-[150px] relative">
                                <i class="fas fa-file-alt absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-[10px]"></i>
                                <input type="text" id="filter-filename" placeholder="Nome..." class="w-full text-xs bg-slate-50 border-0 rounded-lg pl-8 pr-3 py-2 text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none">
                            </div>
                            <select id="filter-filetype" class="text-xs bg-slate-50 border-0 rounded-lg px-3 py-2 text-slate-700 focus:ring-2 focus:ring-indigo-400 outline-none min-w-[110px]">
                                <option value="">Todos os tipos</option>
                                <option value="pdf">PDF</option>
                                <option value="docx">DOCX</option>
                                <option value="txt">TXT</option>
                                <option value="xlsx">XLSX</option>
                            </select>
                            <button onclick="clearSearchFilters()" class="text-[10px] font-bold text-slate-400 hover:text-red-500 transition-colors uppercase tracking-wider">
                                <i class="fas fa-times mr-1"></i>Limpar
                            </button>
                        </div>

                        <!-- Results Area (Unified for Search and IA) -->
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 flex flex-col flex-1 overflow-hidden min-h-0">
                            <div class="p-4 sm:p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 rounded-t-3xl flex-shrink-0">
                                <div class="flex items-center gap-3">
                                    <div id="results-icon" class="w-8 h-8 bg-slate-900 text-white rounded-lg flex items-center justify-center text-xs">
                                        <i class="fas fa-list-ul"></i>
                                    </div>
                                    <h3 id="results-title" class="text-xs sm:text-sm font-bold text-slate-900 tracking-widest uppercase">RESULTADOS</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button id="ia-clear-btn" onclick="clearIAHistory()" class="hidden text-[10px] font-medium text-slate-400 hover:text-slate-600 transition-colors px-2 py-1 rounded-lg hover:bg-slate-100" title="Limpar histórico">
                                        <i class="fas fa-eraser mr-1"></i>Limpar
                                    </button>
                                    <span id="search-count-badge" class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-3 py-1.5 rounded-full hidden"></span>
                                </div>
                            </div>
                            
                            <div id="search-results" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 custom-scrollbar min-h-0">
                                <!-- Welcome / Empty state -->
                                <div id="empty-results-state" class="flex flex-col items-center justify-center h-full text-center space-y-6 py-12">
                                    <div class="relative">
                                        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center text-slate-200">
                                            <i class="fas fa-search text-5xl"></i>
                                        </div>
                                        <div class="absolute -bottom-2 -right-2 w-10 h-10 bg-white rounded-2xl shadow-lg border border-slate-100 flex items-center justify-center text-indigo-500">
                                            <i class="fas fa-robot text-lg"></i>
                                        </div>
                                    </div>
                                    <div class="max-w-xs space-y-2">
                                        <p class="text-base font-bold text-slate-900 uppercase tracking-widest">Aguardando sua pesquisa</p>
                                        <p class="text-sm text-slate-500">Use a busca rápida para encontrar termos ou pergunte à IA para analisar o caso.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="search-load-more" class="hidden border-t border-slate-100 p-3 text-center bg-slate-50/30 flex-shrink-0">
                                <button onclick="loadMoreResults()" class="inline-flex items-center px-6 py-2 bg-white border border-slate-200 rounded-full text-xs font-bold text-slate-600 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm">
                                    <i class="fas fa-chevron-down mr-2"></i>Carregar mais resultados
                                </button>
                            </div>
                            
                            <!-- Search Bar moved to footer -->
                            <div id="search-bar-container" class="p-3 sm:p-4 border-t border-slate-100 bg-slate-50/30 flex-shrink-0">
                                <div class="flex items-center gap-2">
                                    <!-- Mode Toggle: Left side (Search/AI) -->
                                    <div class="flex items-center gap-1 p-1 rounded-lg" id="mode-toggle-container">
                                        <button id="mode-search-btn" onclick="setSearchMode('search')" class="mode-toggle-btn w-8 h-8 flex items-center justify-center rounded-md text-xs font-bold transition-all bg-emerald-500 text-white shadow-sm" title="Busca Simples">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <button id="mode-ia-btn" onclick="setSearchMode('ia')" class="mode-toggle-btn w-8 h-8 flex items-center justify-center rounded-md text-xs font-bold transition-all bg-slate-300 text-slate-600 hover:bg-purple-500 hover:text-white" title="Busca IA">
                                            <i class="fas fa-brain"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Input Field -->
                                    <div class="flex-1 relative">
                                        <i id="unified-icon" class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-xs"></i>
                                        <input type="text" id="unified-input" placeholder="Pesquise ou pergunte ao Dr. Jus..." class="w-full pl-9 pr-3 py-2.5 bg-emerald-50 border-2 border-emerald-200 rounded-xl text-sm text-slate-900 font-medium focus:ring-2 focus:ring-emerald-400 focus:border-transparent outline-none transition-all shadow-sm" onkeydown="handleUnifiedKeydown(event)">
                                    </div>
                                    
                                    <!-- Advanced Search Button -->
                                    <button onclick="openInteractiveSearchModal()" class="flex-none text-slate-400 hover:text-indigo-600 p-2 rounded-lg hover:bg-indigo-50 transition-all" title="Busca Avançada">
                                        <i class="fas fa-sliders-h text-sm"></i>
                                    </button>
                                    
                                    <!-- Submit Button (compact) -->
                                    <button id="unified-submit-btn" onclick="dispatchUnifiedSearch()" class="bg-indigo-600 text-white w-9 h-9 flex items-center justify-center rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all shadow-md shadow-indigo-200 active:scale-95">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile Bottom Navigation Bar -->
        <nav id="mobile-bottom-nav" class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 shadow-lg z-50 px-2 pb-safe">
            <div class="flex items-center justify-around h-16">
                <button data-mobile-tab-btn data-tab="busca" class="flex flex-col items-center justify-center flex-1 h-full py-2 text-slate-500 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-search text-lg mb-1"></i>
                    <span class="text-[10px] font-medium">Pesquisa</span>
                </button>
                <button onclick="openNotesModal();" class="flex flex-col items-center justify-center flex-1 h-full py-2 text-slate-500 hover:text-amber-600 transition-colors">
                    <i class="fas fa-sticky-note text-lg mb-1"></i>
                    <span class="text-[10px] font-medium">Notas</span>
                </button>
                <button data-mobile-tab-btn data-tab="arquivos" class="flex flex-col items-center justify-center flex-1 h-full py-2 text-slate-500 hover:text-indigo-600 transition-colors">
                    <i class="fas fa-folder-open text-lg mb-1"></i>
                    <span class="text-[10px] font-medium">Arquivos</span>
                </button>
            </div>
        </nav>
        
        <!-- Mobile tab bar removed for SPA layout -->
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

@media (max-width: 767px) {
    /* Sidebar - hidden by default on mobile, shown via tabs */
    .col-span-3 {
        display: none !important;
    }
    
    .col-span-3.mobile-visible {
        display: flex !important;
    }

    /* Mobile tab panels */
    .mobile-tab-panel {
        display: none;
    }

    .mobile-tab-panel.active {
        display: block;
    }

    /* Adjust main content for bottom nav */
    main {
        padding-bottom: 4rem;
    }
    
    .grid-cols-12 {
        display: flex;
        flex-direction: column;
    }
    
    .col-span-12.md\:col-span-9 {
        width: 100%;
    }

    #mobile-tab-bar button.active {
        color: #312e81;
    }

    #mobile-tab-bar button.active i {
        color: #312e81;
    }

    /* WhatsApp-style mobile chat layout */
    .col-span-12.md\:col-span-9 {
        height: calc(100vh - 4rem - 64px) !important;
        display: flex;
        flex-direction: column;
    }

    .mobile-tab-panel.active {
        height: 100%;
        display: flex !important;
        flex-direction: column;
    }

    .mobile-tab-panel.active .space-y-4 {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .mobile-tab-panel.active .bg-white.rounded-3xl {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* Make search results scrollable, input fixed at bottom */
    #search-results {
        flex: 1;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        min-height: 0 !important;
    }

    #search-bar-container {
        flex-shrink: 0 !important;
        background: white !important;
        border-top: 1px solid #e2e8f0 !important;
        padding: 12px !important;
        position: sticky;
        bottom: 0;
    }

    /* Hide filters bar on mobile in chat mode */
    #search-filters-bar {
        display: none !important;
    }

    /* Mobile mode toggle - distinct colors for each mode */
    .mode-toggle-btn {
        width: 36px !important;
        height: 36px !important;
    }
}

#mobile-tab-bar button:hover {
    background: #f8fafc;
}

#mobile-tab-bar {
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}

/* Modal animations */
#pdf-modal, #pdf-panel, #interactive-search-modal, #invitation-modal, #notes-modal {
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

/* Split View Panel */
#pdf-panel {
    transition: transform 0.3s ease-in-out;
}

#pdf-panel.hidden {
    transform: translateX(100%);
}

/* Main content adjustment for split view */
.col-span-12.mr-\[34rem\] {
    margin-right: 34rem;
}

@media (max-width: 1280px) {
    .col-span-12.mr-\[34rem\] {
        margin-right: 0;
    }
}

/* Enhanced Typing Indicator with rotating messages */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.25rem;
    border-radius: 1rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e4e7ec;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08), 0 4px 10px rgba(99, 102, 241, 0.05);
}

.typing-indicator strong {
    font-size: 0.65rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #64748b;
    min-width: 180px;
}

.typing-dots {
    display: flex;
    gap: 0.45rem;
}

.typing-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    animation: typingPulse 1s infinite ease-in-out;
    box-shadow: 0 0 8px rgba(99, 102, 241, 0.4);
}

.typing-dot:nth-child(2) {
    animation-delay: 0.13s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.26s;
}

@keyframes typingPulse {
    0%,
    100% {
        transform: scale(0.76);
        opacity: 0.4;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
    /* Mobile typing indicator - more visible */
    .typing-indicator {
        padding: 1rem 1.5rem !important;
        margin: 0.5rem 0 !important;
    }
    
    .typing-indicator strong {
        font-size: 0.75rem !important;
        min-width: auto !important;
    }
    
    /* Ensure typing indicator is always visible on mobile */
    @media (max-width: 767px) {
        #search-results {
            scroll-behavior: smooth;
        }
        
        .typing-indicator {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%) !important;
            border: 2px solid #6366f1 !important;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2) !important;
        }
    }
}

/* Skeleton loading animation */
.skeleton {
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 0.5rem;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-line {
    height: 12px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 6px;
    margin-bottom: 8px;
}

.skeleton-line:last-child {
    width: 70%;
}
</style>

<script>
// Interactive Search Modal Functions (simplified natural-language builder)
let interactiveQuery = ''; // kept for legacy compat

function openInteractiveSearchModal() {
    document.getElementById('interactive-search-modal').classList.remove('hidden');
    updateQueryPreview();
    document.getElementById('isb-all').focus();
}

function closeInteractiveSearchModal() {
    document.getElementById('interactive-search-modal').classList.add('hidden');
}

function _isbVal(id) {
    return (document.getElementById(id)?.value || '').trim();
}

function _buildIsbQuery() {
    const allTerms = _isbVal('isb-all').split(/\s+/).filter(Boolean);
    const anyTerms = _isbVal('isb-any').split(/\s+/).filter(Boolean);
    const notTerms = _isbVal('isb-not').split(/\s+/).filter(Boolean);
    const parts = [];
    if (allTerms.length) parts.push(allTerms.map(t => `"${t}"`).join(' AND '));
    if (anyTerms.length) parts.push('(' + anyTerms.map(t => `"${t}"`).join(' OR ') + ')');
    if (notTerms.length) parts.push(notTerms.map(t => `NOT "${t}"`).join(' '));
    return parts.join(' AND ');
}

function updateQueryPreview() {
    const preview = document.getElementById('query-preview');
    if (!preview) return;
    const q = _buildIsbQuery();
    preview.textContent = q || '(preencha os campos acima)';
}

// Attach live preview to the 3 inputs
['isb-all', 'isb-any', 'isb-not'].forEach(id => {
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById(id)?.addEventListener('input', updateQueryPreview);
    });
});

function clearQuery() {
    ['isb-all', 'isb-any', 'isb-not'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    updateQueryPreview();
}

function performInteractiveSearch() {
    const query = _buildIsbQuery();
    if (!query.trim()) return;
    closeInteractiveSearchModal();
    // Use unified search pipeline
    _unifiedPerformSearch(query);
}

// addOperator kept as no-op for any residual references
function addOperator() {}

// PDF Viewer Functions - Split View
let currentPdfUrl = '';
let currentPdfFilename = '';
let pdfSearchTerm = '';
let pdfSearchResults = [];
let pdfCurrentMatch = 0;
let pdfViewMode = 'split'; // 'split' or 'fullscreen'

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

async function viewPdf(encodedFilename, page = null) {
    const filename = decodeFilename(encodedFilename);
    
    if (!filename.toLowerCase().endsWith('.pdf')) {
        alert('Visualização disponível apenas para arquivos PDF.');
        return;
    }

    currentPdfFilename = filename;
    currentPdfUrl = '/storage/uploads/' + encodeURIComponent(filename);
    
    const pageHash = page ? `#page=${page}` : '';
    const pdfSrc = currentPdfUrl + pageHash;

    // Use split view by default
    openPdfPanel(filename, pdfSrc, page);
}

function openPdfPanel(filename, pdfSrc, page = null) {
    const panel = document.getElementById('pdf-panel');
    const viewer = document.getElementById('pdf-viewer');
    const filenameEl = document.getElementById('pdf-filename');
    const openTab = document.getElementById('pdf-open-tab');

    filenameEl.textContent = filename + (page ? ` — p. ${page}` : '');
    openTab.href = currentPdfUrl;
    viewer.src = pdfSrc;

    panel.classList.remove('hidden');
    
    // Add split-view class to main content for layout adjustment
    document.querySelector('.col-span-12').classList.add('mr-[34rem]');
    
    pdfViewMode = 'split';
}

function closePdfPanel() {
    const panel = document.getElementById('pdf-panel');
    const viewer = document.getElementById('pdf-viewer');
    
    panel.classList.add('hidden');
    viewer.src = '';
    
    // Remove split-view class
    document.querySelector('.col-span-12')?.classList.remove('mr-[34rem]');
    
    pdfViewMode = 'split';
}

function togglePdfFullscreen() {
    if (pdfViewMode === 'split') {
        // Switch to fullscreen
        closePdfPanel();
        const modal = document.getElementById('pdf-modal');
        const viewer = document.getElementById('pdf-viewer-modal');
        const filenameEl = document.getElementById('pdf-filename-modal');
        const openTab = document.getElementById('pdf-open-tab-modal');
        
        filenameEl.textContent = currentPdfFilename;
        openTab.href = currentPdfUrl;
        viewer.src = currentPdfUrl;
        
        modal.classList.remove('hidden');
        pdfViewMode = 'fullscreen';
    } else {
        // Switch to split view
        closePdfModal();
        openPdfPanel(currentPdfFilename, currentPdfUrl);
    }
}

// Open a PDF source from Dr. Jus citation — always opens at the cited page
function openPdfAtPage(encodedFilename, page) {
    viewPdf(encodedFilename, page || null);
}

function closePdfModal() {
    const modal = document.getElementById('pdf-modal');
    const viewer = document.getElementById('pdf-viewer-modal');
    
    if (modal) {
        modal.classList.add('hidden');
        if (viewer) viewer.src = '';
    }
    
    pdfSearchTerm = '';
    pdfSearchResults = [];
    
    const searchInput = document.getElementById('pdf-search-input');
    if (searchInput) searchInput.value = '';
    
    const searchCount = document.getElementById('pdf-search-count');
    if (searchCount) searchCount.classList.add('hidden');
    
    // Reset view mode
    pdfViewMode = 'split';
}

function downloadPdf() {
    const link = document.createElement('a');
    link.href = currentPdfUrl;
    link.download = currentPdfFilename;
    link.click();
}

function focusDocument(encodedFilename, page = null) {
    const filename = decodeFilename(encodedFilename);

    // If it's a PDF and we have a page, open directly in the viewer
    if (filename.toLowerCase().endsWith('.pdf') && page) {
        openPdfAtPage(encodedFilename, page);
        return;
    }

    // For non-PDF or no page: highlight the doc in the list
    const docList = document.getElementById('doc-list');
    if (!docList) return;
    const target = docList.querySelector(`[data-doc-filename="${encodedFilename}"]`);
    docList.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (target) {
        target.classList.add('ring-2', 'ring-indigo-400', 'ring-offset-1', 'ring-offset-white');
        setTimeout(() => {
            target.classList.remove('ring-2', 'ring-indigo-400', 'ring-offset-1', 'ring-offset-white');
        }, 2200);
    }
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
            docList.innerHTML = `
                <div class="flex flex-col items-center justify-center py-8 text-center space-y-3">
                    <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-file-upload text-2xl text-slate-200"></i>
                    </div>
                    <p class="text-sm font-bold text-slate-400">Nenhum documento ainda</p>
                    <p class="text-xs text-slate-300">Arraste um PDF acima para começar</p>
                </div>`;
            return;
        }
        
        docList.innerHTML = docs.map(d => {
            const filename = d.filename || '';
            const safeFilename = escapeHtml(filename);
            const encodedFilename = encodeURIComponent(filename);
            const isPdf = filename.toLowerCase().endsWith('.pdf');

            return `
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl hover:bg-indigo-50 transition-colors group" data-doc-filename="${encodedFilename}">
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
    const confirmed = await showConfirm({
        title: 'Excluir documento',
        message: `"${filename}" será removido permanentemente do caso. Esta ação não pode ser desfeita.`,
        okLabel: 'Excluir',
        icon: 'fas fa-trash',
        iconBg: 'bg-red-100',
        iconColor: 'text-red-600',
    });
    if (!confirmed) return;

    try {
        const response = await fetch('/api/delete_file', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: '<?php echo $case_id; ?>', filename: filename })
        });
        const result = await response.json();
        if (result.status === 'success') {
            fetchDocuments();
        } else {
            await showConfirm({
                title: 'Erro ao excluir',
                message: result.message || 'Erro desconhecido. Tente novamente.',
                okLabel: 'OK',
                okClass: 'bg-slate-700 hover:bg-slate-800',
                icon: 'fas fa-exclamation-circle',
                iconBg: 'bg-orange-100',
                iconColor: 'text-orange-600',
            });
        }
    } catch (e) {
        await showConfirm({
            title: 'Erro de conexão',
            message: 'Não foi possível comunicar com o servidor: ' + e.message,
            okLabel: 'OK',
            okClass: 'bg-slate-700 hover:bg-slate-800',
            icon: 'fas fa-wifi text-lg',
            iconBg: 'bg-orange-100',
            iconColor: 'text-orange-600',
        });
    }
}

// ==================== CHUNKED FILE UPLOAD ====================
// Configuration
const CHUNK_SIZE = 1024 * 1024; // 1MB chunks
const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB max
const CASE_ID = '<?php echo $case_id; ?>';
const CURRENT_USER_EMAIL = '<?php echo addslashes(current_user()['email'] ?? ''); ?>';
const CURRENT_USER_NAME  = '<?php echo addslashes(current_user()['name']  ?? ''); ?>';

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
            console.log('[UPLOAD] upload_status response:', statusResponse.ok ? await statusResponse.clone().json() : 'FAILED');
            
            if (statusResponse.ok) {
                const statusData = await statusResponse.json();
                // Only mark chunks as uploaded if the server confirms they exist
                if (statusData.uploaded_chunks && statusData.uploaded_chunks > 0) {
                    // Server returns count, but we don't have the list - skip resuming
                    // Just show progress
                    console.log('[UPLOAD] Server has', statusData.uploaded_chunks, 'chunks already uploaded');
                }
            }
            
            // Upload chunks - always start from 0 to ensure all chunks are uploaded
            
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
        
        return response.clone().json();
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

// ==================== CONFIRM DIALOG ====================
let _confirmResolve = null;
let _confirmReject = null;

function showConfirm({ title = 'Confirmar', message = '', okLabel = 'Confirmar', okClass = 'bg-red-600 hover:bg-red-700', icon = 'fas fa-exclamation-triangle', iconBg = 'bg-red-100', iconColor = 'text-red-600' } = {}) {
    return new Promise((resolve, reject) => {
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        document.getElementById('confirm-ok-btn').textContent = okLabel;
        document.getElementById('confirm-ok-btn').className = `flex-1 px-4 py-3 text-sm font-bold text-white rounded-xl transition-colors ${okClass}`;
        document.getElementById('confirm-icon').className = `${icon} text-lg ${iconColor}`;
        document.getElementById('confirm-icon-wrap').className = `w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 ${iconBg}`;
        const modal = document.getElementById('confirm-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        _confirmResolve = () => { _closeConfirm(); resolve(true); };
        _confirmReject  = () => { _closeConfirm(); resolve(false); };
    });
}

function _closeConfirm() {
    const modal = document.getElementById('confirm-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// ==================== UNIFIED SEARCH MODE ====================
let _searchMode = 'search'; // 'search' | 'ia'

function setSearchMode(mode) {
    _searchMode = mode;
    const searchBtn = document.getElementById('mode-search-btn');
    const iaBtn = document.getElementById('mode-ia-btn');
    const icon = document.getElementById('unified-icon');
    const input = document.getElementById('unified-input');
    const submitBtn = document.getElementById('unified-submit-btn');
    const filtersBar = document.getElementById('search-filters-bar');
    const resultsTitle = document.getElementById('results-title');
    const resultsIcon = document.getElementById('results-icon');

    // Compact toggle button styles - WhatsApp style distinct colors
    const activeToggleClass = 'mode-toggle-btn w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all bg-emerald-500 text-white shadow-sm';
    const inactiveToggleClass = 'mode-toggle-btn w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all bg-slate-300 text-slate-600 hover:bg-purple-500 hover:text-white';

    const iaClearBtn = document.getElementById('ia-clear-btn');
    // Update toggle container background based on mode
    const toggleContainer = document.getElementById('mode-toggle-container');
    
    if (mode === 'search') {
        searchBtn.className = activeToggleClass;
        iaBtn.className = inactiveToggleClass;
        // Search mode: green colors
        icon.className = 'fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 text-xs';
        input.placeholder = 'Pesquise fatos, nomes ou termos jurídicos...';
        input.className = 'w-full pl-9 pr-3 py-2.5 bg-emerald-50 border-2 border-emerald-200 rounded-xl text-sm text-slate-900 font-medium focus:ring-2 focus:ring-emerald-400 focus:border-transparent outline-none transition-all shadow-sm';
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        submitBtn.className = 'bg-emerald-600 text-white w-9 h-9 flex items-center justify-center rounded-xl font-bold text-sm hover:bg-emerald-700 transition-all shadow-md shadow-emerald-200 active:scale-95';
        if (filtersBar) filtersBar.classList.remove('hidden');
        resultsTitle.textContent = 'RESULTADOS DA BUSCA';
        resultsIcon.innerHTML = '<i class="fas fa-list-ul"></i>';
        if (iaClearBtn) iaClearBtn.classList.add('hidden');
        if (toggleContainer) toggleContainer.className = 'flex items-center gap-1 p-1 rounded-lg bg-emerald-100';
    } else {
        iaBtn.className = activeToggleClass;
        searchBtn.className = inactiveToggleClass;
        // IA mode: purple colors
        icon.className = 'fas fa-brain absolute left-3 top-1/2 -translate-y-1/2 text-purple-500 text-xs';
        input.placeholder = 'Faça uma pergunta complexa sobre o caso...';
        input.className = 'w-full pl-9 pr-3 py-2.5 bg-purple-50 border-2 border-purple-200 rounded-xl text-sm text-slate-900 font-medium focus:ring-2 focus:ring-purple-400 focus:border-transparent outline-none transition-all shadow-sm';
        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        submitBtn.className = 'bg-purple-600 text-white w-9 h-9 flex items-center justify-center rounded-xl font-bold text-sm hover:bg-purple-700 transition-all shadow-md shadow-purple-200 active:scale-95';
        if (filtersBar) filtersBar.classList.add('hidden');
        resultsTitle.textContent = 'RESPOSTA DO DR. JUS';
        resultsIcon.innerHTML = '<i class="fas fa-robot"></i>';
        // Restore IA history view
        _renderIAHistoryPanel();
        if (toggleContainer) toggleContainer.className = 'flex items-center gap-1 p-1 rounded-lg bg-purple-100';
    }
    input.focus();
}

function handleUnifiedKeydown(event) {
    if (event.key === 'Enter') dispatchUnifiedSearch();
}

function dispatchUnifiedSearch() {
    const val = document.getElementById('unified-input').value.trim();
    if (!val) return;
    if (_searchMode === 'search') {
        _searchState.query = val;
        _unifiedPerformSearch(val);
    } else {
        _unifiedAskIA(val);
        document.getElementById('unified-input').value = '';
    }
}

async function _unifiedPerformSearch(query) {
    _searchState = {
        query,
        offset: 0,
        total: 0,
        top_k: 20,
        filename_filter: document.getElementById('filter-filename').value.trim(),
        file_type_filter: document.getElementById('filter-filetype').value,
    };
    const resultsDiv = document.getElementById('search-results');
    
    // Skeleton loading with rotating messages
    const searchMessages = [
        'Buscando na base de arquivos',
        'Checando nos arquivos',
        'Analisando documentos'
    ];
    let msgIdx = 0;
    
    resultsDiv.innerHTML = `
        <div class="flex flex-col items-center justify-center h-full py-16 space-y-6">
            <div class="relative">
                <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center">
                    <i class="fas fa-search text-3xl text-indigo-300"></i>
                </div>
                <div class="absolute -bottom-1 -right-1 w-8 h-8 bg-white rounded-xl shadow-md border border-slate-100 flex items-center justify-center">
                    <i class="fas fa-circle-notch fa-spin text-indigo-600 text-xs"></i>
                </div>
            </div>
            <div class="text-center space-y-4">
                <p class="text-sm font-black text-slate-900 uppercase tracking-widest animate-pulse">${searchMessages[0]}</p>
                <!-- Skeleton cards -->
                <div class="w-full max-w-md space-y-3">
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line"></div>
                    <div class="skeleton-line"></div>
                </div>
            </div>
        </div>`;
    
    // Rotate search messages
    const msgEl = resultsDiv.querySelector('.animate-pulse');
    const msgInterval = setInterval(() => {
        msgIdx = (msgIdx + 1) % searchMessages.length;
        if (msgEl) msgEl.textContent = searchMessages[msgIdx];
    }, 2500);
    
    document.getElementById('search-load-more').classList.add('hidden');
    document.getElementById('search-count-badge').classList.add('hidden');
    
    await _fetchSearchPage(true);
    clearInterval(msgInterval);
}

// IA conversation history for the current case session
let _iaConversation = [];

function _renderIAHistoryPanel() {
    const resultsDiv = document.getElementById('search-results');
    const iaClearBtn = document.getElementById('ia-clear-btn');

    if (_iaConversation.length === 0) {
        resultsDiv.innerHTML = `<div id="ia-empty-state" class="flex flex-col items-center justify-center h-full text-center space-y-4 py-12 text-slate-400">
            <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center">
                <i class="fas fa-robot text-2xl text-indigo-300"></i>
            </div>
            <p class="text-sm font-medium text-slate-500">Faça uma pergunta ao Dr. Jus</p>
        </div>`;
        if (iaClearBtn) iaClearBtn.classList.add('hidden');
        return;
    }

    if (iaClearBtn) iaClearBtn.classList.remove('hidden');

    let html = '<div id="ia-history-wrapper" class="space-y-8">';
    _iaConversation.forEach((item, idx) => {
        html += `<div class="ia-turn space-y-4" data-turn="${idx}">
            <div class="flex justify-end">
                <div class="bg-indigo-600 p-4 rounded-2xl rounded-tr-none text-white text-sm font-medium max-w-[80%] shadow-lg shadow-indigo-100">
                    ${escapeHtml(item.question)}
                </div>
            </div>
            <div class="ia-answer-slot"></div>
        </div>`;
    });
    html += '</div>';
    resultsDiv.innerHTML = html;

    // Re-render each answer
    const turns = resultsDiv.querySelectorAll('.ia-turn');
    _iaConversation.forEach((item, idx) => {
        const slot = turns[idx].querySelector('.ia-answer-slot');
        if (item.pending) {
            // Rotating feedback messages
            const feedbackMessages = [
                'Entendendo sua questão',
                'Buscando na base de arquivos',
                'Checando nos arquivos',
                'Analisando documentos relevantes',
                'Processando informações',
                'Preparando resposta'
            ];
            let msgIndex = 0;
            slot.innerHTML = `<div class="typing-indicator mobile-typing">
                <div class="typing-dots">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
                <strong data-typing-text>${feedbackMessages[0]}</strong>
                <span class="mobile-processing-badge md:hidden ml-2 px-2 py-1 bg-indigo-100 text-indigo-700 text-xs rounded-full font-medium">Processando...</span>
            </div>`;
            
            // Rotate messages every 2 seconds
            const messageEl = slot.querySelector('[data-typing-text]');
            const messageInterval = setInterval(() => {
                msgIndex = (msgIndex + 1) % feedbackMessages.length;
                if (messageEl && !item.pending) {
                    clearInterval(messageInterval);
                } else if (messageEl) {
                    messageEl.textContent = feedbackMessages[msgIndex];
                }
            }, 2000);
            
            // Store interval to clear later
            item._messageInterval = messageInterval;
        } else if (item.error) {
            slot.innerHTML = `<div class="bg-gradient-to-br from-red-50 to-orange-50 p-5 rounded-2xl rounded-tl-none border border-red-100 text-sm text-red-700 max-w-[90%] shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-brain text-red-500"></i>
                    </div>
                    <div>
                        <p class="font-bold text-red-800 mb-1">Ops! Algo aconteceu</p>
                        <p class="text-red-600">Estamos ajustando o cérebro do Dr. Jus para esta análise. Tente novamente em instantes.</p>
                    </div>
                </div>
                <button onclick="_unifiedAskIA('${escapeHtml(item.question)}')" class="mt-3 w-full px-4 py-2 bg-white border border-red-200 text-red-600 text-xs font-bold rounded-lg hover:bg-red-50 transition-colors">
                    <i class="fas fa-redo mr-1"></i>Tentar novamente
                </button>
            </div>`;
        } else {
            _renderIAResponseInUnified(slot, item.data, item.question);
        }
    });

    resultsDiv.scrollTop = resultsDiv.scrollHeight;
    
    // Force scroll on mobile with smooth behavior
    setTimeout(() => {
        resultsDiv.scrollTo({
            top: resultsDiv.scrollHeight,
            behavior: 'smooth'
        });
    }, 100);
}

function clearIAHistory() {
    _iaConversation = [];
    _renderIAHistoryPanel();
}

async function _unifiedAskIA(question) {
    const turnIdx = _iaConversation.length;
    _iaConversation.push({ question, pending: true, data: null, error: false });
    _renderIAHistoryPanel();

    // Scroll to bottom to show loading indicator
    const resultsDiv = document.getElementById('search-results');
    resultsDiv.scrollTop = resultsDiv.scrollHeight;

    try {
        const formData = new FormData();
        formData.append('case_id', '<?php echo $case_id; ?>');
        formData.append('question', question);
        // Use default IA_PROVIDER from .env (gemini) - remove hardcoded openrouter
        const response = await fetch('/api/ask_ia', { method: 'POST', body: formData });
        const data = await response.json();

        _iaConversation[turnIdx].pending = false;
        _iaConversation[turnIdx].data = data;
    } catch (e) {
        _iaConversation[turnIdx].pending = false;
        _iaConversation[turnIdx].error = true;
    }

    _renderIAHistoryPanel();
}

// Search pagination state
let _searchState = { query: '', offset: 0, total: 0, top_k: 20, filename_filter: '', file_type_filter: '' };

function clearSearchFilters() {
    document.getElementById('filter-filename').value = '';
    document.getElementById('filter-filetype').value = '';
}

function _renderSearchResult(r) {
    const ext = r.filename.split('.').pop().toUpperCase();
    let highlightedSnippet = r.snippet;
    
    // Highlight terms if search state has a query
    if (_searchState.query) {
        const terms = _searchState.query.split(/\s+/).filter(t => t.length > 2);
        if (terms.length) {
            const pattern = new RegExp(`(${terms.join('|')})`, 'gi');
            highlightedSnippet = r.snippet.replace(pattern, '<mark class="bg-yellow-200 text-slate-800 rounded px-1">$1</mark>');
        }
    }

    return `
        <div class="p-5 bg-white rounded-2xl border border-slate-100 shadow-sm hover:border-indigo-200 transition-all">
            <div class="flex justify-between items-center mb-3">
                <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-1 rounded truncate max-w-[150px]" title="${r.filename}">${r.filename}</span>
                <div class="flex items-center gap-2">
                    <span class="text-[9px] font-black text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">${ext}</span>
                    <span class="text-[10px] font-bold text-slate-400">Pág. ${r.page}</span>
                </div>
            </div>
            <p class="text-sm text-slate-700 leading-relaxed">${highlightedSnippet}</p>
        </div>
    `;
}

async function performSearch() {
    const query = document.getElementById('search-input').value.trim();
    if (!query) return;

    _searchState = {
        query,
        offset: 0,
        total: 0,
        top_k: 20,
        filename_filter: document.getElementById('filter-filename').value.trim(),
        file_type_filter: document.getElementById('filter-filetype').value,
    };

    const resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '<div class="flex items-center justify-center h-full"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-600"></i></div>';
    document.getElementById('search-load-more').classList.add('hidden');
    document.getElementById('search-count-badge').classList.add('hidden');

    await _fetchSearchPage(true);
}

async function loadMoreResults() {
    _searchState.offset += _searchState.top_k;
    await _fetchSearchPage(false);
}

async function _fetchSearchPage(replace) {
    const resultsDiv = document.getElementById('search-results');
    const loadMoreDiv = document.getElementById('search-load-more');
    const badge = document.getElementById('search-count-badge');

    try {
        const body = {
            case_id: '<?php echo $case_id; ?>',
            query: _searchState.query,
            top_k: _searchState.top_k,
            offset: _searchState.offset,
        };
        if (_searchState.filename_filter) body.filename_filter = _searchState.filename_filter;
        if (_searchState.file_type_filter) body.file_type_filter = _searchState.file_type_filter;

        const response = await fetch('/api/search', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body),
        });
        const data = await response.json();

        // Support both old array format and new paginated format
        const results = Array.isArray(data) ? data : (data.results || []);
        const total = Array.isArray(data) ? results.length : (data.total || results.length);
        _searchState.total = total;

        if (replace && results.length === 0) {
            resultsDiv.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-center space-y-6 py-12">
                    <div class="w-24 h-24 bg-slate-50 rounded-3xl flex items-center justify-center relative group">
                        <i class="fas fa-search text-4xl text-slate-300 group-hover:scale-110 transition-transform duration-300"></i>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-white rounded-full shadow-md flex items-center justify-center text-amber-500">
                            <i class="fas fa-question text-sm"></i>
                        </div>
                    </div>
                    <div class="max-w-xs space-y-3">
                        <p class="text-base font-black text-slate-900">Nenhum resultado encontrado</p>
                        <p class="text-xs text-slate-500 leading-relaxed">Não encontramos termos exatos para "<span class="font-bold text-slate-700">${escapeHtml(_searchState.query)}</span>".</p>
                    </div>
                    <button onclick="setSearchMode('ia'); document.getElementById('unified-input').value = ${JSON.stringify(_searchState.query)}; document.getElementById('unified-input').focus();" 
                        class="px-6 py-3 bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-700 hover:bg-indigo-100 hover:border-indigo-200 transition-all shadow-sm flex items-center gap-2 group">
                        <span class="w-6 h-6 bg-white rounded-lg flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                            <i class="fas fa-robot text-indigo-600 text-[10px]"></i>
                        </span>
                        Perguntar ao Dr. Jus
                    </button>
                </div>`;
            loadMoreDiv.classList.add('hidden');
            badge.classList.add('hidden');
            return;
        }

        const html = results.map(_renderSearchResult).join('');
        if (replace) {
            resultsDiv.innerHTML = html;
        } else {
            resultsDiv.insertAdjacentHTML('beforeend', html);
        }

        // Update badge
        badge.textContent = `${Math.min(_searchState.offset + _searchState.top_k, total)} / ${total}`;
        badge.classList.remove('hidden');

        // Show/hide "load more" button
        const loaded = _searchState.offset + results.length;
        if (loaded < total) {
            loadMoreDiv.classList.remove('hidden');
        } else {
            loadMoreDiv.classList.add('hidden');
        }
    } catch (e) {
        if (replace) resultsDiv.innerHTML = '<p class="text-red-500 text-center mt-10">Erro na busca.</p>';
    }
}

// ==================== SEARCH FILTERS & IA RENDERER ====================
let _feedbackCounter = 0;

function clearSearchFilters() {
    document.getElementById('filter-filename').value = '';
    document.getElementById('filter-filetype').value = '';
    if (_searchState.query) {
        _unifiedPerformSearch(_searchState.query);
    }
}

function _renderIAResponseInUnified(container, data, question = '') {
    const indicator = container.querySelector('.typing-indicator');
    if (indicator) indicator.remove();

    const sources = data.sources || [];
    let sourceInfoHtml = '';

    if (sources.length) {
        // Group sources by filename
        const groupedSources = {};
        sources.forEach(source => {
            const filename = source.filename || 'Arquivo desconhecido';
            if (!groupedSources[filename]) {
                groupedSources[filename] = [];
            }
            if (source.page) {
                groupedSources[filename].push(source.page);
            }
        });

        // Render grouped sources: "Filename [page1] [page2] ..."
        const linkHtml = Object.entries(groupedSources).map(([filename, pages]) => {
            const encoded = encodeURIComponent(filename);
            const safeName = escapeHtml(filename);
            const pagesHtml = pages.map(page => 
                `<button type="button" class="text-[10px] text-slate-500 underline hover:text-indigo-600 transition-colors" onclick="focusDocument('${encoded}', ${page})">[${page}]</button>`
            ).join('');
            return `<span class="flex items-center gap-1"><span class="text-[10px] text-slate-600 font-medium">${safeName}</span>${pagesHtml}</span>`;
        }).join('<span class="text-slate-300 mx-2">|</span>');
        sourceInfoHtml = `
            <div class="mt-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                    <i class="fas fa-quote-left text-[8px]"></i> Fontes citadas
                </p>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">${linkHtml}</div>
            </div>`;
    }

    const answer = data.answer || (data.candidates ? data.candidates[0].content.parts[0].text : 'Erro ao processar resposta.');
    const feedbackId = ++_feedbackCounter;
    const snippet = answer.substring(0, 200).replace(/'/g, "\\'");
    const qEsc = escapeHtml(question).replace(/'/g, "\\'");

    const feedbackHtml = `
        <div id="feedback-${feedbackId}" class="flex items-center gap-3 mt-6 pt-4 border-t border-slate-100">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Esta análise foi útil?</span>
            <div class="flex gap-1">
                <button onclick="sendFeedback(${feedbackId}, 1, '${qEsc}', '${snippet}')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-green-50 text-slate-300 hover:text-green-600 transition-colors border border-transparent hover:border-green-100">
                    <i class="fas fa-thumbs-up text-xs"></i>
                </button>
                <button onclick="sendFeedback(${feedbackId}, -1, '${qEsc}', '${snippet}')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-slate-300 hover:text-red-500 transition-colors border border-transparent hover:border-red-100">
                    <i class="fas fa-thumbs-down text-xs"></i>
                </button>
            </div>
        </div>`;

    const formattedAnswer = answer.replace(/\n/g, '<br>');
    const answerHtml = `
        <div class="bg-white p-2 rounded-2xl animate-fadeIn">
            <div class="text-base text-slate-700 leading-relaxed space-y-4">
                ${formattedAnswer}
            </div>
            ${sourceInfoHtml}
            ${feedbackHtml}
        </div>`;
    
    container.insertAdjacentHTML('beforeend', answerHtml);
}

async function sendFeedback(feedbackId, vote, question, snippet) {
    const container = document.getElementById(`feedback-${feedbackId}`);
    try {
        await fetch('/api/answer_feedback', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                case_id: '<?php echo $case_id; ?>',
                question,
                answer_snippet: snippet,
                vote,
            }),
        });
    } catch(_) {}
    if (container) {
        const icon = vote === 1 ? '👍' : '👎';
        container.innerHTML = `<span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">${icon} Obrigado pelo feedback!</span>`;
    }
}

// Update query preview when typing (new simplified builder fields)
['isb-all', 'isb-any', 'isb-not'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updateQueryPreview);
});

// Load documents on page load
fetchDocuments();

// ==================== EXECUTIVE SUMMARY FUNCTIONS ====================
async function loadExecutiveSummary() {
    const factsContainer = document.getElementById("executive-facts");
    const proofStatusContainer = document.getElementById("proof-status-indicator");
    
    if (!factsContainer) return;
    
    // First check cache
    try {
        const cacheResponse = await fetch(`/api/executive_summary_cache?case_id=${CASE_ID}`);
        const cacheData = await cacheResponse.json();
        
        if (cacheData.cached && cacheData.data) {
            // Show cached data with refresh button
            renderExecutiveSummary(cacheData.data);
            addRefreshButton();
            return;
        }
        
        // No cache - show "clique aqui para buscar" message
        showNoCacheMessage();
        
    } catch (e) {
        console.error("Error checking cache:", e);
        showNoCacheMessage();
    }
}

function showNoCacheMessage() {
    const factsContainer = document.getElementById("executive-facts");
    const partiesContainer = document.getElementById("executive-parties");
    const proofStatusContainer = document.getElementById("proof-status-indicator");
    
    if (factsContainer) {
        factsContainer.innerHTML = `
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i class="fas fa-calendar-alt text-indigo-400"></i>
                Fatos Relevantes
            </h4>
            <div class="bg-indigo-50 rounded-xl p-4 text-center">
                <p class="text-xs text-indigo-600 font-medium">
                    <i class="fas fa-info-circle mr-1"></i>
                    Não há dados, <button onclick="refreshExecutiveSummary()" class="underline hover:text-indigo-800 font-bold">clique aqui para buscar</button>
                </p>
            </div>`;
    }
    
    if (partiesContainer) {
        partiesContainer.innerHTML = `
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i class="fas fa-users text-purple-400"></i>
                Partes Envolvidas
            </h4>
            <div class="bg-purple-50 rounded-xl p-4 text-center">
                <button onclick="refreshExecutiveSummary()" class="text-xs text-purple-600 font-medium hover:text-purple-800 underline">
                    <i class="fas fa-search mr-1"></i>Buscar informações
                </button>
            </div>`;
    }
    
    if (proofStatusContainer) {
        proofStatusContainer.className = "flex items-center gap-2 p-3 bg-slate-50 rounded-xl";
        proofStatusContainer.innerHTML = `
            <div class="w-3 h-3 bg-slate-400 rounded-full"></div>
            <span class="text-xs font-bold text-slate-700">Aguardando dados</span>`;
    }
}

function addRefreshButton() {
    const cardHeader = document.querySelector(".mobile-tab-panel .bg-white.rounded-2xl > h3");
    if (cardHeader && !document.getElementById("exec-summary-refresh-btn")) {
        const refreshBtn = document.createElement("button");
        refreshBtn.id = "exec-summary-refresh-btn";
        refreshBtn.className = "ml-auto text-slate-400 hover:text-indigo-600 transition-colors p-1";
        refreshBtn.title = "Atualizar sumário";
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt text-xs"></i>';
        refreshBtn.onclick = refreshExecutiveSummary;
        cardHeader.appendChild(refreshBtn);
    }
}

async function refreshExecutiveSummary() {
    const factsContainer = document.getElementById("executive-facts");
    
    // Show loading state
    if (factsContainer) {
        factsContainer.innerHTML = `
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i class="fas fa-calendar-alt text-indigo-400"></i>
                Fatos Relevantes
            </h4>
            <div class="bg-indigo-50 rounded-xl p-3">
                <p class="text-xs text-indigo-600 font-medium animate-pulse">
                    <i class="fas fa-circle-notch fa-spin mr-1"></i>Gerando sumário...
                </p>
            </div>`;
    }
    
    try {
        // Call refresh endpoint
        const response = await fetch("/api/executive_summary_refresh", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ case_id: CASE_ID })
        });
        
        if (!response.ok) {
            throw new Error("Failed to refresh");
        }
        
        const data = await response.json();
        
        if (data.data) {
            renderExecutiveSummary(data.data);
            addRefreshButton();
        }
        
    } catch (e) {
        console.error("Error refreshing executive summary:", e);
        if (factsContainer) {
            factsContainer.innerHTML = `
                <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-indigo-400"></i>
                    Fatos Relevantes
                </h4>
                <div class="bg-red-50 rounded-xl p-3">
                    <p class="text-xs text-red-600 font-medium">
                        <i class="fas fa-exclamation-circle mr-1"></i>Erro ao gerar sumário
                    </p>
                </div>`;
        }
    }
}

function renderExecutiveSummary(data) {
    const factsContainer = document.getElementById("executive-facts");
    const partiesContainer = document.getElementById("executive-parties");
    const proofStatusContainer = document.getElementById("proof-status-indicator");
    
    // Update Facts
    if (factsContainer && data.facts && data.facts.length > 0) {
        const factsHtml = data.facts.map(fact => `
            <div class="flex items-start gap-2 p-2 bg-white rounded-lg border border-indigo-100">
                <div class="w-2 h-2 bg-indigo-500 rounded-full mt-1.5 flex-shrink-0"></div>
                <span class="text-xs text-slate-700">${escapeHtml(fact)}</span>
            </div>`).join("");
        
        factsContainer.innerHTML = `
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i class="fas fa-calendar-alt text-indigo-400"></i>
                Fatos Relevantes
            </h4>
            <div class="space-y-2 max-h-40 overflow-y-auto">${factsHtml}</div>`;
    }
    
    // Update Parties
    if (partiesContainer && data.parties) {
        const author = data.parties.author || { name: "Não identificado", role: "Autor" };
        const defendant = data.parties.defendant || { name: "Não identificado", role: "Réu" };
        const judge = data.parties.judge || { name: "Não identificado", role: "Juiz" };
        
        partiesContainer.innerHTML = `
            <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-2">
                <i class="fas fa-users text-purple-400"></i>
                Partes Envolvidas
            </h4>
            <div class="space-y-2">
                <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
                    <span class="text-xs font-medium text-slate-600">${escapeHtml(author.role)}</span>
                    <span class="text-[10px] text-indigo-600 font-medium truncate max-w-[100px]">${escapeHtml(author.name)}</span>
                </div>
                <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
                    <span class="text-xs font-medium text-slate-600">${escapeHtml(defendant.role)}</span>
                    <span class="text-[10px] text-indigo-600 font-medium truncate max-w-[100px]">${escapeHtml(defendant.name)}</span>
                </div>
                <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg">
                    <span class="text-xs font-medium text-slate-600">${escapeHtml(judge.role)}</span>
                    <span class="text-[10px] text-indigo-600 font-medium truncate max-w-[100px]">${escapeHtml(judge.name)}</span>
                </div>
            </div>`;
    }
    
    // Update Proof Status
    if (proofStatusContainer && data.proof_status) {
        const status = data.proof_status;
        const statusConfig = {
            "ok": { class: "bg-emerald-500", text: "Provas OK", textClass: "text-emerald-700", bgClass: "bg-emerald-50" },
            "warning": { class: "bg-amber-500", text: "Atenção necessária", textClass: "text-amber-700", bgClass: "bg-amber-50" },
            "suspect": { class: "bg-red-500", text: "Provas suspeitas", textClass: "text-red-700", bgClass: "bg-red-50" },
            "pending": { class: "bg-slate-400", text: "Aguardando documentos", textClass: "text-slate-700", bgClass: "bg-slate-50" }
        };
        
        const config = statusConfig[status] || statusConfig.pending;
        proofStatusContainer.className = `flex items-center gap-2 p-3 ${config.bgClass} rounded-xl`;
        proofStatusContainer.innerHTML = `
            <div class="w-3 h-3 ${config.class} rounded-full"></div>
            <span class="text-xs font-bold ${config.textClass}">${config.text}</span>`;
    }
}



// Load executive summary on page load
loadExecutiveSummary();

// ==================== CASE NOTES SYSTEM ====================
// Notes storage key for localStorage
const NOTES_STORAGE_KEY = 'kapjus_case_notes_<?php echo $case_id; ?>';
let notes = [];
let selectedNoteColor = 'yellow';

// Initialize notes from localStorage
function initNotes() {
    const stored = localStorage.getItem(NOTES_STORAGE_KEY);
    if (stored) {
        try {
            notes = JSON.parse(stored);
        } catch (e) {
            notes = [];
        }
    }
    renderNotesModal();
}

// Open Notes Modal
function openNotesModal() {
    document.getElementById('notes-modal').classList.remove('hidden');
    renderNotesModal();
    // Set initial color selection
    setNoteColor(selectedNoteColor);
}

// Close Notes Modal
function closeNotesModal() {
    document.getElementById('notes-modal').classList.add('hidden');
}

// Save notes to localStorage
function saveNotes() {
    localStorage.setItem(NOTES_STORAGE_KEY, JSON.stringify(notes));
}

// Set note color selection
function setNoteColor(color) {
    selectedNoteColor = color;
    document.querySelectorAll('.note-color-btn').forEach(btn => {
        if (btn.dataset.color === color) {
            btn.classList.add('ring-indigo-500');
        } else {
            btn.classList.remove('ring-indigo-500');
        }
    });
}

// Create a new note
function createNote(event) {
    event.preventDefault();
    const textEl = document.getElementById('note-text-modal');
    const text = textEl.value.trim();
    if (!text) return;
    
    const note = {
        id: Date.now().toString(),
        text: text,
        author: CURRENT_USER_NAME || 'Advogado',
        color: selectedNoteColor,
        createdAt: new Date().toISOString()
    };
    
    notes.unshift(note);
    saveNotes();
    renderNotesModal();
    textEl.value = '';
}

// Delete a note
async function deleteNote(noteId) {
    const confirmed = await showConfirm({
        title: 'Excluir nota',
        message: 'Esta nota será excluída permanentemente.',
        okLabel: 'Excluir',
        icon: 'fas fa-trash',
        iconBg: 'bg-red-100',
        iconColor: 'text-red-600',
    });
    if (!confirmed) return;
    
    notes = notes.filter(n => n.id !== noteId);
    saveNotes();
    renderNotesModal();
}

// Edit a note - show inline edit
function startEditNote(noteId) {
    const note = notes.find(n => n.id === noteId);
    if (!note) return;
    
    const card = document.querySelector(`[data-note-id="${noteId}"]`);
    if (!card) return;
    
    const content = card.querySelector('.note-content');
    const editArea = document.createElement('div');
    editArea.className = 'space-y-2';
    editArea.innerHTML = `
        <textarea class="note-edit-text w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:ring-2 focus:ring-amber-400 focus:border-amber-400 outline-none resize-none" rows="2">${escapeHtml(note.text)}</textarea>
        <div class="flex gap-2 justify-end">
            <button onclick="cancelEditNote('${noteId}')" class="px-3 py-1.5 text-xs font-bold text-slate-500 hover:text-slate-700">Cancelar</button>
            <button onclick="saveEditNote('${noteId}')" class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700">Salvar</button>
        </div>
    `;
    
    content.classList.add('hidden');
    card.insertBefore(editArea, content);
}

// Cancel note edit
function cancelEditNote(noteId) {
    const card = document.querySelector(`[data-note-id="${noteId}"]`);
    if (!card) return;
    const editArea = card.querySelector('.space-y-2');
    const content = card.querySelector('.note-content');
    if (editArea) editArea.remove();
    content.classList.remove('hidden');
}

// Save note edit
function saveEditNote(noteId) {
    const card = document.querySelector(`[data-note-id="${noteId}"]`);
    if (!card) return;
    const editText = card.querySelector('.note-edit-text');
    const note = notes.find(n => n.id === noteId);
    if (!note || !editText) return;
    
    note.text = editText.value.trim();
    if (!note.text) {
        deleteNote(noteId);
        return;
    }
    
    saveNotes();
    renderNotesModal();
}

// Format date for display
function formatNoteDate(isoString) {
    const date = new Date(isoString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'agora';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' min atrás';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h atrás';
    
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Get color classes for note
function getNoteColorClasses(color) {
    const colors = {
        red: { bg: 'bg-red-50', border: 'border-l-red-500', text: 'text-red-700' },
        yellow: { bg: 'bg-yellow-50', border: 'border-l-yellow-400', text: 'text-yellow-700' },
        green: { bg: 'bg-emerald-50', border: 'border-l-emerald-500', text: 'text-emerald-700' },
        blue: { bg: 'bg-blue-50', border: 'border-l-blue-500', text: 'text-blue-700' }
    };
    return colors[color] || colors.yellow;
}

// Render all notes (modal version)
function renderNotesModal() {
    const container = document.getElementById('notes-list-modal');
    if (!container) return;
    
    if (notes.length === 0) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-40 text-center space-y-3">
                <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-sticky-note text-xl text-slate-300"></i>
                </div>
                <p class="text-sm font-medium text-slate-400">Nenhuma nota ainda</p>
                <p class="text-xs text-slate-300">Crie uma nota acima</p>
            </div>`;
        return;
    }
    
    container.innerHTML = notes.map(note => {
        const colorClasses = getNoteColorClasses(note.color);
        return `
            <div data-note-id="${note.id}" class="note-card bg-white rounded-xl border border-slate-100 border-l-4 ${colorClasses.border} shadow-sm hover:shadow-md transition-shadow">
                <div class="p-3 note-content">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <span class="text-[10px] font-bold ${colorClasses.text} bg-slate-100 px-2 py-0.5 rounded uppercase">${note.color}</span>
                        <div class="flex gap-1">
                            <button onclick="startEditNote('${note.id}')" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Editar">
                                <i class="fas fa-edit text-[10px]"></i>
                            </button>
                            <button onclick="deleteNote('${note.id}')" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Excluir">
                                <i class="fas fa-trash text-[10px]"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-wrap">${escapeHtml(note.text)}</p>
                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-slate-50">
                        <span class="text-xs font-medium text-slate-500">${escapeHtml(note.author)}</span>
                        <span class="text-xs text-slate-400">${formatNoteDate(note.createdAt)}</span>
                    </div>
                </div>
            </div>`;
    }).join('');
}

// Initialize notes on page load
initNotes();

// ==================== LOCALSTORAGE PERSISTENCE FOR SEARCH & IA ====================
const SEARCH_STATE_KEY = 'kapjus_search_state_<?php echo $case_id; ?>';
const IA_CONVERSATION_KEY = 'kapjus_ia_conversation_<?php echo $case_id; ?>';

// Save search state
function saveSearchState() {
    if (_searchState.query) {
        localStorage.setItem(SEARCH_STATE_KEY, JSON.stringify(_searchState));
    }
}

// Load search state
function loadSearchState() {
    const stored = localStorage.getItem(SEARCH_STATE_KEY);
    if (stored) {
        try {
            const state = JSON.parse(stored);
            return state;
        } catch (e) {
            return null;
        }
    }
    return null;
}

// Save IA conversation
function saveIAConversation() {
    localStorage.setItem(IA_CONVERSATION_KEY, JSON.stringify(_iaConversation));
}

// Load IA conversation
function loadIAConversation() {
    const stored = localStorage.getItem(IA_CONVERSATION_KEY);
    if (stored) {
        try {
            return JSON.parse(stored);
        } catch (e) {
            return [];
        }
    }
    return [];
}

// Clear all persisted data
function clearPersistedData() {
    localStorage.removeItem(SEARCH_STATE_KEY);
    localStorage.removeItem(IA_CONVERSATION_KEY);
    localStorage.removeItem(NOTES_STORAGE_KEY);
}

// Modified clearIAHistory to also persist
const _originalClearIAHistory = clearIAHistory;
clearIAHistory = function() {
    _iaConversation = [];
    localStorage.removeItem(IA_CONVERSATION_KEY);
    _renderIAHistoryPanel();
};

// Modified _unifiedPerformSearch to save state
const _originalUnifiedPerformSearch = _unifiedPerformSearch;
_unifiedPerformSearch = async function(query) {
    await _originalUnifiedPerformSearch(query);
    saveSearchState();
};

// Modified _unifiedAskIA to save conversation
const _originalUnifiedAskIA = _unifiedAskIA;
_unifiedAskIA = async function(question) {
    await _originalUnifiedAskIA(question);
    saveIAConversation();
};

// Load persisted data on initialization
document.addEventListener('DOMContentLoaded', function() {
    // Load IA conversation
    const savedIA = loadIAConversation();
    if (savedIA.length > 0) {
        _iaConversation = savedIA;
        setSearchMode('ia');
        _renderIAHistoryPanel();
    }
    
    // Load search state
    const savedSearch = loadSearchState();
    if (savedSearch && savedSearch.query) {
        _searchState = savedSearch;
        setSearchMode('search');
        document.getElementById('unified-input').value = savedSearch.query;
    }
});

// ==================== INLINE CASE TITLE EDIT ====================
function startEditCaseTitle() {
    document.getElementById('case-title-display').classList.add('hidden');
    const form = document.getElementById('case-title-form');
    form.classList.remove('hidden');
    form.classList.add('flex');
    document.getElementById('case-title-input').focus();
    document.getElementById('case-title-input').select();
}

function cancelEditCaseTitle() {
    document.getElementById('case-title-form').classList.add('hidden');
    document.getElementById('case-title-form').classList.remove('flex');
    document.getElementById('case-title-display').classList.remove('hidden');
}

async function saveCaseTitle(event) {
    event.preventDefault();
    const newName = document.getElementById('case-title-input').value.trim();
    if (!newName) return;
    try {
        const res = await fetch('/api/update_case', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: <?php echo $case_id; ?>, name: newName }),
        });
        const data = await res.json();
        if (data.status === 'ok') {
            // Update display text (strip pencil icon, re-set)
            const display = document.getElementById('case-title-display');
            display.childNodes[0].textContent = newName + ' ';
            cancelEditCaseTitle();
        }
    } catch (e) {
        cancelEditCaseTitle();
    }
}

// ==================== LAWYER INVITATION FUNCTIONS ====================

function openInvitationModal() {
    document.getElementById('invitation-modal').classList.remove('hidden');
    showInviteForm();
}

function closeInvitationModal() {
    document.getElementById('invitation-modal').classList.add('hidden');
}

function showInviteForm() {
    document.getElementById('invite-form-section').classList.remove('hidden');
    document.getElementById('invitations-list-section').classList.add('hidden');
    document.getElementById('access-history-section').classList.add('hidden');
    // Clear form
    document.getElementById('invitee-name').value = '';
    document.getElementById('invitee-email').value = '';
    document.getElementById('invitee-role').value = 'viewer';
}

function showInvitationsList() {
    document.getElementById('invite-form-section').classList.add('hidden');
    document.getElementById('invitations-list-section').classList.remove('hidden');
    document.getElementById('access-history-section').classList.add('hidden');
    loadInvitations();
}

function showAccessHistory() {
    document.getElementById('invite-form-section').classList.add('hidden');
    document.getElementById('invitations-list-section').classList.add('hidden');
    document.getElementById('access-history-section').classList.remove('hidden');
    loadAccessHistory();
}

async function sendInvitation() {
    const name = document.getElementById('invitee-name').value.trim();
    const email = document.getElementById('invitee-email').value.trim();
    const role = document.getElementById('invitee-role').value;
    
    if (!name || !email) {
        showConfirm({ title: 'Campo obrigatório', message: 'Preencha o nome e o e-mail do advogado.', okLabel: 'OK', okClass: 'bg-slate-700 hover:bg-slate-800', icon: 'fas fa-info-circle', iconBg: 'bg-indigo-100', iconColor: 'text-indigo-600' });
        return;
    }

    if (!email.includes('@')) {
        showConfirm({ title: 'E-mail inválido', message: 'Insira um endereço de e-mail válido.', okLabel: 'OK', okClass: 'bg-slate-700 hover:bg-slate-800', icon: 'fas fa-envelope', iconBg: 'bg-orange-100', iconColor: 'text-orange-500' });
        return;
    }

    try {
        const response = await fetch('/api/invite_lawyer', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                case_id: CASE_ID,
                inviter_email: CURRENT_USER_EMAIL,
                inviter_name: CURRENT_USER_NAME,
                invitee_name: name,
                invitee_email: email,
                role: role
            })
        });

        const data = await response.json();

        if (response.ok && data.status === 'success') {
            showConfirm({ title: 'Convite enviado!', message: `${name} receberá um link de acesso por e-mail. O link expira em 48 horas.`, okLabel: 'OK', okClass: 'bg-green-600 hover:bg-green-700', icon: 'fas fa-check-circle', iconBg: 'bg-green-100', iconColor: 'text-green-600' });
            showInviteForm();
        } else {
            const detail = data.message || data.error?.message || data.detail || 'Erro desconhecido';
            showConfirm({ title: 'Erro ao enviar convite', message: detail, okLabel: 'OK', okClass: 'bg-slate-700 hover:bg-slate-800', icon: 'fas fa-exclamation-circle', iconBg: 'bg-red-100', iconColor: 'text-red-600' });
        }
    } catch (e) {
        showConfirm({ title: 'Erro de conexão', message: e.message, okLabel: 'OK', okClass: 'bg-slate-700 hover:bg-slate-800', icon: 'fas fa-wifi', iconBg: 'bg-orange-100', iconColor: 'text-orange-500' });
    }
}

async function loadInvitations() {
    const list = document.getElementById('invitations-list');
    list.innerHTML = '<div class="flex items-center justify-center p-4"><i class="fas fa-circle-notch fa-spin text-indigo-600"></i></div>';
    
    try {
        const response = await fetch('/api/invitations', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: CASE_ID })
        });
        
        const data = await response.json();
        const invitations = data.invitations || [];
        
        if (invitations.length === 0) {
            list.innerHTML = '<p class="text-sm text-slate-400 text-center py-4">Nenhum convite enviado ainda.</p>';
            return;
        }
        
        list.innerHTML = invitations.map(inv => {
            const statusClass = {
                'pending': 'bg-amber-100 text-amber-700',
                'accepted': 'bg-emerald-100 text-emerald-700',
                'revoked': 'bg-red-100 text-red-700',
                'expired': 'bg-slate-100 text-slate-700'
            }[inv.status] || 'bg-slate-100 text-slate-700';
            
            const statusLabel = {
                'pending': 'Pendente',
                'accepted': 'Aceito',
                'revoked': 'Revogado',
                'expired': 'Expirado'
            }[inv.status] || inv.status;
            
            const roleLabel = {
                'viewer': 'Visualizador',
                'commenter': 'Colaborador',
                'editor': 'Editor'
            }[inv.role] || inv.role;
            
            const actions = inv.status === 'pending' ? `
                <button onclick="revokeInvitation('${inv.id}')" class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-lg hover:bg-red-200 transition-colors">
                    <i class="fas fa-times mr-1"></i>Revogar
                </button>
            ` : '';
            
            return `
                <div class="p-4 bg-slate-50 rounded-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-bold text-slate-900">${escapeHtml(inv.invitee_name)}</p>
                            <p class="text-sm text-slate-500">${escapeHtml(inv.invitee_email)}</p>
                            <p class="text-xs text-slate-400 mt-1">${roleLabel} • Enviado em ${inv.created_at}</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 ${statusClass} text-xs font-bold rounded">${statusLabel}</span>
                            ${actions}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (e) {
        list.innerHTML = '<p class="text-sm text-red-400 text-center py-4">Erro ao carregar convites.</p>';
    }
}

async function revokeInvitation(invitationId) {
    const confirmed = await showConfirm({
        title: 'Revogar convite',
        message: 'O advogado perderá o acesso ao caso imediatamente. Esta ação não pode ser desfeita.',
        okLabel: 'Revogar',
        icon: 'fas fa-user-times',
        iconBg: 'bg-orange-100',
        iconColor: 'text-orange-600',
        okClass: 'bg-orange-600 hover:bg-orange-700',
    });
    if (!confirmed) return;
    
    try {
        const response = await fetch('/api/revoke_invitation', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ invitation_id: invitationId, case_id: CASE_ID })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            loadInvitations();
        } else {
            showConfirm({ title: 'Erro ao revogar', message: data.message || 'Erro desconhecido', okLabel: 'OK', okClass: 'bg-slate-700 hover:bg-slate-800', icon: 'fas fa-exclamation-circle', iconBg: 'bg-red-100', iconColor: 'text-red-600' });
        }
    } catch (e) {
        showConfirm({ title: 'Erro de conexão', message: e.message, okLabel: 'OK', okClass: 'bg-slate-700 hover:bg-slate-800', icon: 'fas fa-wifi', iconBg: 'bg-orange-100', iconColor: 'text-orange-500' });
    }
}

async function loadAccessHistory() {
    const list = document.getElementById('access-history-list');
    list.innerHTML = '<div class="flex items-center justify-center p-4"><i class="fas fa-circle-notch fa-spin text-indigo-600"></i></div>';
    
    try {
        const response = await fetch('/api/access_history', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ case_id: CASE_ID })
        });
        
        const data = await response.json();
        const logs = data.logs || [];
        
        if (logs.length === 0) {
            list.innerHTML = '<p class="text-sm text-slate-400 text-center py-4">Nenhum registro de acesso.</p>';
            return;
        }
        
        list.innerHTML = logs.map(log => `
            <div class="p-4 bg-slate-50 rounded-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-bold text-slate-900">${escapeHtml(log.lawyer_name)}</p>
                        <p class="text-sm text-slate-500">${escapeHtml(log.lawyer_email)}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-400">${log.accessed_at}</p>
                        <p class="text-xs font-bold text-indigo-600">${log.action}</p>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (e) {
        list.innerHTML = '<p class="text-sm text-red-400 text-center py-4">Erro ao carregar histórico.</p>';
    }
}
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mobileTabButtons = document.querySelectorAll('[data-mobile-tab-btn]');
    const mobilePanels = document.querySelectorAll('[data-mobile-section]');
    const sidebar = document.querySelector('.col-span-3');

    function setActiveMobileTab(tab) {
        // Update panels visibility
        mobilePanels.forEach(panel => {
            panel.classList.toggle('active', panel.dataset.mobileSection === tab);
        });

        // Show/hide sidebar on mobile based on tab
        if (window.innerWidth < 768) {
            if (tab === 'arquivos') {
                sidebar.classList.remove('hidden');
                sidebar.classList.add('mobile-visible');
            } else {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('mobile-visible');
            }
        }

        // Update bottom nav button styling
        mobileTabButtons.forEach(button => {
            const isActive = button.dataset.tab === tab;
            button.classList.toggle('active', isActive);
            
            // Update colors based on active state
            if (isActive) {
                button.classList.remove('text-slate-500');
                button.classList.add('text-indigo-600');
            } else {
                button.classList.remove('text-indigo-600');
                button.classList.add('text-slate-500');
            }
        });
    }

    mobileTabButtons.forEach(button => {
        button.addEventListener('click', () => {
            setActiveMobileTab(button.dataset.tab);
        });
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('hidden', 'mobile-visible');
            sidebar.classList.add('md:flex');
        } else {
            sidebar.classList.remove('md:flex');
            sidebar.classList.add('hidden');
        }
    });

    setActiveMobileTab('busca');
});
</script>
