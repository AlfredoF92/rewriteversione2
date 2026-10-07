# Info — Ricorda

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_remember.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_remember.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Punti elenco “da ricordare” legati alla frase / agli appunti (mnemonica breve).

## Chi lo crea di solito
- **AI** con prompt `prompt-per-generare-i-punti-elenco.md` / `genera-ricorda.md`.
- Spesso dopo (o insieme) agli appunti grammaticali.

## Prompt checklist
`prompt-per-generare-i-punti-elenco.md`

## Cosa deve sapere un’altra AI
- Campo `remember`, parallelo alle frasi.
- Frasi corte, azionabili, non ripetere tutto l’appunto grammar.

