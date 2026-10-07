# Script — Ricorda (punti elenco)

**Campo checklist:** `remember`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** prompt LLM

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) per generare o aggiornare il campo.

## Come eseguire / usare

Segui i prompt. Campo: remember.

---

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


---

# Allegato genera-ricorda.md

# Genera Ricorda

Quando l’utente dice **"Genera ricorda"** / **"riempi il campo ricorda"** e fornisce uno slug o ID storia, segui questa procedura.

Campo: **Ricorda** (`phrase_remember`). Solo popup «Leggi gli appunti?» sulla frase completata.

- Analisi grammaticale → `genera-appunti-frasi.md`
- Pronuncia → `genera-consigli-pronuncia.md`
- Note della storia → `genera-note-della-storia.md`
- **Punti elenco Ricorda** → `genera-appunti-campo-ricorda/prompt-per-generare-i-punti-elenco.md`

Se l’utente indica un intervallo (es. “prime 10 frasi”), lavora solo su quelle. Indice 0-based. Altrimenti tutte.

---

## 1. Trova storia e lingue

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root localloverewrite202608 -e "SELECT ID, post_title, post_name FROM wp_posts WHERE post_name = 'SLUG-QUI' AND post_type = 'llm_story';"
```

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ID_QUI AND meta_key IN ('_llm_known_lang','_llm_target_lang');"
```

---

## 2. Leggi le frasi

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_interface, phrase_target, CHAR_LENGTH(phrase_remember) AS remember_len FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC;"
```

---

## 3. Componi `phrase_remember`

Fonte: `phrase_grammar` (Consigli sulla traduzione). **Non** usare etimologia. **Non** inventare esempi o equivalenti assenti da quei consigli. Se c’è già `Remember:` / `Ricorda:` in fondo all’analisi, partine da lì.

Ordine fisso:

1. Prima riga: frase **esatta** di `phrase_target`.
2. Poi l’etichetta in [`_llm_known_lang`] (`Remember:` / `Ricorda:` / …).
3. Poi **esattamente 3 punti**. Li genera **solo**  
   `genera-appunti-campo-ricorda/prompt-per-generare-i-punti-elenco.md`.

In HTML:

```html
<p>FRASE_TARGET</p>
<p><strong>Remember:</strong></p>
<ul>
<li><strong>"Hello" → "Ciao"</strong>: informal <em>greeting</em> for friends and people your age.</li>
<li>"<strong>Ciao</strong>" is not a verb: it does not change, and there is no I or you.</li>
<li>The same stamp can open and close a chat; for a more polite meeting use "<strong>Buongiorno</strong>".</li>
</ul>
```

---

## 4. Salva

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "source C:/xampp/htdocs/localloverewrite202608/database/NOMEFILE.sql"
```

Oppure uno script PHP che chiama `LLM_Story_Repository::update_phrase_rich_field( $story_id, $index, 'remember', $html )`.

---

## 5. Verifica

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT sort_order, phrase_target, phrase_remember FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC LIMIT 10;"
```
