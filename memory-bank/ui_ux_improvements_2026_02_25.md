# UI/UX Improvements - 2026-02-25

## Overview
Implemented several UI/UX improvements to transform KAPJus into a more professional "Central de Inteligência da Causa".

## Changes Made

### 1. Search Bar Restructuring (Estilo Conversacional)
- **Moved search input to footer** of the central column (results area)
- **Replaced large buttons** with compact toggle icons inside the search bar:
  - Left side: Search icon (🔍) for keyword/exact search
  - Right side: Brain icon (🧠) for AI/semantic search
- **Visual feedback**: Gray for inactive, blue/indigo for active state
- **Compact design**: Smaller input field with inline mode toggle

**Files Modified:**
- `src/php/case_detail.php` - Lines 409-500 (search bar HTML)
- `src/php/case_detail.php` - JavaScript `setSearchMode()` function

### 2. User Feedback Messages
- **Rotating status messages** during IA processing:
  - "Entendendo sua questão"
  - "Buscando na base de arquivos"
  - "Checando nos arquivos"
  - "Analisando documentos relevantes"
  - "Processando informações"
  - "Preparando resposta"
- **Skeleton screens** during search loading with animated skeleton lines
- **Enhanced typing indicator** with gradient styling and rotating messages

**Files Modified:**
- `src/php/case_detail.php` - `_renderIAHistoryPanel()` function
- `src/php/case_detail.php` - `_unifiedPerformSearch()` function
- CSS styles for `.typing-indicator`, `.skeleton`, `.skeleton-line`

### 3. Error Handling (Friendly Messages)
- **Replaced technical errors** with user-friendly messages:
  - Instead of "400 - pro is not a valid model ID"
  - Shows: "Estamos ajustando o cérebro do Dr. Jus para esta análise. Tente novamente em instantes."
- **Retry button** in error state to easily retry the question
- **Gradient styling** for error boxes

**Files Modified:**
- `src/php/case_detail.php` - Error handling in `_renderIAHistoryPanel()`

### 4. Executive Summary Dashboard (Sidebar)
- **New "Sumário Executivo" card** at the top of sidebar with:
  - **Fatos Relevantes**: Shows dates and key events from case
  - **Partes Envolvidas**: Author, Defendant, Judge (extracted from case name)
  - **Status da Prova**: Visual indicator (green=OK, amber=warning, red=suspect)
- **Auto-updates** when documents are uploaded
- **API endpoint** for fetching executive summary data

**Files Modified:**
- `src/php/case_detail.php` - Sidebar HTML structure
- `src/php/case_detail.php` - `loadExecutiveSummary()` function
- `public/index.php` - `/api/executive_summary` endpoint

### 5. Split View Document Viewing
- **Side panel** opens next to chat when viewing PDFs
- **Toggle button** to switch between split view and fullscreen
- **Persistent state**: Panel stays open while navigating
- **Responsive**: Fullscreen on smaller screens, split on larger

**Files Modified:**
- `src/php/case_detail.php` - New `#pdf-panel` HTML element
- `src/php/case_detail.php` - `viewPdf()`, `openPdfPanel()`, `closePdfPanel()`, `togglePdfFullscreen()` functions
- CSS for split view layout

## API Endpoints Added
- `POST /api/executive_summary` - Returns case summary with facts, parties, and proof status

## Status
✅ All improvements implemented and syntax validated

## Testing
- PHP syntax validated for both `case_detail.php` and `index.php`
- No errors detected
