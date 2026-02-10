import fitz  # PyMuPDF
import os
import json
import sqlite3
import requests
import uvicorn
import logging
import uuid
import time
import shutil
import threading
from typing import List, Dict, Optional
from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from PIL import Image
import pytesseract
from docx import Document as DocxDocument
from openpyxl import load_workbook
from openai import OpenAI

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

# Configurações de Caminhos (must be defined first)
BASE_DIR = "/var/www/html/kapjus.kaponline.com.br"
UPLOAD_DIR = os.path.join(BASE_DIR, "storage/uploads")
DB_PATH = os.path.join(BASE_DIR, "database/kapjus.db")
SOCKET_PATH = os.path.join(BASE_DIR, "socket/kapjus.sock")
CHUNKS_DIR = os.path.join(BASE_DIR, "storage/uploads/chunks")

# Chunked upload configuration
CHUNK_SIZE = 1024 * 1024  # 1MB chunks
MAX_FILE_SIZE = 100 * 1024 * 1024  # 100MB max
UPLOAD_TIMEOUT = 24 * 60 * 60  # 24 hours in seconds

# Create chunks directory on startup
os.makedirs(CHUNKS_DIR, exist_ok=True)

# Configurações de IA (Devem ser configuradas no ambiente)
OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY", "")
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")

# OpenRouter Model Fallback Chain (best to worst)
OPENROUTER_MODELS = [
    "deepseek/deepseek-r1:free",
    "meta-llama/llama-3.3-70b-instruct:free",
    "tngtech/deepseek-r1t2-chimera:free",
    "arcee-ai/trinity-large-preview:free",
    "qwen/qwen3-next-80b-a3b-instruct:free",
    "openai/gpt-oss-120b:free",
    "stepfun/step-3-5-flash:free",
    "z-ai/glm-4-5-air:free",
    "nvidia/nemotron-3-nano-30b-a3b:free",
    "google/gemma-3-27b:free",
    "arcee-ai/trinity-mini:free",
    "openai/gpt-oss-20b:free",
    "nvidia/nemotron-nano-9b-v2:free",
    "upstage/solar-pro-3:free",
    "venice/venice-uncensored:free",
    "liquid/lfm2.5-1.2b-instruct:free",
]

# Create OpenRouter client
openrouter_client = OpenAI(
    api_key=OPENROUTER_API_KEY,
    base_url="https://openrouter.ai/api/v1"
)

app = FastAPI()

# Enable VSS extension for vector similarity search
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

# Test VSS availability on module load
_vss_available = False
try:
    _test_conn = sqlite3.connect(DB_PATH)
    _vss_available = enable_vss_extension(_test_conn)
    _test_conn.close()
except Exception:
    pass

logger.info(f"VSS extension available: {_vss_available}")

# Cleanup stale uploads (older than UPLOAD_TIMEOUT)
def cleanup_stale_uploads():
    """Remove chunks directories older than UPLOAD_TIMEOUT"""
    if not os.path.exists(CHUNKS_DIR):
        return
    
    current_time = time.time()
    for upload_id in os.listdir(CHUNKS_DIR):
        upload_path = os.path.join(CHUNKS_DIR, upload_id)
        if os.path.isdir(upload_path):
            stat = os.stat(upload_path)
            if current_time - stat.st_mtime > UPLOAD_TIMEOUT:
                shutil.rmtree(upload_path)
                logger.info(f"Cleaned up stale upload: {upload_id}")

# Cleanup on startup
cleanup_stale_uploads()

class SearchQuery(BaseModel):
    case_id: str
    query: str
    top_k: int = 5

def extract_text_from_pdf(pdf_path: str) -> List[Dict]:
    """
    Extrai texto de PDF. Se o PDF for escaneado (sem texto nativo),
    aplica OCR usando pytesseract.
    """
    doc = fitz.open(pdf_path)
    pages_content = []
    
    for page_num in range(len(doc)):
        page = doc.load_page(page_num)
        text = page.get_text("text")
        
        # Verificar se o texto extraído está vazio ou é muito curto (possível PDF escaneado)
        if not text or len(text.strip()) < 50:
            # Aplicar OCR na página
            pix = page.get_pixmap(matrix=fitz.Matrix(300/72, 300/72))
            img_data = pix.tobytes("png")
            try:
                from PIL import Image
                import io
                img = Image.open(io.BytesIO(img_data))
                text = pytesseract.image_to_string(img, lang='por')
            except Exception as ocr_error:
                text = f"[OCR Error: {str(ocr_error)}]"
        
        pages_content.append({"page": page_num + 1, "content": text})
    
    return pages_content

