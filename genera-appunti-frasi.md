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

Lavora sul campo Analisi grammaticale il campo Note/Traduzione alternativa.


## 3. Scrivi l'analisi grammaticale

Per ogni frase, scrivi l'analisi seguendo queste regole:

- **Lingua**: scritto in Italiano, spiega le strutture della lingua target (es. Inglese)
- **Struttura**: leggi `_llm_known_lang` e `_llm_target_lang` dalla storia per sapere le lingue
- **Formato di ogni punto**: `"parte in lingua nota" → "traduzione"` — spiegazione in lingua [`_llm_known_lang`]
- **Tono**: amichevole e semplice, come se spiegassi a un ragazzo
- **Lunghezza**: MINIMO 220 parole per frase
- **Evidenzia sempre**: Coniugazione completa del verbo (tra parentesi mettimi anche la traduzione delle varie congiugazioni). Se è sostantivo/aggettivo/avverbio, modo di dire, una struttura regolare o irregolari o qualcosa che mi fa ricordare di che tipo è quella parola o quella struttura.
- **Pronuncia**: aiuta l'utente a capire come si pronuncia una determinata parola, un'espressione o tutta la frase, dai consigli ed esempi per aiutare l'utente a pronunciare e ricordare come si pronuncia correttamente quella parola
- **Etimologia o curiosità**:  etimologia o curiosità sulla frase o su una determinata parola

Quindi, prendi la frase dividila per punti, parola per parola o anche più parole insieme e fai un elenco di "punti" come descritto prima: 
- **Formato di ogni punto**: `"parte in lingua nota" → "traduzione in _llm_target_lang"` — spiegazione. La spiegazione deve essere esaustiva e scritta in [`_llm_known_lang`]. E deve fare in modo di aiutare l'utente che sta imparando quella lingua, approfondire e capire magari le differenze con la propria lingua madre, eventualmente altri esempi per capire come utilizzare quella parola in altri contesti. 



Comportati da insegnante di elementari di [`_llm_known_lang`] e devi aiutare un ragazzo che conosce [`_llm_known_lang`] ad imparare la lingua [`_llm_target_lang`]. Usa frasi semplici, concetti semplici, esempi semplici per capire la traduzione. Usa le virgolette quando utilizzi parole [`_llm_target_lang`] durante il discorso.


Importante: come traduzione di riferimento devi prendere il campo "traduzione della frase" che si trova nel blocco frase.

## 4. Scrivi la traduzione alternativa

Campo traduzione alternativa / note alternative: 
prendi in input quello che c'è già scritto, troverai già la frase alternativa con la  traduzione alternativa. Prendile e trasforma però quel campo in: 

  - Un unico paragrafo di testo semplice, senza andate a capo
   - Deve iniziare ESATTAMENTE con: Una traduzione alternativa potrebbe essere: "[frase alternativa]" che in [LINGUA CHE STAI IMPARANDO] si può tradurre in "[traduzione alternativa]"
   - Subito dopo, uno spazio e poi le note grammaticali sulla differenza rispetto alla versione principale
   - Massimo 75 parole per le note
   - Nessun tag HTML e nessun markdown

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
