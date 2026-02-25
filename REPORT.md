# REPORT — KapJus: Análise do Sistema, Search e UX

> Data: 2026-02-24
> Autor: Claude (Anthropic)
> Base: Análise completa do código-fonte em produção

---

## 1. O Que o Sistema Faz

**KapJus** (KapOnline Jurídico) é uma plataforma web de gestão inteligente de documentos jurídicos com IA conversacional. Destina-se a advogados brasileiros que precisam organizar peças processuais e obter respostas rápidas sobre o conteúdo dos autos.

### Funcionalidades principais

| Funcionalidade | Descrição |
|---|---|
| **Gestão de casos** | Cria e lista processos/casos jurídicos |
| **Upload de documentos** | Aceita PDF, DOCX, XLSX, TXT, imagens — com upload chunked (1 MB por parte) e barra de progresso |
| **Extração de texto** | PyMuPDF para PDFs; OCR (Tesseract, pt) como fallback; python-docx; openpyxl |
| **Indexação híbrida** | FTS5 (BM25) + vetorial opcional (sqlite-vss, text-embedding-3-small 1536 dims) |
| **Busca por palavras-chave** | FTS5 com snippets em destaque (<b>) |
| **Busca interativa** | Builder visual de queries FTS5 com operadores OR / AND / NOT |
| **Dr. Jus (chat IA)** | Pipeline RAG híbrido 5 estágios: expansão de query → busca híbrida → RRF → cross-encoder → geração |
| **Visualizador de PDF** | PDF.js inline com busca interna ao documento |
| **Convite de advogados** | Magic links com expiração de 48 h, roles (viewer/commenter/editor), log de acesso |
| **Multi-provider IA** | OpenRouter (16 modelos fallback) + Google Gemini |

### Arquitetura resumida

```
Browser → Apache → PHP (router + SSR) → Unix Socket → Python FastAPI (RAG + indexação)
                                      → SQLite (FTS5 + VSS)
```

---

## 2. Análise do Pipeline de Search

### 2.1 O que está bem

- **BM25 via FTS5** é a escolha correta para busca lexical em português jurídico — termos técnicos exatos (NCPC, art. 1.022, recurso especial) são preservados.
- **RRF (Reciprocal Rank Fusion)** para fundir resultados FTS5 + VSS é a técnica padrão de mercado (usada no Elasticsearch Hybrid Search e Cohere Rerank).
- **Cross-encoder re-ranking com LLM** é uma abordagem sofisticada: elimina falsos positivos que passam pelo BM25/VSS.
- **Chunking por seção semântica** (detecção de cabeçalhos em maiúsculas) é mais inteligente que chunking fixo de N caracteres.
- **Query expansion via LLM** gera sinônimos jurídicos automaticamente — fundamental para cobrir variações terminológicas (petição/peça, sentença/decisão).
- **Graceful degradation**: se `sqlite-vss` não estiver disponível, o sistema cai apenas para FTS5 sem quebrar.

### 2.2 Problemas e Oportunidades de Melhoria no Search

#### P1 — FTS5 sem tokenizador unicode_61 ou porter
O tokenizador padrão do FTS5 (`unicode61`) não faz stemming. Buscas por "recursos" não encontram "recurso"; "peticionou" não encontra "petição". Para português jurídico isso é crítico.

**Sugestão:**
```sql
-- Recriar tabela com tokenizador que faz stemming
CREATE VIRTUAL TABLE documents_fts USING fts5(
    content,
    case_id UNINDEXED,
    filename UNINDEXED,
    page_number UNINDEXED,
    tokenize = 'unicode61 remove_diacritics 2'
);
```
Ou integrar `spaCy` (pt_core_news_sm) para lematização antes da indexação.

#### P2 — Sem diacríticos normalizados na busca simples
Se o usuário busca "petição" mas o PDF foi extraído com encoding corrompido como "peti??o", não há fallback. O sanitizador atual preserva diacríticos, mas não há normalização NFD/NFC antes de indexar.

**Sugestão:** Aplicar `unicodedata.normalize('NFC', text)` em toda extração antes de indexar.

#### P3 — RETRIEVE_CHUNK_COUNT = 30 fixo, FINAL_CONTEXT_CHUNKS = 7
Com documentos longos (500+ páginas), 30 chunks podem não cobrir o universo relevante. Com documentos curtos, 7 chunks finais podem ser excessivos para o contexto.

**Sugestão:** Tornar dinâmico:
- Recuperar `min(30, total_chunks_do_caso * 0.1)` candidatos
- Reduzir para `min(7, candidatos_com_score > threshold)` no final

#### P4 — Query expansion chama LLM mesmo para buscas simples de 1-2 palavras
Para queries curtas como "FGTS" ou "rescisão", a expansão via LLM adiciona latência (~1-2s) sem ganho significativo.

**Sugestão:** Skip query expansion se `len(query.split()) <= 2 and not re.search(r'\?|como|quando|qual', query)`.