def extract_text_from_image(image_path: str) -> List[Dict]:
    """
    Extrai texto de imagens (JPG, PNG) usando OCR com pytesseract.
    """
    try:
        img = Image.open(image_path)
        text = pytesseract.image_to_string(img, lang='por')
        return [{"page": 1, "content": text}]
    except Exception as e:
        return [{"page": 1, "content": f"[OCR Error: {str(e)}]"}]

def extract_text_from_docx(docx_path: str) -> List[Dict]:
    """
    Extrai texto de documentos DOCX usando python-docx.
    """
    try:
        doc = DocxDocument(docx_path)
        full_text = []
        for paragraph in doc.paragraphs:
            full_text.append(paragraph.text)
        
        # Also extract from tables
        for table in doc.tables:
            for row in table.rows:
                for cell in row.cells:
                    full_text.append(cell.text)
        
        text = "\n".join(full_text)
        return [{"page": 1, "content": text}]
    except Exception as e:
        return [{"page": 1, "content": f"[DOCX Error: {str(e)}]"}]

def extract_text_from_txt(txt_path: str) -> List[Dict]:
    """
    Extrai texto de arquivos TXT.
    """
    try:
        with open(txt_path, 'r', encoding='utf-8') as f:
            text = f.read()
        return [{"page": 1, "content": text}]
    except Exception as e:
        return [{"page": 1, "content": f"[TXT Error: {str(e)}]"}]

def extract_text_from_xlsx(xlsx_path: str) -> List[Dict]:
    """
    Extrai texto de arquivos Excel (XLSX) usando openpyxl.
    """
    try:
        wb = load_workbook(xlsx_path)
        full_text = []
        
        for sheet_name in wb.sheetnames:
            ws = wb[sheet_name]
            full_text.append(f"=== Sheet: {sheet_name} ===")
            
            for row in ws.iter_rows(values_only=True):
                row_text = []
                for cell in row:
                    if cell is not None:
                        row_text.append(str(cell))
                if row_text:
                    full_text.append(" | ".join(row_text))
        
        text = "\n".join(full_text)
        return [{"page": 1, "content": text}]
    except Exception as e:
        return [{"page": 1, "content": f"[XLSX Error: {str(e)}]"}]

def extract_text_from_document(file_path: str) -> List[Dict]:
    """
    Detecta o tipo de arquivo e roteia para a função de extração apropriada.
    """
    ext = os.path.splitext(file_path)[1].lower()
    
    extractors = {
        '.pdf': extract_text_from_pdf,
        '.jpg': extract_text_from_image,
        '.jpeg': extract_text_from_image,
        '.png': extract_text_from_image,
        '.docx': extract_text_from_docx,
        '.txt': extract_text_from_txt,
        '.xlsx': extract_text_from_xlsx,
    }
    
    if ext in extractors:
        return extractors[ext](file_path)
    else:
        return [{"page": 1, "content": f"[Unsupported format: {ext}]"}]

def store_document(case_id: str, filename: str, pages: List[Dict]):
    """
    Armezena o conteúdo extraído no banco de dados SQLite.
    """
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, case_id TEXT, filename TEXT, page_number INTEGER, content TEXT)")
    cursor.execute("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(content, case_id UNINDEXED, filename UNINDEXED, page_number UNINDEXED)")

    for p in pages:
        cursor.execute("INSERT INTO documents (case_id, filename, page_number, content) VALUES (?, ?, ?, ?)", (case_id, filename, p['page'], p['content']))
        cursor.execute("INSERT INTO documents_fts (content, case_id, filename, page_number) VALUES (?, ?, ?, ?)", (p['content'], case_id, filename, p['page']))
    
    conn.commit()
    conn.close()

# ==================== VSS (Vector Similarity Search) Infrastructure ====================

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

