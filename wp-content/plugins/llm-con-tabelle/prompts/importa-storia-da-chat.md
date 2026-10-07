# Script — Importa storia da chat

**Campo checklist:** `import_chat`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** prompt LLM + creazione bozza locale

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) quando l'utente in chat chiede di **caricare** o **importare** una storia.

Copia anche in: `wp-content/plugins/llm-con-tabelle/prompts/importa-storia-da-chat.md`

---

# Importa storia da chat

Quando l’utente dice una di queste cose (o equivalente) e **incolla** titolo / trama / intro / finale / frasi:

- «carica questa storia»
- «importa questa storia»
- «importa storia da chat»
- «crea la storia da questo testo»
- «caricala tu»

segui **questa** procedura. Non usare `Crea storia da documento.md` (quello **genera** una storia da una lezione di grammatica). Non usare solo `script_phrases.md` (quello non crea il post).

Dettaglio campi scheda WordPress: `crea-storia-da-frasi.md` (step 2).  
Dopo questo import **non** lanciare da soli appunti / pronuncia / audio. Quelli restano i passi successivi di `TODO-STORIA.md`.

---

## Cosa stai creando

Una storia Lovrite in **bozza** (`draft`):

- un post `llm_story`
- i testi di cornice (titoli, plot, intro, finale, card, CEFR, 10 topic, coin, categoria coppia)
- le frasi in `wp_llm_story_phrases`

Per ogni riga di gioco, in questo passo riempi solo:

- `phrase_target` — lingua da imparare
- `phrase_interface` — lingua nota
- `phrase_notes` — scena in lingua nota (se c’è nel testo, o la completi)
- `phrase_notes_target` — stessa scena in lingua obiettivo (se c’è nel testo, o la completi)

Lascia vuoti: `phrase_grammar`, `phrase_alt`, `phrase_remember`, `phrase_pronunciation`, `phrase_ipa`, `phrase_approx`, audio, copertina, crucintarsio.

Non pubblicare. Non fare commit / push / FTP.

---

## Regola d’oro: una riga = domanda + risposta

Se il materiale ha, per ogni numero, una **domanda** e una **risposta**, sono **una sola frase di gioco**, non due.

Uniscile nello stesso campo, nello stesso ordine, separate da uno spazio.

Esempio (known `it` → target `en`):

```
phrase_target:     Where is my family? My family is on another plane.
phrase_interface:  Dov'è la mia famiglia? La mia famiglia è su un altro aereo.
phrase_notes:      Quando l'aereo atterra, Kevin cerca la sua famiglia tra i passeggeri. Solo in quel momento scopre di essere arrivato a New York senza di loro.
phrase_notes_target: When the plane lands, Kevin looks for his family among the passengers. Only then does he discover that he has arrived in New York without them.
```

- Il paragrafo di scena (dopo domanda e risposta) **non** entra in `phrase_target` / `phrase_interface`. Va in `phrase_notes` / `phrase_notes_target`.
- Non spezzare domanda e risposta in due `sort_order`.
- Non mettere la risposta solo nelle note.
- Non unire due numeri diversi in una riga.
- Non inventare frasi extra e non saltare numeri.

Se un blocco è solo affermazione (niente domanda), quella affermazione è la frase intera.

---

## 1. Leggi il testo e mappa i pezzi

Il testo in chat è spesso disordinato. Cerca, anche con etichette diverse:

| Nel testo utente | Campo |
|---|---|
| Titolo in lingua obiettivo (spesso con bandiera / nome film) | `_llm_title_target_lang` (H1) |
| Titolo o sottotitolo in lingua nota | `post_title` (sottotitolo hero) |
| Sottotitolo tipo «20 frasi per…» | `post_excerpt` e `_llm_story_card_text` |
| Introduzione / Intro | `_llm_story_intro` |
| Finale | `_llm_story_finale` |
| Trama / Plot | `_llm_story_plot` |
| Open questions, Closed questions, elenchi grammaticali | base per `_llm_story_grammar_topics` |
| Blocchi numerati 1, 2, 3… con EN/IT (o altre lingue) | frasi |

Lingue (`it` \| `en` \| `pl` \| `es`):

- Se ci sono etichette (`ENGLISH` / `ITALIANO`, bandiere, `EN:` / `IT:`), usale.
- Se l’utente dice «conosco X, voglio imparare Y», quello è known → target.
- Se non è chiaro: lingua delle note/trama = nota; lingua delle domande da esercitare = obiettivo.

Categoria coppia: slug da `LLM_Magazine::pair_category_slugs()`. Esempi: it→en = `it-english`; en→it = `en-italian`; pl→it = `pl-wloski`.

### Come leggere un blocco numerato tipico

```
3
ENGLISH
Does this bus go to Manhattan?
Yes, it does.
Kevin decides not to panic. ...
ITALIANO
Questo autobus va a Manhattan?
Sì.
Kevin decide di non farsi prendere dal panico. ...
```

