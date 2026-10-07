# Script — Caricare gli appunti (slot)

**Campo checklist:** `load_appunti`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** procedura AI (import slot, non generazione testo)

Questo file è il punto di ingresso quando l’utente dice di **caricare / salvare / importare gli appunti** (o incolla un blocco `=== FRASE N ===` già pronto).

Per **scrivere** gli appunti da zero → `script_grammar.md` / `Prompt_Crea_Appunti.md`.  
Questo file serve solo a **metterli negli slot** senza cambiare le frasi.

---

## Obiettivo

Per ogni frase della storia, riempire fino a 10 slot:

| Campo DB | Contenuto |
|---|---|
| `grammar_title_1` … `grammar_title_10` | Titolo slot (una riga) |
| `grammar_body_1` … `grammar_body_10` | Corpo (HTML semplice con `<br />` tra le righe va bene) |
| `phrase_grammar` | HTML ricostruito automaticamente dagli slot |

API: `LLM_Story_Repository::update_phrase_grammar_slots( $story_id, $index_0based, $slots )`  
dove `$slots` è una lista di `{ title, body }` (max 10).

`update_phrase_rich_field( …, 'grammar', $html )` **non** è il percorso preferito: spezza l’HTML in slot in modo grezzo e può rovinare la struttura. Preferisci sempre gli slot strutturati.

---

## Input atteso (paste utente)

Testo plain, niente JSON obbligatorio. Blocchi:

```
=== FRASE 1 ===
EN: Where is everyone?
IT: Dove sono tutti?

"Where" → "Dove"
Where in Italian is "dove".
…
❌ …
💡 Deep dive:
…

"is" → "sono"
…

Full conjugation of the present tense of "essere":
io sono (I am)
…

Etymology fun fact
…
```

- `FRASE 1` → `sort_order` / indice **0** (1-based nel paste, 0-based nel DB).
- Le righe `EN:` / `IT:` (o altre sigle) sono solo intestazione: **non** diventano slot e **non** sovrascrivono le frasi.

---

## Come spezzare in slot

Nuovo slot quando la riga è:

1. Una coppia titolo: `"…" → "…"` (virgolette + freccia), **oppure**
2. Coniugazione: riga che inizia con `Full conjugation` / `Coniugazione` / equivalente in lingua nota, **oppure**
3. Etimologia: `Etymology fun fact` / `Etymology curiosity` / `Curiosità etimologia` / equivalente, **oppure**
4. Eventuale `Alternative translation` / `Remember` se presente come sezione propria.

Tutto ciò che segue fino al prossimo titolo = **body** di quello slot.  
Nel body puoi unire le righe con `<br />`.

### Deep dive (importante)

Se nel body compare `💡 Deep dive:` (o simile), le righe successive — anche se sembrano `"a" → "b"` — restano nel **corpo dello stesso slot** finché non c’è una **riga vuota** e poi un vero nuovo titolo di livello frase (altra coppia pezzo-frase, coniugazione, etimologia).

Non creare 10 slot falsi dalle liste di esempi dentro il Deep dive.

### Cap

Max **10** slot per frase. Se ce ne sono di più, tieni i più importanti (coppie pezzo-frase + coniugazione + etimologia) e scarta il resto in coda, oppure chiedi all’utente.

---

## Cosa NON fare

- Non riscrivere / sostituire `phrase_interface` o `phrase_target`.
- Non cancellare note, audio, alt, remember, pronuncia.
- Non salvare tutto come un unico blob HTML “bello” senza riempire le colonne slot: in admin e in checklist contano gli **slot**.
- Non dire “ok” senza verificare: dopo il salvataggio, per ogni frase, almeno 1 slot non vuoto (meglio: tutte le coppie + coniugazione + etimologia presenti nel paste).

---

## Flusso operativo consigliato (online)

1. Conferma `story_id` (o slug → ID).
2. Conta le frasi: devono già esserci (questo passo non le crea).
3. Parsa il paste → array `sort_order => [ {title, body}, … ]`.
4. Per ogni indice: `LLM_Story_Repository::update_phrase_grammar_slots( $story_id, $i, $slots )`.
5. Se lavori sul sito pubblico (non CLI locale): one-shot PHP in root WP protetto da chiave, FTP, HTTP GET, poi elimina lo script.
6. Verifica checklist: riga **Caricare gli appunti** e **Appunti grammaticali** devono mostrare slot N/N.

---

## Relazione con gli altri step

| Step | File | Ruolo |
|---|---|---|
| Appunti grammaticali | `script_grammar.md` | **Genera** il testo degli appunti |
| **Caricare gli appunti** | questo file | **Importa** il testo negli slot |
| Ricorda | `script_remember.md` | Campo `phrase_remember` separato |
| Note frase | `script_notes.md` | Note trama, non grammatica |

---

## Checklist di accettazione

- [ ] Nessuna frase EN/IT modificata
- [ ] Slot title/body popolati per ogni frase del paste
- [ ] Deep dive non ha creato slot fantasma
- [ ] Coniugazione ed etimologia sono slot propri dove presenti
- [ ] Checklist aggiornata (refresh meta / `LLM_Story_Checklist::refresh`)
