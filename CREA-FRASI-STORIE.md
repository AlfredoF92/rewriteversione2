# Crea frasi storie (bozza in chat)

Quando l’utente chiede di **inventare / scrivere le frasi** di una storia (prima di caricarla in WordPress), lavora **solo in chat**. Non creare ancora la storia nel database e **non** eseguire `genera-appunti-frasi.md`, salvo richiesta esplicita.

Questo file è lo **step 0**. Poi:

1. l’utente conferma o corregge le frasi
2. quando dice di creare la storia → `crea-storia-da-frasi.md`
3. quando dice di generare gli appunti → `genera-appunti-frasi.md`

---

## Cosa chiedere / cosa serve

- **Coppia linguistica**: lingua nota (interfaccia) + lingua da imparare  
  Esempio: «Italiano → inglese» = noto `it`, obiettivo `en`
- **Livello CEFR** delle frasi obiettivo (se non detto: stima, di solito A2)
- **Argomento**: persona, luogo, opera, aneddoto, ecc.
- **Quante frasi** (default 20, se non specificato)
- Input facoltativi: titolo, categoria extra, coin, copertina

---

## Come scrivere le frasi (in chat)

Scrivi **prima in chat**, in questo ordine:

### 1. Scheda breve

- Titolo in lingua nota
- Titolo in lingua obiettivo
- Livello
- Coppia
- Eventuale nota (es. «la foto è un’altra opera: usiamo X»)

### 2. Trama, introduzione, finale

Tutti in **lingua nota**, tono amichevole.

| Campo | Lunghezza | Ruolo |
|---|---|---|
| **Trama** | 40–80 parole | Riassunto narrativo (scheda + hero) |
| **Introduzione** | 2–4 frasi | Panoramica prima del gioco; invita a imparare |
| **Finale** | 2–4 frasi | Dopo l’ultima frase: complimenti + cosa resta |

Usa intro e finale per **contesto extra** che non sta nelle 20 frasi corte (date, museo, confronto, perché conta).

### 3. Le N frasi

Per ogni riga:

1. **Frase obiettivo** — corta, una sola idea, livello richiesto (es. inglese A2: present/past simple, parole comuni, niente subordinate lunghe)
2. **Frase interfaccia** — traduzione naturale in lingua nota (non parola-per-parola se suona male)
3. **Primo rigo appunti** — **una sola riga** di approfondimento (curiosità, contesto, differenza utile). Andrà come **prima riga** dell’analisi grammaticale quando si esegue `genera-appunti-frasi.md`. Non è ancora l’analisi completa.

Formato in chat:

```
1. EN: …
   IT: …
   Appunti (1° rigo): …
```

Regole:

- Non unire due idee in una frase
- Non saltare numeri
- Non ripetere la stessa informazione in tre frasi di fila
- Le frasi raccontano la storia; trama / intro / finale danno la panoramica
- Se l’utente manda un’immagine, verifica che sia **davvero** l’opera richiesta prima di scrivere

---

## Cosa non fare in questo step

- Non creare il post `llm_story`
- Non scrivere le 220+ parole di analisi grammaticale
- Non eseguire SQL / PHP di insert
- Non caricare online
- Non aprire `genera-appunti-frasi.md` finché l’utente non lo chiede

---

## Dopo la conferma in chat

| L’utente dice | File da seguire |
|---|---|
| «crea la storia» / «caricala in bozza» | `crea-storia-da-frasi.md` |
| «genera appunti» / «usa genera-appunti» | `genera-appunti-frasi.md` — il **primo rigo** di ogni `phrase_grammar` è l’approfondimento già scritto qui; poi il resto dell’analisi |

In `crea-storia-da-frasi.md` le frasi obiettivo vanno in `phrase_target`, quelle note in `phrase_interface`.
