# Info — Frasi + frasi alternative

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_phrases_alt_export.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_phrases_alt_export.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Stesso elenco frasi, più la traduzione alternativa (campo `alt`) per ogni riga, in formato classico esportabile.

## Chi lo crea di solito
- Le **frasi base**: utente / import (vedi `info_phrases.md`).
- Le **alternative**: di solito AI con prompt di traduzione alternativa, o compilazione manuale in admin.
- In checklist non c’è un prompt “Vedi prompt” su questa riga: c’è “Vedi frasi + alt…” per esportare il blocco.

## Cosa deve sapere un’altra AI
- Formato tipico: IT/EN (o sigle della storia) + riga `*-alternativa`.
- Serve per QC, generazione appunti, o confronti di parafrasi.
- Se `alt` è vuoto, l’export segnala comunque le frasi senza alternativa.

