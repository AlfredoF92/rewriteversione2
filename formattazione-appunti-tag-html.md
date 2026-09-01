# Formattazione appunti tag HTML

Quando l’utente dice **«formattazione appunti»** / **«formatta appunti frase»**, formatta con i tag HTML sotto. **Non cambiare il senso del testo**: solo grassetto, a capo e paragrafi.

Script PHP: `database/format-grammar-html.php` — aggiorna **Analisi grammaticale** (`phrase_grammar`) **e** **Consigli sulla pronuncia** (`phrase_pronunciation`) della stessa frase.

```powershell
php database/format-grammar-html.php STORY_ID [INDICE]
```

`INDICE` è 0-based (default `0` = prima frase). Esempio prima frase della storia 3168:

```powershell
php database/format-grammar-html.php 3168 0
```

---

## 1. Leggi il campo

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_interface, phrase_grammar FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC;"
```

Lavora su `phrase_grammar` e `phrase_pronunciation`. Non toccare `phrase_alt`.

Se il testo è già HTML, lo script lo toglie e riformatta da capo (si può rilanciare).

---

## 2. Regole HTML (campo Analisi grammaticale)

Ogni blocco separato da riga vuota diventa un `<p>…</p>`.

### Coppie `"lingua nota" → "lingua target"`

Il titolo va in grassetto, poi a capo, poi la spiegazione:

```html
<p><strong>"Davide era" → "David was" </strong><br />spiegazione…</p>
```

Il titolo è tutto ciò che sta prima del trattino lungo (`—`) dopo la coppia.

### Coniugazione del verbo

Quando nel paragrafo c’è `Coniugazione…:`, vai a capo prima dell’elenco e **una riga per persona**:

```html
<br /><br />Coniugazione del Past Simple di "to be": <br />I was (ero)<br />You were (eri)<br />He/She/It was (era)<br />We were (eravamo)<br />You were (eravate)<br />They were (erano). <br />testo che segue…
```

In polacco, stessa cosa sulle persone: `ja`, `ty`, `on/ona/ono`, `my`, `wy`, `oni/one`.

### Titoli di paragrafo in grassetto

Se il paragrafo inizia con un’etichetta, quella parte va in `<strong>`:

- Pronuncia
- Etimologia
- Curiosità etimologia / sull’etimologia (e varianti)
- Ricorda
- Consigli / Consigli sulla pronuncia
- Le parole
- Differenza strutturale

```html
<p><strong>Ricorda:</strong> …</p>
<p><strong>Pronuncia:</strong> …</p>
```

### A capo ogni 3 frasi

Dentro lo stesso `<p>`, dopo tre frasi che finiscono con punto, inserisci `<br />`. Non aprire un altro paragrafo.

### Ricorda

Paragrafo suo. Se c’è un esempio `Es. …`, mettilo in corsivo:

```html
<p><strong>Ricorda:</strong> … <em>Es. "He was young" (era giovane), "They were soldiers" (erano soldati).</em></p>
```

### Tag ammessi

Solo: `<p>`, `<strong>`, `<br />`, `<em>`. Niente markdown (`**`, `_`).

---

## 2b. Consigli sulla pronuncia (`phrase_pronunciation`)

Ogni parola (blocco separato da riga vuota) diventa un `<p>`. Il titolo è la riga

`Parola --> /IPA/ (approssimata)`

in grassetto, poi a capo i consigli (massimo due frasi, tono da insegnante). Ogni 3 frasi con punto: `<br />` nello stesso paragrafo.

```html
<p><strong>Dzień --> /dʑɛɲ/ (GIÈN)</strong><br />Di’ “già”, ma più piano e morbido. Alla fine fai la “gn” di “gnocco”, non una “n” normale.</p>
```

Il testo grezzo si scrive ancora in piano (come nel prompt di pronuncia). Questo script aggiunge i tag.

---

## 3. Salva

Lo script PHP aggiorna il DB locale (`localloverewrite202608`). Verifica:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, LEFT(phrase_grammar, 80) FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC LIMIT 3;"
```

**Non fare commit / push / FTP** se l’utente non lo chiede. Gli appunti stanno nel DB, non nei file del plugin.

---

## Note

- Il gioco già spezza `phrase_grammar` sui `<p>` e mostra l’HTML (grassetto, a capo).
- Altre regole si aggiungono in questo file quando l’utente le dice.
