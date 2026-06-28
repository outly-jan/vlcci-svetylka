# Vlčí a světýlkové odborky

WordPress plugin pro evidenci plnění odborek vlčat a světlušek.  
Středisko Chlumec nad Cidlinou.

---

## Shortcodes

### `[vlcci_prehled]`

Zobrazí přehled všech šestek/rojů s dětmi a jejich postupem v odborkách.  
Děti jsou uvedeny pouze přezdívkou (bez jména a příjmení).

**Parametry:**

| Parametr | Výchozí | Popis |
|----------|---------|-------|
| `oddil_id` | `0` (vše) | Omezí výpis na konkrétní oddíl podle jeho ID z databáze |

**Příklady:**

```
[vlcci_prehled]
```
Zobrazí všechny oddíly.

```
[vlcci_prehled oddil_id="1"]
```
Zobrazí pouze oddíl s ID 1.

---

## Deploy

Soubory se nasazují automaticky přes `deploy-webhook.php`.  
Po každém mergi do větve `main` na GitHubu zavolá webhook URL:

```
https://skautchlumec.cz/wp-content/plugins/vlcci-svetylka/deploy-webhook.php?token=TOKEN
```

Stáhne `vlcci-odborky.php` a všechny obrázky z `images/`.

> **Pozor:** `deploy-webhook.php` na serveru obsahuje tajný token — neupravuj ho přes git, přepsal by se na `CHANGE_ME`.

---

## Struktura

```
vlcci-svetylka/
├── vlcci-odborky.php      # Hlavní soubor pluginu
├── deploy-webhook.php     # Deploy skript (token nastavit ručně na serveru)
└── images/                # Obrázky odznaků (30 souborů)
```

## Databázové tabulky

Plugin vytváří tyto tabulky (prefix `vo_`):

| Tabulka | Obsah |
|---------|-------|
| `vo_oddily` | Oddíly (vlčata / světlušky) |
| `vo_sestky` | Šestky a roje |
| `vo_vedouci` | Přiřazení vedoucích k šestkám |
| `vo_deti` | Děti (jméno, příjmení, přezdívka) |
| `vo_odborky` | Definice 30 odborek |
| `vo_ukoly` | Úkoly odborek (10 na odborku) |
| `vo_plneni` | Záznamy o splnění úkolů |

Odborky a jejich úkoly se předvyplní automaticky při aktivaci pluginu.  
Minimální počet úkolů pro splnění odborky: **8 z 10**.
