-- KAPJus Multi-Tenancy PostgreSQL Schema
-- Sprint 5: PostgreSQL Migration with Multi-Tenancy Support
-- Version: 1.0
-- Created: 2026-02-27

-- ============================================
-- SECTION 1: Required Extensions
-- ============================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "vector";
CREATE EXTENSION IF NOT EXISTS "btree_gin";

-- ============================================
-- SECTION 2: Organizations Table
-- ============================================
CREATE TABLE organizations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT NOT NULL,
    subdomain TEXT UNIQUE,
    branding_config JSONB DEFAULT '{}',
    plan_type TEXT NOT NULL DEFAULT 'free' CHECK (plan_type IN ('free', 'pro', 'enterprise')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    storage_quota_mb INTEGER DEFAULT 1024,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_organizations_subdomain ON organizations(subdomain) WHERE subdomain IS NOT NULL;
CREATE INDEX idx_organizations_active ON organizations(is_active) WHERE is_active = TRUE;

-- ============================================
-- SECTION 3: Users Table with RBAC
-- ============================================
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    email TEXT NOT NULL,
    name TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'advogado' CHECK (role IN ('admin', 'advogado', 'visualizador', 'perito')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    avatar_url TEXT,
    phone TEXT,
    oauth_provider TEXT,
    oauth_uid TEXT,
    last_login_at TIMESTAMPTZ,
    password_changed_at TIMESTAMPTZ,
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(organization_id, email)
);

CREATE INDEX idx_users_organization ON users(organization_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(is_active) WHERE is_active = TRUE;

-- ============================================
-- SECTION 4: Cases Table
-- ============================================
CREATE TABLE cases (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    description TEXT,
    metadata JSONB DEFAULT '{}',
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'archived', 'closed')),
    created_by_user_id UUID REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_cases_organization ON cases(organization_id);
CREATE INDEX idx_cases_status ON cases(status);
CREATE INDEX idx_cases_created_by ON cases(created_by_user_id);

-- ============================================
-- SECTION 5: Documents Table with Vector Support
-- ============================================
CREATE TABLE documents (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    case_id UUID NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
    filename TEXT NOT NULL,
    original_filename TEXT,
    mime_type TEXT,
    file_size_bytes BIGINT,
    page_count INTEGER,
    page_number INTEGER,
    content TEXT,
    embedding vector(1536),
    checksum TEXT,
    storage_path TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Standard indexes
CREATE INDEX idx_documents_case ON documents(case_id);
CREATE INDEX idx_documents_filename ON documents(filename);

-- Vector similarity search index (HNSW)
CREATE INDEX idx_documents_embedding ON documents USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);

-- Full-text search index (GIN)
CREATE INDEX idx_documents_content_fts ON documents USING GIN (to_tsvector('portuguese', content));

-- ============================================
-- SECTION 6: Case Notes Table
-- ============================================
CREATE TABLE case_notes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    case_id UUID NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    text TEXT NOT NULL,
    source_info JSONB,
    color TEXT DEFAULT 'yellow' CHECK (color IN ('yellow', 'green', 'blue', 'red', 'purple')),
    is_ai_generated BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_case_notes_case ON case_notes(case_id);
CREATE INDEX idx_case_notes_user ON case_notes(user_id);

-- ============================================
-- SECTION 7: Case Invitees (External Access)
-- ============================================
CREATE TABLE case_invitees (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    case_id UUID NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
    inviter_user_id UUID NOT NULL REFERENCES users(id),
    invitee_email TEXT NOT NULL,
    invitee_name TEXT,
    token_hash TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'visualizador' CHECK (role IN ('visualizador', 'perito')),
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'revoked', 'expired')),
    expires_at TIMESTAMPTZ NOT NULL,
    accepted_at TIMESTAMPTZ,
    revoked_at TIMESTAMPTZ,
    access_count INTEGER DEFAULT 0,
    last_accessed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_case_invitees_case ON case_invitees(case_id);
CREATE INDEX idx_case_invitees_email ON case_invitees(invitee_email);
CREATE INDEX idx_case_invitees_token ON case_invitees(token_hash);
CREATE INDEX idx_case_invitees_status ON case_invitees(status);
CREATE INDEX idx_case_invitees_expires ON case_invitees(expires_at) WHERE status = 'pending';

-- ============================================
-- SECTION 8: Executive Summary Cache
-- ============================================
CREATE TABLE executive_summary_cache (
    case_id UUID PRIMARY KEY REFERENCES cases(id) ON DELETE CASCADE,
    facts JSONB NOT NULL DEFAULT '[]',
    parties JSONB NOT NULL DEFAULT '{}',
    proof_status TEXT NOT NULL DEFAULT 'pending' CHECK (proof_status IN ('pending', 'warning', 'ok')),
    ai_model_used TEXT,
    generated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ
);

CREATE INDEX idx_exec_summary_generated ON executive_summary_cache(generated_at);

-- ============================================
-- SECTION 9: User Sessions
-- ============================================
CREATE TABLE user_sessions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token_hash TEXT NOT NULL UNIQUE,
    ip_address INET,
    user_agent TEXT,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_activity_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_user_sessions_user ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_token ON user_sessions(session_token_hash);
CREATE INDEX idx_user_sessions_expires ON user_sessions(expires_at);

-- ============================================
-- SECTION 10: Document Access Logs (Audit Trail)
-- ============================================
CREATE TABLE document_access_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    document_id UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id),
    invitee_id UUID REFERENCES case_invitees(id),
    action TEXT NOT NULL CHECK (action IN ('view', 'download', 'search', 'delete')),
    ip_address INET,
    user_agent TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_doc_access_logs_document ON document_access_logs(document_id);
