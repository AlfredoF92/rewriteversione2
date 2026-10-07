<?php
/**
 * Confronto modelli — storia 4001.
 * Claude Opus 5 / Gemini 3.1 Pro / Claude Opus 4.8.
 * Frase sort_order=1 rigenerata (solo anteprima confronto).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

return array (
  'meta' => 
  array (
    'generated_at' => '2026-09-16T14:21:50+02:00',
    'story_live_id' => 4001,
    'story_local_id' => 3767,
    'phrases' => '0-1 (UI); gemini/claude data may still hold 0-2',
    'prompt_base' => 'controllo-qualita.md + database/_tmp_compare_prompt_template.md',
    'real_models' => true,
    'note' => 'outputs[1] (Buongiorno…) rigenerati con QC aggiornato; DB storia non toccato.',
  ),
  'models' => 
  array (
    'opus5' => 
    array (
      'label' => 'Claude Opus 5',
      'slug' => 'claude-opus-5-thinking-high',
      'provider' => 'Anthropic (via Cursor)',
      'notes' => 'Generato in Cursor Agent con Claude Opus 5 (thinking high). Sostituisce Grok su questa storia.',
      'prompt' => '# Confronto modelli — riscrittura phrase_grammar (SOLO frase sort_order=1)

Segui ESATTAMENTE le regole in:
`c:\\xampp\\htdocs\\localloverewrite202608\\controllo-qualita.md`
(file aggiornato: meccanismo con esempi concreti, vai a capo dopo il punto, niente parola "forma" grezza per i verbi, ❌ con "Evita di usare"/"È un errore dire…", emoji in `<p>` separati, ecc.)

## Contesto storia
- Titoli: Le prime 50 parole / Pierwsze 50 słów
- Live ID: 4001 · Locale ID: 3767
- Livello: A1
- Lingua nota (`_llm_known_lang`): **it** — tutta la prosa in italiano
- Lingua target (`_llm_target_lang`): **pl**
- Riassunto: Cinquanta frasi semplici. Due animali che non si sono mai visti. Una notte per conoscersi.
- Frasi vicine (solo contesto):
  - 0 Ciao! → Cześć!
  - **1 Buongiorno, come stai? → Dzień dobry, jak się masz?** ← SOLO QUESTA
  - 2 Sto bene, grazie. → Dobrze, dziękuję.

## Cosa fare
Riscrivi SOLO `phrase_grammar` della frase sort_order **1**.
NON cambiare: titoli `<strong>`, elenchi di coniugazione, parole PL tra `"<em>…</em>"`, blocco Ricorda (IDENTICO).
NON toccare: phrase_alt, phrase_remember, pronuncia.
NON aggiungere/togliere/invertire paragrafi strutturali rispetto allo scheletro.

## Output
Scrivi JSON UTF-8 (caratteri reali, NON `\\uXXXX`, tag HTML reali) SOLO con chiave `"1"`:

`C:/xampp/htdocs/localloverewrite202608/database/_tmp_out_4001_p1_opus5.json`

```json
{
  "1": "<p>...</p>"
}
```

## Frase da riscrivere (JSON)
`[
    {
        "sort_order": "1",
        "phrase_interface": "Buongiorno, come stai?",
        "phrase_target": "Dzień dobry, jak się masz?",
        "phrase_grammar": "<p><strong>\\"Buongiorno\\" → \\"<em>Dzień dobry</em>\\" </strong><br />💡 In italiano uniamo \\"buon\\" e \\"giorno\\" in una sola parola. In polacco rimangono due parole separate: \\"<em>dzień</em>\\" (giorno) e \\"<em>dobry</em>\\" (buono). È il saluto perfetto e formale per i negozi e le persone che non conosci.</p>\\r\\n<p>💬 Esempio: Entri in panetteria e dici alla commessa: \\"<em>Dzień dobry!</em>\\" (Buongiorno!).<br />💬 Esempio: Incontri il tuo vicino di casa anziano: \\"<em>Dzień dobry, panie Tomaszu</em>\\" (Buongiorno, signor Tomasz).</p>\\r\\n<p>⚠️ ❌ \\"<em>Dzień dobra</em>\\". Questo è un errore di distrazione! Visto che \\"<em>dzień</em>\\" (giorno) è una parola maschile, anche l\'aggettivo \\"buono\\" deve essere al maschile, e finisce con la y: \\"<em>dobry</em>\\".<br />✅ \\"<em>Dzień dobry</em>\\".</p>\\r\\n<p>📌 In breve: con gli estranei e nei negozi usa sempre \\"<em>dzień dobry</em>\\" (giorno buono).</p>\\r\\n<p><strong>\\"come stai\\" → \\"<em>jak się masz</em>\\" </strong><br />💡 Qui c\'è una grossa differenza con l\'italiano! Noi usiamo il verbo \\"stare\\", mentre i polacchi usano il verbo \\"avere\\" (<em>mieć</em>) insieme alla parolina riflessiva \\"<em>się</em>\\". Letteralmente stanno chiedendo: \\"Come hai te stesso?\\". Il verbo \\"<em>masz</em>\\" significa \\"tu hai\\".</p>\\r\\n<p>💬 Esempio: Incontri un tuo amico e gli chiedi: \\"<em>Jak się masz dzisiaj?</em>\\" (Come stai oggi?).<br />💬 Esempio: Per rispondere potresti dire: \\"<em>Mam się dobrze</em>\\" (Sto bene - letteralmente: mi ho bene).</p>\\r\\n<p>⚠️ ❌ \\"<em>Jak jesteś?</em>\\". È l\'errore più comune per gli italiani e gli inglesi, perché traducono \\"Come sei? / How are you?\\". Ma in polacco non ha alcun senso per chiedere della salute.<br />✅ \\"<em>Jak się masz?</em>\\". Usa sempre il verbo avere!</p>\\r\\n<p>📌 In breve: per chiedere come sta un amico pensa a \\"come ti hai?\\": <em>jak</em> (come) + <em>się</em> (ti) + <em>masz</em> (hai).</p>\\r\\n<p><strong>Coniugazione completa del presente di \\"mieć\\":</strong> <br />ja mam (io ho)<br />ty masz (tu hai)<br />on/ona/ono ma (lui/lei ha)<br />my mamy (noi abbiamo)<br />wy macie (voi avete)<br />oni/one mają (loro hanno).</p>\\r\\n<p><strong>Ricorda:</strong> il saluto è nome più aggettivo. La domanda di salute è \\"<em>mieć</em>\\" più \\"<em>się</em>\\", sempre insieme.</p>\\r\\n<p>📜 <strong>Curiosità etimologia:</strong><br />La parola \\"<em>dzień</em>\\" è antichissima e deriva dalla radice indoeuropea per \\"luce del giorno\\" o \\"cielo luminoso\\". È esattamente la stessa radice da cui deriva la parola latina \\"<em>dies</em>\\", che in italiano è diventata \\"dì\\" (come in lune-dì, marte-dì)! E il verbo \\"<em>mieć</em>\\" (avere) in origine voleva dire \\"tenere qualcosa stretto nelle mani\\".</p>",
        "phrase_alt": "<p>Una traduzione alternativa di \\"<em>Dzień dobry, jak się masz?</em>\\"<br />potrebbe essere \\"<em>Dzień dobry, jak leci?</em>\\"<br />In italiano è \\"Buongiorno, come va?\\"<br />\\"<em>Jak leci</em>\\" è più da amici. \\"<em>Jak się masz</em>\\" è la domanda intera, con \\"<em>się</em>\\".</p>",
        "phrase_remember": "<p>Dzień dobry, jak się masz?</p><p><strong>Ricorda:</strong></p><ul><li><strong>\\"Buongiorno\\" → \\"Dzień dobry\\"</strong>: saluto <em>formale</em> del giorno.</li><li><strong>\\"Come stai\\" → \\"jak się masz\\"</strong>: non togliere \\"<strong>się</strong>\\".</li><li>\\"<strong>Masz</strong>\\" è la forma «tu» di \\"<strong>mieć</strong>\\".</li></ul>"
    }
]`

## Nota modello
Variante confronto: Claude Opus 5 (`claude-opus-5-thinking-high`). Solo JSON nel file. Solo chiave `"1"`.
Questo output serve SOLO alla pagina wp-admin Confronto modelli: NON applicare al DB.',
      'outputs' => 
      array (
        0 => '<p><strong>"Ciao" → "<em>Cześć</em>" </strong><br />💡 "<em>Cześć</em>" è il "ciao" dei polacchi: lo usi con gli amici, con i cugini, con i compagni di classe.<br />La cosa comoda è che serve <strong>due volte</strong>, quando arrivi e quando vai via, proprio come il nostro "ciao".<br />La regola vera riguarda <strong>chi hai davanti</strong>: se è uno della tua età, o una persona che conosci bene, va benissimo. Se è un adulto che non conosci, no.<br />Non è un verbo, quindi non cambia mai: nessuna desinenza da aggiungere, nessun plurale.</p>
<p>💬 Esempio: incontri il tuo amico Marek davanti a scuola: "<em>Cześć, Marek!</em>" (Ciao, Marek!).<br />💬 Esempio: entri in una stanza piena di amici: "<em>Cześć wszystkim!</em>" (Ciao a tutti!).<br />💬 Esempio: è tardi, prendi lo zaino e vai via: "<em>Cześć, do jutra!</em>" (Ciao, a domani!).<br />💬 Esempio: rispondi al messaggio di una compagna di classe: "<em>Cześć! Co robisz?</em>" (Ciao! Che fai?).</p>
<p>⚠️ ❌ "<em>Cześć</em>" detto al professore, al dottore o a una signora anziana che non conosci. Non è corretto dire così: è un saluto tra amici e suona troppo diretto.<br />✅ Con gli adulti che non conosci usa sempre "<em>Dzień dobry</em>".</p>
<p>⚠️ ❌ Cambiare la fine della parola quando saluti più persone, come se fosse un verbo. Non usare mai forme inventate.<br />✅ "<em>Cześć</em>" resta identico per un amico o per venti amici, e se vuoi aggiungi "<em>wszystkim</em>" (a tutti).</p>
<p>⚠️ ❌ Cercare un\'altra parola per il momento in cui te ne vai. È un errore dire che serve solo per arrivare.<br />✅ "<em>Cześć</em>" va bene anche come saluto finale tra amici; con chi non conosci, per andare via, si dice "<em>Do widzenia</em>".</p>
<p>📌 In breve: usa "<em>cześć</em>" come il nostro "ciao" informale, sia quando arrivi sia quando te ne vai, e passa a "<em>dzień dobry</em>" con gli adulti che non conosci.</p>
<p><strong>Ricorda:</strong> "<em>cześć</em>" non si piega. Se la situazione è formale, cambia saluto, non la desinenza.</p>
<p>📜 <strong>Curiosità etimologia:</strong><br />Oggi "<em>cześć</em>" è solo un saluto veloce, ma un tempo era una parola pesante. Viene dall\'antico slavo "<em>čьstь</em>", che voleva dire <strong>"onore"</strong>, <strong>"rispetto"</strong>.<br />Quel significato in polacco non è mai sparito: ancora oggi "<em>cześć</em>" vuol dire anche "onore", e "<em>na cześć</em>" vuol dire "in onore di". Salutare così, all\'inizio, era come dire "onore a te".<br />Andando ancora più indietro si arriva a una radice antichissima che significava "notare", "fare attenzione": onorare qualcuno era, in fondo, accorgersi di lui. Dalla stessa radice nasce anche il verbo polacco "<em>czytać</em>" (leggere), perché leggere è guardare i segni con attenzione.<br />Il russo "честь" (onore) è la stessa parola: si vede che è roba di famiglia slava, vecchia più di mille anni. Il nostro "onore", invece, arriva dal latino "<em>honor</em>": due parole che dicono la stessa cosa, ma arrivate da strade diverse.</p>',
        1 => '<p><strong>"Buongiorno" → "<em>Dzień dobry</em>" </strong><br />💡 In italiano "buongiorno" è una parola sola, perché uniamo "buon" + "giorno".<br />In polacco invece restano due parole separate: "<em>dzień</em>" significa "giorno" e "<em>dobry</em>" significa "buono".<br />L\'ordine è al contrario rispetto al nostro: prima il nome, poi l\'aggettivo, quindi "giorno buono".<br />È il saluto <strong>formale</strong>: lo usi nei negozi, dal medico, con gli sconosciuti e con le persone più grandi di te.<br />Se entri in un bar alle nove del mattino dici "<em>Dzień dobry</em>" e vai sempre sul sicuro.</p>
<p>💬 Esempio: Entri in panetteria e dici alla commessa: "<em>Dzień dobry!</em>" (Buongiorno!).<br />💬 Esempio: Incontri il tuo vicino di casa anziano sulle scale: "<em>Dzień dobry, panie Tomaszu</em>" (Buongiorno, signor Tomasz).<br />💬 Esempio: Arrivi in ufficio e saluti i colleghi: "<em>Dzień dobry państwu</em>" (Buongiorno a tutti voi).</p>
<p>⚠️ ❌ "<em>Dzień dobra</em>". Evita di usare "<em>dobra</em>" in questo saluto.<br />"<em>Dzień</em>" (giorno) è una parola <strong>maschile</strong>, quindi anche l\'aggettivo "buono" deve essere maschile e finisce in -y: "<em>dobry</em>".<br />✅ "<em>Dzień dobry</em>".<br />❌ "<em>Dzień dobry</em>" alle dieci di sera. È un errore dire "<em>dzień dobry</em>" quando è già buio.<br />La sera si cambia saluto e si dice "<em>dobry wieczór</em>" (buonasera).<br />✅ "<em>Dobry wieczór</em>".</p>
<p>📌 In breve: con gli estranei, nei negozi e al lavoro di giorno usa sempre "<em>dzień dobry</em>", cioè "giorno buono".</p>
<p><strong>"come stai" → "<em>jak się masz</em>" </strong><br />💡 Qui italiano e polacco ragionano in modo diverso.<br />Noi diciamo "come stai?" con il verbo "stare", i polacchi usano il verbo "<em>mieć</em>", che significa "avere", insieme alla parolina "<em>się</em>" (te stesso).<br />Parola per parola stanno chiedendo: "come hai te stesso?".<br />"<em>Masz</em>" corrisponde all\'italiano "hai": è la <strong>seconda persona singolare</strong> del verbo "<em>mieć</em>" ("avere") al presente semplice, quindi "tu hai" → "<em>ty masz</em>".<br />Il presente semplice serve per parlare di adesso e delle abitudini, come il nostro presente indicativo.<br />"<em>Się</em>" da sola non si traduce e non si toglie mai: sta attaccata al verbo, un po\' come il "si" di "si chiama".</p>
<p>💬 Esempio: Incontri un amico al bar e gli chiedi: "<em>Jak się masz dzisiaj?</em>" (Come stai oggi?).<br />💬 Esempio: Rispondi a chi te lo ha chiesto: "<em>Mam się dobrze, dziękuję</em>" (Sto bene, grazie).<br />💬 Esempio: Chiedi notizie della mamma di un amico: "<em>Jak się ma twoja mama?</em>" (Come sta tua mamma?), dove "<em>ma</em>" è la terza persona singolare, cioè "lui/lei ha".</p>
<p>⚠️ ❌ "<em>Jak jesteś?</em>". È un errore dire così, ed è il più comune per chi parla italiano o inglese, perché traduce "come sei?" oppure "how are you?".<br />In polacco "<em>jesteś</em>" vuol dire "tu sei" e racconta com\'è fatta una persona, non come si sente.<br />✅ "<em>Jak się masz?</em>".<br />❌ "<em>Jak masz?</em>". Non usare mai questa domanda senza "<em>się</em>": la frase resta a metà e chi ti ascolta non capisce.<br />✅ "<em>Jak się masz?</em>", sempre con il verbo avere + "<em>się</em>".</p>
<p>📌 In breve: per chiedere come sta qualcuno pensa a "come ti hai?": "<em>jak</em>" (come) + "<em>się</em>" (ti) + "<em>masz</em>" (hai).</p>
<p><strong>Coniugazione completa del presente di "mieć":</strong> <br />ja mam (io ho)<br />ty masz (tu hai)<br />on/ona/ono ma (lui/lei ha)<br />my mamy (noi abbiamo)<br />wy macie (voi avete)<br />oni/one mają (loro hanno).</p>
<p><strong>Ricorda:</strong> il saluto è nome più aggettivo. La domanda di salute è "<em>mieć</em>" più "<em>się</em>", sempre insieme.</p>
<p>📜 <strong>Curiosità etimologia:</strong><br />La parola "<em>dzień</em>" viene dall\'antico slavo "<em>dьnь</em>" e prima ancora da una radice indoeuropea che voleva dire "luce del giorno", "cielo che brilla".<br />È la stessa radice del latino "<em>dies</em>", che in italiano è diventato "dì": lo senti ancora dentro lune-dì, marte-dì, mercole-dì.<br />Quindi, quando dici "<em>dzień dobry</em>", stai usando una parola cugina del nostro "dì", nata migliaia di anni fa.<br />Anche "<em>mieć</em>" ha una storia curiosa: all\'inizio non voleva dire "avere", ma "prendere", "tenere stretto in mano".<br />Dal tenere in mano è passato al possedere, ed è per questo che oggi i polacchi, per chiedere come stai, ti chiedono se "tieni bene te stesso".</p>',
      ),
      'usage' => 
      array (
        'prompt_chars' => 5279,
        'output_chars' => 7550,
        'tokens_in_est' => 1320,
        'tokens_out_est' => 1888,
        'tokens_total_est' => 3208,
        'method' => 'chars/4 local estimate (Cursor subagent tokens not exposed)',
        'note' => 'outputs[1] rigenerato con controllo-qualita.md aggiornato',
      ),
    ),
    'gemini' => 
    array (
      'label' => 'Gemini 3.1 Pro',
      'slug' => 'gemini-3.1-pro',
      'provider' => 'Google (via Cursor)',
      'notes' => 'Generato in Cursor Agent con Gemini 3.1 Pro. Stesso prompt di controllo qualità delle altre colonne.',
      'prompt' => '# Confronto modelli — riscrittura phrase_grammar (SOLO frase sort_order=1)

Segui ESATTAMENTE le regole in:
`c:\\xampp\\htdocs\\localloverewrite202608\\controllo-qualita.md`
(file aggiornato: meccanismo con esempi concreti, vai a capo dopo il punto, niente parola "forma" grezza per i verbi, ❌ con "Evita di usare"/"È un errore dire…", emoji in `<p>` separati, ecc.)

## Contesto storia
- Titoli: Le prime 50 parole / Pierwsze 50 słów
- Live ID: 4001 · Locale ID: 3767
- Livello: A1
- Lingua nota (`_llm_known_lang`): **it** — tutta la prosa in italiano
- Lingua target (`_llm_target_lang`): **pl**
- Riassunto: Cinquanta frasi semplici. Due animali che non si sono mai visti. Una notte per conoscersi.
- Frasi vicine (solo contesto):
  - 0 Ciao! → Cześć!
  - **1 Buongiorno, come stai? → Dzień dobry, jak się masz?** ← SOLO QUESTA
  - 2 Sto bene, grazie. → Dobrze, dziękuję.

## Cosa fare
Riscrivi SOLO `phrase_grammar` della frase sort_order **1**.
NON cambiare: titoli `<strong>`, elenchi di coniugazione, parole PL tra `"<em>…</em>"`, blocco Ricorda (IDENTICO).
NON toccare: phrase_alt, phrase_remember, pronuncia.
NON aggiungere/togliere/invertire paragrafi strutturali rispetto allo scheletro.

## Output
Scrivi JSON UTF-8 (caratteri reali, NON `\\uXXXX`, tag HTML reali) SOLO con chiave `"1"`:

`C:/xampp/htdocs/localloverewrite202608/database/_tmp_out_4001_p1_gemini.json`

```json
{
  "1": "<p>...</p>"
}
```

## Frase da riscrivere (JSON)
`[
    {
        "sort_order": "1",
        "phrase_interface": "Buongiorno, come stai?",
        "phrase_target": "Dzień dobry, jak się masz?",
        "phrase_grammar": "<p><strong>\\"Buongiorno\\" → \\"<em>Dzień dobry</em>\\" </strong><br />💡 In italiano uniamo \\"buon\\" e \\"giorno\\" in una sola parola. In polacco rimangono due parole separate: \\"<em>dzień</em>\\" (giorno) e \\"<em>dobry</em>\\" (buono). È il saluto perfetto e formale per i negozi e le persone che non conosci.</p>\\r\\n<p>💬 Esempio: Entri in panetteria e dici alla commessa: \\"<em>Dzień dobry!</em>\\" (Buongiorno!).<br />💬 Esempio: Incontri il tuo vicino di casa anziano: \\"<em>Dzień dobry, panie Tomaszu</em>\\" (Buongiorno, signor Tomasz).</p>\\r\\n<p>⚠️ ❌ \\"<em>Dzień dobra</em>\\". Questo è un errore di distrazione! Visto che \\"<em>dzień</em>\\" (giorno) è una parola maschile, anche l\'aggettivo \\"buono\\" deve essere al maschile, e finisce con la y: \\"<em>dobry</em>\\".<br />✅ \\"<em>Dzień dobry</em>\\".</p>\\r\\n<p>📌 In breve: con gli estranei e nei negozi usa sempre \\"<em>dzień dobry</em>\\" (giorno buono).</p>\\r\\n<p><strong>\\"come stai\\" → \\"<em>jak się masz</em>\\" </strong><br />💡 Qui c\'è una grossa differenza con l\'italiano! Noi usiamo il verbo \\"stare\\", mentre i polacchi usano il verbo \\"avere\\" (<em>mieć</em>) insieme alla parolina riflessiva \\"<em>się</em>\\". Letteralmente stanno chiedendo: \\"Come hai te stesso?\\". Il verbo \\"<em>masz</em>\\" significa \\"tu hai\\".</p>\\r\\n<p>💬 Esempio: Incontri un tuo amico e gli chiedi: \\"<em>Jak się masz dzisiaj?</em>\\" (Come stai oggi?).<br />💬 Esempio: Per rispondere potresti dire: \\"<em>Mam się dobrze</em>\\" (Sto bene - letteralmente: mi ho bene).</p>\\r\\n<p>⚠️ ❌ \\"<em>Jak jesteś?</em>\\". È l\'errore più comune per gli italiani e gli inglesi, perché traducono \\"Come sei? / How are you?\\". Ma in polacco non ha alcun senso per chiedere della salute.<br />✅ \\"<em>Jak się masz?</em>\\". Usa sempre il verbo avere!</p>\\r\\n<p>📌 In breve: per chiedere come sta un amico pensa a \\"come ti hai?\\": <em>jak</em> (come) + <em>się</em> (ti) + <em>masz</em> (hai).</p>\\r\\n<p><strong>Coniugazione completa del presente di \\"mieć\\":</strong> <br />ja mam (io ho)<br />ty masz (tu hai)<br />on/ona/ono ma (lui/lei ha)<br />my mamy (noi abbiamo)<br />wy macie (voi avete)<br />oni/one mają (loro hanno).</p>\\r\\n<p><strong>Ricorda:</strong> il saluto è nome più aggettivo. La domanda di salute è \\"<em>mieć</em>\\" più \\"<em>się</em>\\", sempre insieme.</p>\\r\\n<p>📜 <strong>Curiosità etimologia:</strong><br />La parola \\"<em>dzień</em>\\" è antichissima e deriva dalla radice indoeuropea per \\"luce del giorno\\" o \\"cielo luminoso\\". È esattamente la stessa radice da cui deriva la parola latina \\"<em>dies</em>\\", che in italiano è diventata \\"dì\\" (come in lune-dì, marte-dì)! E il verbo \\"<em>mieć</em>\\" (avere) in origine voleva dire \\"tenere qualcosa stretto nelle mani\\".</p>",
        "phrase_alt": "<p>Una traduzione alternativa di \\"<em>Dzień dobry, jak się masz?</em>\\"<br />potrebbe essere \\"<em>Dzień dobry, jak leci?</em>\\"<br />In italiano è \\"Buongiorno, come va?\\"<br />\\"<em>Jak leci</em>\\" è più da amici. \\"<em>Jak się masz</em>\\" è la domanda intera, con \\"<em>się</em>\\".</p>",
        "phrase_remember": "<p>Dzień dobry, jak się masz?</p><p><strong>Ricorda:</strong></p><ul><li><strong>\\"Buongiorno\\" → \\"Dzień dobry\\"</strong>: saluto <em>formale</em> del giorno.</li><li><strong>\\"Come stai\\" → \\"jak się masz\\"</strong>: non togliere \\"<strong>się</strong>\\".</li><li>\\"<strong>Masz</strong>\\" è la forma «tu» di \\"<strong>mieć</strong>\\".</li></ul>"
    }
]`

## Nota modello
Variante confronto: Gemini 3.1 Pro (`gemini-3.1-pro`). Solo JSON nel file. Solo chiave `"1"`.
Questo output serve SOLO alla pagina wp-admin Confronto modelli: NON applicare al DB.',
      'outputs' => 
      array (
        0 => '<p><strong>"Ciao" → "<em>Cześć</em>" </strong></p>
<p>💡 In polacco, questo è il saluto perfetto per gli amici, i familiari o i ragazzi della tua età. Funziona esattamente come il nostro "ciao": lo usi quando arrivi in un posto e anche quando vai via. È una parola sola, molto comoda, che va bene per tutto!</p>
<p>💬 Esempio: Incontri il tuo amico Marek per strada e gli dici: "<em>Cześć, Marek!</em>" (Ciao, Marek!).<br />💬 Esempio: Entri in classe e saluti i tuoi compagni: "<em>Cześć wszystkim!</em>" (Ciao a tutti!).<br />💬 Esempio: Finisce la partita di calcio, saluti la squadra e vai a casa: "<em>Cześć, do jutra!</em>" (Ciao, a domani!).</p>
<p>⚠️ ❌ "<em>Cześć</em>" detto al professore a scuola. È un errore perché è troppo amichevole e risulta maleducato.<br />✅ "<em>Dzień dobry</em>" detto al professore. Con gli adulti si usa sempre il saluto formale.<br />❌ "<em>Cześć</em>" detto alla commessa del negozio che non conosci.<br />✅ "<em>Dzień dobry</em>" detto alla commessa. Con le persone che non conosci si usa la forma di cortesia.</p>
<p>📌 In breve: usa "<em>cześć</em>" solo con le persone con cui hai confidenza, sia per salutare all\'arrivo che alla partenza.</p>
<p><strong>Ricorda:</strong> "<em>cześć</em>" non si piega. Se la situazione è formale, cambia saluto, non la desinenza.</p>
<p>📜 <strong>Curiosità etimologia:</strong> Oggi usiamo "<em>cześć</em>" come un semplice "ciao", ma tanto tempo fa, nel mondo degli antichi slavi, questa parola era molto importante. Nasce dalla radice antica "<em>čьstь</em>", che voleva dire "onore" o "rispetto". Ancora oggi, nel vocabolario polacco, la parola per "onore" è proprio "<em>cześć</em>". Quindi, quando un amico polacco ti saluta, ti sta dicendo in modo antico: "Ti porto rispetto!".</p>',
        1 => '<p><strong>"Buongiorno" → "<em>Dzień dobry</em>" </strong><br />💡 In italiano uniamo "buon" e "giorno" in una sola parola.<br />In polacco rimangono due parole separate: "<em>dzień</em>" significa "giorno" e "<em>dobry</em>" significa "buono".<br />È il saluto formale perfetto per i negozi e per le persone che non conosci.</p>
<p>💬 Esempio: Entri in panetteria e dici alla commessa: "<em>Dzień dobry!</em>" (Buongiorno!).<br />💬 Esempio: Incontri il tuo vicino di casa anziano e dici: "<em>Dzień dobry, panie Tomaszu</em>" (Buongiorno, signor Tomasz).<br />💬 Esempio: Saluti il medico quando entri nello studio: "<em>Dzień dobry, doktorze</em>" (Buongiorno, dottore).</p>
<p>❌ Non è corretto dire "<em>Dzień dobra</em>".<br />Visto che "<em>dzień</em>" (giorno) è una parola maschile, anche l\'aggettivo "buono" deve essere al maschile, e finisce con la y: "<em>dobry</em>".</p>
<p>✅ "<em>Dzień dobry</em>".</p>
<p>📌 In breve: con gli estranei e nei negozi usa sempre "<em>dzień dobry</em>" (giorno buono).</p>
<p><strong>"come stai" → "<em>jak się masz</em>" </strong><br />💡 Qui c\'è una grossa differenza con l\'italiano.<br />Noi usiamo il verbo "stare", mentre i polacchi usano il verbo "avere", ovvero "<em>mieć</em>", insieme alla parolina riflessiva "<em>się</em>".<br />Letteralmente stanno chiedendo: "Come hai te stesso?".<br />"<em>Masz</em>" corrisponde all\'italiano "hai", ed è la seconda persona singolare del verbo "<em>mieć</em>" al presente semplice.<br />Si traduce quindi con tu hai: "<em>ty masz</em>".</p>
<p>💬 Esempio: Incontri un tuo amico e gli chiedi: "<em>Jak się masz dzisiaj?</em>" (Come stai oggi?).<br />💬 Esempio: Il tuo amico ti risponde: "<em>Mam się dobrze</em>" (Sto bene, letteralmente: mi ho bene).<br />💬 Esempio: Chiami tua sorella e le domandi: "<em>Cześć, jak się masz?</em>" (Ciao, come stai?).</p>
<p>❌ Evita di usare "<em>Jak jesteś?</em>".<br />Questo è l\'errore più comune per gli italiani e gli inglesi, perché traducono "Come sei?".<br />In polacco però non ha alcun senso per chiedere della salute.</p>
<p>✅ "<em>Jak się masz?</em>".<br />Usa sempre il verbo avere.</p>
<p>📌 In breve: per chiedere come sta un amico pensa a "come ti hai?": "<em>jak</em>" (come) + "<em>się</em>" (ti) + "<em>masz</em>" (hai).</p>
<p><strong>Coniugazione completa del presente di "mieć":</strong> <br />ja mam (io ho)<br />ty masz (tu hai)<br />on/ona/ono ma (lui/lei ha)<br />my mamy (noi abbiamo)<br />wy macie (voi avete)<br />oni/one mają (loro hanno).</p>
<p><strong>Ricorda:</strong> il saluto è nome più aggettivo. La domanda di salute è "<em>mieć</em>" più "<em>się</em>", sempre insieme.</p>
<p>📜 <strong>Curiosità etimologia:</strong><br />La parola "<em>dzień</em>" è antichissima e deriva dalla radice indoeuropea che indicava la luce del giorno o il cielo luminoso.<br />È esattamente la stessa radice da cui deriva la parola latina "<em>dies</em>", che in italiano è diventata "dì", come per lune-dì e marte-dì.<br />Inoltre, il verbo "<em>mieć</em>" (avere) in origine voleva dire "tenere qualcosa stretto nelle mani".</p>',
        2 => '<p><strong>"Sto bene" → "<em>Dobrze</em>" </strong></p>
<p>💡 In italiano, per dire come stiamo, usiamo il verbo "stare" e diciamo "sto bene". In polacco, invece, il verbo scompare! Per rispondere ti basta dire la parola "<em>dobrze</em>", che significa semplicemente "bene". È facilissimo e non cambia mai, sia che tu sia un ragazzo sia che tu sia una ragazza.</p>
<p>💬 Esempio: Incontri un amico a scuola e ti chiede: "<em>Jak się masz?</em>" (Come stai?). Tu rispondi in modo diretto: "<em>Dobrze!</em>" (Bene!).<br />💬 Esempio: La mamma ti chiede come è andata la giornata e tu dici: "<em>Wszystko dobrze</em>" (Tutto bene). Anche qui, non serve nessun verbo.</p>
<p>⚠️ ❌ "<em>Jestem dobrze</em>". Questo è un errore classico! Spesso proviamo a tradurre letteralmente "Io sono bene" o "Io sto bene", ma in polacco il verbo "<em>jestem</em>" (io sono) non si usa per dire come stai.<br />✅ "<em>Dobrze</em>". Usa solo questa parola da sola!<br />❌ "<em>Dobry</em>". Potresti confonderti perché assomiglia a "buono", ma "<em>dobry</em>" si usa solo per le cose, come "un buon gelato".<br />✅ "<em>Dobrze</em>". Per dire come stai, usa sempre "<em>dobrze</em>".</p>
<p>📌 In breve: quando devi rispondere "sto bene", usa solo la parola "<em>dobrze</em>", senza aggiungere nessun verbo prima.</p>
<p><strong>"grazie" → "<em>dziękuję</em>" </strong></p>
<p>💡 In italiano "grazie" è una parola fissa. In polacco, invece, "<em>dziękuję</em>" è un vero e proprio verbo! Significa "io ringrazio". Visto che il verbo dice già chi sta parlando, i polacchi lo usano da solo, senza mettere "io" ("<em>ja</em>") davanti. Se vuoi dire "grazie mille", ti basta aggiungere la parola "<em>bardzo</em>" (molto) prima del verbo.</p>
<p>💬 Esempio: Sei in pizzeria, il cameriere ti porta la pizza e tu dici: "<em>Dziękuję!</em>" (Grazie!).<br />💬 Esempio: Un amico ti presta un videogioco che volevi tanto e tu gli dici: "<em>Bardzo dziękuję!</em>" (Grazie mille!).</p>
<p>⚠️ ❌ "<em>Ja dziękuję</em>". Visto che "<em>dziękuję</em>" significa già "io ringrazio", aggiungere "<em>ja</em>" (io) è una ripetizione inutile e suona strano.<br />✅ "<em>Dziękuję</em>". Dillo in modo semplice e diretto.<br />❌ "<em>Dziękuję bardzo</em>". Anche se si capisce, l\'ordine più naturale per i polacchi è mettere "molto" prima del verbo.<br />✅ "<em>Bardzo dziękuję</em>". Metti "<em>bardzo</em>" all\'inizio per dire "grazie mille".</p>
<p>📌 In breve: quando devi dire "grazie", pensa al verbo "io ringrazio" e usa solo "<em>dziękuję</em>". Se sei molto felice del favore, usa "<em>bardzo dziękuję</em>".</p>
<p><strong>Coniugazione completa del presente di "dziękować":</strong> <br />ja dziękuję (io ringrazio)<br />ty dziękujesz (tu ringrazi)<br />on/ona/ono dziękuje (lui/lei ringrazia)<br />my dziękujemy (noi ringraziamo)<br />wy dziękujecie (voi ringraziate)<br />oni/one dziękują (loro ringraziano).</p>
<p><strong>Ricorda:</strong> la risposta è un avverbio, non un verbo. "<em>Dziękuję</em>" è "<em>io ringrazio</em>" usato come "<em>grazie</em>".</p>
<p>📜 <strong>Curiosità etimologia:</strong> Sapevi che la parola "<em>dziękować</em>" (ringraziare) in realtà non è nata in Polonia? Moltissimo tempo fa, le antiche tribù slave l\'hanno presa in prestito dal vecchio tedesco! Nasce dall\'antica parola germanica "<em>dankōn</em>", che significava sia "pensare" sia "ringraziare". È la stessa identica radice da cui nascono l\'inglese "<em>thank</em>" e il tedesco "<em>danke</em>". I polacchi l\'hanno adattata alla loro lingua trasformandola in "<em>dziękuję</em>". Quindi, quando ringrazi in polacco, stai usando un lontanissimo parente del "Thank you"!</p>',
      ),
      'usage' => 
      array (
        'prompt_chars' => 5268,
        'output_chars' => 8473,
        'tokens_in_est' => 1317,
        'tokens_out_est' => 2119,
        'tokens_total_est' => 3436,
        'method' => 'chars/4 local estimate (Cursor subagent tokens not exposed)',
        'note' => 'outputs[1] rigenerato con controllo-qualita.md aggiornato',
      ),
    ),
    'claude' => 
    array (
      'label' => 'Claude Opus 4.8',
      'slug' => 'claude-opus-4-8-thinking-high',
      'provider' => 'Anthropic (via Cursor)',
      'notes' => 'Generato in Cursor Agent con Claude Opus 4.8 (thinking high). Stesso prompt di controllo qualità delle altre colonne.',
      'prompt' => '# Confronto modelli — riscrittura phrase_grammar (SOLO frase sort_order=1)

Segui ESATTAMENTE le regole in:
`c:\\xampp\\htdocs\\localloverewrite202608\\controllo-qualita.md`
(file aggiornato: meccanismo con esempi concreti, vai a capo dopo il punto, niente parola "forma" grezza per i verbi, ❌ con "Evita di usare"/"È un errore dire…", emoji in `<p>` separati, ecc.)

## Contesto storia
- Titoli: Le prime 50 parole / Pierwsze 50 słów
- Live ID: 4001 · Locale ID: 3767
- Livello: A1
- Lingua nota (`_llm_known_lang`): **it** — tutta la prosa in italiano
- Lingua target (`_llm_target_lang`): **pl**
- Riassunto: Cinquanta frasi semplici. Due animali che non si sono mai visti. Una notte per conoscersi.
- Frasi vicine (solo contesto):
  - 0 Ciao! → Cześć!
  - **1 Buongiorno, come stai? → Dzień dobry, jak się masz?** ← SOLO QUESTA
  - 2 Sto bene, grazie. → Dobrze, dziękuję.

## Cosa fare
Riscrivi SOLO `phrase_grammar` della frase sort_order **1**.
NON cambiare: titoli `<strong>`, elenchi di coniugazione, parole PL tra `"<em>…</em>"`, blocco Ricorda (IDENTICO).
NON toccare: phrase_alt, phrase_remember, pronuncia.
NON aggiungere/togliere/invertire paragrafi strutturali rispetto allo scheletro.

## Output
Scrivi JSON UTF-8 (caratteri reali, NON `\\uXXXX`, tag HTML reali) SOLO con chiave `"1"`:

`C:/xampp/htdocs/localloverewrite202608/database/_tmp_out_4001_p1_claude.json`

```json
{
  "1": "<p>...</p>"
}
```

## Frase da riscrivere (JSON)
`[
    {
        "sort_order": "1",
        "phrase_interface": "Buongiorno, come stai?",
        "phrase_target": "Dzień dobry, jak się masz?",
        "phrase_grammar": "<p><strong>\\"Buongiorno\\" → \\"<em>Dzień dobry</em>\\" </strong><br />💡 In italiano uniamo \\"buon\\" e \\"giorno\\" in una sola parola. In polacco rimangono due parole separate: \\"<em>dzień</em>\\" (giorno) e \\"<em>dobry</em>\\" (buono). È il saluto perfetto e formale per i negozi e le persone che non conosci.</p>\\r\\n<p>💬 Esempio: Entri in panetteria e dici alla commessa: \\"<em>Dzień dobry!</em>\\" (Buongiorno!).<br />💬 Esempio: Incontri il tuo vicino di casa anziano: \\"<em>Dzień dobry, panie Tomaszu</em>\\" (Buongiorno, signor Tomasz).</p>\\r\\n<p>⚠️ ❌ \\"<em>Dzień dobra</em>\\". Questo è un errore di distrazione! Visto che \\"<em>dzień</em>\\" (giorno) è una parola maschile, anche l\'aggettivo \\"buono\\" deve essere al maschile, e finisce con la y: \\"<em>dobry</em>\\".<br />✅ \\"<em>Dzień dobry</em>\\".</p>\\r\\n<p>📌 In breve: con gli estranei e nei negozi usa sempre \\"<em>dzień dobry</em>\\" (giorno buono).</p>\\r\\n<p><strong>\\"come stai\\" → \\"<em>jak się masz</em>\\" </strong><br />💡 Qui c\'è una grossa differenza con l\'italiano! Noi usiamo il verbo \\"stare\\", mentre i polacchi usano il verbo \\"avere\\" (<em>mieć</em>) insieme alla parolina riflessiva \\"<em>się</em>\\". Letteralmente stanno chiedendo: \\"Come hai te stesso?\\". Il verbo \\"<em>masz</em>\\" significa \\"tu hai\\".</p>\\r\\n<p>💬 Esempio: Incontri un tuo amico e gli chiedi: \\"<em>Jak się masz dzisiaj?</em>\\" (Come stai oggi?).<br />💬 Esempio: Per rispondere potresti dire: \\"<em>Mam się dobrze</em>\\" (Sto bene - letteralmente: mi ho bene).</p>\\r\\n<p>⚠️ ❌ \\"<em>Jak jesteś?</em>\\". È l\'errore più comune per gli italiani e gli inglesi, perché traducono \\"Come sei? / How are you?\\". Ma in polacco non ha alcun senso per chiedere della salute.<br />✅ \\"<em>Jak się masz?</em>\\". Usa sempre il verbo avere!</p>\\r\\n<p>📌 In breve: per chiedere come sta un amico pensa a \\"come ti hai?\\": <em>jak</em> (come) + <em>się</em> (ti) + <em>masz</em> (hai).</p>\\r\\n<p><strong>Coniugazione completa del presente di \\"mieć\\":</strong> <br />ja mam (io ho)<br />ty masz (tu hai)<br />on/ona/ono ma (lui/lei ha)<br />my mamy (noi abbiamo)<br />wy macie (voi avete)<br />oni/one mają (loro hanno).</p>\\r\\n<p><strong>Ricorda:</strong> il saluto è nome più aggettivo. La domanda di salute è \\"<em>mieć</em>\\" più \\"<em>się</em>\\", sempre insieme.</p>\\r\\n<p>📜 <strong>Curiosità etimologia:</strong><br />La parola \\"<em>dzień</em>\\" è antichissima e deriva dalla radice indoeuropea per \\"luce del giorno\\" o \\"cielo luminoso\\". È esattamente la stessa radice da cui deriva la parola latina \\"<em>dies</em>\\", che in italiano è diventata \\"dì\\" (come in lune-dì, marte-dì)! E il verbo \\"<em>mieć</em>\\" (avere) in origine voleva dire \\"tenere qualcosa stretto nelle mani\\".</p>",
        "phrase_alt": "<p>Una traduzione alternativa di \\"<em>Dzień dobry, jak się masz?</em>\\"<br />potrebbe essere \\"<em>Dzień dobry, jak leci?</em>\\"<br />In italiano è \\"Buongiorno, come va?\\"<br />\\"<em>Jak leci</em>\\" è più da amici. \\"<em>Jak się masz</em>\\" è la domanda intera, con \\"<em>się</em>\\".</p>",
        "phrase_remember": "<p>Dzień dobry, jak się masz?</p><p><strong>Ricorda:</strong></p><ul><li><strong>\\"Buongiorno\\" → \\"Dzień dobry\\"</strong>: saluto <em>formale</em> del giorno.</li><li><strong>\\"Come stai\\" → \\"jak się masz\\"</strong>: non togliere \\"<strong>się</strong>\\".</li><li>\\"<strong>Masz</strong>\\" è la forma «tu» di \\"<strong>mieć</strong>\\".</li></ul>"
    }
]`

## Nota modello
Variante confronto: Claude Opus 4.8 (`claude-opus-4-8-thinking-high`). Solo JSON nel file. Solo chiave `"1"`.
Questo output serve SOLO alla pagina wp-admin Confronto modelli: NON applicare al DB.',
      'outputs' => 
      array (
        0 => '<p><strong>"Ciao" → "<em>Cześć</em>" </strong><br />💡 In polacco "<em>Cześć</em>" è il saluto tra amici, familiari e coetanei. È comodo perché va bene sia quando arrivi sia quando vai via, proprio come il nostro "ciao".</p>
<p>💬 Esempio: incontri il tuo amico Marek e gli dici "<em>Cześć, Marek!</em>" (Ciao, Marek!).<br />💬 Esempio: entri in una stanza piena di amici: "<em>Cześć wszystkim!</em>" (Ciao a tutti!).<br />💬 Esempio: torni a casa e saluti: "<em>Cześć, do jutra!</em>" (Ciao, a domani!).</p>
<p>⚠️ ❌ "<em>Cześć</em>" detto al professore, al medico o a uno sconosciuto più grande di te: suona maleducato, perché è troppo confidenziale.<br />✅ Con gli adulti che non conosci usa sempre "<em>Dzień dobry</em>".</p>
<p>📌 In breve: usa "<em>cześć</em>" come il nostro "ciao" tra amici, sia quando arrivi sia quando parti.</p>
<p><strong>Ricorda:</strong> "<em>cześć</em>" non si piega. Se la situazione è formale, cambia saluto, non la desinenza.</p>
<p>📜 <strong>Curiosità etimologia:</strong><br />Oggi "<em>cześć</em>" è un semplice "ciao", ma per gli antichi Slavi era una parola importante. Nasce dalla vecchia radice "<em>čьstь</em>", che voleva dire "onore" e "rispetto". Non a caso, ancora oggi in polacco "onore" si dice proprio "<em>cześć</em>". Quindi, quando un polacco ti saluta, ti sta quasi dicendo "Ti rendo onore!".</p>',
        1 => '<p><strong>"Buongiorno" → "<em>Dzień dobry</em>" </strong><br />💡 In italiano diciamo tutto attaccato, "buongiorno", una parola sola.<br />In polacco invece restano <strong>due parole separate</strong>: "<em>dzień</em>" (giorno) e "<em>dobry</em>" (buono).<br />È il saluto <strong>formale</strong> perfetto quando entri in un negozio o parli con una persona che non conosci.</p>
<p>💬 Esempio: entri in panetteria e saluti la commessa, "<em>Dzień dobry!</em>" (Buongiorno!).<br />💬 Esempio: incontri il tuo vicino anziano e dici "<em>Dzień dobry, panie Tomaszu</em>" (Buongiorno, signor Tomasz).<br />💬 Esempio: arrivi dal medico e lo saluti, "<em>Dzień dobry, pani doktor</em>" (Buongiorno, dottoressa).</p>
<p>⚠️ ❌ Evita di usare "<em>Dzień dobra</em>".<br />Visto che "<em>dzień</em>" (giorno) è una parola <strong>maschile</strong>, anche "buono" deve stare al maschile e finisce in -y.<br />✅ Di\' sempre "<em>Dzień dobry</em>".</p>
<p>📌 In breve: con gli estranei e nei negozi usa sempre "<em>dzień dobry</em>" (giorno buono).</p>
<p><strong>"come stai" → "<em>jak się masz</em>" </strong><br />💡 Qui c\'è una bella differenza con l\'italiano.<br />Noi usiamo il verbo <strong>"stare"</strong> ("come stai?"), mentre i polacchi usano il verbo <strong>avere</strong>, cioè "<em>mieć</em>", insieme alla parolina riflessiva "<em>się</em>".<br />Alla lettera stanno chiedendo "come hai te stesso?".<br />La parola "<em>masz</em>" corrisponde all\'italiano "hai", la <strong>seconda persona singolare</strong> del verbo "<em>mieć</em>" ("avere") al presente semplice: "tu hai" → "<em>ty masz</em>".</p>
<p>💬 Esempio: incontri un amico e gli chiedi "<em>Jak się masz dzisiaj?</em>" (Come stai oggi?).<br />💬 Esempio: per rispondere puoi dire "<em>Mam się dobrze</em>" (Sto bene).<br />💬 Esempio: al telefono chiedi a tua sorella "<em>Cześć, jak się masz?</em>" (Ciao, come stai?).</p>
<p>⚠️ ❌ È un errore dire "<em>Jak jesteś?</em>".<br />Molti italiani sbagliano perché traducono parola per parola "come sei?", ma in polacco, per chiedere della salute, non ha senso.<br />✅ Di\' sempre "<em>Jak się masz?</em>", con il verbo avere.</p>
<p>📌 In breve: per chiedere come sta un amico pensa a "come ti hai?": "<em>jak</em>" (come) + "<em>się</em>" (ti) + "<em>masz</em>" (hai).</p>
<p><strong>Coniugazione completa del presente di "mieć":</strong> <br />ja mam (io ho)<br />ty masz (tu hai)<br />on/ona/ono ma (lui/lei ha)<br />my mamy (noi abbiamo)<br />wy macie (voi avete)<br />oni/one mają (loro hanno).</p>
<p><strong>Ricorda:</strong> il saluto è nome più aggettivo. La domanda di salute è "<em>mieć</em>" più "<em>się</em>", sempre insieme.</p>
<p>📜 <strong>Curiosità etimologia:</strong><br />La parola "<em>dzień</em>" è antichissima e nasce da una radice indoeuropea che voleva dire "luce del giorno", "cielo chiaro".<br />È la stessa radice della parola latina "<em>dies</em>", che in italiano è diventata "dì", quella che senti in lune-dì, marte-dì!<br />Anche il verbo "<em>mieć</em>" (avere) all\'inizio significava "tenere qualcosa stretto tra le mani".</p>',
        2 => '<p><strong>"Sto bene" → "<em>Dobrze</em>" </strong><br />💡 In italiano, se ti chiedono come stai, usi un verbo: dici "sto bene". In polacco il verbo sparisce! Basta rispondere "<em>dobrze</em>", cioè "bene". E non cambia se sei un ragazzo o una ragazza: resta sempre uguale.</p>
<p>💬 Esempio: un amico ti ferma per strada e ti chiede "<em>Jak się masz?</em>" (Come stai?). Tu rispondi solo "<em>Dobrze!</em>" (Bene!).<br />💬 Esempio: sei al telefono con la mamma e dici "<em>Wszystko dobrze</em>" (Tutto bene). Anche qui niente verbo.</p>
<p>⚠️ ❌ "<em>Jestem dobrze</em>". È l\'errore più comune per noi italiani, perché traduciamo "Io sono bene". Ma in polacco "<em>jestem</em>" (io sono) non si usa per dire come stai.<br />✅ "<em>Dobrze</em>". Basta la parola da sola!</p>
<p>⚠️ ❌ "<em>Dobry</em>". Potresti dire "<em>dobry</em>" perché somiglia a "buono". Ma "<em>dobry</em>" serve a descrivere le cose (per esempio "un buon caffè").<br />✅ "<em>Dobrze</em>". Quando parli di come stai è un avverbio: usa sempre "<em>dobrze</em>".</p>
<p>📌 In breve: per rispondere "sto bene" pensa solo alla parola "<em>dobrze</em>", senza nessun verbo davanti.</p>
<p><strong>"grazie" → "<em>dziękuję</em>" </strong><br />💡 In italiano "grazie" è una parola fissa. In polacco "<em>dziękuję</em>" è in realtà un verbo e vuol dire "io ringrazio". Proprio per questo lo dici da solo, senza mettere "io" ("<em>ja</em>") davanti. Se vuoi dire "grazie mille", metti "<em>bardzo</em>" (molto) prima.</p>
<p>💬 Esempio: sei al bar, il cameriere ti porta l\'acqua e dici "<em>Dziękuję!</em>" (Grazie!).<br />💬 Esempio: qualcuno ti fa un grosso favore e vuoi ringraziarlo tanto: "<em>Bardzo dziękuję za pomoc</em>" (Grazie mille per l\'aiuto).</p>
<p>⚠️ ❌ "<em>Ja dziękuję</em>". Siccome "<em>dziękuję</em>" vuol già dire "io ringrazio", mettere "<em>ja</em>" davanti è una ripetizione inutile.<br />✅ "<em>Dziękuję</em>". Semplice e diretto.</p>
<p>📌 In breve: per dire "grazie" pensa a "io ringrazio" e usa solo "<em>dziękuję</em>". Se sei molto grato, aggiungi "<em>bardzo</em>" davanti.</p>
<p><strong>Coniugazione completa del presente di "dziękować":</strong> <br />ja dziękuję (io ringrazio)<br />ty dziękujesz (tu ringrazi)<br />on/ona/ono dziękuje (lui/lei ringrazia)<br />my dziękujemy (noi ringraziamo)<br />wy dziękujecie (voi ringraziate)<br />oni/one dziękują (loro ringraziano).</p>
<p><strong>Ricorda:</strong> la risposta è un avverbio, non un verbo. "<em>Dziękuję</em>" è "<em>io ringrazio</em>" usato come "<em>grazie</em>".</p>
<p>📜 <strong>Curiosità etimologia:</strong><br />Lo sapevi che "<em>dziękować</em>" (ringraziare) non è nata in Polonia? Tanto tempo fa le antiche tribù slave l\'hanno presa in prestito dall\'antico tedesco! Viene dalla vecchia parola germanica "<em>dankōn</em>", che voleva dire sia "pensare" sia "ringraziare". È la stessa radice dell\'inglese "<em>thank</em>" e del tedesco "<em>danke</em>". I polacchi l\'hanno adattata alla loro pronuncia, trasformandola in "<em>dziękuję</em>". Quando ringrazi in polacco, usi un lontano cugino del "Thank you"!</p>',
      ),
      'usage' => 
      array (
        'prompt_chars' => 5284,
        'output_chars' => 7508,
        'tokens_in_est' => 1321,
        'tokens_out_est' => 1877,
        'tokens_total_est' => 3198,
        'method' => 'chars/4 local estimate (Cursor subagent tokens not exposed)',
        'note' => 'outputs[1] rigenerato con controllo-qualita.md aggiornato',
      ),
    ),
  ),
);
