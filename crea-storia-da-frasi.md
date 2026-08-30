# Crea storia da frasi (bozza)

Quando l’utente dice di **creare una storia**, passa un elenco di frasi e indica la **coppia linguistica** (es. «Conosco l’italiano — voglio imparare l’inglese»), segui questa procedura esatta.

Non pubblicare. La storia va sempre in **bozza** (`draft`), salvo richiesta esplicita di pubblicare.

Dopo lo step 2, applica **sempre** `genera-appunti-frasi.md` (step 3).

---

## Input obbligatori

- **Coppia**: lingua nota (interfaccia) + lingua da imparare (obiettivo)
- **Frasi**: elenco, una per riga (o elenco numerato)

Input facoltativi: titolo, livello CEFR, categoria contenuto, coin, slug.

---

## Mappa lingue

Codici validi: `it` | `en` | `pl` | `es`.

| Come lo dice l’utente | Codice |
|---|---|
| italiano / Italian | `it` |
| inglese / English | `en` |
| polacco / Polish | `pl` |
| spagnolo / Spanish | `es` |

Esempio: «Conosco l’italiano — voglio imparare l’inglese» → `known=it`, `target=en`.

Categoria coppia (obbligatoria): slug `KNOWN-SLUGOBIETTIVO` da `LLM_Magazine::pair_category_slugs()`.

Esempi: it→en = `it-english`; en→it = `en-italian`; pl→it = `pl-wloski` (eccezione già in sito).

Se la categoria non esiste, creala (nome visibile tipo `it-english`). Non usare «Senza categoria».

---

## Step 1 — Frasi nella posizione giusta

Per ogni riga:

1. Rileva la lingua della frase (o usa l’indicazione dell’utente).
2. Metti il testo nella colonna corretta:
   - `phrase_target` = lingua **obiettivo** (quella da imparare)
   - `phrase_interface` = lingua **nota** (interfaccia)
3. Se la frase è già in lingua obiettivo → copiala in `phrase_target` e **traducila** in lingua nota per `phrase_interface`.
4. Se la frase è in lingua nota → copiala in `phrase_interface` e **traducila** in lingua obiettivo per `phrase_target`.
5. Conserva l’ordine dato dall’utente (`sort_order` 0, 1, 2…).
6. Non saltare righe. Non unire due frasi. Non inventare frasi extra.
7. In questo step `phrase_grammar` e `phrase_alt` restano vuoti (li riempie lo step 3).

In questo step puoi già preparare, per ogni frase, una **traduzione alternativa** breve (due versioni: nota + obiettivo): servirà allo step 3 per il campo note.

---

## Step 2 — Crea la storia in bozza (tutti i campi)

Compila **tutti** i campi della scheda WordPress storia `llm_story`.

Testi di trama / intro / finale / scheda / estratto: scritti in **lingua nota** (interfaccia), tono amichevole.

| Campo WordPress | Dove | Cosa scrivere |
|---|---|---|
| Titolo | `post_title` | Titolo in lingua **nota**. In pagina è il sottotitolo hero. |
| Titolo lingua obiettivo | `_llm_title_target_lang` | Titolo in lingua da imparare. In pagina è l’H1. |
| Estratto / sottotitolo scheda | `post_excerpt` | 1–2 frasi in lingua nota, per elenco/card. |
| Contenuto editor | `post_content` | Paragrafo breve = trama (o vuoto se non serve). |
| Lingua interfaccia (nota) | `_llm_known_lang` | Codice (`it`, `en`, …) |
| Lingua da imparare | `_llm_target_lang` | Codice |
| Trama della storia | `_llm_story_plot` | 40–80 parole, riassunto narrativo |
| Introduzione | `_llm_story_intro` | 2–4 frasi. Appare prima delle frasi (typewriter). Invita a imparare. |
| Finale | `_llm_story_finale` | 2–4 frasi. Appare a storia completata. Complimenti + cosa ha imparato. |
| Livello CEFR | `_llm_story_cefr_level` | A1, A2, B1, B2, C1 o C2 (se non indicato: stima dal vocabolario) |
| Topic grammaticali | `_llm_story_grammar_topics` | Un topic per riga |
| Breve testo scheda | `_llm_story_card_text` | 1–2 frasi per la card |
| Costo coin | `_llm_story_coin_cost` | Default **10** se l’utente non dice altro |
| Premio coin | `_llm_story_coin_reward` | Default **25** se l’utente non dice altro |
| Categoria | taxonomy `category` | Sempre la categoria **coppia**. Altre categorie solo se l’utente le chiede. |
| Stato | `post_status` | **`draft`** |
| Immagine in evidenza | thumbnail | Solo se l’utente la fornisce o chiede di sceglierla. |

Salva con uno script PHP CLI che carica WordPress (`wp-load.php`) e usa `wp_insert_post` + `update_post_meta` + `LLM_Story_Repository::save_phrases`. Non inserire a mano le righe `wp_posts` in SQL.

Modello dati in `database/` (es. `database/story-draft-DATA.php`) + runner `database/create-story-draft.php`.

Intestazione runner:

```php
if ( php_sapi_name() !== 'cli' ) {
	exit( 'Solo CLI.' );
}
$root = dirname( __DIR__ );
require $root . '/wp-load.php';
```

Esecuzione:

```powershell
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\localloverewrite202608\database\create-story-draft.php" "C:\xampp\htdocs\localloverewrite202608\database\NOME-DATI.php"
```

Verifica:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT ID, post_title, post_name, post_status FROM wp_posts WHERE ID = ID_QUI AND post_type = 'llm_story';"
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ID_QUI AND meta_key LIKE '_llm%' ORDER BY meta_key;"
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, LEFT(phrase_interface,60) AS iface, LEFT(phrase_target,60) AS tgt FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC;"
```

Controlla: `post_status = draft`, lingue corrette, numero frasi = elenco utente, target/interface non scambiati.

---

## Step 3 — Appunti frasi

Appena hai l’ID storia, esegui **tutto** `genera-appunti-frasi.md`:

- analisi grammaticale (minimo 220 parole, in lingua nota)
- ordine fisso: spiegazione (eventuale `Ricorda:`) → un paragrafo `Pronuncia:` in fondo → un paragrafo `Curiosità etimologia:` in fondo; niente pronuncia/etimologia in mezzo; niente punteggiatura; niente formule tipo “da rubare”
- traduzione alternativa nel formato obbligatorio
- salva nel DB e verifica `CHAR_LENGTH(phrase_grammar) > 0` su tutte le righe

Usa come traduzione di riferimento il campo `phrase_target` / `phrase_interface` già salvati.

---

## Note

- DB locale: `localloverewrite202608`
- MySQL XAMPP: `C:\xampp\mysql\bin\mysql.exe`
- PHP XAMPP: `C:\xampp\php\php.exe`
- File SQL/PHP temporanei in `database/`; si possono cancellare dopo
- **Non fare commit / push / FTP** se l’utente non lo chiede
- Non pubblicare la storia in questo flusso
