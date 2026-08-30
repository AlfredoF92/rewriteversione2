# Script Caricamento Online — loverewrite.com

> Questo file NON va mai caricato sul server FTP.
> Usalo ogni volta che ti viene chiesto di caricare file online.

---

## Credenziali FTP

| Campo    | Valore                        |
|----------|-------------------------------|
| Hostname | cpanel19.vhosting-it.com      |
| Username | loverew1                      |
| Password | 42UZ*dOyr94W*s                |
| Porta    | 21 (FTP standard)             |
| Cartella | /public_html                  |

---

## Cartella locale di riferimento

```
C:\xampp\htdocs\localloverewrite
```

---

## Regole di caricamento

### File da NON toccare mai online
- `wp-config.php` → contiene le credenziali del database online, diverse da quelle locali
- `caricamento-online-script.md` → questo file stesso

### File/cartelle da ESCLUDERE dal caricamento
- `wp-config.php`
- `caricamento-online-script.md`
- `.git/` (cartella git locale)
- `database/` (database locali)
- `node_modules/` (se presente)
- `*.log`
- `.DS_Store`, `Thumbs.db`
- File di backup: `*.zip`, `*installer*.php`, `*archive*`, `dup-installer/`

### File/cartelle che SI possono caricare
- `wp-content/` (temi, plugin, uploads, ecc.) — tutto incluso
- `wp-admin/` (solo se aggiornato)
- `wp-includes/` (solo se aggiornato)
- `.htaccess`
- `index.php`
- Tutti gli altri file WordPress core (escluse le eccezioni sopra)

---

## Logica di sincronizzazione

1. Confronta la **data di modifica** di ogni file locale con quella del corrispondente file remoto via FTP
2. Carica solo i file locali **più recenti** rispetto alla versione online
3. Se un file non esiste ancora online, caricalo
4. Non eliminare mai file dal server che non esistono in locale (sicurezza)
5. Le modifiche vengono fatte **solo in locale** — il server non viene mai toccato manualmente

---

## Database

- I database locale e online sono **separati e indipendenti**
- Non sincronizzare mai il database automaticamente
- Operazioni sul DB solo se esplicitamente richieste dall'utente

---

## Strumento da usare per FTP

> Il server richiede **TLS** (`--ssl-reqd`). Senza SSL risponde `421 cleartext sessions not accepted`.

```powershell
curl.exe --ssl-reqd -u "loverew1:42UZ*dOyr94W*s" ftp://cpanel19.vhosting-it.com/public_html/
```

Per caricare un singolo file:
```powershell
curl.exe --ssl-reqd -u "loverew1:42UZ*dOyr94W*s" -T "percorso/file.php" ftp://cpanel19.vhosting-it.com/public_html/percorso/file.php
```

---

## Note aggiuntive

- Il sito usa **WordPress**
- Git è usato solo come backup/versioning locale — NON come sistema di deploy
- In caso di dubbio su un file, chiedere all'utente prima di caricare
