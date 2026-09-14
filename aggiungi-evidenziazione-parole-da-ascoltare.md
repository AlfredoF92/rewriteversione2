# Aggiungi evidenziazione parole da ascoltare

Quando l’utente dice **«aggiungi evidenziazione parole da ascoltare»** / **«lancia evidenziazione parole da ascoltare»** / **«genera audio parole appunti»** e dà uno slug o ID storia, segui questa procedura.

**Non scrivere codice** la prima volta che leggi questo file: conferma le regole. Il codice e gli script si fanno solo quando l’utente dice di lanciare.

Questo **sostituisce** l’ascolto on-demand del testo evidenziato (`listen_notes_sel`: selezioni una porzione → popup Play → TTS al volo). Quella strada non si usa più. L’utente non evidenzia nulla a mano. Vede già, negli appunti, le parole/frasi cliccabili con audio **già scaricato**.

---

## Obiettivo

Negli **appunti della frase** (campo Analisi grammaticale, `phrase_grammar`) l’utente che sta imparando la lingua obiettivo della storia (es. inglese) deve poter **cliccare** una parola o una frase in quella lingua e **ascoltare** l’audio.

Flusso:

1. Trovare in `phrase_grammar` tutte le **parole e frasi nella lingua obiettivo** (di solito già tra virgolette, o già in `<em>`).
2. Avvolgerle in un tag HTML stabile, collegato all’audio.
3. Generare e salvare un MP3 Azure femminile per ogni testo distinto (niente TTS al click).
4. Lato utente: se l’opzione in *Strumenti utili* è accesa, quelle parole si vedono evidenziate (sfondo grigio chiaro) e al click partono l’audio.
5. Lato admin: sotto gli appunti della frase, elenco di tutte le parole/frasi estratte, ciascuna con Play.

Lingua da ascoltare = **`_llm_target_lang`** della storia. L’inglese è solo l’esempio. Su una storia IT→PL si ascolta il polacco, ecc. Non si tagga la lingua nota (spiegazioni, traduzioni tra parentesi, etichette `Ricorda:`, `Coniugazione…`).

---

## Cosa taggare (solo lingua obiettivo)

Non si conta “almeno N parole” in modo cieco. Due parole da sole (`coral reef`, `to live`, `did not`) **non** si taggano: non sono una frase da ascoltare.

Si tagga solo:

1. **Frasi-esempio** in lingua obiettivo (di solito dopo 💬 Esempio / Ex. / Es.), tra virgolette, con soggetto e verbo: `Nemo is small`, `They live near the sea`.
2. **Righe di coniugazione**: la forma inglese prima della parentesi (`I lived`, `You live`, `He/She/It lives`). Qui due parole vanno bene perché *è* la persona del verbo.
3. **Lato destro della coppia titolo** `"nota" → "obiettivo"` solo se è un **sintagma lungo** (3+ parole), tipo `in the coral reef`. Non `lived`, non `coral reef`.

Una sola parola (`lived`, `Marlin`, `reef`, `the`) **si lascia stare**.

### A. Esempi tra virgolette (frasi vere)

Tagga **il contenuto** (e l’eventuale `<em>`), **non** le virgolette.

```html
<!-- no: una parola -->
"<em>lived</em>"
<!-- no: due parole, non è una frase -->
"<em>coral reef</em>"
"<em>to live</em>"
<!-- sì: frase -->
"<span class="llm-notes-word" data-listen="Marlin lived in the reef"><em>Marlin lived in the reef</em></span>"
```

**Niente esempi sbagliati.** Se la riga è un ❌ / “errore tipico” / “non dire”, non taggare quella citazione (né la sbagliata né, in quella riga, il contrasto ✅ se è attaccato allo stesso avviso: si tagga solo l’esempio buono se è un 💬 Esempio a sé).

### B. Coniugazione

Nelle righe persona per persona tagga **solo la forma nella lingua obiettivo**, prima della traduzione tra parentesi. Non taggare `io vivo`, `tu vivi`, né l’etichetta `Full conjugation…` / `Coniugazione…`. Qui due parole (`I lived`) vanno bene.

```html
I live (io vivo)
You live (tu vivi)
He/She/It lives (lui/lei vive)
```

diventa:

```html
<span class="llm-notes-word" data-listen="I live">I live</span> (io vivo)<br />
<span class="llm-notes-word" data-listen="You live">You live</span> (tu vivi)<br />
<span class="llm-notes-word" data-listen="He/She/It lives">He/She/It lives</span> (lui/lei vive)
```

Stesso criterio per `In the present:` / `Al presente:` e per phrasal (`I set off`, `you set off`, …).

### C. Titolo coppia `"nota" → "obiettivo"`

Nel titolo in `<strong>`, tagga **solo il pezzo a destra della freccia** se è un sintagma di **3+ parole**. Il pezzo a sinistra resta spiegazione. Una o due parole a destra: non taggare.

```html
<strong>"viveva" → "<em>lived</em>"</strong>
<strong>"nella barriera corallina" → "<span class="llm-notes-word" data-listen="in the coral reef"><em>in the coral reef</em></span>"</strong>
```

---

## Cosa NON taggare