def store_document_with_embedding(case_id: str, filename: str, pages: List[Dict]):
    """Store document with both text and vector embeddings."""
    conn = sqlite3.connect(DB_PATH)
    
    if _vss_available:
        create_vss_table(conn)
    
    cursor = conn.cursor()
    cursor.execute("CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, case_id TEXT, filename TEXT, page_number INTEGER, content TEXT)")
    cursor.execute("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(content, case_id UNINDEXED, filename UNINDEXED, page_number UNINDEXED)")

    for p in pages:
        content = p['content']
        page_num = p['page']
        
        # Store text in documents and FTS tables
        cursor.execute("INSERT INTO documents (case_id, filename, page_number, content) VALUES (?, ?, ?, ?)", 
                      (case_id, filename, page_num, content))
        doc_id = cursor.lastrowid
        cursor.execute("INSERT INTO documents_fts (content, case_id, filename, page_number) VALUES (?, ?, ?, ?)", 
                      (content, case_id, filename, page_num))
        
        # Generate and store embedding if VSS is available
        if _vss_available:
            embedding = get_embedding(content)
            if embedding:
                try:
                    cursor.execute("INSERT INTO vss_chunks (rowid, embedding) VALUES (?, ?)", 
                                  (doc_id, embedding))
                    logger.debug(f"Stored embedding for {filename} page {page_num}")
                except Exception as e:
                    logger.warning(f"Failed to store embedding for {filename} page {page_num}: {e}")
    
    conn.commit()
    conn.close()
    logger.info(f"Stored document with embeddings: {filename} ({len(pages)} pages)")

def index_document_embeddings(conn: sqlite3.Connection, case_id: str = None):
    """Generate and store embeddings for documents without vectors."""
    if not _vss_available:
        logger.warning("VSS extension not available, skipping embedding indexing")
        return 0
    
    cursor = conn.cursor()
    
    # Create vss_chunks table if it doesn't exist
    create_vss_table(conn)
    
    # Find documents without embeddings
    if case_id:
        cursor.execute("""
            SELECT d.id, d.case_id, d.filename, d.page_number, d.content
            FROM documents d
            LEFT JOIN vss_chunks v ON d.id = v.rowid
            WHERE v.rowid IS NULL AND d.case_id = ?
            ORDER BY d.id
        """, (case_id,))
    else:
        cursor.execute("""
            SELECT d.id, d.case_id, d.filename, d.page_number, d.content
            FROM documents d
            LEFT JOIN vss_chunks v ON d.id = v.rowid
            WHERE v.rowid IS NULL
            ORDER BY d.id
        """)
    
    docs_to_index = cursor.fetchall()
    
    if not docs_to_index:
        logger.info("No documents need embedding indexing")
        return 0
    
    logger.info(f"Indexing {len(docs_to_index)} documents for vector search")
    indexed_count = 0
    
    for doc in docs_to_index:
        doc_id, doc_case_id, filename, page_num, content = doc
        embedding = get_embedding(content)
        if embedding:
            try:
                cursor.execute("INSERT INTO vss_chunks (rowid, embedding) VALUES (?, ?)", 
                              (doc_id, embedding))
                indexed_count += 1
                logger.debug(f"Indexed embedding for {filename} page {page_num}")
            except Exception as e:
                logger.warning(f"Failed to index embedding for {filename} page {page_num}: {e}")
    
    conn.commit()
    logger.info(f"Indexed {indexed_count} embeddings successfully")
    return indexed_count

class SetupVSSQuery(BaseModel):
    case_id: Optional[str] = None

@app.post("/setup_vss")
async def setup_vss(query: SetupVSSQuery):
    """Setup VSS infrastructure: create vss_chunks table and index existing documents."""
    if not _vss_available:
        return JSONResponse(
            status_code=503,
            content={"status": "error", "message": "VSS extension not available"}
        )
    
    conn = sqlite3.connect(DB_PATH)
    
    try:
        # Create vss_chunks table
        table_created = create_vss_table(conn)
        
        if not table_created:
            raise Exception("Failed to create vss_chunks table")
        
        # Index existing documents
        indexed = index_document_embeddings(conn, query.case_id)
        
        # Get stats
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM vss_chunks")
        total_embeddings = cursor.fetchone()[0]
        
        return {
            "status": "success",
            "message": "VSS infrastructure setup complete",
            "vss_available": _vss_available,
            "table_created": True,
            "indexed_documents": indexed,
            "total_embeddings": total_embeddings
        }
    
    except Exception as e:
        logger.error(f"VSS setup failed: {e}")
        return JSONResponse(
            status_code=500,
            content={"status": "error", "message": str(e)}
        )
    finally:
        conn.close()