#### P5 — Cross-encoder sempre usa OpenRouter, independente do provider configurado
Se a chave OpenRouter estiver com rate limit, o re-ranking falha silenciosamente (retorna candidatos não-re-rankeados), prejudicando a qualidade sem alertar o usuário.

**Sugestão:** Adicionar flag explícita `rerank_failed=True` na resposta e logar; considerar usar o provider configurado como fallback.

#### P6 — FTS5 busca simples retorna apenas 5 resultados (LIMIT 5)
Na busca manual (não RAG), o usuário vê apenas 5 snippets. Em casos com centenas de documentos, isso é muito restritivo.

**Sugestão:** Paginar resultados (10 por página) com botão "Carregar mais", ou exibir pelo menos 20 com scroll virtual.

#### P7 — Ausência de busca por metadados
Não é possível filtrar por: nome do arquivo, intervalo de datas de upload, tipo de documento (PDF/DOCX), ou número de página.

**Sugestão:** Adicionar filtros sidebar: "Apenas PDF", "Apenas páginas 1-10", "Arquivo contém 'contestação'".

#### P8 — Sem feedback de relevância (thumbs up/down)
Não há mecanismo para o usuário sinalizar que um resultado foi útil ou irrelevante.

**Sugestão:** Botões +/- nos resultados do Dr. Jus, persistidos no DB, usados futuramente para fine-tuning ou re-ranking personalizado.

---

## 3. Análise de UX Design

### 3.1 O que está bem

- **Mobile tab bar** com 4 tabs fixas no rodapé é padrão correto para mobile-first.
- **Chat minimizável** para floating button — permite voltar ao contexto sem perder histórico.
- **Drag-and-drop** com fallback para file picker — acessível.
- **Barra de progresso por arquivo** no upload chunked — feedback visual claro.
- **Botão cancelar** no upload em andamento — controle ao usuário.
- **PDF.js inline** com busca — elimina necessidade de app externo.
- **Design system consistente**: rounded-2xl, indigo-600, slate-900, transições suaves.

### 3.2 Problemas e Oportunidades de Melhoria no UX

#### U1 — Usuário hardcoded ("Osvaldo J. Filho") e sem autenticação
Qualquer pessoa com a URL acessa qualquer caso. Para um sistema jurídico isso é um risco grave de confidencialidade (sigilo profissional, OAB).

**Sugestão (alta prioridade):** Implementar autenticação básica — login com email + senha, ou SSO (Google OAuth). Proteger rotas com middleware PHP `requireAuth()`.

#### U2 — Sem feedback de estado vazio contextual
Quando não há documentos no caso, a sidebar exibe lista vazia sem instrução. Quando a busca não retorna resultados, a mensagem é genérica.

**Sugestão:**
```
Estado vazio de documentos:
"Nenhum documento ainda. Arraste um PDF aqui para começar."
[Ícone grande de upload]

Busca sem resultados:
"Nenhum resultado para 'X'. Tente: termos mais curtos, ou pergunte ao Dr. Jus →"
[Link que pré-preenche o chat com a mesma query]
```

#### U3 — Search bar e chat são visuais separados e desconectados
O usuário não sabe quando usar "Buscar" vs "Dr. Jus". A distinção não está explicada na UI.

**Sugestão:** Tooltip ou texto de ajuda:
- Buscar: *"Encontre trechos exatos nos documentos"*
- Dr. Jus: *"Faça perguntas e obtenha respostas fundamentadas"*

Ou unificar em um único campo com modo selecionável (toggle "Busca rápida / Perguntar à IA").

#### U4 — Histórico de chat não persiste entre sessões
Ao recarregar a página, todo o histórico do Dr. Jus some. Não há persistência no DB.

**Sugestão:** Salvar mensagens em `localStorage` (solução rápida) ou tabela `chat_sessions` no SQLite (solução robusta). Exibir "Conversa anterior" com botão para limpar.

#### U5 — Fontes citadas pelo Dr. Jus não são clicáveis
A resposta menciona "Fonte 1 [documento.pdf:pg 12]" como texto puro. O usuário precisa manualmente abrir o PDF e navegar até a página.

**Sugestão:** Renderizar cada fonte como link clicável:
```html
<a onclick="openPDFAtPage('documento.pdf', 12)">documento.pdf:pg 12</a>
```
Abrir o PDF.js viewer já posicionado na página correta (o viewer aceita `#page=12` no hash).

#### U6 — Sem indicação visual de "indexando" após upload
Após o upload completar, o documento aparece na lista, mas o usuário não sabe se já pode buscá-lo.

**Sugestão:** Badge "Indexando..." → "Pronto" no item da lista de documentos, com polling de `/api/documents` até confirmar status.

#### U7 — Modal de busca interativa tem UX complexa para o perfil do usuário
O builder de queries FTS5 com OR/AND/NOT exige conhecimento técnico que advogados geralmente não têm.

