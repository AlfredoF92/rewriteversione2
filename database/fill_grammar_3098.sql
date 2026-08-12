SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
-- Analisi grammaticale per storia ID 3098 (ciao-mi-chiamo-alfredo)

UPDATE wp_llm_story_phrases SET phrase_grammar =
'"Mi chiamo" → "My name is" — Questa è la differenza più importante! In italiano usiamo il verbo riflessivo chiamarsi (letteralmente: io chiamo me stesso). In inglese non esiste questa struttura: si dice "My name is" (il mio nome è), con aggettivo possessivo + verbo to be. Niente riflessivo!

"Buongiorno" → "Good morning" — In italiano è una parola sola, in inglese sono due parole separate. "Good" è un aggettivo (buono/a) e "morning" è il sostantivo (mattina). Lo stesso schema vale per: good afternoon, good evening.

"Ciao" → "Hi" — Entrambi saluti informali. "Hi" è invariabile: non cambia mai in base a chi parli. In inglese non esiste distinzione formale/informale nel saluto come in italiano (ciao vs. buongiorno).'
WHERE id = 862;

UPDATE wp_llm_story_phrases SET phrase_grammar =
'"Come" → "What" — Attenzione: qui "come" non si traduce con "how" ma con "what" (cosa/quale). "What\'s your name?" significa letteralmente "Qual è il tuo nome?".

"Ti chiami" → "your name" — Di nuovo il riflessivo italiano sparisce. "Ti chiami" = "tu chiami te stesso". In inglese si usa il possessivo "your name" (il tuo nome) + il verbo to be. Niente verbo "chiamarsi"!

"What\'s" = "What is" — L\'apostrofo indica una contrazione, cioè due parole fuse in una. In inglese parlato le contrazioni sono normalissime: what is → what\'s, it is → it\'s, I am → I\'m. Molto più comune che in italiano!'
WHERE id = 863;

UPDATE wp_llm_story_phrases SET phrase_grammar =
'"Da dove" → "Where...from" — In italiano "da dove" sta tutto all\'inizio della frase. In inglese invece "from" va alla FINE: "Where are you from?" — la preposizione è spostata in coda. Questo succede spessissimo in inglese e non è un errore, è la norma!

"Vieni" → "are you (from)" — Attenzione: in italiano usiamo "venire" per la provenienza. In inglese invece si usa il verbo to be + from: "Where are you from?" Non è "where do you come", ma "where are you"!

Inversione soggetto-verbo nelle domande con to be — nella frase normale diresti "You are from Italy". Per farne una domanda inverti: "Are you from Italy?". Vale per tutti: I am → Am I? / He is → Is he? / They are → Are they?'
WHERE id = 864;
