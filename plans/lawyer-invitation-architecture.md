# Lawyer Invitation Feature - Architecture Analysis

## Executive Summary

This document provides a comprehensive analysis of the existing codebase to guide the implementation of the Lawyer Invitation feature. The analysis covers API patterns, database schema recommendations, frontend integration points, and security considerations.

---

## 1. Current API Endpoint Pattern

### 1.1 Communication Architecture

The KAPJUS system uses a **Unix socket-based communication** between PHP (frontend) and Python (FastAPI processor):

```
PHP Frontend → Unix Socket (/socket/kapjus.sock) → FastAPI Processor
```

### 1.2 Socket Client Pattern ([`src/php/socket_client.php`](src/php/socket_client.php:9))

The `call_python_api()` function (lines 9-89) handles all communication:

```php
function call_python_api($endpoint, $data = [], $is_json = true, $files = [])
```

**Key characteristics:**
- Uses `stream_socket_client()` with Unix socket path
- Builds raw HTTP/1.1 POST requests
- Supports JSON and multipart/form-data for file uploads
- Parses HTTP response by splitting on `\r\n\r\n`

### 1.3 FastAPI Endpoint Pattern ([`src/python/processor.py`](src/python/processor.py))

All endpoints follow this pattern:

```python
# JSON Request (Pydantic model)
@app.post("/endpoint_name")
async def endpoint_function(query: RequestModel):
    conn = sqlite3.connect(DB_PATH)
    # ... processing ...
    return {"field": "value"}

# Form Request (multipart)
@app.post("/upload_chunk")
async def upload_chunk(
    upload_id: str = Form(...),
    chunk_index: int = Form(...),
    chunk: UploadFile = File(...)
):
    # ... processing ...
    return {"status": "success"}
```

### 1.4 Existing Endpoints Reference

| Endpoint | Method | Input | Purpose |
|----------|--------|-------|---------|
| `/documents` | POST | `{"case_id": str}` | List documents for a case |
| `/delete_file` | POST | `{"case_id": str, "filename": str}` | Delete a document |
| `/search` | POST | `{"case_id": str, "query": str}` | FTS5 search |
| `/ask_ia` | POST | Form data | AI question answering |
| `/upload_init` | POST | JSON | Initialize chunked upload |
| `/upload_chunk` | POST | Form data | Upload single chunk |
| `/upload_complete` | POST | JSON | Complete chunked upload |
| `/health` | GET | None | Health check |

### 1.5 Error Handling Pattern

```python
from fastapi import HTTPException

@app.post("/endpoint")
async def endpoint(request: RequestModel):
    try:
        # processing
        return {"status": "success"}
    except Exception as e:
        logger.error(f"Operation failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))
```

---

## 2. Database Schema Recommendations

### 2.1 Existing Schema

From [`processor.py`](src/python/processor.py:399-400):

```sql
CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id TEXT,
    filename TEXT,
    page_number INTEGER,
    content TEXT
)

CREATE VIRTUAL TABLE documents_fts USING fts5(
    content, case_id UNINDEXED, filename UNINDEXED, page_number UNINDEXED
)
```

### 2.2 Recommended `lawyer_invitations` Table Schema

```sql
CREATE TABLE lawyer_invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id TEXT NOT NULL,
    inviter_email TEXT NOT NULL,          -- User who sent the invitation
    invitee_email TEXT NOT NULL,          -- Lawyer's email address
    invitee_name TEXT,                    -- Lawyer's name (optional)
    token TEXT NOT NULL UNIQUE,           -- Magic link token (64+ chars)
    token_hash TEXT NOT NULL,             -- Hashed token for verification
    role TEXT DEFAULT 'viewer',           -- 'viewer', 'editor', 'admin'
    status TEXT DEFAULT 'pending',         -- 'pending', 'accepted', 'revoked', 'expired'
    expires_at DATETIME NOT NULL,         -- Token expiration (48 hours from creation)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME,                 -- When lawyer accepted
    revoked_at DATETIME,                  -- When invitation was revoked
    access_history TEXT,                  -- JSON array of access events
    
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_invitations_token ON lawyer_invitations(token);
CREATE INDEX idx_invitations_email ON lawyer_invitations(invitee_email);
CREATE INDEX idx_invitations_case ON lawyer_invitations(case_id);
CREATE INDEX idx_invitations_status ON lawyerInvitations(status);
CREATE INDEX idx_invitations_expires ON lawyerInvitations(expires_at);
```