**Sugestão:** Simplificar para linguagem natural:
- "Contém TODAS as palavras: ___" → AND
- "Contém QUALQUER palavra: ___" → OR
- "Não contém: ___" → NOT
- Esconder a sintaxe FTS5 gerada (ou mostrar como "consulta avançada" colapsada).

#### U8 — Sem breadcrumb ou navegação entre casos
Na tela de detalhe do caso, não há botão visível "← Voltar para meus casos". O usuário fica preso se não souber usar o browser back.

**Sugestão:** Adicionar breadcrumb: `Casos > Processo 001/2024` no topo da página de detalhe.

#### U9 — Título e descrição do caso não são editáveis após criação
Erros de digitação no nome do caso ficam permanentes.

**Sugestão:** Botão de edição inline no cabeçalho do caso (lápis icon → input field inline com save/cancel).

#### U10 — Sem ordenação ou filtro na lista de casos
Com muitos processos, a lista da home fica desorganizada. Não há busca, ordenação por data, ou filtro por status.

**Sugestão:**
- Input de busca por nome do caso (filtro client-side com Alpine.js)
- Ordenação: "Mais recentes" / "Mais antigos" / "Alfabética"
- Futuramente: tags/status (Ativo, Arquivado, Em prazo)

#### U11 — Nenhum toast/feedback global para ações destrutivas
Ao deletar um documento, não há confirmação (confirm dialog) nem undo. A ação é imediata e irreversível.

**Sugestão:**
- Dialog de confirmação: "Excluir documento.pdf? Esta ação não pode ser desfeita."
- Toast de sucesso/erro após todas as ações (upload, delete, invite)

#### U12 — Layout de 12 colunas quebre em tablets (768-1024px)
O layout sidebar (4 cols) + main (8 cols) funciona bem em desktop (>1280px) e mobile (<768px), mas em tablets mid-range o conteúdo fica espremido.

**Sugestão:** Adicionar breakpoint `lg` intermediário — sidebar colapsável como drawer em tablets, expandida em desktop.

---

## 4. Problemas Técnicos Prioritários

| # | Problema | Severidade | Impacto |
|---|---|---|---|
| T1 | Ausência de autenticação | **Crítica** | Qualquer URL vaza todos os dados do caso |
| T2 | `case_id` como TEXT em `documents` vs INTEGER em `cases` | Alta | Bugs sutis em JOINs e filtros |
| T3 | Cookie `kapjus_session` sem assinatura | Alta | Forgeable — alguém pode assumir sessão de outro advogado |
| T4 | `python-dotenv` ausente do `requirements.txt` | Média | Deploy limpo falha silenciosamente |
| T5 | `inviter_email` obrigatório no backend mas não enviado pelo frontend | Média | Convite de advogados provavelmente quebrado em produção |
| T6 | Arquivos em `/storage/uploads/` sem controle de acesso Apache | Alta | Qualquer pessoa com URL do arquivo acessa documento sigiloso |
| T7 | Tabela `lawyers` legada nunca limpa | Baixa | Confusão em manutenção futura |

---

## 5. Sugestões de Roadmap (Priorizado)

### Sprint 1 — Segurança (crítico)
1. Implementar autenticação com email + senha (PHP sessions com hash bcrypt)
2. Proteger `/storage/uploads/` com `mod_rewrite` → PHP download controller com verificação de sessão
3. Assinar cookie `kapjus_session` com `hash_hmac('sha256', payload, SECRET_KEY)`
4. Corrigir `inviter_email` no frontend do convite

### Sprint 2 — Search Quality
5. Normalizar Unicode (NFC) antes de indexar
6. Aumentar LIMIT da busca simples para 20 com paginação
7. Skip query expansion para queries ≤ 2 palavras
8. Adicionar filtros de metadados (tipo de arquivo, nome)

### Sprint 3 — UX Core
9. Estado vazio contextual (documentos e busca)
10. Fontes do Dr. Jus como links clicáveis para PDF.js na página correta
11. Persistência de histórico de chat em localStorage
12. Breadcrumb de navegação
13. Confirmação antes de deletar documento

### Sprint 4 — UX Avançado
14. Busca unificada (toggle Busca / IA)
15. Edição inline de título/descrição do caso
16. Filtro e ordenação na lista de casos
17. Simplificação do builder de busca interativa
18. Thumbs up/down nas respostas do Dr. Jus

---

## 6. Resumo Executivo

O KapJus tem uma arquitetura de RAG sofisticada e bem pensada para o domínio jurídico — pipeline híbrido FTS5 + VSS com cross-encoder é tecnicamente mais avançado que a maioria dos produtos de mercado para advogados. O frontend é moderno (Tailwind + Alpine.js) e responsivo.

Os gaps mais urgentes são de **segurança** (sem autenticação, arquivos expostos) e de **UX básica** (estados vazios, fontes clicáveis, histórico de chat). Uma vez resolvidos esses pontos, o sistema estaria pronto para onboarding de clientes externos com confiança.

O potencial do produto é alto: combina busca precisa em documentos jurídicos com IA generativa contextualizada — um diferencial real em um mercado carente de ferramentas modernas.
