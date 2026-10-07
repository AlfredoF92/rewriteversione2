# Info — Pubblicata

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_published.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_published.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Stato WordPress della storia: `publish` (pubblicata), oppure `future` / `draft` / altro.

## Chi lo crea di solito
- **Utente admin**: pubblica o programma la data.
- Cursor non pubblica in autonomia salvo richiesta esplicita.

## Prompt
Nessuno.

## Cosa deve sapere un’altra AI
- Visitatori vedono solo `publish` (con redirect home se non apribile).
- Admin può aprire anche programmate/bozze (“Accedi da admin”).
- Programmare ≠ pubblicare: `future` resta non giocabile al pubblico.

