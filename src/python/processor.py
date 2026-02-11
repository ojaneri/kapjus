import fitz  # PyMuPDF
import os
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv(os.path.join(os.path.dirname(os.path.dirname(__file__)), ".env"))
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

# Debug log file path (for PHP compatibility)
DEBUG_LOG_PATH = "/var/www/html/kapjus.kaponline.com.br/debug.log"

def debug_log(step: str, message: str, params: dict = None):
    """Write to debug.log for PHP compatibility"""
    import datetime
    timestamp = datetime.datetime.now().isoformat()
    params_str = f" | Params: {json.dumps(params)}" if params else ""
    log_entry = f"[{timestamp}] [KAPJUS-{step}] {message}{params_str}\n"
    try:
        with open(DEBUG_LOG_PATH, 'a') as f:
            f.write(log_entry)
    except Exception:
        pass  # Ignore logging errors

def log_step(step: str, message: str, params: dict = None):
    """Log to both logger and debug.log"""
    logger.info(f"[{step}] {message}")
    debug_log(step, message, params)


def call_ai(prompt: str, provider: str = None) -> str:
    """Call AI with the specified or default provider. Returns the response text."""
    effective_provider = provider if provider else IA_PROVIDER
    log_step("AI", f"Calling AI with provider: {effective_provider}", {"prompt_length": len(prompt)})
    
    if effective_provider == "openrouter":
        try:
            response = openrouter_client.chat.completions.create(
                model="deepseek/deepseek-r1:free",
                messages=[
                    {"role": "system", "content": "Você é um assistente jurídico helpful."},
                    {"role": "user", "content": prompt}
                ],
                max_tokens=2048,
                temperature=0.7
            )
            answer = response.choices[0].message.content
            log_step("AI", f"OpenRouter response", {"length": len(answer)})
            return answer
        except Exception as e:
            log_step("AI", f"OpenRouter error", {"error": str(e)})
            raise e
    elif effective_provider == "gemini":
        try:
            url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={GEMINI_API_KEY}"
            response = requests.post(url, json={
                "contents": [{"parts": [{"text": prompt}]}]
            })
            data = response.json()
            if "candidates" in data:
                answer = data["candidates"][0]["content"]["parts"][0]["text"]
                log_step("AI", f"Gemini response", {"length": len(answer)})
                return answer
            else:
                raise Exception(f"Gemini response error: {data}")
        except Exception as e:
            log_step("AI", f"Gemini error", {"error": str(e)})
            raise e
    else:
        raise Exception(f"Unknown provider: {effective_provider}")



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
IA_PROVIDER = os.getenv("IA_PROVIDER", "gemini").lower()  # Options: gemini, openrouter

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


def is_section_heading(line: str) -> bool:
    """
    Determina se uma linha em maiúsculas representa um título/seção lógica.
    """
    stripped = line.strip()
    if len(stripped) < 5:
        return False

    letters = sum(ch.isalpha() for ch in stripped)
    if letters < 4:
        return False

    uppercase_letters = sum(1 for ch in stripped if ch.isalpha() and ch.isupper())
    if uppercase_letters < 3:
        return False

    ratio = uppercase_letters / letters if letters else 0
    return ratio >= 0.7


def split_page_into_sections(page_content: str, page_number: int) -> List[Dict]:
    """
    Divide o texto de uma página em blocos baseados em títulos/sessões em maiúsculas.
    """
    sections = []
    buffer = []
    current_heading = None

    for line in page_content.splitlines():
        stripped_line = line.strip()
        if is_section_heading(stripped_line):
            if buffer:
                chunk_text = "\n".join(buffer).strip()
                if chunk_text:
                    sections.append({"page": page_number, "content": chunk_text, "heading": current_heading})
            current_heading = stripped_line
            buffer = [stripped_line]
            continue

        buffer.append(line)

    if buffer:
        chunk_text = "\n".join(buffer).strip()
        if chunk_text:
            sections.append({"page": page_number, "content": chunk_text, "heading": current_heading})

    return sections


def prepare_logical_chunks(pages: List[Dict]) -> List[Dict]:
    """
    Agrupa todas as páginas em chunks lógicos (títulos em maiúsculas, se disponíveis).
    """
    logical_chunks = []
    for page in pages:
        content = page.get("content", "")
        if not content.strip():
            continue

        sections = split_page_into_sections(content, page["page"])
        logical_chunks.extend(sections or [{"page": page["page"], "content": content.strip(), "heading": None}])

    return logical_chunks

