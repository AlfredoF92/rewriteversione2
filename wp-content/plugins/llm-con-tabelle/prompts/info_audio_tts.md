# Info — Audio ascolto traduzione (M+F)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_audio_tts.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_audio_tts.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
File audio TTS della frase in lingua obiettivo, voce maschile e femminile (ascolto traduzione nel gioco).

## Chi lo crea di solito
- **Pipeline / script** (generazione TTS batch), lanciata da utente o Cursor via tool del progetto.
- Non è testo: sono media allegati alle frasi.

## Prompt
Nessun prompt testo in checklist. Serve servizio TTS + script di upload.

## Cosa deve sapere un’altra AI
- Checklist ok solo se M e F sono completi per tutte le frasi.
- Rigenerare audio se cambia il testo target.

