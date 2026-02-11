#!/bin/bash
# Install sqlite-vss for vector similarity search

echo "Installing sqlite-vss dependencies..."

# Install Python bindings for sqlite-vss
pip3 install sqlite-vss

# Check if extension is available
python3 -c "import sqlite_vss; print('sqlite-vss installed successfully')"

echo ""
echo "If sqlite-vss extension is not found, you may need to:"
echo "1. Install the binary extension: pip3 install sqlite-vss-binary"
echo "2. Or use a Docker container with sqlite-vss pre-installed"
echo ""
echo "Alternative: Use FAISS or ChromaDB for vector storage instead"