def store_document(case_id: str, filename: str, pages: List[Dict]):
    """
    Armezena o conteúdo extraído no banco de dados SQLite.
    """
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("CREATE TABLE IF NOT EXISTS documents (id INTEGER PRIMARY KEY AUTOINCREMENT, case_id TEXT, filename TEXT, page_number INTEGER, content TEXT)")
    cursor.execute("CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(content, case_id UNINDEXED, filename UNINDEXED, page_number UNINDEXED)")

    chunks = prepare_logical_chunks(pages)
    logger.info(f"Storing {filename} ({len(chunks)} logical chunks)")

    for chunk in chunks:
        chunk_content = chunk["content"]
        cursor.execute("INSERT INTO documents (case_id, filename, page_number, content) VALUES (?, ?, ?, ?)", (case_id, filename, chunk['page'], chunk_content))
        cursor.execute("INSERT INTO documents_fts (content, case_id, filename, page_number) VALUES (?, ?, ?, ?)", (chunk_content, case_id, filename, chunk['page']))
    
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

    chunks = prepare_logical_chunks(pages)
    logger.info(f"Storing embeddings for {filename} ({len(chunks)} logical chunks)")

    for chunk in chunks:
        content = chunk['content']
        page_num = chunk['page']
        
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

def expand_query(question: str, provider: str = None) -> Dict[str, any]:
    """
    Expand user question into keywords for FTS5 and semantic query for VSS.
    Uses AI to generate optimized search terms.
    """
    log_step("EXPAND", f"Iniciando expansao da pergunta", {"question": question[:200]})
    
    if not question or not question.strip():
        log_step("EXPAND", "Pergunta vazia recebida", {"keywords": [], "semantic_query": ""})
        return {"keywords": [], "semantic_query": ""}
    
    prompt = f""" Dada a pergunta do usuário: '{question}', retorne um JSON com:
    - keywords: Lista de termos técnicos e sinônimos para busca por palavra-chave (FTS5)
    - semantic_query: Uma frase otimizada para busca vetorial que capture a intenção
    
    Responda APENAS com o JSON, sem formatação adicional."""
    
    log_step("EXPAND", f"Prompt gerado para IA", {"prompt": prompt[:300]})
    
    try:
        content = call_ai(prompt, provider)
        log_step("EXPAND", f"Resposta raw da IA", {"content": content[:500]})
        
        # Clean up if model adds markdown formatting
        if content.startswith("```"):
            content = "\n".join(content.split("\n")[1:-1])
        
        result = json.loads(content)
        keywords = result.get('keywords', [])
        semantic_query = result.get('semantic_query', '')
        
        log_step("EXPAND", f"Expansao concluida", {"keywords": keywords, "semantic_query": semantic_query[:200]})
        
        return {
            "keywords": keywords,
            "semantic_query": semantic_query if semantic_query else question
        }
    except Exception as e:
        log_step("EXPAND", f"Erro na expansao, usando fallback", {"error": str(e)})
        # Fallback: use question as-is
        fallback_keywords = question.split()[:10]
        return {
            "keywords": fallback_keywords,
            "semantic_query": question
        }

def hybrid_search(keywords: List[str], semantic_query: str, case_id: Optional[str] = None, limit: int = 5) -> Dict[str, List[Dict]]:
    """
    Execute parallel hybrid search using FTS5 and VSS.
    Returns dict with 'fts_results' and 'vss_results'.
    """
    log_step("HYBRID", f"Iniciando busca hibrida", {"keywords": keywords, "semantic_query": semantic_query[:200], "case_id": case_id, "limit": limit})
    
    fts_results = []
    vss_results = []
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # FTS5 Query
    try:
        if keywords:
            # Build FTS5 query with OR operator
            fts_query = " ".join(keywords) + "*"
            log_step("HYBRID-FTS5", f"Executando busca FTS5", {"query": fts_query})
            
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
        
        log_step("HYBRID-FTS5", f"Resultados FTS5 encontrados", {"count": len(fts_results)})
    except Exception as e:
        log_step("HYBRID-FTS5", f"Erro na busca FTS5", {"error": str(e)})
    
    # VSS Query (Vector Similarity Search)
    try:
        if _vss_available and semantic_query:
            log_step("HYBRID-VSS", f"Gerando embedding para busca vetorial", {"query": semantic_query[:200]})
            embedding = get_embedding(semantic_query)
            
            if embedding:
                log_step("HYBRID-VSS", f"Embedding gerado", {"dimension": len(embedding)})
                
                if case_id:
                    log_step("HYBRID-VSS", f"Executando VSS com filtro case_id", {"case_id": case_id})
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
                    log_step("HYBRID-VSS", "Executando VSS sem filtro")
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
        else:
            log_step("HYBRID-VSS", f"VSS pulado", {"vss_available": _vss_available, "has_query": bool(semantic_query)})
        
        log_step("HYBRID-VSS", f"Resultados VSS encontrados", {"count": len(vss_results)})
    except Exception as e:
        log_step("HYBRID-VSS", f"Erro na busca VSS", {"error": str(e)})
    
    conn.close()
    
    log_step("HYBRID", f"Busca hibrida concluida", {"fts_count": len(fts_results), "vss_count": len(vss_results)})
    
    return {
        "fts_results": fts_results,
        "vss_results": vss_results
    }

