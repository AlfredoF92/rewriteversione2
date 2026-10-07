# Script — Crucintarsio

**Campo checklist:** `crossword`
**Cartella canonica:** `prompt/CREA STORIA/`
**Tipo:** CLI PHP + skill

Questo file e' il punto di ingresso per un'altra AI: leggilo (e gli allegati citati) per generare o aggiornare il campo.

## Come eseguire / usare

Segui la skill per regole parole/definizioni.

---

## Script
1. `script-crucintarsio-crea-definizioni.php` — dump + crea definizioni
2. `script-genera-crucintarsio.php` — genera griglia

Directory eseguibile: `wp-content/plugins/llm-con-tabelle/scripts/` (copie anche in questa cartella).

Skill Cursor: `.cursor/skills/crucintarsio-storia/SKILL.md`

## Comandi tipici
```
C:\xampp\php\php.exe wp-content/plugins/llm-con-tabelle/scripts/script-crucintarsio-crea-definizioni.php --story=ID --count=N
C:\xampp\php\php.exe wp-content/plugins/llm-con-tabelle/scripts/script-genera-crucintarsio.php --story=ID --intrecci=22 --varianti=6
```