CREATE INDEX idx_doc_access_logs_user ON document_access_logs(user_id);
CREATE INDEX idx_doc_access_logs_created ON document_access_logs(created_at);

-- ============================================
-- SECTION 11: Invitation Tokens Table
-- ============================================
CREATE TABLE invitation_tokens (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    organization_id UUID REFERENCES organizations(id) ON DELETE CASCADE,
    case_id UUID REFERENCES cases(id) ON DELETE CASCADE,
    inviter_user_id UUID REFERENCES users(id),
    email TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'visualizador',
    status TEXT NOT NULL DEFAULT 'pending',
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_invitation_tokens_token ON invitation_tokens(token_hash);
CREATE INDEX idx_invitation_tokens_email ON invitation_tokens(email);

-- ============================================
-- SECTION 12: Row-Level Security (RLS)
-- ============================================

-- Enable RLS on organization-scoped tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE cases ENABLE ROW LEVEL SECURITY;
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE case_notes ENABLE ROW LEVEL SECURITY;
ALTER TABLE case_invitees ENABLE ROW LEVEL SECURITY;
ALTER TABLE executive_summary_cache ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE document_access_logs ENABLE ROW LEVEL SECURITY;

-- Create helper functions for RLS
CREATE OR REPLACE FUNCTION current_organization_id()
RETURNS UUID AS $$
    SELECT (current_setting('app.current_organization_id', TRUE))::UUID;
$$ LANGUAGE SQL STABLE;

CREATE OR REPLACE FUNCTION current_user_id()
RETURNS UUID AS $$
    SELECT (current_setting('app.current_user_id', TRUE))::UUID;
$$ LANGUAGE SQL STABLE;

CREATE OR REPLACE FUNCTION current_user_role()
RETURNS TEXT AS $$
    SELECT current_setting('app.current_user_role', TRUE);
$$ LANGUAGE SQL STABLE;

-- RLS Policies
-- Users: Users can only see users in their organization
CREATE POLICY users_organization_policy ON users
    FOR SELECT USING (organization_id = current_organization_id());

-- Cases: Users can only see cases in their organization
CREATE POLICY cases_organization_policy ON cases
    FOR ALL USING (organization_id = current_organization_id());

-- Documents: Users can only see documents in their organization's cases
CREATE POLICY documents_organization_policy ON documents
    FOR ALL USING (
        case_id IN (
            SELECT id FROM cases 
            WHERE organization_id = current_organization_id()
        )
    );

-- Case Notes: Users can only see notes in their organization's cases
CREATE POLICY case_notes_organization_policy ON case_notes
    FOR ALL USING (
        case_id IN (
            SELECT id FROM cases 
            WHERE organization_id = current_organization_id()
        )
    );

-- Case Invitees: Users can only see invitees for their organization's cases
CREATE POLICY case_invitees_organization_policy ON case_invitees
    FOR ALL USING (
        case_id IN (
            SELECT id FROM cases 
            WHERE organization_id = current_organization_id()
        )
    );

-- User Sessions: Users can only see their own sessions
CREATE POLICY user_sessions_own_policy ON user_sessions
    FOR ALL USING (user_id = current_user_id());

-- Document Access Logs: Users can only see logs for their organization's documents
CREATE POLICY doc_access_logs_organization_policy ON document_access_logs
    FOR ALL USING (
        document_id IN (
            SELECT d.id FROM documents d
            JOIN cases c ON d.case_id = c.id
            WHERE c.organization_id = current_organization_id()
        )
    );

-- ============================================
-- SECTION 13: RBAC Helper Functions
-- ============================================

-- Function to check if current user can perform action on a case
CREATE OR REPLACE FUNCTION can_access_case(case_uuid UUID, action TEXT)
RETURNS BOOLEAN AS $$
DECLARE
    user_role TEXT;
    case_org_id UUID;
    user_org_id UUID;
BEGIN
    user_role := current_user_role();
    user_org_id := current_organization_id();
    
    -- Admin and Advogado have full access within their organization
    IF user_role IN ('admin', 'advogado') THEN
        SELECT organization_id INTO case_org_id FROM cases WHERE id = case_uuid;
        RETURN case_org_id = user_org_id;
    END IF;
    
    -- Visualizador and Perito can only access invited cases
    IF user_role IN ('visualizador', 'perito') THEN
        RETURN EXISTS (
            SELECT 1 FROM case_invitees ci
            JOIN users u ON ci.invitee_email = u.email
            WHERE ci.case_id = case_uuid
            AND u.id = current_user_id()
            AND ci.status = 'accepted'
        );
    END IF;
    
    RETURN FALSE;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to check if current user can perform action on document
CREATE OR REPLACE FUNCTION can_access_document(doc_uuid UUID, action TEXT)
RETURNS BOOLEAN AS $$
DECLARE
    case_uuid UUID;
BEGIN
    SELECT case_id INTO case_uuid FROM documents WHERE id = doc_uuid;
    RETURN can_access_case(case_uuid, action);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- ============================================
-- SECTION 14: Default Data
-- ============================================

-- Insert default organization
INSERT INTO organizations (id, name, subdomain, plan_type)
VALUES ('00000000-0000-0000-0000-000000000001', 'Default Office', 'default', 'free')
ON CONFLICT DO NOTHING;

-- Insert default admin user (password: admin123 - CHANGE IN PRODUCTION!)
-- Note: In production, use proper password hashing!
INSERT INTO users (id, organization_id, email, name, password_hash, role, created_at)
SELECT '00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000001', 'admin@kapjus.com', 'Administrator', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@kapjus.com');