def rank_results(fts_results: List[Dict], vss_results: List[Dict], top_k: int = 5) -> List[Dict]:
    """
    Merge and rank results using Reciprocal Rank Fusion (RRF).
    score = 1 / (rank * 60 + position)
    """
    log_step("RANK", f"Iniciando ranking de resultados", {"fts_count": len(fts_results), "vss_count": len(vss_results), "top_k": top_k})
    
    if not fts_results and not vss_results:
        log_step("RANK", "Sem resultados para ranquear")
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
    
    log_step("RANK", f"Apos FTS5: {len(ranked_items)} items unicos")
    
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
    
    log_step("RANK", f"Apos VSS: {len(ranked_items)} items unicos")
    
    # Sort by combined RRF score and return top-k
    sorted_results = sorted(ranked_items.values(), key=lambda x: x["rrf_score"], reverse=True)
    
    log_step("RANK", f"Resultados ranqueados", {"total": len(sorted_results)})
    for i, item in enumerate(sorted_results[:10]):
        log_step("RANK", f"  Pos {i+1}: rowid={item['rowid']}, score={item['rrf_score']:.6f}, file={item['filename']}:{item['page']}")
    
    final_results = sorted_results[:top_k]
    log_step("RANK", f"Top-K final", {"count": len(final_results)})
    
    return final_results

