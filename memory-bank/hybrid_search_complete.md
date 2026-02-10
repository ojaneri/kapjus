# Hybrid Search System - Complete Implementation Documentation

**Date:** 2026-02-09
**Status:** Complete
**Version:** 2.0

---

## 1. Overview

The Hybrid Search System combines two powerful search technologies to provide superior document retrieval for legal and technical documents:

### What It Does

- **Full-Text Search (FTS5)**: Exact keyword matching with BM25 ranking for precise term retrieval
- **Vector Similarity Search (VSS)**: Semantic understanding using 1536-dimensional embeddings to find conceptually related content

### Why FTS5 + VSS Is Powerful

| FTS5 Strengths | VSS Strengths |
|---------------|---------------|
| Exact keyword matching | Conceptual/semantic matching |
| Fast for known terms | Finds synonyms and related concepts |
| BM25 ranking | Handles misspellings gracefully |
| Low computational cost | Understands context and intent |

**Combined Effect**: Users get both precise keyword matches AND semantically related content, capturing information even when exact terms aren't present in the documents.

### Use Cases

- **Legal Documents**: Find cases by specific articles, statutes, or by legal concepts
- **Technical Documentation**: Search by exact function names OR describe functionality in plain language
- **Contracts**: Locate specific clauses by exact wording OR by describing the intent
- **Research Papers**: Find papers by methodology OR by describing the research question

---

## 2. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     Hybrid Search Pipeline                              │
└─────────────────────────────────────────────────────────────────────────┘

User Question: "qual é o prazo para recurso em sentença?"
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  expand_query() [Line 465]                                              │
│  Orchestrator: AI-powered query expansion                               │
├─────────────────────────────────────────────────────────────────────────┤
│  AI generates:                                                          │
│    - keywords: ["prazo", "recurso", "sentença", "apelação"]            │
│    - semantic_query: "qual é o período limite para apresentar recurso" │
└─────────────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    Parallel Execution                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                      FTS5 Query                                  │   │
│  │  SQL: bm25() ranking with MATCH operator                        │   │
│  │  "prazo recurso sentença*"                                       │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                              +                                          │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                      VSS Query                                   │   │
│  │  SQL: vss_distance() with embeddings                            │   │
│  │  embedding = get_embedding("qual é o período...")              │   │
│  │  dimension: 1536 (text-embedding-3-small)                        │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  rank_results() [Line 605]                                              │
│  RRF Algorithm: Reciprocal Rank Fusion                                  │
│  score = 1.0 / (rank * 60 + position + 1)                              │
│  Deduplication by rowid                                                 │
└─────────────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│  generate_response_with_context() [Line 651]                             │
│  AI Response Generation with retrieved context chunks                   │
└─────────────────────────────────────────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        Final Response                                    │
│  {                                                                       │
│    "answer": "O prazo para recurso de sentença...",                      │
│    "sources": [{"filename": "caso.pdf", "page": 5, "score": 0.023}]    │
│  }                                                                       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Database Schema

### Existing Tables

```sql
-- documents: Core table storing extracted text content
CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id TEXT,
    filename TEXT,
    page_number INTEGER,
    content TEXT
);

-- documents_fts: FTS5 virtual table for full-text search
CREATE VIRTUAL TABLE documents_fts USING fts5(
    content, 
    case_id UNINDEXED, 
    filename UNINDEXED, 
    page_number UNINDEXED
);
```

### New VSS Table

```sql
-- vss_chunks: VSS virtual table for vector similarity search
CREATE VIRTUAL TABLE vss_chunks USING vss0(
    embedding(1536)  -- Matches text-embedding-3-small dimensions
);

-- Links to documents via rowid:
-- vss_chunks.rowid = documents.id
```

### Index Relationships

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   documents     │     │  documents_fts  │     │   vss_chunks    │
│   (content)     │     │   (FTS5 index) │     │   (VSS index)   │
├─────────────────┤     ├─────────────────┤     ├─────────────────┤
│ id (PK)         │────►│ rowid (implicit)│◄────│ rowid (links to │
│ case_id         │     │                 │     │  documents.id)   │
│ filename        │     │                 │     │ embedding(1536) │
│ page_number     │     │                 │     │                 │
│ content         │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

---

## 4. New Functions in processor.py

### 4.1 `enable_vss_extension()` - Line 74

