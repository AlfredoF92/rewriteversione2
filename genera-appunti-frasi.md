# Genera appunti mancanti frasi

Quando l'utente dice **"Genera appunti mancanti frasi"** e fornisce uno slug o ID storia, segui questa procedura esatta.

---

## 1. Trova l'ID storia (se dato lo slug)

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root localloverewrite202608 -e "SELECT ID, post_title, post_name FROM wp_posts WHERE post_name = 'SLUG-QUI' AND post_type = 'llm_story';"
```

## 2. Leggi le frasi della storia

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_interface, phrase_target, phrase_grammar FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC;"
```

Lavora **solo sulle frasi con `phrase_grammar` vuoto** (a meno che l'utente non chieda di sovrascrivere tutto).

## 3. Scrivi l'analisi grammaticale

Per ogni frase, scrivi l'analisi seguendo queste regole:

- **Lingua**: scritto in Italiano, spiega le strutture della lingua target (es. Inglese)
- **Struttura**: leggi `_llm_known_lang` e `_llm_target_lang` dalla storia per sapere le lingue
- **3-4 punti**, uno per paragrafo, separati da riga vuota
- **Formato di ogni punto**: `"parte in lingua nota" → "traduzione"` — spiegazione
- **Tono**: amichevole e semplice, come se spiegassi a un ragazzo
- **Lunghezza**: MASSIMO 250 parole per frase
- **Focus**: solo i punti che un principiante non capirebbe da solo — ignora ciò che è ovvio
- **Evidenzia sempre**: coniugazione del verbo, se è aggettivo/avverbio, strutture regolari o irregolari, differenze strutturali rispetto all'italiano

### Esempio di buona analisi (IT → EN)

```
"Mi chiamo" → "My name is" — In italiano usiamo il verbo riflessivo chiamarsi. In inglese non esiste: si dice "My name is" con aggettivo possessivo + to be. Niente riflessivo!

"Buongiorno" → "Good morning" — In italiano è una parola sola, in inglese sono due parole separate. "Good" è un aggettivo e "morning" è il sostantivo. Stesso schema: good afternoon, good evening.

"Ciao" → "Hi" — Entrambi informali. "Hi" è invariabile: non cambia mai in base a chi parli.
```

## 4. Salva nel DB

Scrivi le query in un file SQL temporaneo in `database/` e poi eseguilo con `--default-character-set=utf8mb4`:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "source C:/xampp/htdocs/localloverewrite202608/database/NOMEFILE.sql"
```

**Intestazione obbligatoria nel file SQL** (per encoding corretto):
```sql
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
```

## 5. Verifica

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, phrase_interface, CHAR_LENGTH(phrase_grammar) as len FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC;"
```

Tutti i campi `len` devono essere > 0.

---

## Note

- Il DB locale è `localloverewrite202608`
- MySQL XAMPP: `C:\xampp\mysql\bin\mysql.exe`
- I file SQL temporanei vanno in `database/` e possono essere cancellati dopo
- **Non fare commit/push/FTP** a meno che l'utente non lo chieda esplicitamente
