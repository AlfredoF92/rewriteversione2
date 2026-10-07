# Script — Consigli pronuncia

**Campo checklist:** `pronunciation`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** prompt LLM

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) per generare o aggiornare il campo.

## Come eseguire / usare

Segui il prompt. Campo: pronunciation.

---

# Genera Consigli sulla pronuncia

Quando l’utente dice **"Genera consigli sulla pronuncia"** / **"riempi la pronuncia"** e fornisce uno slug o ID storia, segui questa procedura esatta.

Questo file riempie i campi **Pronuncia** della frase, non l’analisi grammaticale.

- `phrase_pronunciation` — accordion **Consigli sulla pronuncia** (testo lungo, parola per parola)
- `phrase_ipa` — riga compatta **Trascrizione fonetica IPA**
- `phrase_approx` — riga compatta **Pronuncia approssimata nella tua lingua**

L’analisi grammaticale resta `genera-appunti-frasi.md`. Lì **non** si scrive IPA né “come si legge”.

Se l’utente indica un intervallo (es. “solo le prime 10 frasi”), lavora **solo** su quelle. Indice 0-based: prima frase = `sort_order` 0.

---

## 1. Trova l’ID storia (se dato lo slug)

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root localloverewrite202608 -e "SELECT ID, post_title, post_name FROM wp_posts WHERE post_name = 'SLUG-QUI' AND post_type = 'llm_story';"
```

Leggi le lingue:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ID_QUI AND meta_key IN ('_llm_known_lang','_llm_target_lang');"
```

- `_llm_known_lang` = LINGUA B (quella che lo studente già parla: italiano, polacco, …)
- `_llm_target_lang` = lingua della frase da pronunciare (`phrase_target`)

---

## 2. Leggi le frasi

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_target, phrase_interface, CHAR_LENGTH(phrase_pronunciation) AS pron_len FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC;"
```

Lavora sulla frase in **lingua obiettivo** (`phrase_target`). Non tradurre il significato.

---

## 3. Scrivi `phrase_pronunciation`

Scomponi la frase **parola per parola** (niente punteggiatura). Per ogni parola scrivi **esattamente** così, con una riga vuota tra una parola e la successiva:

```
Parola --> /trascrizione IPA/ (pronuncia approssimata in stile LINGUA B)
Massimo due frasi di consiglio, come un insegnante paziente che spiega a un bambino come si pronuncia.
```

Esempio di forma (polacco → italiano):

```
Dzień --> /dʑɛɲ/ (GIÈN)
Di’ “già”, ma più piano e morbido. Alla fine fai la “gn” di “gnocco”, non una “n” normale.
```

### Regole

- Tono semplice, caldo, parlato. Niente termini da manuale (niente “affricata”, “palatale”, “denasalizza”, “schwa” detto così allo studente: spiega il suono con un esempio).
- Aiuta con un suono già noto della LINGUA B (“come in…”, “non come…”).
- Massimo **due frasi** per parola.
- La pronuncia tra parentesi deve essere facile da leggere per chi parla LINGUA B (maiuscole sulla sillaba forte, trattini tra sillabe se serve).
- Non tradurre il significato.
- Testo semplice, **senza tag HTML** e senza markdown: la formattazione la aggiunge lo script `database/format-grammar-html.php` (vedi `formattazione-appunti-tag-html.md`).
- Contrazioni e parole con trattino restano **una** voce (`didn't`, `twenty-six`).
- Articoli e preposizioni si spiegano comunque, in breve.
- Se la stessa parola torna più volte **nella stessa frase**, scrivila una volta sola (prima occorrenza).
- IPA della varietà più adatta alla storia (es. inglese britannico se la storia è a Londra).

Il prompt corto equivalente è anche in `LLM_Phrase_Pronunciation_Prompt::for_lingua_b()`.

---

## 4. Scrivi `phrase_ipa` e `phrase_approx`

Stesse parole, **nello stesso ordine della frase** (qui le ripetizioni restano, una cella per token).

Esempio:

```
ipa:    / bruːs / / lɪvd / / ɪn / / ə / / smɔːl / / haʊs / / ɪn / / ˈlʌn.dən /
approx: ( BRUUS ) ( livd ) ( in ) ( e ) ( SMOOL ) ( HAUS ) ( in ) ( LÀN-den )
```

Uno spazio tra un blocco e l’altro. Niente HTML.

---

## 5. Salva nel DB

Scrivi i testi in un file PHP temporaneo in `database/` e applicalo con `C:\xampp\php\php.exe` (utf8mb4). In alternativa SQL:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "source C:/xampp/htdocs/localloverewrite202608/database/NOMEFILE.sql"
```

**Intestazione SQL obbligatoria:**

```sql
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
```

Poi formatta l’HTML della pronuncia (una chiamata per indice, 0-based). Lo script riformatta anche `phrase_grammar` della stessa frase: va bene se la grammatica è già nel formato degli appunti.

```powershell
C:\xampp\php\php.exe database/format-grammar-html.php ID_QUI INDICE
```

Se vuoi toccare **solo** la pronuncia, formatta `phrase_pronunciation` nello script di apply (stesse regole della sezione 2b di `formattazione-appunti-tag-html.md`) e non lanciare `format-grammar-html.php`.

---

## 6. Verifica

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, LEFT(phrase_target,40) AS tgt, CHAR_LENGTH(phrase_pronunciation) AS pron, CHAR_LENGTH(phrase_ipa) AS ipa, CHAR_LENGTH(phrase_approx) AS approx FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC;"
```

Sulle frasi chieste: `pron`, `ipa` e `approx` tutti > 0.

---

## Note

- DB locale: `localloverewrite202608`
- MySQL XAMPP: `C:\xampp\mysql\bin\mysql.exe`
- PHP XAMPP: `C:\xampp\php\php.exe`
- File temporanei in `database/`; si possono cancellare dopo
- **Non fare commit / push / FTP** se l’utente non lo chiede. I testi stanno nel DB, non nei file del plugin
- Non pubblicare e non caricare online se l’utente non lo chiede