```python
def enable_vss_extension(conn):
    """Load sqlite-vss extension for vector search capabilities."""
    try:
        conn.enable_load_extension(True)
        conn.load_extension("vss0")
        conn.enable_load_extension(False)
        logger.info("VSS extension loaded successfully")
        return True
    except Exception as e:
        logger.warning(f"VSS extension not available: {e}")
        return False
```

**Purpose**: Attempts to load the sqlite-vss extension at module initialization. Sets global `_vss_available` flag.

---

### 4.2 `create_vss_table()` - Line 256

```python
def create_vss_table(conn):
    """Create VSS virtual table for vector similarity search."""
    if not _vss_available:
        logger.warning("VSS extension not available, skipping vss_chunks table creation")
        return False
    
    cursor = conn.cursor()
    try:
        cursor.execute("""
            CREATE VIRTUAL TABLE IF NOT EXISTS vss_chunks USING vss0(
                embedding(1536)  -- OpenAI text-embedding-3-small dimension
            )
        """)
        conn.commit()
        logger.info("VSS vss_chunks virtual table created/verified")
        return True
    except Exception as e:
        logger.error(f"Failed to create vss_chunks table: {e}")
        return False
```

**Purpose**: Creates the VSS virtual table with 1536-dimensional embeddings to store vector representations of document chunks.

---

### 4.3 `get_embedding()` - Line 276

```python
def get_embedding(text: str, model: str = "text-embedding-3-small") -> Optional[List[float]]:
    """Generate embedding vector for given text using OpenAI API via OpenRouter."""
    if not text or not text.strip():
        return None
    
    try:
        response = openrouter_client.embeddings.create(
            model=model,
            input=text[:8000]  # Truncate to avoid token limits
        )
        embedding = response.data[0].embedding
        logger.debug(f"Generated embedding for text ({len(text)} chars) using {model}")
        return embedding
    except Exception as e:
        logger.error(f"Failed to generate embedding: {e}")
        return None
```

**Purpose**: Calls OpenAI embeddings API (via OpenRouter) to convert text to 1536-dimensional vectors.

**Configuration**:
- Model: `text-embedding-3-small`
- Dimensions: 1536
- Max input: 8000 characters (truncated)

---

### 4.4 `store_document_with_embedding()` - Line 293

```python
def store_document_with_embedding(case_id: str, filename: str, pages: List[Dict]):
    """Store document with both text and vector embeddings."""
    # Stores pages in documents, documents_fts, and vss_chunks tables
    # Generates embeddings for each page if VSS is available
```

**Purpose**: Stores document pages in all three tables simultaneously. Called automatically when uploading documents if VSS is available.

---

### 4.5 `index_document_embeddings()` - Line 330

```python
def index_document_embeddings(conn: sqlite3.Connection, case_id: str = None):
    """Generate and store embeddings for documents without vectors."""
    # Finds documents without embeddings using LEFT JOIN on vss_chunks
    # Batch indexes all found documents
    # Returns count of indexed documents
```

**Purpose**: Batch indexes existing documents that don't have vector embeddings. Used by `/setup_vss` endpoint.

---

### 4.6 `expand_query()` - Line 465

```python
def expand_query(question: str) -> Dict[str, any]:
    """
    Expand user question into keywords for FTS5 and semantic query for VSS.
    Uses AI to generate optimized search terms.
    """
    if not question or not question.strip():
        return {"keywords": [], "semantic_query": ""}
    
    # Prompt for AI to generate keywords and semantic query
    prompt = f""" Dada a pergunta do usuário: '{question}', retorne um JSON com:
    - keywords: Lista de termos técnicos e sinônimos para busca por palavra-chave (FTS5)
    - semantic_query: Uma frase otimizada para busca vetorial que capture a intenção
    
    Responda APENAS com o JSON, sem formatação adicional."""
    
    try:
        response = openrouter_client.chat.completions.create(
            model="deepseek/deepseek-r1:free",
            messages=[
                {"role": "system", "content": "Você é um assistente de busca jurídica. Retorne JSON válido."},
                {"role": "user", "content": prompt}
            ],
            max_tokens=512,
            temperature=0.3
        )
        
        content = response.choices[0].message.content.strip()
        if content.startswith("```"):
            content = "\n".join(content.split("\n")[1:-1])
        
        result = json.loads(content)
        return {
            "keywords": result.get("keywords", []),
            "semantic_query": result.get("semantic_query", question)
        }
    except Exception as e:
        logger.warning(f"Query expansion failed, using fallback: {e}")
        return {
            "keywords": question.split()[:10],
            "semantic_query": question
        }
