# Genera Note della storia

Quando l’utente dice **"Genera note della storia"** / **"riempi le note della storia"** e fornisce uno slug o ID storia, segui questa procedura esatta.

Questo file riempie il campo **Note della storia** di ogni frase (`phrase_notes`).

Non è analisi grammaticale. Non è pronuncia. Non è traduzione alternativa.

- Analisi grammaticale → `genera-appunti-frasi.md` (`phrase_grammar`)
- Consigli sulla pronuncia → `genera-consigli-pronuncia.md` (`phrase_pronunciation`)
- **Note della storia** → questo file (`phrase_notes`)

Se l’utente indica un intervallo (es. “solo le prime 10 frasi”), lavora **solo** su quelle. Indice 0-based: prima frase = `sort_order` 0. Se non dice niente, riempi **tutte** le frasi.

---

## 1. Trova l’ID storia (se dato lo slug)

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root localloverewrite202608 -e "SELECT ID, post_title, post_name FROM wp_posts WHERE post_name = 'SLUG-QUI' AND post_type = 'llm_story';"
```

Leggi titolo, lingue, introduzione e trama:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT post_title FROM wp_posts WHERE ID = ID_QUI; SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ID_QUI AND meta_key IN ('_llm_title_target_lang','_llm_known_lang','_llm_target_lang','_llm_story_intro','_llm_story_plot','_llm_story_card_text');"
```

- `_llm_known_lang` = lingua in cui scrivi le note (quella che lo studente già parla)
- Titolo, intro, trama, card = il mondo della storia. Le note devono esserne coerenti.

---

## 2. Leggi tutte le frasi

Devi vedere **tutta** la storia, non solo la frase in corso. Le note di una frase vivono dentro l’arco completo.

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_interface, phrase_target, CHAR_LENGTH(phrase_notes) AS notes_len FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC;"
```

Usa `phrase_interface` (lingua nota) e `phrase_target` (lingua da imparare) solo per capire **che scena è**. Non spiegare le parole.

---

## 3. Scrivi `phrase_notes`

Per ogni frase, 2 o 3 frasi di prosa (di default **massimo due o tre righe**). Più lungo **solo** se l’utente lo chiede esplicitamente.

Immagina un film. Quella riga di dialogo o di azione è un fotogramma. La nota racconta **la scena**: dove siamo, chi c’è, che atmosfera c’è, cosa rappresenta quel momento nella storia.

### Cosa deve fare

- Coerente con titolo, introduzione, trama e con le altre 49 frasi.
- Tono da sceneggiatura / voce fuori campo: concreto, visivo, un po’ da noir se la storia lo è.
- Scritto in [`_llm_known_lang`].
- Parla **solo** della storia: case, stanze, binari, volti, oggetti, silenzi.
- Se la frase è una domanda (“Chi lo chiamò?”), la nota sta **dentro** quella domanda: il telefono che squilla, l’attesa, non la risposta grammaticale.

### Cosa non deve fare

- Niente lingua da imparare, niente “in inglese si dice”, niente traduzione.
- Niente analisi grammaticale, tempi verbali, articoli, phrasal verb, Past Simple.
- Niente pronuncia, IPA, “come si legge”.
- Niente “Ricorda:”, “Curiosità etimologia”, elenchi, markdown, HTML.
- Niente meta (“in questa frase impariamo…”, “adesso traduci…”).
- Non anticipare il colpo di scena se quella frase non lo ha ancora detto. Resta sul fotogramma corrente, con lo sguardo di chi ha già letto tutta la storia ma non spoilerà oltre ciò che la riga già mostra.

### Forma

- Testo semplice, un unico blocco, 2–3 frasi.
- Nessun titolo, nessun elenco puntato.
- Nessun tag HTML e nessun markdown.

Esempio di tono (non copiare le parole, solo il registro):

```
Notte umida, una casetta bassa tra i tetti di Londra. Bruce è una figura piccola nella nebbia: la casa è stretta, e la città non lo guarda.
```

---

## 4. Salva nel DB

Scrivi i testi in un file PHP in `database/` (array `sort_order => testo`) e applicali con uno script dedicato, oppure con SQL.

**Intestazione obbligatoria nel file SQL** (per encoding corretto):

```sql
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
```

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "source C:/xampp/htdocs/localloverewrite202608/database/NOMEFILE.sql"
```

Campo da aggiornare: `wp_llm_story_phrases.phrase_notes` per `story_id` + `sort_order`.

Se l’utente chiede anche la storia **online**, carica un one-shot in `public_html` (come gli altri `llm-once-*`) e lancialo. Poi il file si auto-elimina.

---

## 5. Verifica

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT sort_order, LEFT(phrase_notes, 80) AS anteprima, CHAR_LENGTH(phrase_notes) AS len FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC;"
```

Tutti i campi `len` devono essere > 0. Ogni nota deve stare sulle 2–3 righe, salvo richiesta diversa dell’utente.
