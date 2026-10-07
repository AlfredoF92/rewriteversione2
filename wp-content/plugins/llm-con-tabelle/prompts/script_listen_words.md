# Prompt per generare l'elenco parole (ascolto appunti)

**Campo checklist:** `listen_words` — Parole evidenziate (ascolto appunti)
**Cartella canonica:** `prompt/CREA STORIA/script_listen_words.md`
**Script audio:** `wp-content/plugins/llm-con-tabelle/scripts/script-genera-audio-parole-appunti.php`

Quando si chiede di generare le parole da ascoltare negli appunti, leggi questo file e segui queste regole. Non inventarne altre.

Per ogni slot di appunti (un paragrafo / un `grammar_body`) prendi, di solito:

- sempre **2 esempi**
- dalle **2 alle 10 parole** del riquadro Approfondimento
- **tutta** la coniugazione, se lo slot è una tabella di persone

Poi genera l'audio solo dopo che l'elenco della prima frase è stato approvato.

---

## Cosa prendere

Lavora sul testo degli appunti grammaticali (`phrase_grammar`, un paragrafo per slot). La lingua da ascoltare è la lingua obiettivo della storia (`_llm_target_lang`).

### 1. Coniugazione — tutte le persone

Ogni riga che inizia con il pronome e ha la forma subito prima della parentesi. Maiuscole e minuscole valgono uguale.

Inglese: `I`, `You`, `He/She/It`, `We`, `They` (anche `he/she/it`, con o senza spazi intorno alle barre).

Esempio di slot:

```
I need (io ho bisogno)
you need (tu hai bisogno)
he/she/it needs (lui/lei/esso ha bisogno)
we need (noi abbiamo bisogno)
you need (voi avete bisogno)
they need (loro hanno bisogno)
```

Si ascolta `I need`, `you need`, `he/she/it needs`, `we need`, `they need`. Il secondo `you need` (voi) usa lo stesso audio, ma in pagina l'icona sta su **entrambe** le righe.

Una coniugazione è solo pronome + una parola (`you need`, `He/She/It lives`). Una frase intera che inizia con `I` o `We` (`We need to buy milk.`) **non** è una coniugazione: è un esempio.

Stesse tabelle per italiano (`io tu lui/lei noi voi loro`), polacco (`ja ty on/ona/ono my wy oni/one`) e spagnolo (`yo tú él/ella/usted …`).

### 2. Esempi — i due dello slot

Su ogni riga `Esempio:` o `💬 Esempio` prendi **tutta** la citazione tra virgolette, così com'è, fino a 14 parole. Di solito sono due righe per slot. Non spezzare la frase in parole singole.

```
Esempio: "We need to buy milk." (Dobbiamo comprare il latte.)
Esempio: "I need to call my mum." (Devo chiamare mia mamma.)
```

Si ascolta `We need to buy milk.` e `I need to call my mum.`. Il testo tra parentesi è la traduzione: non si ascolta.

### 3. Approfondimento — tutte le parole del riquadro

Il riquadro può iniziare con `📌 Approfondimento`, `💡 Approfondimento` o `Deep dive`. Vale anche la riga `Approfondimento:` senza emoji.

Dentro quel riquadro, fino alla fine dello slot, prendi **ogni** citazione in lingua obiettivo, anche se è corta: `my`, `your`, `Then`, `Firstly`, `isn't`, `I'm`, `to be`. Sono dalle 2 alle 10 per slot.

```
📌 Approfondimento:
Tutti i possessivi inglesi: "my" (mio), "your" (tuo), "his" (suo, di lui), "her" (suo, di lei), "its" (suo, di una cosa), "our" (nostro), "your" (vostro), "their" (loro).
```

Si ascoltano `my`, `your`, `his`, `her`, `its`, `our`, `their`. `your` compare due volte (tuo e vostro): un solo audio, icona su **entrambe**.

Non prendere la parola della lingua nota introdotta da `dire`, `significa`, `si traduce` o `si dice`. In `modi per dire "dovere": "must"` si ascolta `must`, non `dovere`.

---

## Cosa non prendere

- Il corpo della spiegazione, prima di `Esempio:` e prima di Approfondimento. Le virgolette lì (`"First" significa "primo"`) non si ascoltano.
- I titoli `"nota" → "obiettivo"`.
- Le righe con ❌ o ➡️, e le correzioni `Non dire` / `Evita` / `È un errore`, anche se contengono la forma giusta tra virgolette.
- Curiosità, etimologia, Ricorda, Remember, e i paragrafi che iniziano con 📜.
- Le glosse italiane tra parentesi.
- Le righe di Approfondimento che dicono `si usano con` o `funzionano come`.
- `Nos complace` e `Les complace`.

Non scartare una parola corta solo perché compare anche dentro un esempio più lungo. `need to` nell'Approfondimento si tiene anche se esiste `We need to buy milk.`

---

## Icone in pagina

L'icona play sta solo su:

- ogni riga di coniugazione, subito prima della parentesi
- ogni citazione di `Esempio:`
- ogni citazione dentro Approfondimento, ripetizioni comprese

In pagina le virgolette a volte restano `"…"`, a volte diventano corsivo. L'icona va sul testo in entrambi i casi. Non metterla sul gloss italiano.

---

## Audio

Due voci Azure per ogni testo distinto: femminile (`audio_id`) e maschile (`audio_male_id`). Una riga con due voci conta come **un** audio nell'elenco. Lo stesso testo, anche con maiuscole diverse, non si duplica.

`he/she/it` si pronuncia senza barre: `he she it`.

Ordine di lavoro:

1. Solo la **prima frase**. Estrai l'elenco e fermati.
2. Dopo l'ok, genera gli MP3 di quella frase.
3. Solo dopo un altro ok, fai le frasi successive.

```
C:\xampp\php\php.exe "C:\xampp\htdocs\localloverewrite202608\wp-content\plugins\llm-con-tabelle\scripts\script-genera-audio-parole-appunti.php" --story=ID --index=0 --extract-only
C:\xampp\php\php.exe "C:\xampp\htdocs\localloverewrite202608\wp-content\plugins\llm-con-tabelle\scripts\script-genera-audio-parole-appunti.php" --story=ID --index=0 --audio-only
```

`--index` parte da 0. Senza `--index` il comando copre tutte le frasi: non usarlo finché la prima non è approvata.

Locale e sito online hanno database separati. La storia si riconosce dallo slug, la frase da `sort_order`, non dall'ID locale.

Non rigenerare gli appunti, non pubblicare una storia programmata, non caricare online senza richiesta.