### 2.3 Recommended `lawyer_access_logs` Table Schema

```sql
CREATE TABLE lawyer_access_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invitation_id INTEGER NOT NULL,
    lawyer_email TEXT NOT NULL,
    action TEXT NOT NULL,                 -- 'login', 'view_document', 'download', 'logout'
    ip_address TEXT,
    user_agent TEXT,
    metadata TEXT,                        -- JSON additional data
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invitation_id) REFERENCES lawyer_invitations(id)
);

CREATE INDEX idx_access_logs_invitation ON lawyer_access_logs(invitation_id);
CREATE INDEX idx_access_logs_created ON lawyer_access_logs(created_at);
```

---

## 3. Frontend Integration Points

### 3.1 Current "Invite Lawyer" Button ([`src/php/case_detail.php`](src/php/case_detail.php:156-158))

```html
<button class="inline-flex items-center px-4 py-2 border border-indigo-100 text-sm font-bold rounded-xl text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors">
    <i class="fas fa-user-plus mr-2"></i> Convidar Advogado
</button>
```

### 3.2 Recommended Modal Integration

Add a modal to `case_detail.php` after the existing modals (around line 140):

```html
<!-- Lawyer Invitation Modal -->
<div id="invite-lawyer-modal" class="fixed inset-0 z-[100] hidden" aria-labelledby="invite-modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onclick="closeInviteLawyerModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl">
                <!-- Modal Header -->
                <div class="px-8 py-6 border-b border-slate-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-black text-slate-900">CONVIDAR ADVOGADO</h3>
                        <button onclick="closeInviteLawyerModal()" class="text-slate-400 hover:text-slate-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="px-8 py-6 space-y-4">
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Email do Advogado</label>
                        <input type="email" id="invitee-email" placeholder="advogado@email.com" 
                            class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Nome do Advogado (opcional)</label>
                        <input type="text" id="invitee-name" placeholder="Dr. Nome Sobrenome"
                            class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Permissão</label>
                        <select id="invitation-role" class="w-full px-5 py-4 bg-white border-2 border-slate-200 rounded-xl">
                            <option value="viewer">Visualizador - Apenas visualizar documentos</option>
                            <option value="editor">Editor - Visualizar e fazer anotações</option>
                        </select>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-8 py-6 border-t border-slate-100 flex justify-end space-x-4">
                    <button onclick="closeInviteLawyerModal()" class="px-6 py-3 text-slate-600 font-medium">Cancelar</button>
                    <button onclick="sendLawyerInvitation()" class="px-8 py-3 bg-indigo-600 text-white font-bold rounded-xl">
                        <i class="fas fa-paper-plane mr-2"></i>ENVIAR CONVITE
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
```

### 3.3 Access History Section

Add a new card in the sidebar (after Document List Card) showing invitation status:

```html
<!-- Invitation History Card -->
<div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
    <h3 class="text-lg font-black text-slate-900 mb-4 flex items-center">
        <span class="w-8 h-8 bg-emerald-500 text-white rounded-lg flex items-center justify-center mr-3 text-xs">
            <i class="fas fa-users"></i>
        </span>
        ACESSOS CONVIDADOS
    </h3>
    <div id="invitation-list" class="space-y-2 max-h-[300px] overflow-y-auto">
        <p class="text-sm text-slate-400 font-medium italic">Nenhum convite enviado.</p>
    </div>
</div>
```

### 3.4 JavaScript Integration Pattern

Following the existing pattern in [`case_detail.php`](src/php/case_detail.php:302-1034):

```javascript
// Open modal
function openInviteLawyerModal() {
    document.getElementById('invite-lawyer-modal').classList.remove('hidden');
}

// Close modal
function closeInviteLawyerModal() {
    document.getElementById('invite-lawyer-modal').classList.add('hidden');
}

// Send invitation
async function sendLawyerInvitation() {
    const email = document.getElementById('invitee-email').value;
    const name = document.getElementById('invitee-name').value;
    const role = document.getElementById('invitation-role').value;
    
    const response = await fetch('/api/invite_lawyer', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            case_id: CASE_ID,
            invitee_email: email,
            invitee_name: name,
            role: role
        })
    });
    
    const result = await response.json();
    if (result.status === 'success') {
        alert('Convite enviado com sucesso!');
        closeInviteLawyerModal();
        fetchInvitations(); // Refresh list
    }
}

// Fetch invitations
async function fetchInvitations() {
    const response = await fetch('/api/invitations', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ case_id: CASE_ID })
    });
    // Update UI
}
```