```

**Purpose**: Uses AI to expand user questions into separate keyword and semantic queries optimized for FTS5 and VSS respectively.

**Returns**:
```python
{
    "keywords": ["prazo", "recurso", "sentença", "apelação"],
    "semantic_query": "qual é o período limite para apresentar recurso contra sentença judicial"
}
```

**Fallback**: If AI fails, uses original question split into words (max 10).

---

### 4.7 `hybrid_search()` - Line 509

```python
def hybrid_search(keywords: List[str], semantic_query: str, case_id: Optional[str] = None, limit: int = 5) -> Dict[str, List[Dict]]:
    """
    Execute parallel hybrid search using FTS5 and VSS.
    Returns dict with 'fts_results' and 'vss_results'.
    """
    fts_results = []
    vss_results = []
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # FTS5 Query - BM25 ranking with MATCH operator
    if keywords:
        fts_query = " ".join(keywords) + "*"
        if case_id:
            cursor.execute("""
                SELECT rowid, content, filename, page_number,
                       bm25(documents_fts) as rank
                FROM documents_fts 
                WHERE case_id = ? AND documents_fts MATCH ?
                ORDER BY rank
                LIMIT ?
            """, (case_id, fts_query, limit))
        else:
            cursor.execute("""
                SELECT rowid, content, filename, page_number,
                       bm25(documents_fts) as rank
                FROM documents_fts 
                WHERE documents_fts MATCH ?
                ORDER BY rank
                LIMIT ?
            """, (fts_query, limit))
        
        for row in cursor.fetchall():
            fts_results.append({
                "rowid": row[0],
                "content": row[1],
                "filename": row[2],
                "page": row[3],
                "rank": row[4]
            })
    
    # VSS Query - Vector similarity with embeddings
    if _vss_available and semantic_query:
        embedding = get_embedding(semantic_query)
        if embedding:
            if case_id:
                cursor.execute("""
                    SELECT v.rowid, d.content, d.filename, d.page_number,
                           vss_distance(v.embedding, ?)
                    FROM vss_chunks v
                    JOIN documents d ON v.rowid = d.id
                    WHERE d.case_id = ?
                    ORDER BY vss_distance(v.embedding, ?)
                    LIMIT ?
                """, (embedding, case_id, embedding, limit))
            else:
                cursor.execute("""
                    SELECT v.rowid, d.content, d.filename, d.page_number,
                           vss_distance(v.embedding, ?)
                    FROM vss_chunks v
                    JOIN documents d ON v.rowid = d.id
                    ORDER BY vss_distance(v.embedding, ?)
                    LIMIT ?
                """, (embedding, embedding, limit))
            
            for row in cursor.fetchall():
                vss_results.append({
                    "rowid": row[0],
                    "content": row[1],
                    "filename": row[2],
                    "page": row[3],
                    "distance": row[4]
                })
    
    conn.close()
    
    return {
        "fts_results": fts_results,
        "vss_results": vss_results
    }
```

**Purpose**: Executes parallel FTS5 and VSS searches and returns combined results.

**FTS5 Query**: Uses `bm25()` ranking with `MATCH` operator and wildcard (`*`)
**VSS Query**: Uses `vss_distance()` for cosine similarity search on embeddings

---

### 4.8 `rank_results()` - Line 605

```python
def rank_results(fts_results: List[Dict], vss_results: List[Dict], top_k: int = 5) -> List[Dict]:
    """
    Merge and rank results using Reciprocal Rank Fusion (RRF).
    score = 1 / (rank * 60 + position)
    """
    if not fts_results and not vss_results:
        return []
    
    ranked_items = {}
    
    # Process FTS5 results with RRF
    for position, item in enumerate(fts_results):
        rowid = item["rowid"]
        rank = position + 1
        rrf_score = 1.0 / (rank * 60 + position + 1)
        
        if rowid not in ranked_items:
            ranked_items[rowid] = {"rowid": rowid, "content": item["content"], 
                                   "filename": item["filename"], "page": item["page"],
                                   "fts_rank": rank, "vss_rank": None, "rrf_score": rrf_score}
        else:
            ranked_items[rowid]["rrf_score"] += rrf_score
            ranked_items[rowid]["fts_rank"] = min(ranked_items[rowid].get("fts_rank"), rank)
    
    # Process VSS results with RRF
    for position, item in enumerate(vss_results):
        rowid = item["rowid"]
        rank = position + 1
        rrf_score = 1.0 / (rank * 60 + position + 1)
        
        if rowid not in ranked_items:
            ranked_items[rowid] = {"rowid": rowid, "content": item["content"], 
                                   "filename": item["filename"], "page": item["page"],
                                   "fts_rank": None, "vss_rank": rank, "rrf_score": rrf_score}
        else:
            ranked_items[rowid]["rrf_score"] += rrf_score
            ranked_items[rowid]["vss_rank"] = min(ranked_items[rowid].get("vss_rank"), rank)
    
    # Sort by combined RRF score and return top-k
    sorted_results = sorted(ranked_items.values(), key=lambda x: x["rrf_score"], reverse=True)
    
    return sorted_results[:top_k]
