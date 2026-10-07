# Info — Importa storia da chat

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/importa-storia-da-chat.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/importa-storia-da-chat.md`

Un'altra AI deve aprire **quel** file quando l’utente in chat chiede di caricare o importare una storia (incolla titolo, trama, intro, finale, frasi). Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Ingresso per creare una storia `llm_story` in **bozza** partendo da un testo incollato in chat, non da un documento di grammatica e non da un CSV admin.

## Chi lo crea di solito
- **Utente**: incolla il materiale in chat e chiede di caricare / importare.
- **AI**: interpreta il testo, completa i campi mancanti della scheda, salva frasi + note.

## Prompt checklist
`importa-storia-da-chat.md`

## Cosa deve sapere un’altra AI
- Una riga di gioco = **domanda + risposta** nello stesso `phrase_target` / `phrase_interface`. Il paragrafo di scena va in `phrase_notes` / `phrase_notes_target`.
- In questo passo: scheda + frasi + note. Niente grammar / alt / Ricorda / pronuncia / audio / copertina.
- Stato sempre `draft`. Non pubblicare.
- Non confondere con `Crea storia da documento.md` (genera una storia nuova da una lezione) né con `script_phrases.md` (solo elenco frasi, non crea il post).