@app.get("/vss_status")
async def vss_status():
    """Get VSS infrastructure status."""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    status = {
        "vss_available": _vss_available,
        "vss_chunks_exists": False,
        "total_embeddings": 0,
        "documents_without_embeddings": 0
    }
    
    try:
        if _vss_available:
            cursor.execute("SELECT COUNT(*) FROM vss_chunks")
            status["total_embeddings"] = cursor.fetchone()[0]
            status["vss_chunks_exists"] = True
            
            cursor.execute("""
                SELECT COUNT(*) FROM documents d
                LEFT JOIN vss_chunks v ON d.id = v.rowid
                WHERE v.rowid IS NULL
            """)
            status["documents_without_embeddings"] = cursor.fetchone()[0]
    except Exception as e:
        logger.error(f"Failed to get VSS status: {e}")
    finally:
        conn.close()
    
    return status

# ==================== HYBRID SEARCH PHASE 2 ====================

def expand_query(question: str) -> Dict[str, any]:
    """
    Expand user question into keywords for FTS5 and semantic query for VSS.
    Uses AI to generate optimized search terms.
    """
    if not question or not question.strip():
        return {"keywords": [], "semantic_query": ""}
    
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
        # Clean up if model adds markdown formatting
        if content.startswith("```"):
            content = "\n".join(content.split("\n")[1:-1])
        
        result = json.loads(content)
        logger.info(f"Query expanded: {len(result.get('keywords', []))} keywords, semantic: '{result.get('semantic_query', '')[:50]}...'")
        return {
            "keywords": result.get("keywords", []),
            "semantic_query": result.get("semantic_query", question)
        }
    except Exception as e:
        logger.warning(f"Query expansion failed, using fallback: {e}")
        # Fallback: use question as-is
        return {
            "keywords": question.split()[:10],
            "semantic_query": question
        }

def hybrid_search(keywords: List[str], semantic_query: str, case_id: Optional[str] = None, limit: int = 5) -> Dict[str, List[Dict]]:
    """
    Execute parallel hybrid search using FTS5 and VSS.
    Returns dict with 'fts_results' and 'vss_results'.
    """
    fts_results = []
    vss_results = []
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # FTS5 Query
    try:
        if keywords:
            # Build FTS5 query with OR operator
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
        
        logger.info(f"FTS5 search returned {len(fts_results)} results")
    except Exception as e:
        logger.warning(f"FTS5 search failed: {e}")
    
    # VSS Query (Vector Similarity Search)
    try:
        if _vss_available and semantic_query:
            # Generate embedding for semantic query
            embedding = get_embedding(semantic_query)
            
            if embedding:
                if case_id:
                    # VSS with case_id filter - need to join with documents table
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
        
        logger.info(f"VSS search returned {len(vss_results)} results")
    except Exception as e:
        logger.warning(f"VSS search failed: {e}")
    
    conn.close()
    
    return {
        "fts_results": fts_results,
        "vss_results": vss_results
    }

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
        # FTS5 rank is lower is better, convert to position-based rank
        rank = position + 1
        rrf_score = 1.0 / (rank * 60 + position + 1)
        
        if rowid not in ranked_items:
            ranked_items[rowid] = {"rowid": rowid, "content": item["content"], 
                                   "filename": item["filename"], "page": item["page"],
                                   "fts_rank": rank, "vss_rank": None, "rrf_score": rrf_score}
        else:
            # Item exists, combine scores
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
    
    logger.info(f"RRF merged {len(sorted_results)} unique documents, returning top {top_k}")
    return sorted_results[:top_k]

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
        logger.info(f"Generated response with {len(ranked_chunks)} context chunks")
        return answer
    except Exception as e:
        logger.error(f"Response generation failed: {e}")
        return f"Erro ao gerar resposta: {str(e)}"