```

**Purpose**: Implements Reciprocal Rank Fusion (RRF) to merge and deduplicate results from both searches.

**RRF Formula**: `score = 1.0 / (rank * 60 + position + 1)`
- Combines scores from both search methods
- Deduplicates by rowid
- Returns top-K results sorted by combined score

---

### 4.9 `generate_response_with_context()` - Line 651

```python
def generate_response_with_context(question: str, ranked_chunks: List[Dict]) -> str:
    """
    Generate AI response using the ranked context chunks.
    """
    if not ranked_chunks:
        return "Não foi possível encontrar informações relevantes para responder à pergunta."
    
    # Build context from chunks
    context_parts = []
    for i, chunk in enumerate(ranked_chunks, 1):
        source_info = f"[{chunk.get('filename', 'unknown')}:pg {chunk.get('page', '?')}]"
        content = chunk.get('content', '')[:1000]  # Limit chunk size
        context_parts.append(f"--- Fonte {i} {source_info} ---\n{content}")
    
    context = "\n\n".join(context_parts)
    
    prompt = f"""Você é um assistente técnico jurídico. Abaixo estão trechos recuperados de documentos. 
Utilize-os estritamente para responder à pergunta final. Se houver contradição, priorize os trechos mais relevantes.

CONTEXTO RECUPERADO:
{context}

PERGUNTA: > {question}

Resposta (cite as fontes utilizadas):"""
    
    try:
        response = openrouter_client.chat.completions.create(
            model="deepseek/deepseek-r1:free",
            messages=[
                {"role": "system", "content": "Você é um assistente jurídico helpful. Responda em português brasileiro, cite as fontes utilizadas."},
                {"role": "user", "content": prompt}
            ],
            max_tokens=2048,
            temperature=0.7
        )
        
        answer = response.choices[0].message.content
        return answer
    except Exception as e:
        logger.error(f"Response generation failed: {e}")
        return f"Erro ao gerar resposta: {str(e)}"
```

**Purpose**: Generates AI response using the ranked context chunks with citations.

---

## 5. New Endpoints

### 5.1 `POST /setup_vss`

Initialize VSS infrastructure - creates vss_chunks table and indexes existing documents.

**Request:**
```json
{
  "case_id": "string (optional)"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "VSS infrastructure setup complete",
  "vss_available": true,
  "table_created": true,
  "indexed_documents": 42,
  "total_embeddings": 42
}
```

---

### 5.2 `GET /vss_status`

Check VSS availability and statistics.

**Response:**
```json
{
  "vss_available": true,
  "vss_chunks_exists": true,
  "total_embeddings": 42,
  "documents_without_embeddings": 0
}
```

---

### 5.3 `POST /hybrid_search`

Complete hybrid search pipeline with AI response generation.

**Request:**
```json
{
  "question": "qual é o prazo para recurso em sentença?",
  "case_id": "RJ-50898255520244025101 (optional)",
  "top_k": 5
}
```

**Response:**
```json
{
  "answer": "O prazo para recurso de sentença...",
  "sources": [
    {"rowid": 42, "filename": "caso.pdf", "page": 5, "score": 0.023}
  ],
  "query_expansion": {
    "keywords": ["prazo", "recurso", "sentença"],
    "semantic_query": "qual é o período limite para apresentar recurso"
  },
  "stats": {
    "fts_results": 3,
    "vss_results": 4,
    "final_results": 5
  }
}
```

---

### 5.4 Updated `POST /ask_ia`

Now accepts optional `use_hybrid: boolean` parameter:

**Request:**
```json
{
  "case_id": "RJ-50898255520244025101",
  "question": "qual é o prazo para recurso?",
  "provider": "openrouter",
  "use_hybrid": true
}
```

---

## 6. API Usage Examples

### Setup VSS Infrastructure

```bash
# Check VSS status
curl http://127.0.0.1:8000/vss_status

