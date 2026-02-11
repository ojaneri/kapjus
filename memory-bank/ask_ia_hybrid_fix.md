# /ask_ia Hybrid Search Fix

## Problem
The `/ask_ia` endpoint was NOT using the hybrid search pipeline due to:

1. **Route Collision**: Two duplicate `@app.post("/ask_ia")` endpoints existed:
   - Line 768: Hybrid-capable endpoint with `use_hybrid` parameter
   - Line 904: FTS5-only endpoint (no hybrid support)
   - FastAPI uses the LAST endpoint defined, so the FTS5-only version was active

2. **Default Behavior**: Even the first endpoint required `use_hybrid=True` (default was `False`)

## Solution
1. Removed the duplicate FTS5-only `/ask_ia` endpoint
2. Changed default `use_hybrid=True` in the unified endpoint
3. The endpoint now follows the full hybrid pipeline by default

## Current /ask_ia Implementation
The `/ask_ia` endpoint now uses the full hybrid search pipeline:

```python
@app.post("/ask_ia")
async def ask_ia_v2(
    case_id: str = Form(...), 
    question: str = Form(...), 
    provider: str = Form("openrouter"),
    use_hybrid: bool = Form(True)  # DEFAULT TO HYBRID
):
```

## Flow (when use_hybrid=True)
1. **Query Expansion**: `expand_query(question)` → keywords + semantic_query
2. **Parallel Search**: `hybrid_search(keywords, semantic_query, case_id)` → FTS5 + VSS results
3. **RRF Ranking**: `rank_results(fts_results, vss_results)` → merged ranked chunks
4. **Response Generation**: `generate_response_with_context(question, ranked_chunks)`

## Response Format
```json
{
  "answer": "AI-generated response citing sources...",
  "hybrid_mode": true,
  "query_expansion": {
    "keywords": ["termo1", "termo2", ...],
    "semantic_query": "Frase otimizada para busca vetorial..."
  },
  "sources": [
    {"rowid": 1, "filename": "documento.pdf", "page": 1, "score": 0.0234}
  ],
  "stats": {
    "fts_results": 5,
    "vss_results": 3,
    "final_results": 5
  }
}
```

## Legacy Mode
Set `use_hybrid=False` to use the original FTS5-only search (for backward compatibility).

## Date
2026-02-10