class HybridSearchQuery(BaseModel):
    question: str
    case_id: Optional[str] = None
    top_k: int = 5

@app.post("/hybrid_search")
async def hybrid_search_endpoint(query: HybridSearchQuery):
    """
    Hybrid search endpoint combining FTS5 and VSS search with AI response generation.
    
    Flow:
    1. Expand question using AI
    2. Parallel FTS5 + VSS search
    3. RRF ranking of results
    4. Generate response with context
    """
    logger.info(f"Hybrid search request: {query.question[:100]}...")
    
    try:
        # Step 1: Expand query
        expanded = expand_query(query.question)
        
        # Step 2: Parallel hybrid search
        search_results = hybrid_search(
            keywords=expanded["keywords"],
            semantic_query=expanded["semantic_query"],
            case_id=query.case_id,
            limit=query.top_k * 2  # Fetch more for ranking
        )
        
        # Step 3: Rank results
        ranked_chunks = rank_results(
            fts_results=search_results["fts_results"],
            vss_results=search_results["vss_results"],
            top_k=query.top_k
        )
        
        # Step 4: Generate response
        answer = generate_response_with_context(query.question, ranked_chunks)
        
        # Build sources list
        sources = [
            {
                "rowid": chunk["rowid"],
                "filename": chunk["filename"],
                "page": chunk["page"],
                "score": round(chunk["rrf_score"], 6)
            }
            for chunk in ranked_chunks
        ]
        
        return {
            "answer": answer,
            "sources": sources,
            "query_expansion": {
                "keywords": expanded["keywords"],
                "semantic_query": expanded["semantic_query"]
            },
            "stats": {
                "fts_results": len(search_results["fts_results"]),
                "vss_results": len(search_results["vss_results"]),
                "final_results": len(ranked_chunks)
            }
        }
    except Exception as e:
        logger.error(f"Hybrid search failed: {e}")
        return JSONResponse(
            status_code=500,
            content={"error": str(e)}
        )

@app.post("/ask_ia")
async def ask_ia_v2(
    case_id: str = Form(...), 
    question: str = Form(...), 
    provider: str = Form("openrouter"),
    use_hybrid: bool = Form(True)  # DEFAULT TO HYBRID
):
    """
    Unified endpoint for AI questions using hybrid search pipeline by default.
    
    Flow:
    1. Query expansion (keywords + semantic_query) via AI
    2. Parallel FTS5 + VSS search
    3. RRF ranking
    4. Response generation with context
    
    Args:
        case_id: ID do caso/processual
        question: Pergunta do usuário
        provider: Provedor de IA (openrouter ou gemini)
        use_hybrid: Se True (default), usa hybrid search; se False, usa FTS5 puro
    """
    logger.info(f"=== /ask_ia DIAGNOSTIC ===")
    logger.info(f"  case_id: {case_id}")
    logger.info(f"  question: {question[:100]}...")
    logger.info(f"  use_hybrid: {use_hybrid}")
    logger.info(f"  provider: {provider}")
    
    if use_hybrid:
        logger.info(f"Using HYBRID search pipeline for ask_ia")
        
        # Step 1: Expand query
        expanded = expand_query(question)
        logger.info(f"  Query expansion: {len(expanded['keywords'])} keywords, semantic: '{expanded['semantic_query'][:50]}...'")
        
        # Step 2: Parallel hybrid search
        search_results = hybrid_search(
            keywords=expanded["keywords"],
            semantic_query=expanded["semantic_query"],
            case_id=case_id,
            limit=10
        )
        logger.info(f"  FTS5 results: {len(search_results['fts_results'])}, VSS results: {len(search_results['vss_results'])}")
        
        # Step 3: Rank results with RRF
        ranked_chunks = rank_results(
            fts_results=search_results["fts_results"],
            vss_results=search_results["vss_results"],
            top_k=5
        )
        logger.info(f"  Ranked chunks: {len(ranked_chunks)}")
        
        # Step 4: Generate response
        answer = generate_response_with_context(question, ranked_chunks)
        
        # Build response with sources
        sources = [
            {
                "rowid": chunk["rowid"],
                "filename": chunk["filename"],
                "page": chunk["page"],
                "score": round(chunk["rrf_score"], 6)
            }
            for chunk in ranked_chunks
        ]
        
        response = {
            "answer": answer,
            "hybrid_mode": True,
            "query_expansion": {
                "keywords": expanded["keywords"],
                "semantic_query": expanded["semantic_query"]
            },
            "sources": sources,
            "stats": {
                "fts_results": len(search_results["fts_results"]),
                "vss_results": len(search_results["vss_results"]),
                "final_results": len(ranked_chunks)
            }
        }
        logger.info(f"  Response generated with {len(sources)} sources")
        return response
    
    # Legacy FTS5-only mode
    logger.info(f"Using LEGACY FTS5-only search (use_hybrid=False)")
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("SELECT content FROM documents_fts WHERE case_id = ? AND documents_fts MATCH ? LIMIT 3", 
                  (case_id, question))
    context = "\n".join([r[0] for r in cursor.fetchall()])
    conn.close()

    prompt = f"Contexto:\n{context}\n\nPergunta: {question}"
    
    if provider == "openrouter":
        return await ask_with_openrouter(prompt)
    elif provider == "gemini":
        url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={GEMINI_API_KEY}"
        response = requests.post(url, json={"contents": [{"parts": [{"text": prompt}]}]})
        return response.json()
    
    return {"error": "Provider not configured"}