# Setup VSS (create table and index all documents)
curl -X POST http://127.0.0.1:8000/setup_vss

# Setup VSS for specific case only
curl -X POST http://127.0.0.1:8000/setup_vss \
  -H "Content-Type: application/json" \
  -d '{"case_id": "RJ-123"}'
```

### Hybrid Search

```bash
# Basic hybrid search
curl -X POST "http://127.0.0.1:8000/hybrid_search" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "qual é o prazo para recurso em sentença?",
    "top_k": 5
  }'

# Hybrid search for specific case
curl -X POST "http://127.0.0.1:8000/hybrid_search" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "quais são os requisitos para gratuidade judiciária?",
    "case_id": "RJ-50898255520244025101",
    "top_k": 10
  }'
```

### Ask IA with Hybrid Search

```bash
curl -X POST "http://127.0.0.1:8000/ask_ia" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "case_id=RJ-50898255520244025101" \
  -d "question=qual é o prazo para recurso?" \
  -d "provider=openrouter" \
  -d "use_hybrid=true"
```

---

## 7. Configuration

### Embedding Model

| Parameter | Value |
|-----------|-------|
| Model | `text-embedding-3-small` |
| Dimensions | 1536 |
| Provider | OpenAI (via OpenRouter) |
| Max Input | 8000 characters |

### AI Models

| Purpose | Model | Temperature |
|---------|-------|-------------|
| Query Expansion | `deepseek/deepseek-r1:free` | 0.3 |
| Response Generation | `deepseek/deepseek-r1:free` | 0.7 |

### OpenRouter Fallback Chain

Models are tried in order if primary fails:
1. `deepseek/deepseek-r1:free`
2. `meta-llama/llama-3.3-70b-instruct:free`
3. `tngtech/deepseek-r1t2-chimera:free`
4. ... (17 more models)

---

## 8. Installation Requirements

### System Dependencies

```bash
# SQLite with VSS extension
# For Ubuntu/Debian:
sudo apt-get install sqlite3 libsqlite3-mod-vss

# Or build from source:
# https://github.com/asg017/sqlite-vss
```

### Python Dependencies

```txt
# requirements.txt
fastapi==0.109.0
uvicorn==0.27.0
pydantic==2.5.3
PyMuPDF==1.23.0
pytesseract==0.3.10
python-docx==1.1.0
openpyxl==3.1.2
Pillow==10.1.0
openai==1.12.0
requests==2.31.0
```

### Environment Variables

```bash
# .env
OPENROUTER_API_KEY=sk-or-v1-...
GEMINI_API_KEY=...  # Optional, for fallback
```

---

## 9. Fallback Behavior

| Scenario | Behavior |
|----------|----------|
| VSS extension unavailable | Falls back to FTS5-only search |
| AI query expansion fails | Uses original question split into words |
| VSS query fails | Returns FTS5 results only |
| Embedding API fails | Skips VSS indexing for that document |
| All OpenRouter models fail | Returns error with details |

---

## 10. Logging

All operations log to `storage/processor.log`:

| Operation | Level |
|-----------|-------|
| VSS extension load | INFO |
| Query expansion | INFO |
| FTS5/VSS search results | INFO |
| RRF merging | INFO |
| Response generation | INFO |
| Extension unavailable | WARNING |
| API failures | WARNING/ERROR |

---

## 11. File Reference

| File | Purpose |
|------|---------|
| [`src/python/processor.py`](src/python/processor.py) | Main implementation (1224 lines) |
| [`memory-bank/vss_infrastructure.md`](memory-bank/vss_infrastructure.md) | Phase 1 documentation |
| [`memory-bank/hybrid_search_phase2.md`](memory-bank/hybrid_search_phase2.md) | Phase 2 documentation |

---

## 12. Summary

The Hybrid Search System provides:

1. **Dual Search Capabilities**: FTS5 for keyword precision, VSS for semantic understanding
2. **AI-Powered Query Expansion**: Automatically generates optimized search terms
3. **RRF Ranking**: Intelligently merges and deduplicates results
4. **Contextual Response Generation**: AI generates answers with cited sources
5. **Graceful Fallbacks**: Works even without VSS extension
6. **Backward Compatibility**: Existing FTS5 endpoints unchanged

The system is production-ready and handles legal/technical document search with high accuracy.
