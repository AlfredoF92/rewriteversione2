# Info — Caricare gli appunti

## Script / prompt (cartella canonica)
File da usare: `prompt/CREA STORIA/script_load_appunti.md`

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/script_load_appunti.md`

Un'altra AI deve aprire **quel** file `script_*.md` per capire **come caricare** gli appunti già scritti (paste utente) negli **slot**. Non confondere con `script_grammar.md` (generazione del testo).

## Cos’è
Passo operativo: prendere gli appunti (testo plain a slot) e salvarli nel database come **grammar slots** (`grammar_title_N` + `grammar_body_N`), sincronizzando `phrase_grammar`.

Non è generare gli appunti. Non è modificare le frasi EN/IT.

## Chi lo crea di solito
- Utente incolla il blocco `=== FRASE N ===` (output di `script_grammar.md` / Prompt_Crea_Appunti).
- AI (Cursor) esegue `script_load_appunti.md` e scrive solo gli slot.

## Prompt checklist
`script_load_appunti.md` — aprire con “Vedi prompt”.

## Cosa deve sapere un’altra AI
- Max **10 slot** per frase.
- Titolo tipico: `"pezzo lingua nota" → "pezzo lingua target"`.
- Corpo = spiegazione sotto il titolo (incluse ❌, 💡 Deep dive, esempi).
- Coniugazione ed etimologia = slot separati.
- **Non** toccare `phrase_interface` / `phrase_target` / note.
- Usare `LLM_Story_Repository::update_phrase_grammar_slots( $story_id, $index, $slots )` (indice 0-based: FRASE 1 → 0).
- Le coppie `"…" → "…"` **dentro** un Deep dive restano nel corpo dello slot corrente: non aprire slot nuovi.
