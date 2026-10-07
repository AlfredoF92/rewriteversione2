# Info — Crucintarsio

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_crossword.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_crossword.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Cruciverba collegato alla storia (CPT/attività crucintarsio).

## Chi lo crea di solito
- **Cursor / script** (`script-crucintarsio-crea-definizioni`, `script-genera-crucintarsio`) + skill progetto.
- Oppure generazione da admin cruciverba, poi link alla storia.

## Prompt / skill
Skill Cursor `crucintarsio-storia`; non è un `.md` “Vedi prompt” della checklist frasi.

## Cosa deve sapere un’altra AI
- Checklist ok se esiste un crucintarsio collegato (ID > 0).
- Definizioni e griglia devono usare lessico coerente con la storia.

