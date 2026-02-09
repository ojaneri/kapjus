# KapJus - Inteligência Jurídica Avançada

O **KapJus** é uma plataforma moderna para advogados e profissionais do direito, projetada para gerenciar grandes volumes de processos em PDF com auxílio de Inteligência Artificial e busca de alta performance.

## 🚀 Funcionalidades
- **Indexação Ultra-Rápida**: Processamento de PDFs com extração de texto por página.
- **Busca Híbrida**: Busca exata (FTS5) e análise semântica.
- **RAG (Retrieval-Augmented Generation)**: Converse com seus processos usando Gemini ou OpenRouter.
- **Timeline Automática**: Extração cronológica de fatos via IA.
- **Gestão de Casos**: Organize documentos por caso e compartilhe com colegas.
- **Acesso para Advogados**: Sistema de convite via e-mail (funcionalidade a ser implementada).

## 🛠️ Tecnologias
- **Backend**: PHP 8.1+ & Python 3.11 (FastAPI)
- **Banco de Dados**: SQLite com FTS5
- **Frontend**: Tailwind CSS & Alpine.js
- **IA**: Gemini API / OpenRouter

## 📦 Instalação
1.  Clone o repositório.
2.  Instale as dependências: `sudo apt-get install -y php-cli php-sqlite3 && sudo pip3 install fastapi uvicorn PyMuPDF openai google-generativeai requests`
3.  Configure as chaves de API no arquivo `run.sh`.
4.  Execute `./run.sh` para iniciar os servidores Python (porta 8000) e PHP (porta 8080).
5.  Acesse `http://localhost:8080` no seu navegador.

---
**Créditos**: Desenvolvido para **Osvaldo J. Filho**.
**Autor**: Manus AI
