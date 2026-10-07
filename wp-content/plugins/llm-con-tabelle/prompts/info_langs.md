# Info — Lingue (nota + obiettivo)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_langs.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_langs.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Coppia linguistica della storia: lingua che l’utente conosce (known) e lingua da imparare (target).

## Chi lo crea di solito
- **Utente / editor** in admin storia (meta lingue), spesso all’apertura della scheda.
- Cursor può proporre la coppia in fase di creazione storia, ma la conferma è umana.

## Prompt
Nessun prompt checklist dedicato. È un metadato obbligatorio.

## Cosa deve sapere un’altra AI
- Meta: `KNOWN_LANG` / `TARGET_LANG`.
- Tutti i prompt di generazione frasi/note/appunti devono rispettare questa coppia.
- Home uscite, magazine e gioco sincronizzano i cookie visitatore su questa coppia.

