# Script — Audio ascolto traduzione M+F

**Campo checklist:** `audio_tts`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** pipeline TTS admin / Azure

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) per generare o aggiornare il campo.

## Come eseguire / usare

Nessun singolo CLI 'script_audio_tts.php' dedicato: usa admin TTS o Azure.

---

## Obiettivo
Audio della frase obiettivo, voci maschile + femminile (ascolto traduzione).

## Come generare
1. In admin storia: pulsanti generazione audio IA / TTS sulle frasi (`LLM_Phrase_TTS`).
2. Oppure pipeline Azure del progetto se configurata (vedi anche `script_audio_azure.md`).

## Regola
Se cambia il testo `target`, rigenerare gli audio.
