# Info — Appunti grammaticali

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_grammar.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_grammar.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Appunti / spiegazioni grammaticali ricche per ogni frase (HTML o testo strutturato), usati nel pannello “appunti” del gioco.

## Chi lo crea di solito
- **AI** con prompt dedicato `Prompt_Crea_Appunti.md` (Cursor o altro LLM).
- QC e ritocchi: utente / Cursor in cicli successivi.
- Esiste anche flusso “genera punti elenco / ricorda” collegato ma distinto.

## Prompt checklist
`Prompt_Crea_Appunti.md` — aprire con “Vedi prompt”.

## Cosa deve sapere un’altra AI
- Output allineato 1:1 alle frasi, in formato **slot** (titolo + corpo), max 10 per frase.
- Può includere markup, esempi, e (se richiesto dal prompt) elementi per ascolto parole.
- Media caratteri e slot compilati sono usati dalla checklist come segnale di completezza.
- Quando l’utente **incolla** gli appunti già pronti da salvare nel DB → usa lo step checklist **Caricare gli appunti** (`info_load_appunti.md` / `script_load_appunti.md`), non riscrivere le frasi.