@app.post("/process_pdf")
async def process_pdf(case_id: str = Form(...), file: UploadFile = File(...)):
    """
    Endpoint legacy para processar PDFs (mantido para compatibilidade).
    """
    os.makedirs(UPLOAD_DIR, exist_ok=True)
    file_path = os.path.join(UPLOAD_DIR, file.filename)
    with open(file_path, "wb") as buffer:
        buffer.write(await file.read())
    
    pages = extract_text_from_pdf(file_path)
    store_document(case_id, file.filename, pages)
    
    return {"status": "success", "pages": len(pages), "filename": file.filename}

class DocumentsQuery(BaseModel):
    case_id: str
    top_k: int = 100

@app.post("/documents")
async def get_documents(query: DocumentsQuery):
    """Get list of documents for a case"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    # Get unique filenames with page count for a case
    cursor.execute("""
        SELECT filename, COUNT(*) as page_count, MAX(id) as latest_id
        FROM documents 
        WHERE case_id = ? 
        GROUP BY filename 
        ORDER BY latest_id DESC
        LIMIT ?
    """, (query.case_id, query.top_k))
    results = cursor.fetchall()
    conn.close()
    return [{"filename": r[0], "page_count": r[1]} for r in results]

@app.post("/search")
async def search(query: SearchQuery):
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("SELECT filename, page_number, content, snippet(documents_fts, 0, '<b>', '</b>', '...', 64) FROM documents_fts WHERE case_id = ? AND documents_fts MATCH ? LIMIT ?", (query.case_id, query.query, query.top_k))
    results = cursor.fetchall()
    conn.close()
    return [{"filename": r[0], "page": r[1], "content": r[2], "snippet": r[3]} for r in results]

    
    return {"error": "Provider not configured"}


async def ask_with_openrouter(prompt: str) -> Dict:
    """
    Faz pergunta à IA usando OpenRouter com fallback automático entre modelos.
    Returns dict with 'answer' field for frontend compatibility.
    """
    for model in OPENROUTER_MODELS:
        try:
            logger.info(f"Trying OpenRouter model: {model}")
            
            response = openrouter_client.chat.completions.create(
                model=model,
                messages=[
                    {"role": "system", "content": "Você é um assistente jurídico helpful. Responda em português brasileiro, de forma clara e profissional."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=2048,
                temperature=0.7
            )
            
            # Extract answer text from response
            answer_text = response.choices[0].message.content
            logger.info(f"Model {model} succeeded, response length: {len(answer_text)}")
            
            # Return format with 'answer' field for frontend compatibility
            return {
                "answer": answer_text,
                "model": model,
                "provider": "openrouter"
            }
            
        except Exception as e:
            logger.warning(f"Model {model} failed: {str(e)}")
            continue
    
    logger.error("All OpenRouter models failed")
    return {"error": "All models failed", "details": "Unable to get response from any OpenRouter model"}

class DeleteFileQuery(BaseModel):
    case_id: str
    filename: str

# ==================== CHUNKED UPLOAD ENDPOINTS ====================

class UploadInitRequest(BaseModel):
    case_id: str
    filename: str
    total_size: int
    total_chunks: int

@app.post("/upload_init")
async def upload_init(request: UploadInitRequest):
    """Initialize a chunked upload session"""
    # Validate file size
    if request.total_size > MAX_FILE_SIZE:
        raise HTTPException(status_code=400, detail=f"File size exceeds maximum limit of {MAX_FILE_SIZE / (1024*1024)}MB")
    
    # Generate unique upload ID
    upload_id = str(uuid.uuid4())
    
    # Create upload directory
    upload_dir = os.path.join(CHUNKS_DIR, upload_id)
    os.makedirs(upload_dir, exist_ok=True)
    
    # Store upload metadata
    metadata = {
        "upload_id": upload_id,
        "case_id": request.case_id,
        "filename": request.filename,
        "total_size": request.total_size,
        "total_chunks": request.total_chunks,
        "uploaded_chunks": [],
        "created_at": time.time()
    }
    
    metadata_file = os.path.join(upload_dir, "metadata.json")
    with open(metadata_file, 'w') as f:
        json.dump(metadata, f)
    
    logger.info(f"Upload initialized: {upload_id} - {request.filename}")
    
    return {
        "status": "success",
        "upload_id": upload_id,
        "chunk_size": CHUNK_SIZE,
        "total_chunks": request.total_chunks
    }

class UploadChunkRequest(BaseModel):
    upload_id: str
    chunk_index: int
    filename: str

@app.post("/upload_chunk")
async def upload_chunk(
    upload_id: str = Form(...),
    chunk_index: int = Form(...),
    filename: str = Form(...),
    case_id: str = Form(...),
    chunk: UploadFile = File(...)
):
    """Receive and store a single chunk"""
    upload_dir = os.path.join(CHUNKS_DIR, upload_id)
    metadata_file = os.path.join(upload_dir, "metadata.json")
    
    # Check if upload session exists
    if not os.path.exists(metadata_file):
        raise HTTPException(status_code=404, detail="Upload session not found or expired")
    
    # Load metadata
    with open(metadata_file, 'r') as f:
        metadata = json.load(f)
    
    # Validate chunk index
    if chunk_index < 0 or chunk_index >= metadata["total_chunks"]:
        raise HTTPException(status_code=400, detail="Invalid chunk index")
    
    # Check if chunk already uploaded
    if chunk_index in metadata["uploaded_chunks"]:
        return {"status": "success", "message": "Chunk already uploaded", "chunk_index": chunk_index}
    
    # Save chunk file
    chunk_filename = f"chunk_{chunk_index:06d}"
    chunk_path = os.path.join(upload_dir, chunk_filename)
    
    with open(chunk_path, "wb") as buffer:
        content = await chunk.read()
        buffer.write(content)
    
    # Update metadata
    metadata["uploaded_chunks"].append(chunk_index)
    metadata["uploaded_chunks"].sort()
    
    with open(metadata_file, 'w') as f:
        json.dump(metadata, f)
    
    progress = (len(metadata["uploaded_chunks"]) / metadata["total_chunks"]) * 100
    
    logger.info(f"Chunk received: {upload_id} - chunk {chunk_index + 1}/{metadata['total_chunks']} ({progress:.1f}%)")
    
    return {
        "status": "success",
        "message": "Chunk uploaded successfully",
        "chunk_index": chunk_index,
        "uploaded_chunks": len(metadata["uploaded_chunks"]),
        "total_chunks": metadata["total_chunks"],
        "progress": progress
    }

@app.get("/upload_status/{upload_id}")
async def get_upload_status(upload_id: str):
    """Get the status of an upload session"""
    upload_dir = os.path.join(CHUNKS_DIR, upload_id)
    metadata_file = os.path.join(upload_dir, "metadata.json")
    
    if not os.path.exists(metadata_file):
        return JSONResponse(status_code=404, content={"status": "error", "message": "Upload session not found or expired"})
    
    with open(metadata_file, 'r') as f:
        metadata = json.load(f)
    
    uploaded_chunks = len(metadata["uploaded_chunks"])
    total_chunks = metadata["total_chunks"]
    
    return {
        "status": "in_progress",
        "upload_id": upload_id,
        "filename": metadata["filename"],
        "uploaded_chunks": metadata["uploaded_chunks"],
        "total_chunks": total_chunks,
        "progress": (uploaded_chunks / total_chunks) * 100 if total_chunks > 0 else 0,
        "missing_chunks": [i for i in range(total_chunks) if i not in metadata["uploaded_chunks"]],
        "created_at": metadata.get("created_at")
    }

class UploadCompleteRequest(BaseModel):
    upload_id: str
    case_id: str

@app.post("/upload_complete")
async def upload_complete(request: UploadCompleteRequest):
    """Finalize a chunked upload by merging all chunks"""
    upload_dir = os.path.join(CHUNKS_DIR, request.upload_id)
    metadata_file = os.path.join(upload_dir, "metadata.json")
    
    if not os.path.exists(metadata_file):
        raise HTTPException(status_code=404, detail="Upload session not found or expired")
    
    with open(metadata_file, 'r') as f:
        metadata = json.load(f)
    
    # Check if all chunks are uploaded
    uploaded_set = set(metadata["uploaded_chunks"])
    expected_set = set(range(metadata["total_chunks"]))
    
    if uploaded_set != expected_set:
        missing = expected_set - uploaded_set
        raise HTTPException(status_code=400, detail=f"Missing chunks: {list(missing)}")
    
    # Merge chunks
    final_path = os.path.join(UPLOAD_DIR, metadata["filename"])
    
    with open(final_path, "wb") as outfile:
        for i in range(metadata["total_chunks"]):
            chunk_path = os.path.join(upload_dir, f"chunk_{i:06d}")
            with open(chunk_path, "rb") as infile:
                outfile.write(infile.read())
    
    # Process the uploaded file
    case_id = request.case_id or metadata["case_id"]
    filename = metadata["filename"]
    
    try:
        # Extract text from the document
        pages = extract_text_from_document(final_path)
        
        # Store in database
        store_document(case_id, filename, pages)
        
        logger.info(f"Upload complete: {filename} - {len(pages)} pages processed")
        
    except Exception as e:
        logger.error(f"Error processing uploaded file: {e}")
        # Clean up on error
        if os.path.exists(final_path):
            os.remove(final_path)
        raise HTTPException(status_code=500, detail=f"Error processing file: {str(e)}")
    
    finally:
        # Clean up chunks directory
        shutil.rmtree(upload_dir)
    
    return {
        "status": "success",
        "message": "File uploaded and processed successfully",
        "filename": filename,
        "pages": len(pages)
    }

@app.delete("/upload/{upload_id}")
async def cancel_upload(upload_id: str):
    """Cancel an upload session and clean up chunks"""
    upload_dir = os.path.join(CHUNKS_DIR, upload_id)
    
    if os.path.exists(upload_dir):
        shutil.rmtree(upload_dir)
        logger.info(f"Upload cancelled: {upload_id}")
        return {"status": "success", "message": "Upload cancelled"}
    
    return JSONResponse(status_code=404, content={"status": "error", "message": "Upload session not found"})


@app.get("/health")
async def health_check():
    """Health check endpoint for service monitoring"""
    return {"status": "healthy", "service": "kapjus-rag"}

if __name__ == "__main__":
    socket_dir = os.path.dirname(SOCKET_PATH)
    os.makedirs(socket_dir, exist_ok=True)
    if os.path.exists(SOCKET_PATH):
        os.remove(SOCKET_PATH)
    
    def run_uvicorn_tcp():
        """Run uvicorn on TCP port 8000"""
        uvicorn.run(app, host="127.0.0.1", port=8000, log_level="info")
    
    # Start TCP server in background thread
    tcp_thread = threading.Thread(target=run_uvicorn_tcp, daemon=True)
    tcp_thread.start()
    
    # Run main server on Unix socket
    uvicorn.run(app, uds=SOCKET_PATH, log_level="info")
