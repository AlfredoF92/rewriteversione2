# Info — Parole evidenziate (ascolto appunti)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_listen_words.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_listen_words.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Parole/segmenti evidenziati negli appunti con audio dedicato (ascolto selettivo nel gioco).

## Chi lo crea di solito
- Spesso **AI** mentre genera/arricchisce gli appunti (`Prompt_Crea_Appunti` / flussi evidenziazione).
- Audio clip: pipeline TTS su quelle parole.
- Documentazione operativa anche in file tipo `aggiungi-evidenziazione-parole-da-ascoltare.md` nel repo.

## Prompt
Tasto **Prompt per generare elenco parole** su questa riga. Apre `prompt/CREA STORIA/script_listen_words.md`: regole per scegliere coniugazioni, i 2 esempi e le parole di Approfondimento, poi generare gli audio.

## Cosa deve sapere un’altra AI
- Servono voci in checklist (conteggio) + audio F/M dove previsto.
- Le evidenziazioni devono corrispondere a testo realmente presente negli appunti.

