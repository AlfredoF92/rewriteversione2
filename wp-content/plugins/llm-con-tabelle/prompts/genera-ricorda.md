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