def generate_response_with_context(question: str, ranked_chunks: List[Dict], provider: str = None) -> str:
    """
    Generate AI response using the ranked context chunks.
    """
    log_step("RESPONSE", f"Iniciando geracao de resposta", {"question": question[:200], "chunks_count": len(ranked_chunks)})
    
    if not ranked_chunks:
        log_step("RESPONSE", "Sem chunks para gerar resposta")
        return "Não foi possível encontrar informações relevantes para responder à pergunta."
    
    # Build context from chunks
    context_parts = []
    for i, chunk in enumerate(ranked_chunks, 1):
        source_info = f"[{chunk.get('filename', 'unknown')}:pg {chunk.get('page', '?')}]"
        content = chunk.get('content', '')[:1000]  # Limit chunk size
        context_parts.append(f"--- Fonte {i} {source_info} ---\n{content}")
    
    context = "\n\n".join(context_parts)
    log_step("RESPONSE", f"Contexto construido", {"chunks": len(context_parts), "context_length": len(context)})
    
    prompt = f"""Você é um assistente técnico jurídico. Abaixo estão trechos recuperados de documentos. 
Utilize-os estritamente para responder à pergunta final. Se houver contradição, priorize os trechos mais relevantes.

CONTEXTO RECUPERADO:
{context}

PERGUNTA: > {question}

Resposta (cite as fontes utilizadas):"""
    
    try:
        answer = call_ai(prompt, provider)
        log_step("RESPONSE", f"Resposta gerada pela IA", {"answer_length": len(answer), "answer_preview": answer[:200]})
        
        return answer
    except Exception as e:
        log_step("RESPONSE", f"Erro ao gerar resposta", {"error": str(e)})
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
    provider: str = Form(None),
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
        provider: Provedor de IA (openrouter ou gemini). Se não informado, usa IA_PROVIDER do .env
        use_hybrid: Se True (default), usa hybrid search; se False, usa FTS5 puro
    """
    # Use provider from request, or fall back to environment variable
    effective_provider = provider if provider else IA_PROVIDER
    log_step("ASK_IA", f"Requisicao recebida", {"case_id": case_id, "question": question[:100], "provider": effective_provider, "use_hybrid": use_hybrid})
    
    if use_hybrid:
        log_step("ASK_IA", "Modo: HYBRID search pipeline")
        
        # Step 1: Expand query
        expanded = expand_query(question, effective_provider)
        log_step("ASK_IA", f"Expansao: keywords={expanded['keywords']}, semantic={expanded['semantic_query'][:50]}...")
        
        # Step 2: Parallel hybrid search
        search_results = hybrid_search(
            keywords=expanded["keywords"],
            semantic_query=expanded["semantic_query"],
            case_id=case_id,
            limit=10
        )
        log_step("ASK_IA", f"Busca: FTS5={len(search_results['fts_results'])}, VSS={len(search_results['vss_results'])}")
        
        # Step 3: Rank results with RRF
        ranked_chunks = rank_results(
            fts_results=search_results["fts_results"],
            vss_results=search_results["vss_results"],
            top_k=5
        )
        log_step("ASK_IA", f"Ranking: {len(ranked_chunks)} chunks selecionados")
        
        # Step 4: Generate response
        answer = generate_response_with_context(question, ranked_chunks, effective_provider)
        
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
        
        log_step("ASK_IA", f"Resposta gerada: sources={len(response['sources'])}, final_results={response['stats']['final_results']}")
        return response
    
    # Legacy FTS5-only mode
    log_step("ASK_IA", "Modo: LEGACY FTS5-only search")
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("SELECT content FROM documents_fts WHERE case_id = ? AND documents_fts MATCH ? LIMIT 3", 
                  (case_id, question))
    context = "\n".join([r[0] for r in cursor.fetchall()])
    conn.close()

    prompt = f"Contexto:\n{context}\n\nPergunta: {question}"
    
    if effective_provider == "openrouter":
        return await ask_with_openrouter(prompt)
    elif effective_provider == "gemini":
        url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={GEMINI_API_KEY}"
        response = requests.post(url, json={"contents": [{"parts": [{"text": prompt}]}]})
        return response.json()
    
    return {"error": f"Provider {effective_provider} not configured"}


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

class DeleteFileQuery(BaseModel):
    case_id: str
    filename: str

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


@app.post("/delete_file")
async def delete_file(request: DeleteFileQuery):
    """Remove a processed document (file + metadata) for a case."""
    filename = os.path.basename(request.filename)
    log_step("DELETE", "Requested document deletion", {"case_id": request.case_id, "filename": filename})

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT id FROM documents WHERE case_id = ? AND filename = ?", (request.case_id, filename))
        rows = cursor.fetchall()

        if not rows:
            log_step("DELETE", "Document not found for deletion", {"case_id": request.case_id, "filename": filename})
            raise HTTPException(status_code=404, detail="Documento não encontrado")

        doc_ids = [row[0] for row in rows]
        log_step("DELETE", "Deleting database entries", {"ids": doc_ids})

        cursor.executemany("DELETE FROM documents WHERE id = ?", [(doc_id,) for doc_id in doc_ids])
        cursor.executemany("DELETE FROM documents_fts WHERE rowid = ?", [(doc_id,) for doc_id in doc_ids])

        if _vss_available:
            cursor.executemany("DELETE FROM vss_chunks WHERE rowid = ?", [(doc_id,) for doc_id in doc_ids])

        conn.commit()
        log_step("DELETE", "Database cleanup completed", {"deleted": len(doc_ids)})
    except HTTPException:
        raise
    except Exception as exc:
        log_step("DELETE", "Database error during delete", {"error": str(exc)})
        raise HTTPException(status_code=500, detail=f"Erro ao remover metadados: {exc}")
    finally:
        conn.close()

    file_path = os.path.join(UPLOAD_DIR, filename)
    try:
        if os.path.exists(file_path):
            os.remove(file_path)
            log_step("DELETE", "Arquivo físico removido", {"path": file_path})
        else:
            log_step("DELETE", "Arquivo físico não encontrado (ignorado)", {"path": file_path})
    except Exception as exc:
        log_step("DELETE", "Erro ao remover arquivo físico", {"error": str(exc)})
        raise HTTPException(status_code=500, detail=f"Erro ao remover arquivo: {exc}")

    log_step("DELETE", "Documento excluído com sucesso", {"case_id": request.case_id, "filename": filename})
    return {"status": "success", "message": "Arquivo excluído com sucesso"}

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
