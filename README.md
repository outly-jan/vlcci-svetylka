# Vlčí a světýlkové odborky

WordPress plugin pro evidenci plnění skautských odborek vlčat a světlušek.  
Vyvinutý pro Středisko Chlumec nad Cidlinou.

---

## Co plugin umí

Každý člen oddílu (vlče nebo světluška) může plnit libovolné odborky. Každá odborka má 10 úkolů a ke splnění je potřeba zvládnout alespoň 8 z nich. Plugin vedoucím umožňuje:

- zadávat datum a vedoucího ke každému splněnému úkolu
- hromadně vyplnit více úkolů najednou jedním datem
- přidělit celou odborku bez zadávání dat (pro odborky splněné před zavedením aplikace)
- přehledně sledovat postup každého člena napříč odbourkami
- zjistit, kteří členové mají daný úkol nesplněný (praktické při plánování výprav)

---

## Záložky aplikace

| Záložka | Popis |
|---------|-------|
| 🏠 **Přehled** | Přehled členů ve tvé šestce / roji a jejich postup v odborkách |
| ✏️ **Plnění** | Zadávání splněných úkolů pro konkrétního člena a odborku |
| 👤 **Po jménech** | Přehled všech členů — jaké odborky kdo plní a jak daleko je |
| 🏅 **Po odborkách** | Přehled odborek — kteří členové je plní; drill-down na konkrétní úkol a seznam těch, komu chybí |
| 📋 **Úkoly** | Katalog všech odborek a jejich úkolů (přehled bez plnění) |
| 🧑‍🤝‍🧑 **Správa členů** | Správa seznamu členů ve šestce / roji (přidání, úprava, deaktivace, smazání) |
| ❓ **Nápověda** | Uživatelský manuál |

Záložky **Oddíly** a **Filtr** jsou dostupné pouze administrátorům.

---

## Přístup

Plugin je přístupný pouze přihlášeným uživatelům s rolí `vedouc_vlat_a_svtluek` nebo `administrator`. Každý vedoucí vidí pouze šestky / roje, ke kterým je přiřazen v administraci.

---

## Instalace

1. Nahraj složku `vlcci-svetylka` do `wp-content/plugins/`.
2. Aktivuj plugin v administraci WordPressu.
3. Vlož shortcode `[vlcci_app]` na stránku, kde má aplikace běžet.
4. V administraci pluginu (menu **Vlčí odborky**) nastav oddíly, šestky a přiřaď vedoucí.

---

## Shortcodes

### `[vlcci_app]`

Hlavní aplikace pro vedoucí. Zobrazí navigaci se všemi záložkami.

### `[vlcci_prehled]`

Veřejný přehled postup členů (zobrazuje pouze přezdívky, bez jmen). Vhodný pro zveřejnění na webu oddílu.

| Parametr | Výchozí | Popis |
|----------|---------|-------|
| `oddil_id` | `0` (vše) | Omezí výpis na konkrétní oddíl |

---

## Databázové tabulky

Plugin vytváří tyto tabulky (prefix `vo_`):

| Tabulka | Obsah |
|---------|-------|
| `vo_oddily` | Oddíly (vlčata / světlušky) |
| `vo_sestky` | Šestky a roje |
| `vo_vedouci` | Přiřazení vedoucích k šestkám |
| `vo_deti` | Členové (jméno, příjmení, přezdívka) |
| `vo_odborky` | Definice odborek |
| `vo_ukoly` | Úkoly odborek (10 na odborku) |
| `vo_plneni` | Záznamy o splnění úkolů (datum, vedoucí, poznámka) |

Odborky a jejich úkoly se předvyplní automaticky při aktivaci pluginu. Minimální počet úkolů pro splnění odborky: **8 z 10**.

---

## Struktura repozitáře

```
vlcci-svetylka/
├── vlcci-odborky.php      # Hlavní soubor pluginu
├── deploy-webhook.php     # Deploy skript (token nastavit ručně na serveru)
└── images/                # Obrázky odznaků odborek
```

---

## Deploy

Po každém mergi do větve `main` se automaticky spustí GitHub Actions workflow, který zavolá deploy webhook na serveru:

```
https://skautchlumec.cz/wp-content/plugins/vlcci-svetylka/deploy-webhook.php?token=TOKEN
```

Webhook stáhne aktuální `vlcci-odborky.php` a soubory z `images/` přímo z GitHubu.

> **Pozor:** `deploy-webhook.php` na serveru obsahuje tajný token — neupravuj ho přes git, jinak se přepíše na `CHANGE_ME`.
