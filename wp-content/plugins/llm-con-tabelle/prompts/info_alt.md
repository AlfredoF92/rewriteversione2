# Info — Traduzione alternativa

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_alt.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_alt.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Seconda resa della frase in lingua obiettivo (parafrasi / alternativa naturale), campo `alt`.

## Chi lo crea di solito
- **AI** (prompt di alternative / parte di pipeline frasi).
- Oppure utente in editing frase.

## Prompt
Nessun file “Vedi prompt” dedicato in checklist; spesso si lavora sull’export “Frasi + alt”.

## Cosa deve sapere un’altra AI
- Non sostituisce la traduzione principale `target`.
- Utile per confronto, esercizi, e appunti.
- Formato export: riga `XX-alternativa: …`.

