# Case File Binding Fix

**Data da Correção:** 2026-02-11

## Problema

A busca RAG estava retornando arquivos de casos errados para consultas. O problema estava na falta de validação de `case_id` nas funções de ranking e seleção de contexto.

### Sintomas:
- Arquivos de um caso aparecendo nas buscas de outro caso
- Resultados irrelevantes sendo retornados para consultas específicas

## Solução

Adicionada validação de `case_id` em duas funções críticas do [`processor.py`](src/python/processor.py):

### 1. [`rank_results()`](src/python/processor.py:420)
- Adicionada validação de `case_id` para filtrar resultados apenas do caso solicitado
- Implementada verificação de segurança antes do ranking

### 2. [`select_final_context_chunks()`](src/python/processor.py:527)
- Adicionada validação de `case_id` para garantir que apenas chunks do caso correto sejam selecionados
- Implementada verificação de segurança antes da seleção final de chunks

## Correção Crítica

### [`/upload_complete`](src/python/processor.py:763)
- **REMOVIDO:** Fallback inseguro que não validava o `case_id`
- O endpoint agora requer `case_id` explícito e válido
- Retorna erro 400 se `case_id` estiver ausente ou inválido

## Arquivos Modificados

- [`src/python/processor.py`](src/python/processor.py)

## Validação

- Serviço reiniciado e operando normalmente
- Sintaxe Python validada com `python3 -m py_compile`
