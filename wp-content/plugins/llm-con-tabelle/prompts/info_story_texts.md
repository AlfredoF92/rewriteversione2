# Info — Testi storia (plot / intro / finale / card)

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_story_texts.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_story_texts.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Quattro testi di contesto:
- **plot**: trama / descrizione lunga (popup, schede)
- **intro**: testo di apertura nel gioco
- **finale**: chiusura
- **card**: testo breve per card/elenco

## Chi lo crea di solito
- Spesso **AI** partendo da documento/canzone, con prompt “Crea storia da documento”.
- Revisione e ritocco: **utente**.

## Prompt checklist
`Crea storia da documento.md` (pulsante Vedi prompt).

## Cosa deve sapere un’altra AI
- Meta: STORY_PLOT, STORY_INTRO, STORY_FINALE, STORY_CARD_TEXT.
- Coerenza con frasi e livello CEFR.
- Plot in lingua nota (interfaccia), utili al visitatore prima di giocare.

