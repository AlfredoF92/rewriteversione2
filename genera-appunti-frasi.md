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

Lavora sul campo Analisi grammaticale e sul campo Note/Traduzione alternativa.

Il riassunto **Ricorda** (`phrase_remember`) è un campo a parte: vedi `genera-ricorda.md`. Non scriverlo qui.


## 3. Scrivi l'analisi grammaticale

Per ogni frase, scrivi l'analisi seguendo queste regole:

- **Lingua**: tutto il testo degli appunti è in [`_llm_known_lang`] (la lingua che la persona già conosce). Spiega le strutture di [`_llm_target_lang`].
- **Etichette dei paragrafi**: anche i titoli (`Remember`, `Full conjugation`, `Etymology curiosity`, `In the present`…) vanno in [`_llm_known_lang`]. **Mai** lasciare `Ricorda`, `Coniugazione`, `Curiosità etimologia`, `Al presente` se la lingua nota non è l’italiano. Vedi la tabella sotto.
- **Struttura**: leggi `_llm_known_lang` e `_llm_target_lang` dalla storia per sapere le lingue
- **Formato di ogni punto**: `"parte in lingua nota" → "traduzione"` — spiegazione in lingua [`_llm_known_lang`]
- **Dopo "means" / "vuol dire" / "significa"**: l’equivalente nella lingua nota va **tra virgolette**. Esempio (nota = inglese): `"Come" in English means "how"`. Non scrivere `means how` senza virgolette.
- **Tono**: amichevole e semplice, come se spiegassi a un ragazzo
- **Niente nomi in codice**: non usare sigle da manuale tipo `CVC`, `CCV`, `CV`, `SVO`, `NP`, `VP`. Spiega con parole normali (es. «consonante-vocale-consonante» → «consonante, vocale, consonante», come in "cat").
- **Lunghezza**: da **150 parole minimo** a **350 parole massimo** (tutti i paragrafi insieme). La lunghezza è **proporzionale** al numero di parole della `phrase_target` (senza punteggiatura):

| Parole nella frase obiettivo | Lunghezza appunti |
|---|---|
| 1–2 | 150–180 |
| 3–4 | 180–230 |
| 5–7 | 230–290 |
| 8 o più | 290–350 |

Mai sotto 150, mai sopra 350. Una frase corta come *Ciao!* resta vicina al minimo; una frase lunga usa più spazio, senza riempire di ripetizioni.
- **Evidenzia sempre**: coniugazione completa del verbo, con l’etichetta nella lingua nota (tra parentesi metti anche la traduzione delle varie persone). Se è sostantivo/aggettivo/avverbio, modo di dire, una struttura regolare o irregolare o qualcosa che fa ricordare di che tipo è quella parola o quella struttura.
- **Due tempi, due elenchi**: se dopo il passato metti anche il presente (o un altro tempo), chiudi l’ultima persona con il punto e inizia il secondo elenco con l’etichetta nella lingua nota (`In the present:` / `Al presente:` / `En presente:` …). Non attaccare l’etichetta sulla stessa riga dell’ultima persona. Lo script di formattazione lo mette a capo da solo, purché l’etichetta abbia i due punti e poi I/You/They (o io/tu/…).
- **Niente pronuncia**: non scrivere IPA, suoni, “come si legge”, palatali, “scandendo”, “ad alta voce”. Tutto ciò che riguarda la pronuncia va in `genera-consigli-pronuncia.md` (campo **Consigli sulla pronuncia**), non in Analisi grammaticale.
- **Niente punteggiatura**: non spiegare virgole, punti, due punti, virgolette, punti interrogativi o qualsiasi altro segno di punteggiatura.
- **Niente formule da “rubare”**: non usare frasi tipo "Stampino da rubare", "Questa è la frase da rubare in ogni ufficio" o simili. Se l’utente deve ricordare qualcosa, usa **solo** l’etichetta `Remember:` / `Ricorda:` / equivalente in [`_llm_known_lang`], seguita dalla spiegazione.

### Etichette obbligatorie (lingua nota)

Scegli la colonna di [`_llm_known_lang`]. Non mescolare.

