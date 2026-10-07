<?php
/**
 * Confronto modelli — storia 3661, frasi 0–2.
 * Generato da modelli reali (Grok 4.6 / Gemini 3.1 Pro / Claude Opus 4.8).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

return array (
  'meta' => 
  array (
    'generated_at' => '2026-09-16T11:19:26+02:00',
    'story_live_id' => 3661,
    'story_local_id' => 3429,
    'phrases' => '0-2',
    'prompt_base' => 'controllo-qualita.md + database/_tmp_compare_prompt_3661_template.md',
    'real_models' => true,
  ),
  'models' => 
  array (
    'grok' => 
    array (
      'label' => 'Grok 4.6',
      'slug' => 'cursor-grok-4.6-high-fast',
      'provider' => 'xAI (via Cursor)',
      'notes' => 'Generated in Cursor Agent with Grok 4.6 high-fast. Same quality-control prompt as the other columns.',
      'prompt' => '# Prompt — Quality control notes (Model compare)

You are a grammar-notes editor for LoveReWrite.
Rewrite ONLY the `phrase_grammar` field (translation tips) following the rules below.

## Story context
- Titles: The First 50 Words / Le prime 50 parole
- Cover line: Una notte a Vienna
- Live ID: 3661 · Local ID: 3429
- Level: A1 · 50 phrases
- Known language (`_llm_known_lang`): **en** (English) — ALL prose in English
- Target language (`_llm_target_lang`): **it** (Italian)
- Summary: Fifty simple sentences. Two animals who have never met. One night to get to know each other. (Lemo the Italian lemur and Connie the English rabbit meet on a train to Vienna.)

## What to rewrite
- Rewrite ONLY the prose AFTER `<strong>…</strong>` titles.
- Do NOT change: strong titles (pairs `"…" → "…"`, Full conjugation, Remember, Etymology curiosity), conjugation lists, Italian words in quotes/italics.
- Do NOT touch: phrase_alt, phrase_remember, pronunciation.
- Do not add/remove/reorder structural paragraphs vs the skeleton (same strong titles, same order). You MAY use multiple emoji `<p>` blocks inside explanations.

## Explanation structure (after each pair bold title)
- 💡 mechanism in very simple words (12–13 year old), with everyday examples
- 💬 at least 2–3 real examples introduced by "Example:" (bar, school, friends, shop), with English gloss
- ⚠️ 2–3 typical errors ❌ vs ✅
- 📌 In short: …
- 📜 Etymology curiosity: REAL historical origin (Latin / Indo-European), old meaning, links to English/Italian; easy vocabulary

Emoji rules:
- One emoji = one new `<p>` (never two different emoji in the same `<p>` with `<br />`)
- Multiple consecutive 💬 may share one `<p>` separated by `<br />`
- Target IT words always as `"<em>word</em>"`
- Use `+` instead of the word "plus" in technical joins (e.g. stare + bene)
- No IPA, no textbook acronyms
- Remember: leave it IDENTICAL

## Required output
Write a UTF-8 JSON file (real characters, NOT `\\uXXXX`; real HTML `</p>` not `<\\/p>`) to:

`C:/xampp/htdocs/localloverewrite202608/database/_tmp_out_3661_grok.json`

Exact structure:
```json
{
  "0": "<p>...</p>",
  "1": "<p>...</p>",
  "2": "<p>...</p>"
}
```
Keys = sort_order 0, 1, 2.
Value = full rewritten phrase_grammar HTML.

## Phrases to rewrite (JSON)
`[
    {
        "sort_order": "0",
        "phrase_interface": "Hello!",
        "phrase_target": "Ciao!",
        "phrase_grammar": "<p><strong>\\"Hello\\" → \\"<em>Ciao<\\/em>\\" <\\/strong><br \\/>\\"<em>Ciao<\\/em>\\" is the friendly Italian hello. English Hello works with almost everyone. Italian splits the door: \\"<em>Ciao<\\/em>\\" for friends, family and people your age; \\"<em>Buongiorno<\\/em>\\" when the meeting feels more polite. <br \\/> \\"<em>Ciao<\\/em>\\" is not a verb, so it never changes for a boy, a girl or a group. You can add a name: \\"<em>Ciao, Lemo<\\/em>\\". You can greet everybody with \\"<em>Ciao a tutti<\\/em>\\". <br \\/> English Hello is mostly an arrival word. Italian \\"<em>Ciao<\\/em>\\" can open a chat and close it too, like a small wave when you leave.<\\/p>\\n<p>There is no I and no you in this line. When you later want a real verb, Italian uses \\"<em>salutare<\\/em>\\" (to greet). <br \\/><br \\/><strong>Full conjugation of the present of \\"salutare\\":<\\/strong> <br \\/>io saluto (I greet)<br \\/>tu saluti (you greet)<br \\/>lui\\/lei saluta (he\\/she greets)<br \\/>noi salutiamo (we greet)<br \\/>voi salutate (you greet)<br \\/>loro salutano (they greet).<\\/p>\\n<p>Examples: \\"<em>Saluto Connie<\\/em>\\" (I greet Connie), \\"<em>Salutiamo gli amici<\\/em>\\" (We greet our friends). After \\"<em>salutare<\\/em>\\" you name the person directly.<\\/p>\\n<p><strong>Remember:<\\/strong> \\"<em>Ciao<\\/em>\\" is the informal stamp. It can start and finish a chat. For a shop helper you have not met, \\"<em>Buongiorno<\\/em>\\" is safer.<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Ciao<\\/em>\\" comes from the old Venetian phrase \\"<em>s-ciào vostro<\\/em>\\", once a bow meaning I am your servant. The heavy meaning melted away, and children now use it as a light hello.<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Ciao!<\\/em>\\"<br \\/>could be \\"<em>Salve!<\\/em>\\"<br \\/>In English that is \\"Hello!\\"<br \\/>\\"<em>Salve<\\/em>\\" is more formal than \\"<em>Ciao<\\/em>\\" and less tied to morning than \\"<em>Buongiorno<\\/em>\\". Speakers do not usually use \\"<em>Salve<\\/em>\\" as a goodbye.<\\/p>",
        "phrase_remember": "<p>Ciao!<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"Hello\\" → \\"Ciao\\"<\\/strong>: informal <em>greeting<\\/em> for friends, family and people your age.<\\/li><li>\\"<strong>Ciao<\\/strong>\\" is not a verb: it never changes, and there is no I or you in the line.<\\/li><li>The same stamp can open and close a chat; for a more polite meeting use \\"<strong>Buongiorno<\\/strong>\\".<\\/li><\\/ul>"
    },
    {
        "sort_order": "1",
        "phrase_interface": "Good morning, how are you?",
        "phrase_target": "Buongiorno, come stai?",
        "phrase_grammar": "<p><strong>\\"Good morning\\" → \\"<em>Buongiorno<\\/em>\\" <\\/strong><br \\/>English keeps two words. Italian glues them into one: \\"<em>buono<\\/em>\\" (good) plus \\"<em>giorno<\\/em>\\" (day). \\"<em>Giorno<\\/em>\\" is masculine, so the greeting is \\"<em>Buongiorno<\\/em>\\", never \\"<em>Buonagiorno<\\/em>\\". <br \\/> The same trick gives \\"<em>Buonasera<\\/em>\\" in the evening, because \\"<em>sera<\\/em>\\" is feminine. \\"<em>Buongiorno<\\/em>\\" is a set formula, not a verb, so it does not change for a man, a woman or a group. You can add a name: \\"<em>Buongiorno, Connie<\\/em>\\". <br \\/> Do not copy Good morning as \\"<em>Buona mattina<\\/em>\\". \\"<em>Mattina<\\/em>\\" means \\"<em>morning<\\/em>\\", but the greeting uses \\"<em>giorno<\\/em>\\".<\\/p>\\n<p><strong>\\"how are you\\" → \\"<em>come stai<\\/em>\\" <\\/strong><br \\/>English builds this with to be. Italian uses \\"<em>stare<\\/em>\\", a verb for how a person feels. \\"<em>Come<\\/em>\\" means \\"<em>how<\\/em>\\". <br \\/> \\"<em>Stai<\\/em>\\" is the friendly you. The ending already says tu, so you do not write an extra you. Do not say \\"<em>Come sei<\\/em>\\" for this question. <br \\/> \\"<em>Sei<\\/em>\\" belongs to \\"<em>essere<\\/em>\\" and asks what you are like, not how you feel today.<\\/p>\\n<p><strong>Full conjugation of the present of \\"stare\\":<\\/strong> <br \\/>io sto (I am \\/ I stay)<br \\/>tu stai (you are \\/ you stay)<br \\/>lui\\/lei sta (he\\/she is \\/ stays)<br \\/>noi stiamo (we are \\/ we stay)<br \\/>voi state (you are \\/ you stay)<br \\/>loro stanno (they are \\/ they stay).<\\/p>\\n<p>Examples: \\"<em>Come stai oggi?<\\/em>\\" (How are you today?), \\"<em>Stiamo bene<\\/em>\\" (We are well). A twin question is \\"<em>Come va<\\/em>\\", which means \\"<em>how is it going<\\/em>\\".<\\/p>\\n<p><strong>Remember:<\\/strong> \\"<em>Buongiorno<\\/em>\\" uses \\"<em>giorno<\\/em>\\", not \\"<em>mattina<\\/em>\\". For wellbeing, ask \\"<em>Come stai<\\/em>\\" with \\"<em>stare<\\/em>\\", not \\"<em>Come sei<\\/em>\\" with \\"<em>essere<\\/em>\\".<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Buongiorno<\\/em>\\" joins Latin bonus (good) and diurnum (daytime), cousin of English diary and journey. \\"<em>Stare<\\/em>\\" comes from Latin stare (to stand).<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Buongiorno, come stai?<\\/em>\\"<br \\/>could be \\"<em>Buongiorno, come va?<\\/em>\\"<br \\/>In English that is \\"Good morning, how\'s it going?\\"<br \\/>\\"<em>Come va<\\/em>\\" does not name you; \\"<em>Come stai<\\/em>\\" looks straight at the friend.<\\/p>",
        "phrase_remember": "<p>Buongiorno, come stai?<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"Good morning\\" → \\"Buongiorno\\"<\\/strong>: one word, built on \\"<strong>giorno<\\/strong>\\", never \\"<strong>Buona mattina<\\/strong>\\".<\\/li><li><strong>\\"how are you\\" → \\"come stai\\"<\\/strong>: ask feelings with <strong>stare<\\/strong>, not <em>essere<\\/em> — not \\"<strong>Come sei<\\/strong>\\".<\\/li><li>\\"<strong>Come<\\/strong>\\" means \\"<em>how<\\/em>\\"; \\"<strong>stai<\\/strong>\\" already says <em>tu<\\/em>, so do not add you.<\\/li><\\/ul>"
    },
    {
        "sort_order": "2",
        "phrase_interface": "I’m fine, thank you.",
        "phrase_target": "Sto bene, grazie.",
        "phrase_grammar": "<p><strong>\\"I’m fine\\" → \\"<em>Sto bene<\\/em>\\" <\\/strong><br \\/>English uses I am with an adjective. Italian uses \\"<em>stare<\\/em>\\" plus \\"<em>bene<\\/em>\\" (well). \\"<em>Sto<\\/em>\\" is the io form of \\"<em>stare<\\/em>\\". <br \\/> You do not say \\"<em>Sono bene<\\/em>\\". \\"<em>Sono<\\/em>\\" belongs to \\"<em>essere<\\/em>\\" and names what you are, not how you feel. \\"<em>Bene<\\/em>\\" is an adverb here, so it does not change for a boy or a girl. <br \\/> A girl still says \\"<em>Sto bene<\\/em>\\", never \\"<em>Sto buona<\\/em>\\" for this answer.<\\/p>\\n<p><strong>\\"thank you\\" → \\"<em>grazie<\\/em>\\" <\\/strong><br \\/>\\"<em>Grazie<\\/em>\\" is a ready-made thank you. It does not change for one person or a group. You can make it warmer with \\"<em>Grazie mille<\\/em>\\" (thanks a lot). <br \\/> You do not add I in front.<\\/p>\\n<p><strong>Full conjugation of the present of \\"stare\\":<\\/strong> <br \\/>io sto (I am \\/ I stay)<br \\/>tu stai (you are \\/ you stay)<br \\/>lui\\/lei sta (he\\/she is \\/ stays)<br \\/>noi stiamo (we are \\/ we stay)<br \\/>voi state (you are \\/ you stay)<br \\/>loro stanno (they are \\/ they stay).<\\/p>\\n<p>Examples: \\"<em>Sto male<\\/em>\\" (I feel bad), \\"<em>Sta meglio<\\/em>\\" (He\\/She feels better), \\"<em>State bene?<\\/em>\\" (Are you well?). The opposite of \\"<em>bene<\\/em>\\" is \\"<em>male<\\/em>\\". To ask again you can say \\"<em>E tu?<\\/em>\\" after \\"<em>grazie<\\/em>\\".<\\/p>\\n<p><strong>Remember:<\\/strong> wellbeing takes \\"<em>stare<\\/em>\\" + \\"<em>bene<\\/em>\\". Do not copy I am fine as \\"<em>Sono bene<\\/em>\\". \\"<em>Grazie<\\/em>\\" stands alone.<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Bene<\\/em>\\" comes from Latin bene (well), cousin of English benefit. \\"<em>Grazie<\\/em>\\" comes from Latin gratias (thanks), the same family as English grace and grateful.<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Sto bene, grazie.<\\/em>\\"<br \\/>could be \\"<em>Tutto bene, grazie.<\\/em>\\"<br \\/>In English that is \\"All good, thank you.\\"<br \\/>\\"<em>Tutto bene<\\/em>\\" is a little more casual than \\"<em>Sto bene<\\/em>\\" and still uses \\"<em>bene<\\/em>\\", not \\"<em>essere<\\/em>\\".<\\/p>",
        "phrase_remember": "<p>Sto bene, grazie.<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"I’m fine\\" → \\"Sto bene\\"<\\/strong>: <strong>stare<\\/strong> + the <em>adverb<\\/em> \\"<strong>bene<\\/strong>\\". Never \\"<strong>Sono bene<\\/strong>\\".<\\/li><li>\\"<strong>Bene<\\/strong>\\" does not change for a boy or a girl; do not say \\"<strong>Sto buona<\\/strong>\\" for this answer.<\\/li><li><strong>\\"thank you\\" → \\"grazie\\"<\\/strong>: a ready-made stamp that stands alone — no I in front.<\\/li><\\/ul>"
    }
]`


## Model note
You are generating the compare variant as: Grok 4.6. Follow the rules. Output ONLY the JSON file, no commentary.
',
      'outputs' => 
      array (
        0 => '<p><strong>"Hello" → "<em>Ciao</em>" </strong></p>
<p>💡 "<em>Ciao</em>" is the friendly Italian hello. In English, <strong>Hello</strong> works with almost everyone: a friend, a teacher, a person in a shop. Italian splits this in two. Use "<em>Ciao</em>" with <strong>friends</strong>, family and people your age. Use "<em>Buongiorno</em>" when you want to be more polite. "<em>Ciao</em>" is <strong>not a verb</strong>, so it never changes: a boy, a girl or a group all say the same word. You can add a name: "<em>Ciao, Lemo</em>". You can greet everybody: "<em>Ciao a tutti</em>". English Hello is mostly for when you arrive. Italian "<em>Ciao</em>" can start a chat <strong>and</strong> close it. There is no I and no you in this line. If you later want a real verb for "to greet", Italian uses "<em>salutare</em>".</p>
<p>💬 Example: You see your friend at school: "<em>Ciao, Connie!</em>" (Hello, Connie!).<br />💬 Example: You walk into a room full of friends: "<em>Ciao a tutti!</em>" (Hello everybody!).<br />💬 Example: You leave the train and wave: "<em>Ciao!</em>" (Bye!). The same word works for goodbye.</p>
<p>⚠️ ❌ "<em>Ciao</em>" to a shop helper you have not met. It is too friendly and can sound rude.<br />✅ "<em>Buongiorno</em>" in a shop or with adults you do not know.</p>
<p>⚠️ ❌ Changing "<em>Ciao</em>" like a verb, as if a group needed a new ending.<br />✅ Leave "<em>Ciao</em>" as it is. It never changes.</p>
<p>⚠️ ❌ Using "<em>Ciao</em>" only when you arrive, like English Hello.<br />✅ Use "<em>Ciao</em>" to say hello <strong>and</strong> to say goodbye.</p>
<p>📌 In short: with friends say "<em>Ciao</em>" when you arrive and when you leave. With a shop helper, "<em>Buongiorno</em>" is safer.</p>
<p><strong>Full conjugation of the present of "salutare":</strong> <br />io saluto (I greet)<br />tu saluti (you greet)<br />lui/lei saluta (he/she greets)<br />noi salutiamo (we greet)<br />voi salutate (you greet)<br />loro salutano (they greet).</p>
<p><strong>Remember:</strong> "<em>Ciao</em>" is the informal stamp. It can start and finish a chat. For a shop helper you have not met, "<em>Buongiorno</em>" is safer.</p>
<p>📜 <strong>Etymology curiosity:</strong><br />"<em>Ciao</em>" comes from an old Venetian phrase: "<em>s-ciào vostro</em>". It was a deep bow. It meant "I am your servant". The short word "<em>s-ciào</em>" is a cousin of Italian "<em>schiavo</em>", which still means "slave". English "slave" comes from the same old family. The heavy meaning went away. Children now use "<em>Ciao</em>" as a light hello and goodbye. English later borrowed the word "ciao" from Italian.</p>',
        1 => '<p><strong>"Good morning" → "<em>Buongiorno</em>" </strong></p>
<p>💡 English keeps <strong>two words</strong>: Good + morning. Italian writes them as <strong>one word</strong>: "<em>buono</em>" (good) + "<em>giorno</em>" (day). "<em>Giorno</em>" is a masculine word, so the greeting is "<em>Buongiorno</em>", never "<em>Buonagiorno</em>". The same trick gives "<em>Buonasera</em>" in the evening, because "<em>sera</em>" means "evening" and it is feminine. "<em>Buongiorno</em>" is not a verb, so it does not change for a man, a woman or a group. You can add a name: "<em>Buongiorno, Connie</em>". Do not copy Good morning as "<em>Buona mattina</em>". "<em>Mattina</em>" means "morning", but the greeting uses "<em>giorno</em>" (day).</p>
<p>💬 Example: You enter a bakery: "<em>Buongiorno!</em>" (Good morning!).<br />💬 Example: You see a teacher at school: "<em>Buongiorno!</em>" (Good morning!).<br />💬 Example: On the train you greet someone politely: "<em>Buongiorno, Connie</em>" (Good morning, Connie).</p>
<p>⚠️ ❌ "<em>Buona mattina</em>". That copies English word for word. Italians do not greet people that way.<br />✅ "<em>Buongiorno</em>". The greeting uses "<em>giorno</em>", not "<em>mattina</em>".</p>
<p>⚠️ ❌ "<em>Buonagiorno</em>". "<em>Giorno</em>" is masculine, so you need "<em>buon</em>" + "<em>giorno</em>" stuck together.<br />✅ "<em>Buongiorno</em>".</p>
<p>⚠️ ❌ "<em>Buon giorno</em>" as two words.<br />✅ "<em>Buongiorno</em>" is written as <strong>one word</strong>.</p>
<p>📌 In short: say "<em>Buongiorno</em>" (good + day), never "<em>Buona mattina</em>".</p>
<p><strong>"how are you" → "<em>come stai</em>" </strong></p>
<p>💡 English builds this with <strong>to be</strong>: How are you? Italian uses "<em>stare</em>", a verb for <strong>how a person feels</strong>. "<em>Come</em>" means "how". "<em>Stai</em>" is the friendly you. The ending already says <strong>tu</strong>, so you do not write an extra you. Do not say "<em>Come sei</em>" for this question. "<em>Sei</em>" belongs to "<em>essere</em>" and asks what you are like, not how you feel today.</p>
<p>💬 Example: You meet a friend at the bar: "<em>Ciao! Come stai?</em>" (Hi! How are you?).<br />💬 Example: At school in the morning: "<em>Come stai oggi?</em>" (How are you today?).<br />💬 Example: A twin question is "<em>Come va?</em>" (How is it going?). It does not name you. "<em>Come stai?</em>" looks straight at your friend.</p>
<p>⚠️ ❌ "<em>Come sei?</em>". That uses "<em>essere</em>" and asks "what are you like?", not "how do you feel?".<br />✅ "<em>Come stai?</em>" with "<em>stare</em>".</p>
<p>⚠️ ❌ "<em>Come tu stai?</em>". You already have "you" inside "<em>stai</em>".<br />✅ "<em>Come stai?</em>". No extra you.</p>
<p>⚠️ ❌ "<em>Come sei tu?</em>". It mixes "<em>essere</em>" and an extra you.<br />✅ "<em>Come stai?</em>". Think "<em>stare</em>", not "to be".</p>
<p>📌 In short: to ask how a friend feels, use "<em>come</em>" + "<em>stai</em>", never "<em>Come sei</em>".</p>
<p><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).</p>
<p><strong>Remember:</strong> "<em>Buongiorno</em>" uses "<em>giorno</em>", not "<em>mattina</em>". For wellbeing, ask "<em>Come stai</em>" with "<em>stare</em>", not "<em>Come sei</em>" with "<em>essere</em>".</p>
<p>📜 <strong>Etymology curiosity:</strong><br />"<em>Buongiorno</em>" joins two old Latin words. Latin "bonus" meant "good". English still has "bonus" for something extra and good. Latin "diurnum" meant "the daytime", from "dies" (day). From that family come English "diary" (a book for each day) and "journey" (once "a day\'s travel"). Italian stuck them together: good + day. "<em>Stare</em>" comes from Latin "stare", which meant "to stand". English "stand", "stay" and "station" are from the same old word. Italian uses "<em>stare</em>" for how you feel, as if asking how you are "standing" today.</p>',
        2 => '<p><strong>"I’m fine" → "<em>Sto bene</em>" </strong></p>
<p>💡 English uses I am + an adjective: I am fine. Italian uses "<em>stare</em>" + "<em>bene</em>" (well). "<em>Sto</em>" is the <strong>io</strong> form of "<em>stare</em>". You do not say "<em>Sono bene</em>". "<em>Sono</em>" belongs to "<em>essere</em>" and names what you are, not how you feel. "<em>Bene</em>" here means "well", and it <strong>does not change</strong> for a boy or a girl. A girl still says "<em>Sto bene</em>", never "<em>Sto buona</em>" for this answer. The opposite of "<em>bene</em>" is "<em>male</em>": "<em>Sto male</em>" means "I feel bad".</p>
<p>💬 Example: A friend asks "<em>Come stai?</em>" and you answer: "<em>Sto bene, grazie</em>" (I\'m fine, thank you).<br />💬 Example: You feel ill: "<em>Sto male</em>" (I feel bad).<br />💬 Example: After "<em>grazie</em>" you can ask back: "<em>E tu?</em>" (And you?).</p>
<p>⚠️ ❌ "<em>Sono bene</em>". That copies I am fine with the wrong verb.<br />✅ "<em>Sto bene</em>". Feelings use "<em>stare</em>".</p>
<p>⚠️ ❌ "<em>Sto buona</em>" because you are a girl. "<em>Bene</em>" does not change.<br />✅ "<em>Sto bene</em>" for a boy and for a girl.</p>
<p>⚠️ ❌ "<em>Sono buono</em>" to mean you feel fine. That means "I am kind", not "I feel well".<br />✅ "<em>Sto bene</em>".</p>
<p>📌 In short: when you want "I\'m fine", think "<em>stare</em>" + "<em>bene</em>", never "<em>Sono bene</em>".</p>
<p><strong>"thank you" → "<em>grazie</em>" </strong></p>
<p>💡 "<em>Grazie</em>" is a ready-made thank you. It is <strong>not a verb</strong> here, so it does not change for one person or a group. You do not add I in front. To make it warmer, say "<em>Grazie mille</em>" (thanks a lot). "<em>Mille</em>" means "a thousand": thanks a thousand.</p>
<p>💬 Example: The waiter brings your water: "<em>Grazie!</em>" (Thank you!).<br />💬 Example: A friend lends you a pen: "<em>Grazie mille!</em>" (Thanks a lot!).<br />💬 Example: In a shop, the helper gives you your change: "<em>Grazie!</em>" (Thank you!).</p>
<p>⚠️ ❌ "<em>Io grazie</em>". You do not put I in front.<br />✅ "<em>Grazie</em>" stands alone.</p>
<p>⚠️ ❌ "<em>Ti grazie</em>". That copies the English order thank + you.<br />✅ "<em>Grazie</em>". No extra you.</p>
<p>⚠️ ❌ "<em>Grazia</em>". That word exists, but it means "grace", not "thank you".<br />✅ "<em>Grazie</em>" with the final e.</p>
<p>📌 In short: "thank you" is just "<em>grazie</em>". No I in front. For a bigger thank you, "<em>Grazie mille</em>".</p>
<p><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).</p>
<p><strong>Remember:</strong> wellbeing takes "<em>stare</em>" + "<em>bene</em>". Do not copy I am fine as "<em>Sono bene</em>". "<em>Grazie</em>" stands alone.</p>
<p>📜 <strong>Etymology curiosity:</strong><br />"<em>Bene</em>" comes from Latin "bene", which already meant "well". English "benefit" is from the same family: a good thing that comes to you. "<em>Grazie</em>" comes from Latin "gratias" (thanks), from a word that meant "something pleasing". English "grace" and "grateful" are cousins. So when you say "<em>grazie</em>", you are close to saying "I am grateful".</p>',
      ),
    ),
    'gemini' => 
    array (
      'label' => 'Gemini 3.1 Pro',
      'slug' => 'gemini-3.1-pro',
      'provider' => 'Google (via Cursor)',
      'notes' => 'Generated in Cursor Agent with Gemini 3.1 Pro. Same quality-control prompt as the other columns.',
      'prompt' => '# Prompt — Quality control notes (Model compare)

You are a grammar-notes editor for LoveReWrite.
Rewrite ONLY the `phrase_grammar` field (translation tips) following the rules below.

## Story context
- Titles: The First 50 Words / Le prime 50 parole
- Cover line: Una notte a Vienna
- Live ID: 3661 · Local ID: 3429
- Level: A1 · 50 phrases
- Known language (`_llm_known_lang`): **en** (English) — ALL prose in English
- Target language (`_llm_target_lang`): **it** (Italian)
- Summary: Fifty simple sentences. Two animals who have never met. One night to get to know each other. (Lemo the Italian lemur and Connie the English rabbit meet on a train to Vienna.)

## What to rewrite
- Rewrite ONLY the prose AFTER `<strong>…</strong>` titles.
- Do NOT change: strong titles (pairs `"…" → "…"`, Full conjugation, Remember, Etymology curiosity), conjugation lists, Italian words in quotes/italics.
- Do NOT touch: phrase_alt, phrase_remember, pronunciation.
- Do not add/remove/reorder structural paragraphs vs the skeleton (same strong titles, same order). You MAY use multiple emoji `<p>` blocks inside explanations.

## Explanation structure (after each pair bold title)
- 💡 mechanism in very simple words (12–13 year old), with everyday examples
- 💬 at least 2–3 real examples introduced by "Example:" (bar, school, friends, shop), with English gloss
- ⚠️ 2–3 typical errors ❌ vs ✅
- 📌 In short: …
- 📜 Etymology curiosity: REAL historical origin (Latin / Indo-European), old meaning, links to English/Italian; easy vocabulary

Emoji rules:
- One emoji = one new `<p>` (never two different emoji in the same `<p>` with `<br />`)
- Multiple consecutive 💬 may share one `<p>` separated by `<br />`
- Target IT words always as `"<em>word</em>"`
- Use `+` instead of the word "plus" in technical joins (e.g. stare + bene)
- No IPA, no textbook acronyms
- Remember: leave it IDENTICAL

## Required output
Write a UTF-8 JSON file (real characters, NOT `\\uXXXX`; real HTML `</p>` not `<\\/p>`) to:

`C:/xampp/htdocs/localloverewrite202608/database/_tmp_out_3661_gemini.json`

Exact structure:
```json
{
  "0": "<p>...</p>",
  "1": "<p>...</p>",
  "2": "<p>...</p>"
}
```
Keys = sort_order 0, 1, 2.
Value = full rewritten phrase_grammar HTML.

## Phrases to rewrite (JSON)
`[
    {
        "sort_order": "0",
        "phrase_interface": "Hello!",
        "phrase_target": "Ciao!",
        "phrase_grammar": "<p><strong>\\"Hello\\" → \\"<em>Ciao<\\/em>\\" <\\/strong><br \\/>\\"<em>Ciao<\\/em>\\" is the friendly Italian hello. English Hello works with almost everyone. Italian splits the door: \\"<em>Ciao<\\/em>\\" for friends, family and people your age; \\"<em>Buongiorno<\\/em>\\" when the meeting feels more polite. <br \\/> \\"<em>Ciao<\\/em>\\" is not a verb, so it never changes for a boy, a girl or a group. You can add a name: \\"<em>Ciao, Lemo<\\/em>\\". You can greet everybody with \\"<em>Ciao a tutti<\\/em>\\". <br \\/> English Hello is mostly an arrival word. Italian \\"<em>Ciao<\\/em>\\" can open a chat and close it too, like a small wave when you leave.<\\/p>\\n<p>There is no I and no you in this line. When you later want a real verb, Italian uses \\"<em>salutare<\\/em>\\" (to greet). <br \\/><br \\/><strong>Full conjugation of the present of \\"salutare\\":<\\/strong> <br \\/>io saluto (I greet)<br \\/>tu saluti (you greet)<br \\/>lui\\/lei saluta (he\\/she greets)<br \\/>noi salutiamo (we greet)<br \\/>voi salutate (you greet)<br \\/>loro salutano (they greet).<\\/p>\\n<p>Examples: \\"<em>Saluto Connie<\\/em>\\" (I greet Connie), \\"<em>Salutiamo gli amici<\\/em>\\" (We greet our friends). After \\"<em>salutare<\\/em>\\" you name the person directly.<\\/p>\\n<p><strong>Remember:<\\/strong> \\"<em>Ciao<\\/em>\\" is the informal stamp. It can start and finish a chat. For a shop helper you have not met, \\"<em>Buongiorno<\\/em>\\" is safer.<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Ciao<\\/em>\\" comes from the old Venetian phrase \\"<em>s-ciào vostro<\\/em>\\", once a bow meaning I am your servant. The heavy meaning melted away, and children now use it as a light hello.<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Ciao!<\\/em>\\"<br \\/>could be \\"<em>Salve!<\\/em>\\"<br \\/>In English that is \\"Hello!\\"<br \\/>\\"<em>Salve<\\/em>\\" is more formal than \\"<em>Ciao<\\/em>\\" and less tied to morning than \\"<em>Buongiorno<\\/em>\\". Speakers do not usually use \\"<em>Salve<\\/em>\\" as a goodbye.<\\/p>",
        "phrase_remember": "<p>Ciao!<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"Hello\\" → \\"Ciao\\"<\\/strong>: informal <em>greeting<\\/em> for friends, family and people your age.<\\/li><li>\\"<strong>Ciao<\\/strong>\\" is not a verb: it never changes, and there is no I or you in the line.<\\/li><li>The same stamp can open and close a chat; for a more polite meeting use \\"<strong>Buongiorno<\\/strong>\\".<\\/li><\\/ul>"
    },
    {
        "sort_order": "1",
        "phrase_interface": "Good morning, how are you?",
        "phrase_target": "Buongiorno, come stai?",
        "phrase_grammar": "<p><strong>\\"Good morning\\" → \\"<em>Buongiorno<\\/em>\\" <\\/strong><br \\/>English keeps two words. Italian glues them into one: \\"<em>buono<\\/em>\\" (good) plus \\"<em>giorno<\\/em>\\" (day). \\"<em>Giorno<\\/em>\\" is masculine, so the greeting is \\"<em>Buongiorno<\\/em>\\", never \\"<em>Buonagiorno<\\/em>\\". <br \\/> The same trick gives \\"<em>Buonasera<\\/em>\\" in the evening, because \\"<em>sera<\\/em>\\" is feminine. \\"<em>Buongiorno<\\/em>\\" is a set formula, not a verb, so it does not change for a man, a woman or a group. You can add a name: \\"<em>Buongiorno, Connie<\\/em>\\". <br \\/> Do not copy Good morning as \\"<em>Buona mattina<\\/em>\\". \\"<em>Mattina<\\/em>\\" means \\"<em>morning<\\/em>\\", but the greeting uses \\"<em>giorno<\\/em>\\".<\\/p>\\n<p><strong>\\"how are you\\" → \\"<em>come stai<\\/em>\\" <\\/strong><br \\/>English builds this with to be. Italian uses \\"<em>stare<\\/em>\\", a verb for how a person feels. \\"<em>Come<\\/em>\\" means \\"<em>how<\\/em>\\". <br \\/> \\"<em>Stai<\\/em>\\" is the friendly you. The ending already says tu, so you do not write an extra you. Do not say \\"<em>Come sei<\\/em>\\" for this question. <br \\/> \\"<em>Sei<\\/em>\\" belongs to \\"<em>essere<\\/em>\\" and asks what you are like, not how you feel today.<\\/p>\\n<p><strong>Full conjugation of the present of \\"stare\\":<\\/strong> <br \\/>io sto (I am \\/ I stay)<br \\/>tu stai (you are \\/ you stay)<br \\/>lui\\/lei sta (he\\/she is \\/ stays)<br \\/>noi stiamo (we are \\/ we stay)<br \\/>voi state (you are \\/ you stay)<br \\/>loro stanno (they are \\/ they stay).<\\/p>\\n<p>Examples: \\"<em>Come stai oggi?<\\/em>\\" (How are you today?), \\"<em>Stiamo bene<\\/em>\\" (We are well). A twin question is \\"<em>Come va<\\/em>\\", which means \\"<em>how is it going<\\/em>\\".<\\/p>\\n<p><strong>Remember:<\\/strong> \\"<em>Buongiorno<\\/em>\\" uses \\"<em>giorno<\\/em>\\", not \\"<em>mattina<\\/em>\\". For wellbeing, ask \\"<em>Come stai<\\/em>\\" with \\"<em>stare<\\/em>\\", not \\"<em>Come sei<\\/em>\\" with \\"<em>essere<\\/em>\\".<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Buongiorno<\\/em>\\" joins Latin bonus (good) and diurnum (daytime), cousin of English diary and journey. \\"<em>Stare<\\/em>\\" comes from Latin stare (to stand).<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Buongiorno, come stai?<\\/em>\\"<br \\/>could be \\"<em>Buongiorno, come va?<\\/em>\\"<br \\/>In English that is \\"Good morning, how\'s it going?\\"<br \\/>\\"<em>Come va<\\/em>\\" does not name you; \\"<em>Come stai<\\/em>\\" looks straight at the friend.<\\/p>",
        "phrase_remember": "<p>Buongiorno, come stai?<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"Good morning\\" → \\"Buongiorno\\"<\\/strong>: one word, built on \\"<strong>giorno<\\/strong>\\", never \\"<strong>Buona mattina<\\/strong>\\".<\\/li><li><strong>\\"how are you\\" → \\"come stai\\"<\\/strong>: ask feelings with <strong>stare<\\/strong>, not <em>essere<\\/em> — not \\"<strong>Come sei<\\/strong>\\".<\\/li><li>\\"<strong>Come<\\/strong>\\" means \\"<em>how<\\/em>\\"; \\"<strong>stai<\\/strong>\\" already says <em>tu<\\/em>, so do not add you.<\\/li><\\/ul>"
    },
    {
        "sort_order": "2",
        "phrase_interface": "I’m fine, thank you.",
        "phrase_target": "Sto bene, grazie.",
        "phrase_grammar": "<p><strong>\\"I’m fine\\" → \\"<em>Sto bene<\\/em>\\" <\\/strong><br \\/>English uses I am with an adjective. Italian uses \\"<em>stare<\\/em>\\" plus \\"<em>bene<\\/em>\\" (well). \\"<em>Sto<\\/em>\\" is the io form of \\"<em>stare<\\/em>\\". <br \\/> You do not say \\"<em>Sono bene<\\/em>\\". \\"<em>Sono<\\/em>\\" belongs to \\"<em>essere<\\/em>\\" and names what you are, not how you feel. \\"<em>Bene<\\/em>\\" is an adverb here, so it does not change for a boy or a girl. <br \\/> A girl still says \\"<em>Sto bene<\\/em>\\", never \\"<em>Sto buona<\\/em>\\" for this answer.<\\/p>\\n<p><strong>\\"thank you\\" → \\"<em>grazie<\\/em>\\" <\\/strong><br \\/>\\"<em>Grazie<\\/em>\\" is a ready-made thank you. It does not change for one person or a group. You can make it warmer with \\"<em>Grazie mille<\\/em>\\" (thanks a lot). <br \\/> You do not add I in front.<\\/p>\\n<p><strong>Full conjugation of the present of \\"stare\\":<\\/strong> <br \\/>io sto (I am \\/ I stay)<br \\/>tu stai (you are \\/ you stay)<br \\/>lui\\/lei sta (he\\/she is \\/ stays)<br \\/>noi stiamo (we are \\/ we stay)<br \\/>voi state (you are \\/ you stay)<br \\/>loro stanno (they are \\/ they stay).<\\/p>\\n<p>Examples: \\"<em>Sto male<\\/em>\\" (I feel bad), \\"<em>Sta meglio<\\/em>\\" (He\\/She feels better), \\"<em>State bene?<\\/em>\\" (Are you well?). The opposite of \\"<em>bene<\\/em>\\" is \\"<em>male<\\/em>\\". To ask again you can say \\"<em>E tu?<\\/em>\\" after \\"<em>grazie<\\/em>\\".<\\/p>\\n<p><strong>Remember:<\\/strong> wellbeing takes \\"<em>stare<\\/em>\\" + \\"<em>bene<\\/em>\\". Do not copy I am fine as \\"<em>Sono bene<\\/em>\\". \\"<em>Grazie<\\/em>\\" stands alone.<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Bene<\\/em>\\" comes from Latin bene (well), cousin of English benefit. \\"<em>Grazie<\\/em>\\" comes from Latin gratias (thanks), the same family as English grace and grateful.<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Sto bene, grazie.<\\/em>\\"<br \\/>could be \\"<em>Tutto bene, grazie.<\\/em>\\"<br \\/>In English that is \\"All good, thank you.\\"<br \\/>\\"<em>Tutto bene<\\/em>\\" is a little more casual than \\"<em>Sto bene<\\/em>\\" and still uses \\"<em>bene<\\/em>\\", not \\"<em>essere<\\/em>\\".<\\/p>",
        "phrase_remember": "<p>Sto bene, grazie.<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"I’m fine\\" → \\"Sto bene\\"<\\/strong>: <strong>stare<\\/strong> + the <em>adverb<\\/em> \\"<strong>bene<\\/strong>\\". Never \\"<strong>Sono bene<\\/strong>\\".<\\/li><li>\\"<strong>Bene<\\/strong>\\" does not change for a boy or a girl; do not say \\"<strong>Sto buona<\\/strong>\\" for this answer.<\\/li><li><strong>\\"thank you\\" → \\"grazie\\"<\\/strong>: a ready-made stamp that stands alone — no I in front.<\\/li><\\/ul>"
    }
]`


## Model note
You are generating the compare variant as: Gemini 3.1 Pro. Follow the rules. Output ONLY the JSON file, no commentary.
',
      'outputs' => 
      array (
        0 => '<p><strong>"Hello" → "<em>Ciao</em>" </strong></p>
<p>💡 In Italian, you use different words depending on who you are talking to. You use "<em>Ciao</em>" with <strong>friends, family, and people your age</strong>. It works both when you arrive and when you leave. For people you do not know well, you use "<em>Buongiorno</em>".</p>
<p>💬 Example: "<em>Ciao, Lemo!</em>" (Hello, Lemo!) when meeting a friend at the park.<br />💬 Example: "<em>Ciao a tutti!</em>" (Hello everyone!) when entering a room.<br />💬 Example: "<em>Ciao, a domani!</em>" (Bye, see you tomorrow!) when leaving school.</p>
<p>⚠️ ❌ "<em>Ciao!</em>" to a shop owner you do not know.<br />⚠️ ✅ "<em>Buongiorno!</em>" to a shop owner you do not know.</p>
<p>📌 In short: use "<em>Ciao</em>" for <strong>friends</strong> to say hello and goodbye, and "<em>Buongiorno</em>" for <strong>polite meetings</strong>.</p>
<p>💡 When you want a <strong>real verb</strong> to say that you greet someone, Italian uses "<em>salutare</em>". You just put the name of the person right after it.</p>
<p><strong>Full conjugation of the present of "salutare":</strong> <br />io saluto (I greet)<br />tu saluti (you greet)<br />lui/lei saluta (he/she greets)<br />noi salutiamo (we greet)<br />voi salutate (you greet)<br />loro salutano (they greet).</p>
<p>💬 Example: "<em>Io saluto Connie.</em>" (I greet Connie.)<br />💬 Example: "<em>Noi salutiamo gli amici.</em>" (We greet our friends.)</p>
<p><strong>Remember:</strong> "<em>Ciao</em>" is the informal stamp. It can start and finish a chat. For a shop helper you have not met, "<em>Buongiorno</em>" is safer.</p>
<p>📜 <strong>Etymology curiosity:</strong> "<em>Ciao</em>" comes from the old Venetian phrase "<em>s-ciào vostro</em>", which meant "I am your slave". Over time, people forgot the heavy meaning and started using it as a simple, friendly hello. It comes from the Latin word "sclavus".</p>',
        1 => '<p><strong>"Good morning" → "<em>Buongiorno</em>" </strong></p>
<p>💡 English uses two words, but Italian <strong>glues them together</strong>: "<em>buono</em>" (good) + "<em>giorno</em>" (day). Because "<em>giorno</em>" is a <strong>masculine</strong> word, we say "<em>Buongiorno</em>". It is a fixed greeting, so it <strong>never changes</strong>, no matter who you are talking to.</p>
<p>💬 Example: "<em>Buongiorno, Lemo!</em>" (Good morning, Lemo!) when you see a friend in the morning.<br />💬 Example: "<em>Buongiorno a tutti!</em>" (Good morning everyone!) when you walk into a classroom.<br />💬 Example: "<em>Buongiorno, signora.</em>" (Good morning, madam.) when you greet a teacher.</p>
<p>⚠️ ❌ "<em>Buona mattina!</em>" to say good morning.<br />⚠️ ✅ "<em>Buongiorno!</em>" to say good morning.</p>
<p>📌 In short: always use the <strong>single word</strong> "<em>Buongiorno</em>" to say good morning.</p>
<p><strong>"how are you" → "<em>come stai</em>" </strong></p>
<p>💡 In English, you ask how someone is. In Italian, you ask how someone <strong>stays or feels</strong>, using the verb "<em>stare</em>". "<em>Come</em>" means "<em>how</em>". Since the ending of "<em>stai</em>" already tells us it means "<em>tu</em>" (you), we <strong>do not need</strong> to add the word for you.</p>
<p>💬 Example: "<em>Come stai oggi?</em>" (How are you today?) when asking a friend about their day.<br />💬 Example: "<em>Ciao, come stai?</em>" (Hello, how are you?) when meeting someone you know well.</p>
<p>⚠️ ❌ "<em>Come sei?</em>" to ask how a friend feels today. This asks what they are like as a person.<br />⚠️ ✅ "<em>Come stai?</em>" to ask how a friend feels today.</p>
<p>📌 In short: use "<em>stare</em>" to ask about <strong>feelings and health</strong>, not "<em>essere</em>".</p>
<p><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).</p>
<p>💬 Example: "<em>Io sto bene.</em>" (I am well.)<br />💬 Example: "<em>Noi stiamo bene.</em>" (We are well.)</p>
<p><strong>Remember:</strong> "<em>Buongiorno</em>" uses "<em>giorno</em>", not "<em>mattina</em>". For wellbeing, ask "<em>Come stai</em>" with "<em>stare</em>", not "<em>Come sei</em>" with "<em>essere</em>".</p>
<p>📜 <strong>Etymology curiosity:</strong> "<em>Buongiorno</em>" joins the Latin words "bonus" (good) and "diurnum" (daytime). "Diurnum" is also the father of the English words "diary" and "journey". "<em>Stare</em>" comes from the Latin word "stare", which meant to stand up straight.</p>',
        2 => '<p><strong>"I’m fine" → "<em>Sto bene</em>" </strong></p>
<p>💡 To say you are fine, Italian uses the verb "<em>stare</em>" + "<em>bene</em>" (well). "<em>Sto</em>" is the form for I. Because "<em>bene</em>" describes <strong>how you feel</strong>, it <strong>never changes</strong>. A boy and a girl both say "<em>Sto bene</em>".</p>
<p>💬 Example: "<em>Sto bene, e tu?</em>" (I am fine, and you?) when a friend asks how you are.<br />💬 Example: "<em>Oggi sto bene.</em>" (Today I am fine.) when talking about your health.</p>
<p>⚠️ ❌ "<em>Sono bene.</em>" to say you are fine.<br />⚠️ ❌ "<em>Sto buona.</em>" if you are a girl saying you are fine.<br />⚠️ ✅ "<em>Sto bene.</em>" for anyone to say they are fine.</p>
<p>📌 In short: always use "<em>stare</em>" + "<em>bene</em>" to talk about your <strong>health or feelings</strong>.</p>
<p><strong>"thank you" → "<em>grazie</em>" </strong></p>
<p>💡 "<em>Grazie</em>" is the standard word to say thank you. It is a <strong>fixed word</strong>, so it <strong>never changes</strong>, whether you are thanking one friend or a whole group of people. You <strong>do not need</strong> to put I in front of it.</p>
<p>💬 Example: "<em>Grazie per il regalo!</em>" (Thank you for the gift!) when someone gives you a present.<br />💬 Example: "<em>Sto bene, grazie.</em>" (I am fine, thank you.) when someone asks how you are.<br />💬 Example: "<em>Grazie mille!</em>" (Thanks a lot!) when you are very happy for some help.</p>
<p>⚠️ ❌ "<em>Io grazie.</em>" to say I thank you.<br />⚠️ ✅ "<em>Grazie.</em>" to say thank you.</p>
<p>📌 In short: just say "<em>Grazie</em>" <strong>all by itself</strong> to thank anyone.</p>
<p><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).</p>
<p>💬 Example: "<em>Io sto male.</em>" (I feel bad.)<br />💬 Example: "<em>Lui sta meglio.</em>" (He feels better.)<br />💬 Example: "<em>Voi state bene?</em>" (Are you well?)</p>
<p><strong>Remember:</strong> wellbeing takes "<em>stare</em>" + "<em>bene</em>". Do not copy I am fine as "<em>Sono bene</em>". "<em>Grazie</em>" stands alone.</p>
<p>📜 <strong>Etymology curiosity:</strong> "<em>Bene</em>" comes from the Latin word "bene" (well), which gave English the word "benefit". "<em>Grazie</em>" comes from the Latin word "gratias" (thanks), which belongs to the same family as the English words "grace" and "grateful".</p>',
      ),
    ),
    'claude' => 
    array (
      'label' => 'Claude Opus 4.8',
      'slug' => 'claude-opus-4-8-thinking-high',
      'provider' => 'Anthropic (via Cursor)',
      'notes' => 'Generated in Cursor Agent with Claude Opus 4.8 (thinking high). Same quality-control prompt as the other columns.',
      'prompt' => '# Prompt — Quality control notes (Model compare)

You are a grammar-notes editor for LoveReWrite.
Rewrite ONLY the `phrase_grammar` field (translation tips) following the rules below.

## Story context
- Titles: The First 50 Words / Le prime 50 parole
- Cover line: Una notte a Vienna
- Live ID: 3661 · Local ID: 3429
- Level: A1 · 50 phrases
- Known language (`_llm_known_lang`): **en** (English) — ALL prose in English
- Target language (`_llm_target_lang`): **it** (Italian)
- Summary: Fifty simple sentences. Two animals who have never met. One night to get to know each other. (Lemo the Italian lemur and Connie the English rabbit meet on a train to Vienna.)

## What to rewrite
- Rewrite ONLY the prose AFTER `<strong>…</strong>` titles.
- Do NOT change: strong titles (pairs `"…" → "…"`, Full conjugation, Remember, Etymology curiosity), conjugation lists, Italian words in quotes/italics.
- Do NOT touch: phrase_alt, phrase_remember, pronunciation.
- Do not add/remove/reorder structural paragraphs vs the skeleton (same strong titles, same order). You MAY use multiple emoji `<p>` blocks inside explanations.

## Explanation structure (after each pair bold title)
- 💡 mechanism in very simple words (12–13 year old), with everyday examples
- 💬 at least 2–3 real examples introduced by "Example:" (bar, school, friends, shop), with English gloss
- ⚠️ 2–3 typical errors ❌ vs ✅
- 📌 In short: …
- 📜 Etymology curiosity: REAL historical origin (Latin / Indo-European), old meaning, links to English/Italian; easy vocabulary

Emoji rules:
- One emoji = one new `<p>` (never two different emoji in the same `<p>` with `<br />`)
- Multiple consecutive 💬 may share one `<p>` separated by `<br />`
- Target IT words always as `"<em>word</em>"`
- Use `+` instead of the word "plus" in technical joins (e.g. stare + bene)
- No IPA, no textbook acronyms
- Remember: leave it IDENTICAL

## Required output
Write a UTF-8 JSON file (real characters, NOT `\\uXXXX`; real HTML `</p>` not `<\\/p>`) to:

`C:/xampp/htdocs/localloverewrite202608/database/_tmp_out_3661_claude.json`

Exact structure:
```json
{
  "0": "<p>...</p>",
  "1": "<p>...</p>",
  "2": "<p>...</p>"
}
```
Keys = sort_order 0, 1, 2.
Value = full rewritten phrase_grammar HTML.

## Phrases to rewrite (JSON)
`[
    {
        "sort_order": "0",
        "phrase_interface": "Hello!",
        "phrase_target": "Ciao!",
        "phrase_grammar": "<p><strong>\\"Hello\\" → \\"<em>Ciao<\\/em>\\" <\\/strong><br \\/>\\"<em>Ciao<\\/em>\\" is the friendly Italian hello. English Hello works with almost everyone. Italian splits the door: \\"<em>Ciao<\\/em>\\" for friends, family and people your age; \\"<em>Buongiorno<\\/em>\\" when the meeting feels more polite. <br \\/> \\"<em>Ciao<\\/em>\\" is not a verb, so it never changes for a boy, a girl or a group. You can add a name: \\"<em>Ciao, Lemo<\\/em>\\". You can greet everybody with \\"<em>Ciao a tutti<\\/em>\\". <br \\/> English Hello is mostly an arrival word. Italian \\"<em>Ciao<\\/em>\\" can open a chat and close it too, like a small wave when you leave.<\\/p>\\n<p>There is no I and no you in this line. When you later want a real verb, Italian uses \\"<em>salutare<\\/em>\\" (to greet). <br \\/><br \\/><strong>Full conjugation of the present of \\"salutare\\":<\\/strong> <br \\/>io saluto (I greet)<br \\/>tu saluti (you greet)<br \\/>lui\\/lei saluta (he\\/she greets)<br \\/>noi salutiamo (we greet)<br \\/>voi salutate (you greet)<br \\/>loro salutano (they greet).<\\/p>\\n<p>Examples: \\"<em>Saluto Connie<\\/em>\\" (I greet Connie), \\"<em>Salutiamo gli amici<\\/em>\\" (We greet our friends). After \\"<em>salutare<\\/em>\\" you name the person directly.<\\/p>\\n<p><strong>Remember:<\\/strong> \\"<em>Ciao<\\/em>\\" is the informal stamp. It can start and finish a chat. For a shop helper you have not met, \\"<em>Buongiorno<\\/em>\\" is safer.<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Ciao<\\/em>\\" comes from the old Venetian phrase \\"<em>s-ciào vostro<\\/em>\\", once a bow meaning I am your servant. The heavy meaning melted away, and children now use it as a light hello.<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Ciao!<\\/em>\\"<br \\/>could be \\"<em>Salve!<\\/em>\\"<br \\/>In English that is \\"Hello!\\"<br \\/>\\"<em>Salve<\\/em>\\" is more formal than \\"<em>Ciao<\\/em>\\" and less tied to morning than \\"<em>Buongiorno<\\/em>\\". Speakers do not usually use \\"<em>Salve<\\/em>\\" as a goodbye.<\\/p>",
        "phrase_remember": "<p>Ciao!<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"Hello\\" → \\"Ciao\\"<\\/strong>: informal <em>greeting<\\/em> for friends, family and people your age.<\\/li><li>\\"<strong>Ciao<\\/strong>\\" is not a verb: it never changes, and there is no I or you in the line.<\\/li><li>The same stamp can open and close a chat; for a more polite meeting use \\"<strong>Buongiorno<\\/strong>\\".<\\/li><\\/ul>"
    },
    {
        "sort_order": "1",
        "phrase_interface": "Good morning, how are you?",
        "phrase_target": "Buongiorno, come stai?",
        "phrase_grammar": "<p><strong>\\"Good morning\\" → \\"<em>Buongiorno<\\/em>\\" <\\/strong><br \\/>English keeps two words. Italian glues them into one: \\"<em>buono<\\/em>\\" (good) plus \\"<em>giorno<\\/em>\\" (day). \\"<em>Giorno<\\/em>\\" is masculine, so the greeting is \\"<em>Buongiorno<\\/em>\\", never \\"<em>Buonagiorno<\\/em>\\". <br \\/> The same trick gives \\"<em>Buonasera<\\/em>\\" in the evening, because \\"<em>sera<\\/em>\\" is feminine. \\"<em>Buongiorno<\\/em>\\" is a set formula, not a verb, so it does not change for a man, a woman or a group. You can add a name: \\"<em>Buongiorno, Connie<\\/em>\\". <br \\/> Do not copy Good morning as \\"<em>Buona mattina<\\/em>\\". \\"<em>Mattina<\\/em>\\" means \\"<em>morning<\\/em>\\", but the greeting uses \\"<em>giorno<\\/em>\\".<\\/p>\\n<p><strong>\\"how are you\\" → \\"<em>come stai<\\/em>\\" <\\/strong><br \\/>English builds this with to be. Italian uses \\"<em>stare<\\/em>\\", a verb for how a person feels. \\"<em>Come<\\/em>\\" means \\"<em>how<\\/em>\\". <br \\/> \\"<em>Stai<\\/em>\\" is the friendly you. The ending already says tu, so you do not write an extra you. Do not say \\"<em>Come sei<\\/em>\\" for this question. <br \\/> \\"<em>Sei<\\/em>\\" belongs to \\"<em>essere<\\/em>\\" and asks what you are like, not how you feel today.<\\/p>\\n<p><strong>Full conjugation of the present of \\"stare\\":<\\/strong> <br \\/>io sto (I am \\/ I stay)<br \\/>tu stai (you are \\/ you stay)<br \\/>lui\\/lei sta (he\\/she is \\/ stays)<br \\/>noi stiamo (we are \\/ we stay)<br \\/>voi state (you are \\/ you stay)<br \\/>loro stanno (they are \\/ they stay).<\\/p>\\n<p>Examples: \\"<em>Come stai oggi?<\\/em>\\" (How are you today?), \\"<em>Stiamo bene<\\/em>\\" (We are well). A twin question is \\"<em>Come va<\\/em>\\", which means \\"<em>how is it going<\\/em>\\".<\\/p>\\n<p><strong>Remember:<\\/strong> \\"<em>Buongiorno<\\/em>\\" uses \\"<em>giorno<\\/em>\\", not \\"<em>mattina<\\/em>\\". For wellbeing, ask \\"<em>Come stai<\\/em>\\" with \\"<em>stare<\\/em>\\", not \\"<em>Come sei<\\/em>\\" with \\"<em>essere<\\/em>\\".<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Buongiorno<\\/em>\\" joins Latin bonus (good) and diurnum (daytime), cousin of English diary and journey. \\"<em>Stare<\\/em>\\" comes from Latin stare (to stand).<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Buongiorno, come stai?<\\/em>\\"<br \\/>could be \\"<em>Buongiorno, come va?<\\/em>\\"<br \\/>In English that is \\"Good morning, how\'s it going?\\"<br \\/>\\"<em>Come va<\\/em>\\" does not name you; \\"<em>Come stai<\\/em>\\" looks straight at the friend.<\\/p>",
        "phrase_remember": "<p>Buongiorno, come stai?<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"Good morning\\" → \\"Buongiorno\\"<\\/strong>: one word, built on \\"<strong>giorno<\\/strong>\\", never \\"<strong>Buona mattina<\\/strong>\\".<\\/li><li><strong>\\"how are you\\" → \\"come stai\\"<\\/strong>: ask feelings with <strong>stare<\\/strong>, not <em>essere<\\/em> — not \\"<strong>Come sei<\\/strong>\\".<\\/li><li>\\"<strong>Come<\\/strong>\\" means \\"<em>how<\\/em>\\"; \\"<strong>stai<\\/strong>\\" already says <em>tu<\\/em>, so do not add you.<\\/li><\\/ul>"
    },
    {
        "sort_order": "2",
        "phrase_interface": "I’m fine, thank you.",
        "phrase_target": "Sto bene, grazie.",
        "phrase_grammar": "<p><strong>\\"I’m fine\\" → \\"<em>Sto bene<\\/em>\\" <\\/strong><br \\/>English uses I am with an adjective. Italian uses \\"<em>stare<\\/em>\\" plus \\"<em>bene<\\/em>\\" (well). \\"<em>Sto<\\/em>\\" is the io form of \\"<em>stare<\\/em>\\". <br \\/> You do not say \\"<em>Sono bene<\\/em>\\". \\"<em>Sono<\\/em>\\" belongs to \\"<em>essere<\\/em>\\" and names what you are, not how you feel. \\"<em>Bene<\\/em>\\" is an adverb here, so it does not change for a boy or a girl. <br \\/> A girl still says \\"<em>Sto bene<\\/em>\\", never \\"<em>Sto buona<\\/em>\\" for this answer.<\\/p>\\n<p><strong>\\"thank you\\" → \\"<em>grazie<\\/em>\\" <\\/strong><br \\/>\\"<em>Grazie<\\/em>\\" is a ready-made thank you. It does not change for one person or a group. You can make it warmer with \\"<em>Grazie mille<\\/em>\\" (thanks a lot). <br \\/> You do not add I in front.<\\/p>\\n<p><strong>Full conjugation of the present of \\"stare\\":<\\/strong> <br \\/>io sto (I am \\/ I stay)<br \\/>tu stai (you are \\/ you stay)<br \\/>lui\\/lei sta (he\\/she is \\/ stays)<br \\/>noi stiamo (we are \\/ we stay)<br \\/>voi state (you are \\/ you stay)<br \\/>loro stanno (they are \\/ they stay).<\\/p>\\n<p>Examples: \\"<em>Sto male<\\/em>\\" (I feel bad), \\"<em>Sta meglio<\\/em>\\" (He\\/She feels better), \\"<em>State bene?<\\/em>\\" (Are you well?). The opposite of \\"<em>bene<\\/em>\\" is \\"<em>male<\\/em>\\". To ask again you can say \\"<em>E tu?<\\/em>\\" after \\"<em>grazie<\\/em>\\".<\\/p>\\n<p><strong>Remember:<\\/strong> wellbeing takes \\"<em>stare<\\/em>\\" + \\"<em>bene<\\/em>\\". Do not copy I am fine as \\"<em>Sono bene<\\/em>\\". \\"<em>Grazie<\\/em>\\" stands alone.<\\/p>\\n<p><strong>Etymology curiosity:<\\/strong> \\"<em>Bene<\\/em>\\" comes from Latin bene (well), cousin of English benefit. \\"<em>Grazie<\\/em>\\" comes from Latin gratias (thanks), the same family as English grace and grateful.<\\/p>",
        "phrase_alt": "<p>An alternative translation of \\"<em>Sto bene, grazie.<\\/em>\\"<br \\/>could be \\"<em>Tutto bene, grazie.<\\/em>\\"<br \\/>In English that is \\"All good, thank you.\\"<br \\/>\\"<em>Tutto bene<\\/em>\\" is a little more casual than \\"<em>Sto bene<\\/em>\\" and still uses \\"<em>bene<\\/em>\\", not \\"<em>essere<\\/em>\\".<\\/p>",
        "phrase_remember": "<p>Sto bene, grazie.<\\/p><p><strong>Remember:<\\/strong><\\/p><ul><li><strong>\\"I’m fine\\" → \\"Sto bene\\"<\\/strong>: <strong>stare<\\/strong> + the <em>adverb<\\/em> \\"<strong>bene<\\/strong>\\". Never \\"<strong>Sono bene<\\/strong>\\".<\\/li><li>\\"<strong>Bene<\\/strong>\\" does not change for a boy or a girl; do not say \\"<strong>Sto buona<\\/strong>\\" for this answer.<\\/li><li><strong>\\"thank you\\" → \\"grazie\\"<\\/strong>: a ready-made stamp that stands alone — no I in front.<\\/li><\\/ul>"
    }
]`


## Model note
You are generating the compare variant as: Claude Opus 4.8. Follow the rules. Output ONLY the JSON file, no commentary.
',
      'outputs' => 
      array (
        0 => '<p><strong>"Hello" → "<em>Ciao</em>" </strong><br />💡 In English you say "Hello" to almost everyone. Italian has two doors. Use "<em>Ciao</em>" with friends, family and people your own age. Use "<em>Buongiorno</em>" when you want to be polite, like with a shop worker you do not know. "<em>Ciao</em>" is not a verb, so it never changes for a boy, a girl or a group. You can add a name after it: "<em>Ciao, Lemo</em>". You can also greet a whole room with "<em>Ciao a tutti</em>" (hi everyone). In English "Hello" is mostly for when you arrive; Italian "<em>Ciao</em>" works both ways, so you can open a chat with it and close it too, like a small wave when you leave.</p>
<p>💬 Example: "<em>Ciao, Connie!</em>" — "Hi, Connie!" (you meet a friend on the train)<br />Example: "<em>Ciao ragazzi!</em>" — "Hi guys!" (you walk into class)<br />Example: "<em>Ciao, a domani!</em>" — "Bye, see you tomorrow!" (you leave the bar)</p>
<p>⚠️ ❌ "<em>Ciao, signor Rossi</em>" (to someone you must show respect) → ✅ "<em>Buongiorno, signor Rossi</em>" — with people you do not know, "<em>Ciao</em>" feels too close.<br />❌ "<em>Cia</em>" → ✅ "<em>Ciao</em>" — the word always keeps its final "<em>o</em>".</p>
<p>📌 In short: when you meet a friend, think "<em>Ciao</em>". When you want to be polite, think "<em>Buongiorno</em>".</p>
<p>This line has no I and no you: "<em>Ciao</em>" stands alone. When you want a real verb for "to greet", Italian uses "<em>salutare</em>".<br /><br /><strong>Full conjugation of the present of "salutare":</strong> <br />io saluto (I greet)<br />tu saluti (you greet)<br />lui/lei saluta (he/she greets)<br />noi salutiamo (we greet)<br />voi salutate (you greet)<br />loro salutano (they greet).</p>
<p>💬 Example: "<em>Saluto Connie</em>" — "I greet Connie"<br />Example: "<em>Salutiamo gli amici</em>" — "We greet our friends". After "<em>salutare</em>" you name the person straight away, with no extra word in between.</p>
<p><strong>Remember:</strong> "<em>Ciao</em>" is the informal stamp. It can start and finish a chat. For a shop helper you have not met, "<em>Buongiorno</em>" is safer.</p>
<p><strong>Etymology curiosity:</strong><br />📜 "<em>Ciao</em>" was born in Venice, from the old phrase "<em>s-ciào vostro</em>", which meant "I am your servant". Long ago people said it as a small bow of respect. Over time the heavy meaning fell away, and today it is just a light, friendly hello that even children use.</p>',
        1 => '<p><strong>"Good morning" → "<em>Buongiorno</em>" </strong><br />💡 English keeps two words. Italian glues them into one: "<em>buono</em>" (good) + "<em>giorno</em>" (day). "<em>Giorno</em>" is masculine, so the greeting is "<em>Buongiorno</em>", never "<em>Buonagiorno</em>". The same trick gives "<em>Buonasera</em>" in the evening, because "<em>sera</em>" is feminine. "<em>Buongiorno</em>" is a fixed word, not a verb, so it does not change for a man, a woman or a group. You can add a name: "<em>Buongiorno, Connie</em>".</p>
<p>💬 Example: "<em>Buongiorno!</em>" — "Good morning!" (you enter the bar)<br />Example: "<em>Buongiorno, dottore.</em>" — "Good morning, doctor." (at the doctor\'s)</p>
<p>⚠️ ❌ "<em>Buona mattina</em>" → ✅ "<em>Buongiorno</em>" — "<em>mattina</em>" does mean "morning", but the greeting always uses "<em>giorno</em>".<br />❌ "<em>Buonagiorno</em>" → ✅ "<em>Buongiorno</em>" — "<em>giorno</em>" is masculine, so you need "<em>buon</em>", not "<em>buona</em>".</p>
<p><strong>"how are you" → "<em>come stai</em>" </strong><br />💡 English builds this with "to be". Italian uses "<em>stare</em>", a verb for how a person feels. "<em>Come</em>" means "how". "<em>Stai</em>" is the friendly you, and the ending already says tu, so you do not add an extra you.</p>
<p>💬 Example: "<em>Ciao, come stai?</em>" — "Hi, how are you?" (you meet a friend)<br />Example: "<em>Come stai oggi?</em>" — "How are you today?"</p>
<p>⚠️ ❌ "<em>Come sei?</em>" → ✅ "<em>Come stai?</em>" — "<em>sei</em>" comes from "<em>essere</em>" and asks what you are like, not how you feel today.</p>
<p>📌 In short: for the greeting think "<em>giorno</em>", not "<em>mattina</em>"; to ask about feelings think "<em>stare</em>", not "<em>essere</em>".</p>
<p><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).</p>
<p>💬 Example: "<em>Stiamo bene</em>" — "We are well"<br />Example: "<em>Come va?</em>" — "How is it going?", a twin question that means almost the same as "<em>Come stai?</em>".</p>
<p><strong>Remember:</strong> "<em>Buongiorno</em>" uses "<em>giorno</em>", not "<em>mattina</em>". For wellbeing, ask "<em>Come stai</em>" with "<em>stare</em>", not "<em>Come sei</em>" with "<em>essere</em>".</p>
<p><strong>Etymology curiosity:</strong><br />📜 "<em>Buongiorno</em>" comes from two Latin words: bonus (good) and diurnum (daytime). That old word diurnum is the cousin of English "diary" and "journey", which once carried the idea of "day" inside them. "<em>Stare</em>" comes from the Latin verb stare, which meant "to stand".</p>',
        2 => '<p><strong>"I’m fine" → "<em>Sto bene</em>" </strong><br />💡 In English you say "I am" + an adjective. Italian uses "<em>stare</em>" + "<em>bene</em>" (well). "<em>Sto</em>" is the io form of "<em>stare</em>". "<em>Bene</em>" is an adverb here, so it does not change for a boy or a girl: a girl also says "<em>Sto bene</em>", never "<em>Sto buona</em>".</p>
<p>💬 Example: "<em>Come stai? Sto bene, grazie.</em>" — "How are you? I\'m fine, thanks."<br />Example: "<em>Oggi sto bene.</em>" — "Today I feel good."</p>
<p>⚠️ ❌ "<em>Sono bene</em>" → ✅ "<em>Sto bene</em>" — "<em>sono</em>" comes from "<em>essere</em>" and names what you are, not how you feel.<br />❌ "<em>Sto buona</em>" → ✅ "<em>Sto bene</em>" — for this answer you need the adverb "<em>bene</em>", which never changes.</p>
<p><strong>"thank you" → "<em>grazie</em>" </strong><br />💡 "<em>Grazie</em>" is a ready-made thank you. It does not change for one person or a group, and you do not put I in front. You can make it warmer with "<em>Grazie mille</em>" (thanks a lot).</p>
<p>💬 Example: "<em>Grazie!</em>" — "Thank you!" (someone holds the door)<br />Example: "<em>Grazie mille!</em>" — "Thanks a lot!" (a friend helps you)</p>
<p>⚠️ ❌ "<em>Io grazie</em>" → ✅ "<em>Grazie</em>" — the word stands alone, with no I before it.</p>
<p>📌 In short: for feelings say "<em>stare</em>" + "<em>bene</em>", not "<em>essere</em>"; to say thanks, "<em>grazie</em>" stands on its own.</p>
<p><strong>Full conjugation of the present of "stare":</strong> <br />io sto (I am / I stay)<br />tu stai (you are / you stay)<br />lui/lei sta (he/she is / stays)<br />noi stiamo (we are / we stay)<br />voi state (you are / you stay)<br />loro stanno (they are / they stay).</p>
<p>💬 Example: "<em>Sto male</em>" — "I feel bad"<br />Example: "<em>Sta meglio</em>" — "He/She feels better"<br />Example: "<em>State bene?</em>" — "Are you well?". The opposite of "<em>bene</em>" is "<em>male</em>", and after "<em>grazie</em>" you can send the question back with "<em>E tu?</em>" (And you?).</p>
<p><strong>Remember:</strong> wellbeing takes "<em>stare</em>" + "<em>bene</em>". Do not copy I am fine as "<em>Sono bene</em>". "<em>Grazie</em>" stands alone.</p>
<p><strong>Etymology curiosity:</strong><br />📜 "<em>Bene</em>" comes from Latin bene (well), the same root that gives English "benefit". "<em>Grazie</em>" comes from Latin gratias (thanks), the same family as English "grace" and "grateful" — so every time you say "<em>grazie</em>" you are really wishing someone a little grace.</p>',
      ),
    ),
  ),
);
