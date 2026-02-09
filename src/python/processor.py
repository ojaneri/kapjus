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

@app.post("/process_document")
async def process_document(case_id: str = Form(...), file: UploadFile = File(...)):
    """
    Endpoint unificado para processar documentos de qualquer formato suportado.
    """
    os.makedirs(UPLOAD_DIR, exist_ok=True)
    file_path = os.path.join(UPLOAD_DIR, file.filename)
    
    with open(file_path, "wb") as buffer:
        buffer.write(await file.read())
    
    # Extrair texto baseado no tipo de arquivo
    pages = extract_text_from_document(file_path)
    
    # Armezenar no banco de dados
    store_document(case_id, file.filename, pages)
    
    return {"status": "success", "pages": len(pages), "filename": file.filename}

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

@app.post("/ask_ia")
async def ask_ia(case_id: str = Form(...), question: str = Form(...), provider: str = Form("openrouter")):
    """
    Endpoint para perguntas à IA com suporte a OpenRouter com fallback de modelos.
    """
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("SELECT content FROM documents_fts WHERE case_id = ? AND documents_fts MATCH ? LIMIT 3", (case_id, question))
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


async def ask_with_openrouter(prompt: str) -> Dict:
    """
    Faz pergunta à IA usando OpenRouter com fallback automático entre modelos.
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
            
            # Format response similar to Gemini API structure
            result = {
                "candidates": [
                    {
                        "content": {
                            "parts": [
                                {"text": response.choices[0].message.content}
                            ]
                        },
                        "model": model,
                        "provider": "openrouter"
                    }
                ]
            }
            
            logger.info(f"Successfully used model: {model}")
            return result
            
        except Exception as e:
            logger.warning(f"Model {model} failed: {str(e)}")
            continue
    
    logger.error("All OpenRouter models failed")
    return {"error": "All models failed", "details": "Unable to get response from any OpenRouter model"}

class DeleteFileQuery(BaseModel):
    case_id: str
    filename: str

@app.post("/delete_file")
async def delete_file(query: DeleteFileQuery):
    """Delete a file from uploads directory and database"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()    
    # Delete from documents table
    cursor.execute("DELETE FROM documents WHERE case_id = ? AND filename = ?", (query.case_id, query.filename))
    # Delete from documents_fts index
    cursor.execute("DELETE FROM documents_fts WHERE case_id = ? AND filename = ?", (query.case_id, query.filename))
    conn.commit()
    conn.close()
    
    # Delete file from filesystem
    file_path = os.path.join(UPLOAD_DIR, query.filename)
    if os.path.exists(file_path):
        os.remove(file_path)
        return {"status": "success", "message": "File deleted successfully"}
    else:
        return {"status": "warning", "message": "File removed from database but not found in uploads directory"}

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
        "uploaded_chunks": uploaded_chunks,
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
