# Info — Consigli sulla pronuncia

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_pronunciation.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_pronunciation.md`

Un'altra AI deve aprire **quel** file `script_*.md` (e gli script PHP `script-*.php` citati dentro, se presenti nella stessa cartella) per generare o aggiornare questo campo. Non cercare script sparsi altrove finche' non indicato li'.

## Cos’è
Testo di aiuto sulla pronuncia della frase (consigli pratici), per-frase.

## Chi lo crea di solito
- **AI** con prompt `genera-consigli-pronuncia.md`.
- Revisione umana se la lingua obiettivo è critica (suoni difficili).

## Prompt checklist
`genera-consigli-pronuncia.md`

## Cosa deve sapere un’altra AI
- Campo `pronunciation`.
- Diverso da IPA (`ipa`) e da trascrizione approssimata (`approx`).