---

## 4. Security Considerations for Magic Links

### 4.1 Token Generation Strategy

**Recommended: Cryptographically secure tokens using Python's `secrets` module**

```python
import secrets

def generate_secure_token(length: int = 64) -> tuple[str, str]:
    """
    Generate a secure token and its hash.
    Returns (raw_token, hashed_token).
    
    The raw token is sent to the lawyer via email.
    Only the hash is stored in the database.
    """
    raw_token = secrets.token_urlsafe(length)
    # Use SHA-256 for hashing (fast, secure for this use case)
    token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
    return raw_token, token_hash
```

**Token requirements:**
- Minimum 64 characters (URL-safe base64)
- Cryptographically random using `secrets.token_urlsafe()`
- Store only the hash in the database
- Send raw token via email

### 4.2 Token Expiration

```python
from datetime import datetime, timedelta

def get_expiration_time(hours: int = 48) -> datetime:
    """Get expiration datetime for invitation."""
    return datetime.utcnow() + timedelta(hours=hours)
```

**Recommendations:**
- Default expiration: 48 hours
- Maximum expiration: 7 days
- Track expiration in database
- Reject expired tokens during verification

### 4.3 Magic Link Verification Flow

```python
@app.post("/verify_invitation")
async def verify_invitation(request: VerifyInvitationRequest):
    """
    Verify a magic link token and return user session info.
    """
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Look up invitation by token hash
    cursor.execute("""
        SELECT id, case_id, invitee_email, role, status, expires_at
        FROM lawyer_invitations
        WHERE token_hash = ?
    """, (hash_token(request.token),))
    
    invitation = cursor.fetchone()
    conn.close()
    
    if not invitation:
        raise HTTPException(status_code=404, detail="Convite não encontrado")
    
    if invitation['status'] != 'pending':
        raise HTTPException(status_code=400, detail=f"Convite já foi {invitation['status']}")
    
    if datetime.fromisoformat(invitation['expires_at']) < datetime.utcnow():
        raise HTTPException(status_code=400, detail="Convite expirado")
    
    # Return session token for the lawyer
    return {
        "status": "valid",
        "case_id": invitation['case_id'],
        "lawyer_email": invitation['invitee_email'],
        "role": invitation['role'],
        "session_token": generate_session_token()
    }
```

### 4.4 Session Management

```python
def generate_session_token() -> str:
    """Generate a session token for authenticated access."""
    return secrets.token_urlsafe(32)

def hash_token(token: str) -> str:
    """Hash a token for secure storage."""
    return hashlib.sha256(token.encode()).hexdigest()
```

**Session recommendations:**
- Session token: 32+ characters
- Session duration: 24 hours (sliding expiration)
- Store session in HTTP-only cookie
- Use secure flag in production (HTTPS)

### 4.5 Access Control

```python
# Check invitation access
def can_access_case(invitation_id: int, case_id: str) -> bool:
    """Check if an invitation grants access to a specific case."""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("""
        SELECT role FROM lawyer_invitations
        WHERE id = ? AND case_id = ? AND status = 'accepted'
        AND expires_at > datetime('now')
    """, (invitation_id, case_id))
    result = cursor.fetchone()
    conn.close()
    return result is not None
```

---

## 5. Proposed API Endpoints

### 5.1 Invitation Management Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/invite_lawyer` | POST | User | Send invitation to lawyer |
| `/revoke_invitation` | POST | User | Revoke a pending invitation |
| `/invitations` | POST | User | List invitations for a case |
| `/invitation_status` | POST | User | Get status of specific invitation |
| `/verify_invitation` | POST | Public | Verify magic link and get session |
| `/accept_invitation` | POST | Public | Lawyer accepts invitation |
| `/magic_login` | POST | Public | Login with magic link token |

### 5.2 Request/Response Models

```python
class InviteLawyerRequest(BaseModel):
    case_id: str
    invitee_email: str
    invitee_name: Optional[str] = None
    role: str = "viewer"  # 'viewer' or 'editor'

class RevokeInvitationRequest(BaseModel):
    invitation_id: int
    case_id: str

class VerifyInvitationRequest(BaseModel):
    token: str
    case_id: str

class InvitationResponse(BaseModel):
    id: int
    invitee_email: str
    invitee_name: Optional[str]
    role: str
    status: str
    created_at: str
    expires_at: str
    accepted_at: Optional[str]
```

