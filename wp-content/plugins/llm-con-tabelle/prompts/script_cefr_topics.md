# Script — CEFR + topic grammaticali

**Campo checklist:** `cefr_topics`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** prompt LLM

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) per generare o aggiornare il campo.

## Come eseguire / usare

Incolla output in admin storia (campi CEFR / topic).

---

## Obiettivo
Compilare `_llm_story_cefr_level` e `_llm_story_grammar_topics`.

## Prompt
Analizza le frasi target della storia e proponi:
1. Livello CEFR (A1–C2) coerente con lessico/strutture; se e' una canzone, indica anche etichetta canzone/song nel CEFR se il progetto la usa cosi'.
2. Elenco topic grammaticali (bullet o CSV breve) realmente presenti nelle frasi.

Non inventare strutture assenti dalle frasi. Lingua di output dei topic: lingua nota della storia.
