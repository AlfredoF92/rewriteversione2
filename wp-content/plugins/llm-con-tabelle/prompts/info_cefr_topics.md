# Info — CEFR + topic grammaticali

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_cefr_topics.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_cefr_topics.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
- **CEFR**: livello (A1–C2) e/o etichetta tipo “canzone”.
- **Topic grammaticali**: elenco argomenti (presente, articoli, …) legati alla storia.

## Chi lo crea di solito
- **Editor / AI** in fase di progettazione didattica.
- Cursor può inferirli dalle frasi; conferma umana consigliata.

## Prompt
Nessun prompt checklist dedicato; possono uscire dal flusso “Crea storia da documento” o da QC.

## Cosa deve sapere un’altra AI
- Meta: STORY_CEFR_LEVEL, STORY_GRAMMAR_TOPICS.
- Influenzano magazine (canzone vs storia), filtri e aspettative di difficoltà.

