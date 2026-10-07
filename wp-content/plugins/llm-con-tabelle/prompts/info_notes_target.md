# Info — Note della frase (lingua obiettivo)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_notes_target.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_notes_target.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Come le note in lingua nota, ma redatte nella **lingua obiettivo** (quella in apprendimento).

## Chi lo crea di solito
- **AI** (stesso filone del prompt note), oppure traduzione/adattamento delle note known.
- Revisione tipica: madrelingua / editor.

## Prompt checklist
`genera-note-della-storia.md` (stesso file; adattare la lingua richiesta).

## Cosa deve sapere un’altra AI
- Campo `notes_target` per-frase.
- Deve restare parallelo a `notes` (stesso insegnamento, altra lingua).