| Ruolo | `it` | `en` | `pl` | `es` |
|---|---|---|---|---|
| Coniugazione | `Coniugazione completa del presente di "…":` | `Full conjugation of the present of "…":` | `Pełna koniugacja czasu teraźniejszego "…":` | `Conjugación completa del presente de "…":` |
| Secondo tempo | `Al presente:` / `Al passato:` / `Al futuro:` | `In the present:` / `In the past:` / `In the future:` | `W czasie teraźniejszym:` / `W czasie przeszłym:` / `W czasie przyszłym:` | `En presente:` / `En pasado:` / `En futuro:` |
| Ricorda | `Ricorda:` | `Remember:` | `Zapamiętaj:` | `Recuerda:` |
| Etimologia | `Curiosità etimologia:` | `Etymology curiosity:` | `Ciekawostka etymologiczna:` | `Curiosidad etimológica:` |

Ordine fisso del testo (non invertire):

1. **Spiegazione** — punti `"parte in lingua nota" → "traduzione in _llm_target_lang"` — spiegazione esaustiva in [`_llm_known_lang`]. Differenze con la lingua madre, altri esempi d’uso. **Niente pronuncia in mezzo né in fondo.** Eventuale `Remember:` / `Ricorda:` sta qui, prima del paragrafo finale.
2. **Etimologia** — **un unico paragrafo**, alla fine di tutto. Deve iniziare esattamente con l’etichetta della tabella (`Etymology curiosity:` se la lingua nota è l’inglese). Etimologia o curiosità sulla frase o su una parola importante.

Non aggiungere un paragrafo `Pronuncia:` nell’analisi grammaticale.

Quindi, prendi la frase, dividila per punti, parola per parola o anche più parole insieme, e fai l’elenco dei punti come sopra. La spiegazione deve aiutare chi sta imparando, con esempi semplici.

Comportati da insegnante di elementari di [`_llm_known_lang`] e aiuta un ragazzo che conosce [`_llm_known_lang`] a imparare [`_llm_target_lang`]. Frasi semplici, concetti semplici, esempi semplici. Usa le virgolette quando citi parole [`_llm_target_lang`] durante il discorso.

Importante: come traduzione di riferimento prendi il campo "traduzione della frase" nel blocco frase.

## 4. Scrivi la traduzione alternativa

Campo: `phrase_alt`.

Parti da `phrase_target` (frase ufficiale da imparare) e da `phrase_interface` (senso in lingua nota). Nel campo trovi già una frase alternativa: usala, non inventarne un’altra se c’è già.

Scrivi in [`_llm_known_lang`]. **Quattro righe in un solo `<p>`**, separate da `<br />` (a capo singolo, non paragrafi). Non invertire le lingue.

1. Frase ufficiale da imparare, tra virgolette  
2. Un’altra frase, **sempre** in [`_llm_target_lang`], tra virgolette  
3. Il senso di quella alternativa in [`_llm_known_lang`], tra virgolette  
4. Spiegazione in lingua nota: perché è lecita, cosa cambia (registro, una parola, una struttura). Massimo 75 parole. Niente pronuncia, niente IPA, niente storia.

Etichette (colonna di [`_llm_known_lang`]):

| | `en` | `it` | `pl` | `es` |
|---|---|---|---|---|
| 1 | `An alternative translation of "…"` | `Una traduzione alternativa di "…"` | `Alternatywne tłumaczenie "…"` | `Una traducción alternativa de "…"` |
| 2 | `could be "…"` | `potrebbe essere "…"` | `mogłoby brzmieć "…"` | `podría ser "…"` |
| 3 | `In English that is "…"` | `In italiano è "…"` | `Po polsku to "…"` | `En español es "…"` |

Esempio (nota = inglese, obiettivo = italiano):

```html
<p>An alternative translation of "<em>Buongiorno, come stai?</em>"<br />could be "<em>Buongiorno, come va?</em>"<br />In English that is "Good morning, how's it going?"<br />"<em>Come va</em>" does not name you; "<em>Come stai</em>" looks straight at the friend.</p>
```

Regole:

- Riga 1 e riga 2 sono **entrambe** in [`_llm_target_lang`].
- Riga 3 è il senso in [`_llm_known_lang`]. Non scrivere «in Italian» e poi mettere l’inglese.
- Nella spiegazione, le parole [`_llm_target_lang`] stanno tra virgolette.
- Solo tag `p` / `em`. Niente markdown.

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
