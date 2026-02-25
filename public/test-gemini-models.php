<?php
/**
 * Gemini Model Tester Helper
 * 
 * Tests the Gemini models configured in .env and verifies which are working.
 * Also lists available models from Google's API.
 * 
 * Usage: Access this file directly in browser or via curl
 */

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$geminiKey = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
$flashModel = $_ENV['GEMINI_FLASH_MODEL'] ?? 'gemini-1.5-flash-8b';
$proModel = $_ENV['GEMINI_PRO_MODEL'] ?? 'gemini-1.5-flash';
$embeddingModel = $_ENV['GEMINI_EMBEDDING_MODEL'] ?? 'text-embedding-004';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini Model Tester</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-900 text-white min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-2">
            <i class="fas fa-robot text-indigo-400 mr-2"></i>
            Gemini Model Tester
        </h1>
        <p class="text-slate-400 mb-8">Teste os modelos configurados no .env e verifique quais estão funcionando</p>
        
        <!-- Current Configuration -->
        <div class="bg-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-cog text-slate-400 mr-2"></i>
                Configuração Atual (.env)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="bg-slate-700/50 p-3 rounded-lg">
                    <span class="text-slate-400">API Key:</span>
                    <span class="font-mono ml-2"><?= substr($geminiKey, 0, 20) ?>...</span>
                </div>
                <div class="bg-slate-700/50 p-3 rounded-lg">
                    <span class="text-slate-400">Flash Model:</span>
                    <span class="font-mono ml-2 text-green-400"><?= $flashModel ?></span>
                </div>
                <div class="bg-slate-700/50 p-3 rounded-lg">
                    <span class="text-slate-400">Pro Model:</span>
                    <span class="font-mono ml-2 text-blue-400"><?= $proModel ?></span>
                </div>
                <div class="bg-slate-700/50 p-3 rounded-lg">
                    <span class="text-slate-400">Embedding Model:</span>
                    <span class="font-mono ml-2 text-purple-400"><?= $embeddingModel ?></span>
                </div>
            </div>
        </div>
        
        <!-- Test Buttons -->
        <div class="flex gap-4 mb-6">
            <button onclick="testAllModels()" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-xl font-bold transition-colors">
                <i class="fas fa-play mr-2"></i>Testar Todos os Modelos
            </button>
            <button onclick="listModels()" class="px-6 py-3 bg-slate-700 hover:bg-slate-600 rounded-xl font-bold transition-colors">
                <i class="fas fa-list mr-2"></i>Listar Modelos Disponíveis
            </button>
        </div>
        
        <!-- Select Model to Test -->
        <div class="bg-slate-800 rounded-2xl p-6 mb-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-mouse-pointer text-slate-400 mr-2"></i>
                Testar Modelo Específico
            </h2>
            <div class="flex gap-4 items-center">
                <select id="modelSelect" class="flex-1 px-4 py-3 bg-slate-700 border border-slate-600 rounded-xl text-white font-mono focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="">Selecione um modelo...</option>
                    <optgroup label="Gemini 3.x (Latest Previews)">
                        <option value="gemini-3.1-pro-preview">gemini-3.1-pro-preview</option>
                        <option value="gemini-3-flash-preview">gemini-3-flash-preview</option>
                        <option value="gemini-3-pro-preview">gemini-3-pro-preview</option>
                    </optgroup>
                    <optgroup label="Gemini 2.5 (Stable/Main)">
                        <option value="gemini-2.5-pro">gemini-2.5-pro</option>
                        <option value="gemini-2.5-flash">gemini-2.5-flash</option>
                        <option value="gemini-2.5-flash-lite">gemini-2.5-flash-lite</option>
                    </optgroup>
                    <optgroup label="Gemini 2.0 (Legacy Stable)">
                        <option value="gemini-2.0-flash-001">gemini-2.0-flash-001</option>
                        <option value="gemini-2.0-flash-lite-001">gemini-2.0-flash-lite-001</option>
                    </optgroup>
                    <optgroup label="Specialized & Multimodal">
                        <option value="deep-research-pro-preview-12-2025">deep-research-pro-preview</option>
                        <option value="gemini-2.5-computer-use-preview-10-2025">gemini-2.5-computer-use-preview</option>
                        <option value="imagen-4.0-generate-001">imagen-4.0-generate-001</option>
                    </optgroup>
                    <optgroup label="Gemini 1.5 (Legado)">
                        <option value="gemini-1.5-flash">gemini-1.5-flash</option>
                        <option value="gemini-1.5-flash-8b">gemini-1.5-flash-8b</option>
                        <option value="gemini-1.5-pro">gemini-1.5-pro</option>
                    </optgroup>
                    <optgroup label="Embeddings">
                        <option value="text-embedding-004">text-embedding-004</option>
                        <option value="text-embedding-3-small">text-embedding-3-small</option>
                        <option value="text-embedding-3-large">text-embedding-3-large</option>
                    </optgroup>
                </select>
                <select id="testType" class="px-4 py-3 bg-slate-700 border border-slate-600 rounded-xl text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option value="generate">Geração</option>
                    <option value="embed">Embedding</option>
                </select>
                <button onclick="testSelectedModel()" class="px-6 py-3 bg-green-600 hover:bg-green-700 rounded-xl font-bold transition-colors">
                    <i class="fas fa-check mr-2"></i>Testar
                </button>
            </div>
        </div>
        
        <!-- Results -->
        <div id="results" class="space-y-4"></div>
        
        <!-- Available Models Reference -->
        <div class="mt-8 bg-slate-800 rounded-2xl p-6">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-info-circle text-slate-400 mr-2"></i>
                Modelos Disponíveis (Referência)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <h3 class="font-bold text-purple-400 mb-2">Gemini 3.x (Latest Previews)</h3>
                    <ul class="space-y-1 text-slate-300 font-mono">
                        <li>gemini-3.1-pro-preview</li>
                        <li>gemini-3-flash-preview</li>
                        <li>gemini-3-pro-preview</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-indigo-400 mb-2">Gemini 2.5 (Stable/Main)</h3>
                    <ul class="space-y-1 text-slate-300 font-mono">
                        <li>gemini-2.5-pro</li>
                        <li>gemini-2.5-flash</li>
                        <li>gemini-2.5-flash-lite</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-blue-400 mb-2">Gemini 2.0 (Legacy Stable)</h3>
                    <ul class="space-y-1 text-slate-300 font-mono">
                        <li>gemini-2.0-flash-001</li>
                        <li>gemini-2.0-flash-lite-001</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-green-400 mb-2">Specialized & Multimodal</h3>
                    <ul class="space-y-1 text-slate-300 font-mono">
                        <li>deep-research-pro</li>
                        <li>gemini-2.5-computer-use</li>
                        <li>imagen-4.0-generate</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-amber-400 mb-2">Gemini 1.5 (Legado)</h3>
                    <ul class="space-y-1 text-slate-300 font-mono">
                        <li>gemini-1.5-flash</li>
                        <li>gemini-1.5-flash-8b</li>
                        <li>gemini-1.5-pro</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-pink-400 mb-2">Embeddings</h3>
                    <ul class="space-y-1 text-slate-300 font-mono">
                        <li>text-embedding-004</li>
                        <li>text-embedding-3-small</li>
                        <li>text-embedding-3-large</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        const GEMINI_KEY = '<?= $geminiKey ?>';
        
        async function testModel(modelName, type) {
            const result = { model: modelName, status: 'testing', message: '' };
            
            try {
                let url, body;
                
                if (type === 'generate') {
                    url = `https://generativelanguage.googleapis.com/v1beta/models/${modelName}:generateContent?key=${GEMINI_KEY}`;
                    body = {
                        contents: [{ parts: [{ text: 'Responda com apenas uma palavra:Olá' }] }]
                    };
                } else if (type === 'embed') {
                    url = `https://generativelanguage.googleapis.com/v1beta/models/${modelName}:embedContent?key=${GEMINI_KEY}`;
                    body = {
                        content: { parts: [{ text: 'test' }] }
                    };
                }
                
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    result.status = 'success';
                    result.message = type === 'generate' ? 'Funcionando!' : 'Embedding gerado com sucesso!';
                } else {
                    result.status = 'error';
                    result.message = data.error?.message || 'Erro desconhecido';
                }
            } catch (e) {
                result.status = 'error';
                result.message = e.message;
            }
            
            return result;
        }
        
        async function testAllModels() {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="text-center py-8"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-400"></i><p class="mt-2">Testando modelos...</p></div>';
            
            const models = [
                { name: '<?= $flashModel ?>', type: 'generate', label: 'Flash (Geração)' },
                { name: '<?= $proModel ?>', type: 'generate', label: 'Pro (Geração)' },
                { name: '<?= $embeddingModel ?>', type: 'embed', label: 'Embedding' }
            ];
            
            let html = '';
            
            for (const model of models) {
                const result = await testModel(model.name, model.type);
                const statusIcon = result.status === 'success' ? '<i class="fas fa-check-circle text-green-400"></i>' : '<i class="fas fa-times-circle text-red-400"></i>';
                const statusClass = result.status === 'success' ? 'bg-green-900/30 border-green-500' : 'bg-red-900/30 border-red-500';
                
                html += `
                    <div class="border ${statusClass} rounded-xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            ${statusIcon}
                            <div>
                                <span class="font-bold">${model.label}</span>
                                <span class="font-mono text-slate-400 ml-2">${result.model}</span>
                            </div>
                        </div>
                        <span class="text-sm ${result.status === 'success' ? 'text-green-400' : 'text-red-400'}">${result.message}</span>
                    </div>
                `;
            }
            
            resultsDiv.innerHTML = html;
        }
        
        async function listModels() {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="text-center py-8"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-400"></i><p class="mt-2">Listando modelos...</p></div>';
            
            try {
                const url = `https://generativelanguage.googleapis.com/v1beta/models?key=${GEMINI_KEY}`;
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.models) {
                    let html = '<div class="bg-slate-800 rounded-2xl p-6"><h3 class="font-bold text-lg mb-4">Modelos Disponíveis na API</h3><div class="grid grid-cols-1 md:grid-cols-2 gap-2">';
                    
                    data.models.forEach(model => {
                        html += `<div class="bg-slate-700/50 p-2 rounded-lg font-mono text-sm">${model.name}</div>`;
                    });
                    
                    html += '</div></div>';
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<div class="bg-red-900/30 border border-red-500 rounded-xl p-4">Erro: ' + (data.error?.message || 'Não foi possível listar modelos') + '</div>';
                }
            } catch (e) {
                resultsDiv.innerHTML = '<div class="bg-red-900/30 border border-red-500 rounded-xl p-4">Erro: ' + e.message + '</div>';
            }
        }
        
        async function testSelectedModel() {
            const modelSelect = document.getElementById('modelSelect');
            const testType = document.getElementById('testType');
            const modelName = modelSelect.value;
            const type = testType.value;
            
            if (!modelName) {
                alert('Por favor, selecione um modelo!');
                return;
            }
            
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<div class="text-center py-8"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-400"></i><p class="mt-2">Testando ' + modelName + '...</p></div>';
            
            const result = await testModel(modelName, type);
            
            const statusIcon = result.status === 'success' ? '<i class="fas fa-check-circle text-green-400"></i>' : '<i class="fas fa-times-circle text-red-400"></i>';
            const statusClass = result.status === 'success' ? 'bg-green-900/30 border-green-500' : 'bg-red-900/30 border-red-500';
            
            resultsDiv.innerHTML = `
                <div class="border ${statusClass} rounded-xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        ${statusIcon}
                        <div>
                            <span class="font-bold">${type === 'generate' ? 'Geração' : 'Embedding'}</span>
                            <span class="font-mono text-slate-400 ml-2">${result.model}</span>
                        </div>
                    </div>
                    <span class="text-sm ${result.status === 'success' ? 'text-green-400' : 'text-red-400'}">${result.message}</span>
                </div>
            `;
        }
        
        // Auto-test on load
        // testAllModels();
    </script>
</body>
</html>