- Prime due battute sotto la lingua obiettivo → `phrase_target` (domanda + risposta).
- Prime due battute sotto la lingua nota → `phrase_interface` (domanda + risposta).
- Paragrafo dopo le battute, lingua nota → `phrase_notes`.
- Paragrafo dopo le battute, lingua obiettivo → `phrase_notes_target`.
- «Yes, it does.» / «Sì.» fanno parte della **frase**, non delle note.

Se sotto una lingua c’è una sola riga, quella è tutta la frase.

---

## 2. Completa i campi mancanti della scheda

Tutto ciò che l’utente ha già scritto, **non riscriverlo**: copialo, al massimo accorcia se è enorme.

Se manca, completalo tu in **lingua nota**, tono amichevole, coerente con le frasi:

| Campo | Default se assente |
|---|---|
| `post_title` | Traduci il titolo obiettivo in lingua nota |
| `_llm_title_target_lang` | Usa il titolo più «film» / lingua da imparare |
| `post_name` (slug) | Da titolo obiettivo, minuscolo, trattini |
| `post_excerpt` / `_llm_story_card_text` | 1–2 frasi da trama o sottotitolo |
| `_llm_story_plot` | 40–80 parole, senza spoilerare il finale se puoi |
| `_llm_story_intro` | 2–4 frasi, invita a imparare |
| `_llm_story_finale` | 2–4 frasi, chiusura + cosa ha imparato |
| `_llm_story_cefr_level` | Stima dal vocabolario (A1–C2) |
| `_llm_story_grammar_topics` | Esattamente **10** punti. Per ciascuno: riga `1. Titolo` + riga descrizione. Parole della lingua obiettivo tra virgolette; traduzione nota tra parentesi. Parti dai topic già nel testo (es. open / closed questions) e completa fino a 10 senza inventare strutture assenti dalle frasi. |
| `_llm_story_coin_cost` | **10** |
| `_llm_story_coin_reward` | **25** |
| `post_status` | **`draft`** |

Se le note di una frase mancano: scrivi 2–3 frasi di scena (niente grammatica, niente pronuncia, niente «in questa frase impariamo»). Vedi `script_notes.md` per il tono. Poi la versione in lingua obiettivo in `phrase_notes_target`.

Se domanda o risposta manca in una delle due lingue: traducila. Non lasciare un campo frase vuoto.

---

## 3. Crea la bozza in locale

DB: `localloverewrite202608`  
MySQL: `C:\xampp\mysql\bin\mysql.exe`  
PHP: `C:\xampp\php\php.exe`

Non usare CLI `wp-load.php` (si blocca). Preferisci **HTTP** one-shot in root WordPress (key-gated) oppure script mysqli. Per il post WordPress serve `wp_insert_post` + meta + `LLM_Story_Repository::save_phrases` (dettaglio in `crea-storia-da-frasi.md` step 2). Non inserire a mano le righe `wp_posts` in SQL. Non usare seed/demo.

Prima di scrivere: se Apache/MySQL sono spenti, avviali o dillo all’utente.

Controlla che non esista già la stessa storia (stesso slug o stesso titolo obiettivo). Se esiste, **fermati** e chiedi se aggiornare o creare un duplicato.

Dopo il salvataggio, verifica:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT ID, post_title, post_name, post_status FROM wp_posts WHERE ID = ID_QUI AND post_type = 'llm_story';"
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ID_QUI AND meta_key LIKE '_llm%' ORDER BY meta_key;"
& "C:\xampp\mysql\bin\mysql.exe" -u root --default-character-set=utf8mb4 localloverewrite202608 -e "SELECT sort_order, LEFT(phrase_interface,80) AS iface, LEFT(phrase_target,80) AS tgt, CHAR_LENGTH(phrase_notes) notes, CHAR_LENGTH(phrase_notes_target) notes_t FROM wp_llm_story_phrases WHERE story_id = ID_QUI ORDER BY sort_order ASC;"
```

Controlla:

- `post_status = draft`
- known/target non scambiati
- numero righe = numero blocchi dell’utente (domanda+risposta = 1)
- ogni `phrase_target` contiene sia domanda sia risposta quando il materiale le aveva entrambe
- `notes` e `notes_t` > 0 su tutte

File temporanei in `database/`; si possono cancellare dopo.

---

## 4. Rispondi all’utente

In chat, dopo l’import, riporta:

- ID locale
- slug
- permalink admin (`/wp-admin/post.php?post=ID&action=edit`)
- coppia known → target
- CEFR
- numero frasi (e ricorda: 20 blocchi = 20 frasi, non 40)
- cosa è pieno (testi + frasi + note)
- cosa è ancora vuoto (grammar, alt, Ricorda, pronuncia, audio, copertina)

Non andare avanti con `genera-appunti-frasi.md` o altri passi se l’utente non lo chiede.

---

## Cosa non fare

- Non pubblicare.
- Non spezzare domanda e risposta.
- Non generare analisi grammaticale lunga in questo passo.
- Non generare IPA / audio / crucintarsio / copertina.
- Non copiare le note dentro la frase di gioco.
- Non copiare domanda+risposta dentro le note.
- Non committare e non caricare online.
