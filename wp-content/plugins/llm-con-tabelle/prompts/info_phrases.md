# Info — Frasi caricate

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_phrases.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_phrases.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Elenco delle frasi della storia: testo in lingua nota (interface) + testo in lingua obiettivo (target), in ordine di gioco.

## Chi lo crea di solito
- **Utente / editor umano**: importa CSV, incolla frasi, o le scrive in admin storia.
- **Cursor / AI**: può generare o riformattare il blocco frasi da un documento/canzone, poi l’utente importa.
- **Non serve un prompt dedicato in checklist** per “creare” il campo: si usa spesso “Crea storia da documento” o import CSV.

## Dove vive nel prodotto
Tabelle frasi della storia (LLM_Story_Repository). Senza frasi la storia non è giocabile.

## Cosa deve sapere un’altra AI
- Rispettare la coppia linguistica della storia (known → target).
- Numero e ordine delle frasi sono la base di tutti gli altri campi per-frase (note, grammar, alt, audio…).
- In checklist: “Vedi frasi…” esporta il testo grezzo per copia/incolla verso altri prompt.

