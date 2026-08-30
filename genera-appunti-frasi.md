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
- **Lunghezza**: MINIMO 220 parole per frase (tutti i paragrafi insieme)
- **Evidenzia sempre**: Coniugazione completa del verbo (tra parentesi mettimi anche la traduzione delle varie congiugazioni). Se è sostantivo/aggettivo/avverbio, modo di dire, una struttura regolare o irregolari o qualcosa che mi fa ricordare di che tipo è quella parola o quella struttura.
- **Niente punteggiatura**: non spiegare virgole, punti, due punti, virgolette, punti interrogativi o qualsiasi altro segno di punteggiatura.
- **Niente formule da “rubare”**: non usare frasi tipo "Stampino da rubare", "Questa è la frase da rubare in ogni ufficio" o simili. Se l’utente deve ricordare qualcosa, usa **solo** `Ricorda:` seguito dalla spiegazione.

Ordine fisso del testo (non invertire):

1. **Spiegazione** — punti `"parte in lingua nota" → "traduzione in _llm_target_lang"` — spiegazione esaustiva in [`_llm_known_lang`]. Differenze con la lingua madre, altri esempi d’uso. **Niente pronuncia e niente etimologia in mezzo.** Eventuale `Ricorda:` sta qui, ancora prima dei due paragrafi finali.
2. **Pronuncia** — **un unico paragrafo**, sempre **alla fine** delle spiegazioni, mai durante il discorso. Deve iniziare esattamente con `Pronuncia:` Consigli sulla pronuncia in generale, per tutte le parole o per le più importanti (parole, espressioni, o tutta la frase). Per ogni parola con IPA, metti **subito accanto tra parentesi** come suonerebbe se la scrivessi nella lingua nota (`_llm_known_lang`): prima l’IPA, poi la riscrittura. Esempio per un italiano che impara l’inglese: `/ˈstɑːr.tɪd/` (startid). La riscrittura deve essere leggibile da chi conosce solo `_llm_known_lang`, senza simboli fonetici extra.
3. **Curiosità etimologia** — **un unico paragrafo**, dopo la pronuncia, alla fine di tutto. Deve iniziare esattamente con `Curiosità etimologia:` Etimologia o curiosità sulla frase o su una parola importante.

Quindi, prendi la frase, dividila per punti, parola per parola o anche più parole insieme, e fai l’elenco dei punti come sopra. La spiegazione deve aiutare chi sta imparando, con esempi semplici.

Comportati da insegnante di elementari di [`_llm_known_lang`] e aiuta un ragazzo che conosce [`_llm_known_lang`] a imparare [`_llm_target_lang`]. Frasi semplici, concetti semplici, esempi semplici. Usa le virgolette quando citi parole [`_llm_target_lang`] durante il discorso.

Importante: come traduzione di riferimento prendi il campo "traduzione della frase" nel blocco frase.

## 4. Scrivi la traduzione alternativa

Campo traduzione alternativa / note alternative: 
prendi in input quello che c'è già scritto, troverai già la frase alternativa con la  traduzione alternativa. Prendile e trasforma però quel campo in: 

  - Un unico paragrafo di testo semplice, senza andate a capo
   - Deve iniziare ESATTAMENTE con: Una traduzione alternativa potrebbe essere: "[frase alternativa]" che in [LINGUA CHE STAI IMPARANDO] si può tradurre in "[traduzione alternativa]"
   - Subito dopo, uno spazio e poi le note grammaticali sulla differenza rispetto alla versione principale
   - Massimo 75 parole per le note
   - Nessun tag HTML e nessun markdown

## 5. Salva nel DB

Scrivi le query in un file SQL temporaneo in `database/` e poi eseguilo con `--default-character-set=utf8mb4`:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "source C:/xampp/htdocs/localloverewrite202608/database/NOMEFILE.sql"
```

**Intestazione obbligatoria nel file SQL** (per encoding corretto):
```sql
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
```

## 6. Verifica

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
