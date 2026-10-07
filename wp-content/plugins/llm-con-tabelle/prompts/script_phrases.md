# Script — Frasi caricate

**Campo checklist:** `phrases`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** prompt LLM + import utente

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) per generare o aggiornare il campo.

## Come eseguire / usare

Checklist: Info + Vedi frasi. Nessun CLI PHP dedicato.

---

## Obiettivo
Produrre l'elenco frasi `interface` (lingua nota) + `target` (lingua obiettivo), in ordine di gioco.

## Flusso consigliato
1. Se parti da documento/canzone: usa anche `script_story_texts.md` / `Crea storia da documento.md` per contestualizzare, poi estrai le frasi.
2. Formato di scambio tipico (una coppia per blocco):

IT: ...
EN: ...

(usa le sigle reali della storia: known/target)

3. Import in WordPress: admin storia (CSV / editor frasi) oppure repository `LLM_Story_Repository`.

## Output atteso
N frasi allineate 1:1; niente numerazione obbligatoria nel testo esportato dalla checklist ("Vedi frasi…").
