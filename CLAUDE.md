# Pravidla pro práci na tomto projektu

## Workflow
- Vyvíjej na větvi `claude/skautchlumec-wordpress-plugin-mcvbsz`
- Po každé smysluplné změně: commit → push → PR → squash merge do main
- PR popisky piš česky
- Při merge konfliktu: `git fetch origin main && git rebase origin/main && git push -f`

## Před každým mergem — povinná kontrola
Před vytvořením PR vždy spusť:
```
php -l vlcci-odborky.php
```
Pokud PHP hlásí chybu, nesmíš mergovat.

## Časté chyby — PHP single-quoted stringy
Hlavní PHP soubor používá `echo '<style>...</style>'` — celé CSS je uvnitř single-quoted stringu.
**Uvozovky v CSS (např. `font-family: 'Segoe UI'`) musí být escapované jako `\'Segoe UI\'`.**
Stejně tak jakékoli jiné jednoduché uvozovky uvnitř `echo '...'` bloků.

## Deploy
- Po mergi do main se automaticky spustí GitHub Actions a zavolá deploy webhook na serveru
- Token je uložen v GitHub secret `DEPLOY_SECRET`
- Soubor `deploy-webhook.php` na serveru má token nastaven ručně — nikdy ho neměň přes git
