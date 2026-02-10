# VSS Infrastructure Implementation (Phase 1)

**Date:** 2026-02-09
**Status:** Completed

## Overview
Phase 1 of the Hybrid Search System has been implemented, adding Vector Similarity Search (VSS) infrastructure to the kapjus-rag document processing system.

## Implementation Details

### 1. VSS Extension Loading
- Added `enable_vss_extension()` function to load sqlite-vss extension
- Extension is tested on module load via `_vss_available` flag
- Graceful fallback if extension is not available

### 2. VSS Virtual Table
- Created `create_vss_table()` function
- `vss_chunks` virtual table uses `vss0` with 1536-dimensional embeddings
- Matches OpenAI text-embedding-3-small model dimensions

### 3. Embedding Generation
- Added `get_embedding()` function using OpenAI embeddings API via OpenRouter
- Supports text-embedding-3-small model (1536 dimensions)
- Automatic text truncation to 8000 characters to avoid token limits
- Error handling and logging for API failures

### 4. Document Storage with Vectors
- Added `store_document_with_embedding()` function
- Stores text in `documents` and `documents_fts` tables
- Generates and stores embeddings in `vss_chunks` table
- Links embeddings to document rowid for synchronization

### 5. Indexing Existing Documents
- Added `index_document_embeddings()` function
- Finds documents without embeddings (LEFT JOIN on vss_chunks)
- Batch indexes all existing documents
- Optional case_id filtering

### 6. New Endpoints
- **POST /setup_vss** - Initialize VSS infrastructure
  - Creates vss_chunks table
  - Indexes all existing documents
  - Returns status and statistics
  
- **GET /vss_status** - Check VSS infrastructure status
  - Returns vss_available flag
  - Total embeddings count
  - Documents pending indexing

## Database Schema Changes
```sql
-- New virtual table created
CREATE VIRTUAL TABLE vss_chunks USING vss0(embedding(1536));

-- Links to documents via rowid
-- vss_chunks.rowid = documents.id
```

## Dependencies
- `sqlite-vss` extension must be installed
- OpenRouter API key for embeddings (same as existing AI calls)
- Existing FTS5 infrastructure unchanged

## Testing
```bash
# Check VSS status
curl http://127.0.0.1:8000/vss_status

# Setup VSS (create table and index existing documents)
curl -X POST http://127.0.0.1:8000/setup_vss

# With case_id filter
curl -X POST http://127.0.0.1:8000/setup_vss -H "Content-Type: application/json" -d '{"case_id": "RJ-123"}'
```

## Next Steps (Phase 2)
- Implement hybrid search combining FTS5 and VSS
- Add cosine similarity ranking
- Implement query expansion for better semantic matching
- Create combined search endpoint with RRF ranking
