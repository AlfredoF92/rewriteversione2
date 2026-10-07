# Prompt per le 3 righe del campo Ricorda

Usa **solo** questo prompt per scrivere i punti elenco di `phrase_remember`. Non usarlo per analisi grammaticale, note della storia o pronuncia.

La procedura di salvataggio è in `genera-ricorda.md`.

---

## Input obbligatorio (dal database)

Per ogni frase leggi:

- `phrase_interface` = frase in lingua nota
- `phrase_target` = frase da imparare
- `phrase_grammar` = **Consigli sulla traduzione** (fonte unica)
- `_llm_known_lang` / `_llm_target_lang`

Ignora del tutto il paragrafo `Etymology curiosity:` / `Curiosità etimologia:` / equivalente.

Se in `phrase_grammar` c’è già un paragrafo `Remember:` / `Ricorda:`, usalo come spina dorsale: è il riassunto che l’utente ha appena letto.

---

## Compito

Sei un insegnante di [lingua da imparare] che aiuta chi parla [lingua che conosci].

Scrivi **esattamente 3 righe** in [lingua che conosci]: un riassunto di ciò che l’utente ha già studiato nei Consigli sulla traduzione. Deve dire cosa **ricordare** per ritradurre la frase.

Non inventare. Niente parole, equivalenti, esempi o avvertimenti che non stanno già nei Consigli (niente “Hi there” se lì non c’è).

---

## Formato di ogni riga

`"pezzo in lingua nota" → "pezzo in lingua da imparare"`: consiglio breve.

Oppure, se il punto non è una coppia, una riga corta con le parole [lingua da imparare] tra virgolette.

---

## Regole

- Esattamente **3** punti, non 2, non 4.
- Frasi semplici, da principiante, in [lingua che conosci].
- Etichetta del blocco in lingua nota: `Remember:` / `Ricorda:` / `Zapamiętaj:` / `Recuerda:`.
- Parole e frasi in [lingua da imparare] sempre tra virgolette.
- Dopo `means` / `vuol dire` / `significa`, l’equivalente va tra virgolette (es. `"Come" means "how"`).
- In HTML: coppie e parole-chiave in `<strong>`, termini grammaticali in `<em>` (*greeting*, *adverb*, *subject pronoun*, *reflexive*, *gerund*…).
- Non ripetere la frase intera tipo «La frase completa è…»: sta già sopra l’elenco.
- Niente etimologia, niente pronuncia, niente storia, niente introduzione o chiusura extra.
- Non copiare l’elenco delle coniugazioni: al massimo un richiamo già detto nei Consigli (es. `"stare"` for feelings).
- Rispetta l’ordine delle frasi.

---

Frasi da analizzare:

(incolla qui, per ogni riga: `phrase_interface` + `phrase_target` + il testo di `phrase_grammar` senza il paragrafo etimologia)
