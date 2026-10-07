# Info — Audio Azure (M+F)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_audio_azure.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_audio_azure.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Variante audio generata con Azure (o pipeline Azure del progetto), voci M+F per frase.

## Chi lo crea di solito
- **Script / automatismi** del repo (cartelle tipo `_tmp_*_azure`), poi associazione alla storia.
- Operatore umano avvia e verifica.

## Prompt
Nessuno in checklist.

## Cosa deve sapere un’altra AI
- Distinto da `audio_tts` “ascolto traduzione” generico: traccia la completezza del canale Azure.
- Se i testi frasi cambiano, gli audio vanno rigenerati.