### 5.3 Email Template (for magic links)

```html
<!-- Magic Link Email Template -->
<html>
<body>
    <h2>Convite para acessar caso jurídico</h2>
    <p>Você foi convidado para acessar o caso <strong>{case_name}</strong>.</p>
    <p>Clique no link abaixo para acessar:</p>
    <a href="https://kapjus.kaponline.com.br/magic-login?token={magic_token}&case_id={case_id}">
        Acessar Caso
    </a>
    <p>Este link expira em 48 horas.</p>
    <p>Se você não solicitou este convite, ignore este email.</p>
</body>
</html>
```

---

## 6. Implementation Roadmap

### Phase 1: Database & Backend
1. Create `lawyer_invitations` and `lawyer_access_logs` tables
2. Add `/invite_lawyer` endpoint in [`processor.py`](src/python/processor.py)
3. Add token generation and hashing utilities
4. Add `/verify_invitation` endpoint
5. Add `/accept_invitation` endpoint
6. Add `/magic_login` endpoint
7. Add `/revoke_invitation` endpoint
8. Add `/invitations` endpoint for listing

### Phase 2: Frontend Integration
1. Add invitation modal to [`case_detail.php`](src/php/case_detail.php)
2. Add JavaScript functions for invitation management
3. Add access history card in sidebar
4. Implement `fetchInvitations()` function
5. Add magic login page (if not exists)

### Phase 3: Security & Polish
1. Implement session management
2. Add access logging to `lawyer_access_logs`
3. Add rate limiting for invitation emails
4. Implement email sending (via configured SMTP)
5. Add invitation expiration cleanup job

---

## 7. Code Pattern Summary

### 7.1 Consistent Patterns to Follow

1. **Pydantic Models**: Use for request validation
2. **SQLite Connections**: Always close in `finally` block
3. **Error Handling**: Return `HTTPException` with proper status codes
4. **Logging**: Use `log_step()` for important operations
5. **Response Format**: Return `{"status": "success", ...}` or error details

### 7.2 Socket Communication

All endpoints are called via Unix socket at `/var/www/html/kapjus.kaponline.com.br/socket/kapjus.sock` using the pattern in [`socket_client.php`](src/php/socket_client.php:9-89).

---

## 8. Appendix: Database Schema SQL

```sql
-- lawyer_invitations table
CREATE TABLE IF NOT EXISTS lawyer_invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id TEXT NOT NULL,
    inviter_email TEXT NOT NULL,
    invitee_email TEXT NOT NULL,
    invitee_name TEXT,
    token TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    role TEXT DEFAULT 'viewer',
    status TEXT DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME,
    revoked_at DATETIME,
    access_history TEXT,
    FOREIGN KEY (case_id) REFERENCES cases(id)
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_invitations_token ON lawyer_invitations(token);
CREATE INDEX IF NOT EXISTS idx_invitations_email ON lawyer_invitations(invitee_email);
CREATE INDEX IF NOT EXISTS idx_invitations_case ON lawyer_invitations(case_id);
CREATE INDEX IF NOT EXISTS idx_invitations_status ON lawyer_invitations(status);
CREATE INDEX IF NOT EXISTS idx_invitations_expires ON lawyer_invitations(expires_at);

-- lawyer_access_logs table
CREATE TABLE IF NOT EXISTS lawyer_access_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invitation_id INTEGER NOT NULL,
    lawyer_email TEXT NOT NULL,
    action TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    metadata TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invitation_id) REFERENCES lawyer_invitations(id)
);

CREATE INDEX IF NOT EXISTS idx_access_logs_invitation ON lawyer_access_logs(invitation_id);
CREATE INDEX IF NOT EXISTS idx_access_logs_created ON lawyer_access_logs(created_at);
```

---

## 9. Files to Modify

1. **[`src/python/processor.py`](src/python/processor.py)** - Add new endpoints
2. **[`src/php/case_detail.php`](src/php/case_detail.php)** - Add invitation modal and JavaScript
3. **[`src/php/socket_client.php`](src/php/socket_client.php)** - Add invitation API handlers
4. **New: `src/php/magic_login.php`** - Magic link login page

---

*Document generated for KAPJUS Lawyer Invitation Feature implementation*
*Author: Architect Mode Analysis*
*Date: 2026-02-11*
