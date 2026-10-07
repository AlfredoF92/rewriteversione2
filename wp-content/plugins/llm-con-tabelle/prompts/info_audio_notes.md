# Info — Audio delle note

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_audio_notes.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_audio_notes.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Audio TTS delle note della frase (ascolto appunti/note), per-frase.

## Chi lo crea di solito
- **Pipeline TTS** dopo che le note testo esistono.
- Utente/Cursor lanciano lo script; non si “scrive” a mano il wav/mp3.

## Prompt
Nessuno in checklist (dipende dalle note già generate).

## Cosa deve sapere un’altra AI
- Prerequisito: note compilate.
- Allineare lingua della nota alla voce TTS scelta.

