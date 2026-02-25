# TODO - KapJus Report & Melhorias

## Tarefas

- [x] Explorar codebase completo (arquitetura, rotas, frontend, search, DB)
- [x] Criar REPORT.md com análise do sistema e sugestões de melhoria
- [x] Priorizar Sprint 2 (Search Quality) com o time

### Sprint 2 — Search Quality ✅
- [x] **Item 5** — Normalização Unicode NFC em todas as funções de extração de texto (PDF, OCR, DOCX, TXT)
- [x] **Item 6** — Paginação na busca simples: default top_k=20, offset, botão "Carregar mais", badge total
- [x] **Item 7** — Skip query expansion para queries ≤ 2 tokens sem palavras interrogativas
- [x] **Item 8** — Filtros de metadados na busca: por nome de arquivo e por tipo (pdf, docx, txt, xlsx, png, jpg)

### Sprint 4 — UX Avançado ✅
- [x] **Item 14** — Toggle "Busca rápida / Perguntar à IA" no campo unificado da área de busca
- [x] **Item 15** — Edição inline de título/descrição do caso no navbar (clique → input → salvar via `/api/update_case`)
- [x] **Item 16** — Filtro por nome e ordenação (recente/antigo/A-Z/Z-A) na lista de casos da home
- [x] **Item 17** — Builder de busca interativa simplificado: campos "E / OU / NÃO" em linguagem natural, query técnica colapsada
- [x] **Item 18** — Thumbs up/down nas respostas do Dr. Jus com persistência em `answer_feedback` no SQLite

### Próximas sprints (a priorizar)
- [x] **Sprint 1** — Segurança: autenticação, proteção de /storage/uploads/, cookie seguro, fix inviter_email
- [x] **Sprint 3** — UX Core: estado vazio contextual, fontes clicáveis no Dr. Jus, histórico de chat, breadcrumb, confirmação ao deletar