- Tutto il testo in lingua nota (spiegazioni, “si può tradurre con”, etimologia in italiano/inglese noto, ecc.).
- IPA, pronuncia approssimativa, consigli di pronuncia (`phrase_pronunciation`): **fuori scope**.
- Traduzione alternativa (`phrase_alt`), Ricorda (`phrase_remember`), note della storia: **fuori scope** in questa versione, salvo richiesta esplicita.
- Etichette (`Remember:`, `Ricorda:`, `Etymology curiosity:`, `Coniugazione completa…`, `In the present:`).
- **Una sola parola** (`lived`, `Marlin`, `the`).
- **Due parole che non sono coniugazione**: `coral reef`, `to live`, `did not`, `reef coral`.
- **Esempi sbagliati** (riga ⚠️ / ❌ / “errore tipico”).
- Citazioni in **lingua nota** (`nella barriera corallina`, `una borsa di pelle`, `durava a lungo`).
- Etimologia in greco / latino / antico inglese (`libban`, `korallion`, `rif`…).
- Punteggiatura da sola, numeri di elenco, frecce `→`.
- Parole già dentro un `span.llm-notes-word` (lo script deve essere rilanciabile senza duplicare i tag).

---

## Attributo `data-listen`

- Testo **esatto** da far pronunciare, senza virgolette HTML, senza `<em>`.
- Spazi normalizzati (un solo spazio, trim).
- Stesso testo (confronto case-insensitive, trim) → **un solo MP3**, riusato da tutti gli span uguali nella storia.
- Esempi: frase vera (in pratica 3+ parole). Coniugazione: forma della persona (`I lived`). Titolo: 3+ parole. Mai 1 parola; mai 2 parole se non è coniugazione.

---

## Audio

- Voce: **Azure femminile**, locale della lingua obiettivo (stesso mapping di `LLM_Story_Phrase_Game::speech_locale`).
- Un file MP3 per ogni valore distinto di `data-listen` nella storia.
- Allegato WordPress (come gli altri TTS), non base64 al volo.
- Rilancio: salta i testi che hanno già l’allegato, a meno di `--force`.
- Non mischiare maschile/femminile: solo femminile.

Tabella (o meta) di appoggio per frase: elenco `{ text, attachment_id }` così l’admin e il frontend sanno cosa mostrare. Se un audio è condiviso tra più frasi (stessa parola in due appunti), l’allegato è uno, i riferimenti sono per frase.

---

## Lato utente (gioco)

Opzione in *Strumenti utili*, **spenta di default**:

- **Etichetta** (IT): `Ascolta le parole negli appunti`
- **Desc** (IT): `Negli appunti le parole e le frasi nella lingua che stai imparando sono evidenziate: clicca per ascoltarle.`

Rimuovere il comportamento vecchio: niente selezione, niente popup “Ascolta / Ascolta in italiano”.

Se l’opzione è **off**: gli span restano nel HTML ma **senza** stile da “si può cliccare” e **senza** play (testo normale).

Se l’opzione è **on**:

- Sfondo **grigio chiaro** sullo span (v1). Sottolineatura a puntini: da valutare dopo, non in v1.
- Cursore pointer, click → play dell’MP3 già scaricato.
- Un click su un’altra parola ferma l’audio precedente.
- Niente secondo pulsante “nella lingua nota”: si ascolta solo la lingua obiettivo.

---

## Lato admin

Quando si apre / si clicca una **frase** in wp-admin (stesso schermo dove già si vedono gli appunti):

1. Appunti della frase (come oggi).
2. **Sotto**, blocco **Parole da ascoltare**:
   - elenco di ogni testo estratto da quella frase (ordine di apparizione, senza duplicati visivi se identici);
   - accanto, pulsante Play che usa l’allegato;
   - se manca l’audio: stato “manca” (non nascondere la riga).

Serve per controllare che lo script abbia preso le cose giuste e che gli MP3 ci siano.

---

## Script da lanciare (quando esiste)

Lavoro in **locale** sul database `localloverewrite202608`. ID storia o slug.

```powershell
php database/tag-notes-listen-words.php STORY_ID [INDICE|all]
php wp-content/plugins/llm-con-tabelle/scripts/script-genera-audio-parole-appunti.php --story=STORY_ID
```

`INDICE` è 0-based. `all` = tutte le frasi.

Ordine:

1. Leggere `phrase_grammar` di ogni frase.
2. Estrarre e wrappare (senza cambiare il senso del testo).
3. Salvare l’HTML aggiornato.
4. Generare gli MP3 mancanti.
5. Mostrare un riepilogo: quante parole/frasi, quanti audio nuovi, eventuali salti.

Finché gli script non esistono, **non improvvisare** un dump SQL a mano: prima si implementano gli script, poi si lanciano sulla storia chiesta.

---

## Comandi di lettura (controllo)

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root localloverewrite202608 -e "SELECT ID, post_title, post_name FROM wp_posts WHERE post_name = 'SLUG-QUI' AND post_type = 'llm_story';"
```

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT id, sort_order, phrase_target, phrase_grammar FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC, id ASC LIMIT 3;"
```

Lingue storia: meta `_llm_target_lang` e `_llm_known_lang`.

---

## Fuori da questo prompt

- Non rigenerare gli appunti (non è `genera-appunti-frasi.md`).
- Non riformattare paragrafi/grassetto (non è `formattazione-appunti-tag-html.md`), se non per inserire gli span.
- Non committare e non caricare online finché l’utente non lo chiede.
- Non toccare `wp-config.php`.
