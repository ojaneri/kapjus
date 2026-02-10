# Hybrid Search System - Phase 2 Implementation

## Overview
Phase 2 implements the Hybrid Search Orchestrator Logic that combines:
- **FTS5** (Full-Text Search) for keyword matching
- **VSS** (Vector Similarity Search) for semantic search

## New Functions

### 1. `expand_query(question: str) -> Dict[str, any]`
**Location:** [`src/python/processor.py:463`](src/python/processor.py:463)

Uses AI to expand user questions into:
- `keywords`: List of technical terms and synonyms for FTS5 search
- `semantic_query`: Optimized phrase for vector similarity search

**Prompt:**
```
 Dada a pergunta do usuário: '[PERGUNTA]', retorne um JSON com:
 - keywords: Lista de termos técnicos e sinônimos para busca por palavra-chave (FTS5)
 - semantic_query: Uma frase otimizada para busca vetorial que capture a intenção
```

**Fallback:** If AI fails, uses original question split into words.

### 2. `hybrid_search(keywords, semantic_query, case_id, limit) -> Dict`
**Location:** [`src/python/processor.py:507`](src/python/processor.py:507)

Executes parallel searches:

**FTS5 Query:**
```sql
SELECT rowid, content, filename, page_number, bm25(documents_fts) as rank
FROM documents_fts 
WHERE case_id = ? AND documents_fts MATCH ?
ORDER BY rank
LIMIT ?
```

**VSS Query:**
```sql
SELECT v.rowid, d.content, d.filename, d.page_number, vss_distance(v.embedding, ?)
FROM vss_chunks v
JOIN documents d ON v.rowid = d.id
WHERE d.case_id = ?
ORDER BY vss_distance(v.embedding, ?)
LIMIT ?
```

**Fallback:** If VSS is unavailable, returns FTS5 results only.

### 3. `rank_results(fts_results, vss_results, top_k) -> List[Dict]`
**Location:** [`src/python/processor.py:593`](src/python/processor.py:593)

Implements **Reciprocal Rank Fusion (RRF)**:
```python
score = 1.0 / (rank * 60 + position + 1)
```

Combines and deduplicates results from both searches, returns top-K sorted by combined RRF score.

### 4. `generate_response_with_context(question, ranked_chunks) -> str`
**Location:** [`src/python/processor.py:651`](src/python/processor.py:651)

Generates AI response using ranked context chunks with prompt:
```
Você é um assistente técnico jurídico. Abaixo estão trechos recuperados de documentos. 
Utilize-os estritamente para responder à pergunta final. Se houver contradição, priorize os trechos mais relevantes.

CONTEXTO RECUPERADO: [LISTA DE CHUNKS]
PERGUNTA: > [PERGUNTA ORIGINAL]

Resposta (cite as fontes utilizadas):
```

## New Endpoints

### POST /hybrid_search
**Location:** [`src/python/processor.py:693`](src/python/processor.py:693)

**Request:**
```json
{
  "question": "string",
  "case_id": "string (optional)",
  "top_k": 5
}
```

**Response:**
```json
{
  "answer": "string",
  "sources": [
    {"rowid": int, "filename": "string", "page": int, "score": float}
  ],
  "query_expansion": {
    "keywords": ["termo1", "termo2"],
    "semantic_query": "frase otimizada"
  },
  "stats": {
    "fts_results": int,
    "vss_results": int,
    "final_results": int
  }
}
```

### Updated POST /ask_ia
**Location:** [`src/python/processor.py:751`](src/python/processor.py:751)

Now accepts optional `use_hybrid: boolean` parameter:
```json
{
  "case_id": "string",
  "question": "string",
  "provider": "openrouter",
  "use_hybrid": true
}
```

## Hybrid Search Flow

```
User Question
     ↓
expand_query() → AI generates keywords + semantic_query
     ↓
┌─────────────────────────────────────┐
│  Parallel Execution                  │
│  ┌──────────┐   ┌─────────────────┐  │
│  │   FTS5   │   │      VSS        │  │
│  │  search  │   │     search      │  │
│  └──────────┘   └─────────────────┘  │
└─────────────────────────────────────┘
     ↓
rank_results() → RRF scoring + deduplication
     ↓
generate_response_with_context()
     ↓
Final Answer + Sources
```

## Fallback Behavior

| Scenario | Behavior |
|----------|----------|
| VSS unavailable | Falls back to FTS5-only search |
| AI query expansion fails | Uses original question split into words |
| VSS query fails | Returns FTS5 results only |
| Both searches fail | Returns error |

## API Usage Examples

### cURL - Hybrid Search
```bash
curl -X POST "http://localhost:8000/hybrid_search" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "qual é o prazo para recurso?",
    "case_id": "RJ-50898255520244025101",
    "top_k": 5
  }'
```

### cURL - Ask IA with Hybrid
```bash
curl -X POST "http://localhost:8000/ask_ia" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "case_id=RJ-50898255520244025101" \
  -d "question=qual é o prazo para recurso?" \
  -d "provider=openrouter" \
  -d "use_hybrid=true"
```

## Logging
All operations log to `storage/processor.log`:
- Query expansion: `INFO`
- Search results: `INFO`
- RRF merging: `INFO`
- Errors: `WARNING`/`ERROR`
