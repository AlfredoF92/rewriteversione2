# Formattazione appunti tag HTML

Quando l’utente dice **«formattazione appunti»** / **«formatta appunti frase»**, formatta con i tag HTML sotto. **Non cambiare il senso del testo**: solo grassetto, a capo e paragrafi. Non riscrivere le frasi e non inventare spiegazioni.

**Niente nomi in codice** (anche se comparissero già nel testo, non aggiungerne di nuovi): niente sigle tipo `CVC`, `CCV`, `CV`, `SVO`, `NP`, `VP`. Se formatti, lascia il testo com’è; se un giorno si riscrive l’appunto, si dice in parole semplici (consonante, vocale, consonante).

Script PHP: `database/format-grammar-html.php` — aggiorna **Analisi grammaticale** (`phrase_grammar`), **Consigli sulla pronuncia** (`phrase_pronunciation`) e **traduzione alternativa** (`phrase_alt`) della stessa frase.

```powershell
php database/format-grammar-html.php STORY_ID [INDICE|all]
```

`INDICE` è 0-based (default `0` = prima frase). `all` riformatta tutte le frasi. Esempio prima frase della storia 3168:

```powershell
php database/format-grammar-html.php 3168 0
php database/format-grammar-html.php 3172 all
```

---

## 1. Leggi il campo

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_interface, phrase_grammar FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC;"
```

Lavora su `phrase_grammar`, `phrase_pronunciation` e `phrase_alt`.

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

Quando nel paragrafo c’è un’etichetta di coniugazione nella lingua nota (`Coniugazione…:` / `Full conjugation` / `Conjugation` / `Pełna koniugacja` / `Conjugación`), vai a capo prima dell’elenco e **una riga per persona**:

```html
<br /><br /><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).
</p>
<p>testo che segue…
```

In polacco, stessa cosa sulle persone: `ja`, `ty`, `on/ona/ono`, `my`, `wy`, `oni/one`. In italiano: `io`, `tu`, `lui/lei`, `noi`, `voi`, `loro`. Anche `He`, `She`, `It` da soli (non solo `He/She/It`) vanno ciascuno a capo.

**Dopo l’ultima persona** della coniugazione (anche dopo un secondo tempo tipo `In the present:` / `Al presente:`): vai a capo, **chiudi il `<p>`** e apri un nuovo paragrafo per il testo che segue. Non lasciare esempi o note sulla stessa riga, né nello stesso paragrafo, dell’ultima persona.

Le righe della coniugazione **non** vanno in corsivo (né le forme verbali né le traduzioni tra parentesi).

### Secondo tempo (In the present, Al presente, …)

Se dopo la prima coniugazione c’è un altro tempo (`In the present:`, `In the past:`, `Al presente:`, `Al passato:`, `Al futuro:`, `Al participio:`, `All'infinito:`, anche con testo in mezzo), **quel blocco non resta sulla riga di They / oni**. Sempre:

1. chiudi l’ultima persona del tempo precedente (con il punto)
2. vai a capo
3. etichetta del nuovo tempo in grassetto
4. di nuovo **una riga per persona**

```html
They fitted (loro montarono).
<br /><br /><strong>In the present:</strong> <br />I fit (io monto / sto)<br />You fit (tu monti)<br />He/She/It fits (lui/lei monta)<br />We fit (noi montiamo)<br />You fit (voi montate)<br />They fit (loro montano).
```

Un commento tipo «In the present the third person takes -s» (senza due punti + I/You/…) non è una nuova coniugazione: resta testo dopo l’elenco, **sempre a capo** dopo il punto di They / oni.

### Dopo il punto dell’ultima persona

Qualunque testo dopo il punto dell’ultima persona (`They …).` / `oni …).`) va a capo. Non solo `Al presente:`: anche `Nel phrasal…`, un altro elenco, una nota. Mai sulla stessa riga di They.

```html
They set (loro mettono).
<br />Nel phrasal "set off" al passato tutte le persone restano "set off": I set off (partii)<br />you set off (partisti)<br />they set off (partirono).
```

### Titoli di paragrafo in grassetto

Se il paragrafo inizia con un’etichetta **nella lingua nota**, quella parte va in `<strong>`. Riconosci sia le etichette italiane sia quelle inglesi / polacche / spagnole:

- Remember / Ricorda / Zapamiętaj / Recuerda
- Etymology curiosity / Curiosità etimologia / Ciekawostka etymologiczna / Curiosidad etimológica
- Etymology / Etimologia
- Pronunciation / Pronuncia
- Tips / Consigli / Consigli sulla pronuncia
- The words / Le parole
- Structural difference / Differenza strutturale

```html
<p><strong>Remember:</strong> …</p>
<p><strong>Etymology curiosity:</strong> …</p>
```

### A capo ogni 3 frasi

Dentro lo stesso `<p>`, dopo tre frasi che finiscono con punto, inserisci `<br />`. Non aprire un altro paragrafo.

### Remember / Ricorda

Paragrafo suo. Se c’è un esempio `Es. …` / `Ex. …`, mettilo in corsivo:

```html
<p><strong>Remember:</strong> … <em>Ex. "He was young" (era giovane), "They were soldiers" (erano soldati).</em></p>
```

### Corsivo sulla lingua da imparare

Le parole nella **lingua obiettivo** (`_llm_target_lang`) vanno in `<em>`.

- Nella coppia titolo, solo il pezzo a destra della freccia: `"Hello" → "<em>Ciao</em>"`
- Nel testo della spiegazione, le citazioni tra virgolette (sono parole/frasi da imparare): `"<em>stare</em>"`
- **Eccezione:** non toccare le coniugazioni (elenco persone). Lì niente corsivo.

```html
<p><strong>"Hello" → "<em>Ciao</em>"</strong><br />Italian opens with "<em>Ciao</em>" for friends.</p>
```

### Dopo "means" (virgolette + corsivo)

Dopo `means` (o `mean`, `meaning` usato come “vuol dire”) l’equivalente nella lingua nota va **tra virgolette** e in **corsivo**.

Esempio grezzo: `"Come" in English means how`  
Diventa:

```html
"<em>Come</em>" in English means "<em>how</em>"
```

- Se le virgolette ci sono già (`means "how"`), metti solo il corsivo dentro.
- Se mancano (`means how` / `means morning, but`), aggiungi le virgolette intorno al gloss breve e poi il corsivo.
- Non avvolgere intere frasi dopo `means that…` / `this means the verb…`. Solo il gloss tipo traduzione (di solito 1–4 parole).
- Stessa idea per `vuol dire` / `significa` quando gli appunti sono in italiano: `vuol dire "come"`.

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

## 2c. Traduzione alternativa (`phrase_alt`)

Un solo `<p>`, quattro righe separate da `<br />` (a capo singolo, niente spazio da paragrafo).

- Righe 1 e 2: citazioni in lingua obiettivo → virgolette + `<em>`
- Riga 3: senso in lingua nota → virgolette, **senza** corsivo
- Riga 4: spiegazione; le citazioni in lingua obiettivo → virgolette + `<em>`

```html
<p>An alternative translation of "<em>Buongiorno, come stai?</em>"<br />could be "<em>Buongiorno, come va?</em>"<br />In English that is "Good morning, how's it going?"<br />"<em>Come va</em>" does not name you; "<em>Come stai</em>" looks straight at the friend.</p>
```

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
- In **genera appunti** non usare sigle da codice (`CVC` ecc.): solo parole normali. Questa formattazione non tocca il contenuto delle frasi.
- Altre regole si aggiungono in questo file quando l’utente le dice.
