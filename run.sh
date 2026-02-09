#!/bin/bash

# Matar processos anteriores se existirem
pkill -f "python3 src/python/processor.py"
pkill -f "php -S 0.0.0.0:8080"

# Ativar ambiente e rodar o backend Python em background
echo "Iniciando Backend Python (RAG)..."
cd /home/ubuntu/kapjus
export OPENROUTER_API_KEY="sk-or-v1-..." # Substituir por chave real ou passar via ENV
export GEMINI_API_KEY="AIza..." # Substituir por chave real ou passar via ENV
python3 src/python/processor.py &

# Rodar o frontend PHP
echo "Iniciando Frontend PHP em http://localhost:8080..."
cd /home/ubuntu/kapjus/public
php -S 0.0.0.0:8080
