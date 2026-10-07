# Info — Note della frase (lingua nota)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_notes.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_notes.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Nota didattica per ogni frase, scritta nella **lingua nota** (quella che l’utente già parla).

## Chi lo crea di solito
- **AI** con prompt `genera-note-della-storia.md`, poi revisione umana.
- A volte compilazione/editing manuale in admin o front-end (admin bar).

## Prompt checklist
`genera-note-della-storia.md`

## Cosa deve sapere un’altra AI
- Una nota per frase, allineata all’ordine frasi.
- Lingua = known lang della storia.
- Non confondere con `notes_target` (stesso contenuto concettuale ma in lingua obiettivo).

