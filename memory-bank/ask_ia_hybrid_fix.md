# Ask IA Hybrid Fix - Gemini Provider Support

## Problem
OpenRouter API was returning 401 error: "No cookie auth credentials found"

## Solution
1. Added `IA_PROVIDER` environment variable to select between OpenRouter and Gemini
2. Created `call_ai()` helper function that routes requests based on provider
3. Updated `expand_query()` to use `call_ai()` instead of direct OpenRouter calls
4. Updated `generate_response_with_context()` to use `call_ai()`
5. Updated frontend `case_detail.php` to send `provider: 'gemini'`

## Changes
- `.env`: Added `IA_PROVIDER=gemini`
- `src/python/processor.py`: Added `call_ai()` function, updated `expand_query()` and `generate_response_with_context()`
- `src/php/case_detail.php`: Updated `askIA()` to send provider and handle Gemini response format

## Status
- ✅ Syntax validated
- ✅ Permissions applied
- ✅ Service restarted
- ✅ Changes committed

## Testing
Test via frontend at: https://kapjus.kaponline.com.br/case_detail.php?caso=RJ-50898255520244025101
