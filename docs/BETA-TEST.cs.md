# Test bety přes ACP

Stav: majitel již nainstaloval **beta.1** a nahlásil chybějící ikonu ACP a chybu `template_store_missing` při otevření pravidel. **Kompletní test na běžícím fóru stále čeká.** Beta.2 opravuje obě závady. Automatické mocky a test prohlížeče nepotvrzují skutečnou instalaci ani aktualizaci. Výsledky zaznamenejte před zveřejněním a označením verze za stabilní.

Použijte privátní licencovanou testovací instalaci **IPS 5.0.20 + Pages**, se zálohou databáze i souborů. Poznamenejte verzi PHP, build IPS, motiv, prohlížeč, CDN/cache a SHA256 balíčku. Aktuální balíček je **`1.0.0-beta.2`, build `1000002`**; předchozí balíček byl `1.0.0-beta.1`, build `1000001`.

## Základní instalace

1. V ACP nahrajte `rqblockplacements-1.0.0-beta.2.tar`. Ověřte dokončení instalace, verzi/build `1.0.0-beta.2` / `1000002` a viditelnou ikonu mřížky poblíž spodního konce běžného úzkého menu ACP. Otevřete **Block placements → Placement rules** a **Block placements → Settings**. Po importu češtiny jsou cesty **Umístění bloků → Pravidla umístění / Nastavení**. Jde o vlastní záložku aplikace, nikoli podmenu nativních reklam nebo Pages.
2. V Pages vytvořte veřejný HTML blok s jednoznačným textem `RQBP TEST` a odkazem. Nejprve ověřte jeho běžné nativní zobrazení.
3. Přidejte CSS pravidlo za skutečnou kategorii, například `li[data-categoryID="4"]`, poloha **za**, první výskyt, bez omezení. Číslo nahraďte ID ze svého fóra. Blok má být mezi kategoriemi, ne mezi příspěvky v tématu.
4. Přepněte na polohu **před** a ověřte pořadí i absenci duplicit.
5. Samostatně vyzkoušejte bezpečný dostupný nativní hook, například `main` v `core/front/global/globalTemplate`, před/za. Zkontrolujte i okolní HTML. Hook, který v instalaci není, nemá být nabízen.
6. Tentýž blok zvolte pickerem a pak přesný zápis `{block="skutecny_klic"}`. Dva tagy, přidané HTML a jiné šablonové výrazy musí formulář odmítnout.

Přímý přístup: zachovejte skutečný adresář ACP ze své aktuální adresy a otevřete jeho `index.php` s dotazem `?app=rqblockplacements&module=placements&controller=rules`. Přejmenovaný adresář ACP se nesmí změnit. V beta.1 mohla aplikaci otevřít i prázdná položka dole v úzkém menu; beta.2 musí ikonu zobrazit bez nutnosti najetí myší nebo obrazovky širší než 1 900 px.

## Filtry a opakování

- Všechny platformy, pouze Windows, vícenásobný výběr konkrétních iPhone/Android a neznámá platforma. Rozhoduje rozpoznání IPS podle user-agentu; zúžení okna desktopu platformu nemění.
- Povolená hlavní a vyloučená vedlejší skupina: zákaz musí vyhrát. Dále povolená vedlejší skupina, nepovolený člen, administrátor a host.
- Omezení na Forums, návštěva Pages a fóra, povolená URL `/topic/*` a překrývající se zákaz. Vyzkoušejte query string i instalaci v podadresáři.
- Nejméně tři cíle: první, druhý a všechny výskyty. Vyzkoušejte nedosažitelný číslovaný výskyt. Po dynamickém načtení obsahu IPS nesmějí vzniknout duplicity ani cílení do vloženého bloku.
- Dvě pravidla na stejné místo: změna pořadí, úprava, duplikace, vypnutí a smazání. Po smazání nesmí zůstat aktivní marker.

## Nativní vykreslení a chyby

- Ověřte jednoduchý HTML blok i widget/feed vyžadující CSS a JavaScript, v CSS režimu i globálním hooku. Zkontrolujte konzoli prohlížeče a chyby načítání souborů.
- Blok závislý na aktuálním požadavku musí vidět původní URL/query a uživatele. Nesmí vzniknout druhý HTTP požadavek k endpointu pro vykreslení bloku.
- Soukromý, neaktivní a smazaný blok; neexistující CSS cíl; hook nevyvolaný na dané stránce. Návštěvník nesmí vidět interní chybové texty ani HTML soukromého bloku.
- U opakovaného interaktivního bloku zkontrolujte duplicitní HTML ID a inicializaci. Obsah konkrétního bloku může být vhodný jen pro jeden výskyt.
- Diagnostiku otevřete jako oprávněný administrátor; běžný člen a host ji nesmějí číst ani měnit pravidla přes přímou URL.
- Stejné URL hosta načtěte desktopovým a mobilním user-agentem přes skutečnou CDN. Výstup jedné skupiny se nesmí vracet druhé z cache. Po změně pravidel vyprázdněte dřívější cache dotčených stránek.
- Vypněte globální přepínač, aplikaci a samostatně Pages. Výstup se musí zastavit bez chyby pro návštěvníka.

## Aktualizace a odinstalace

1. Uložte reprezentativní CSS/hook pravidla, pořadí, skupiny a platformy; zazálohujte databázi.
2. Výslovně ověřte **beta.1 (`1000001`) → beta.2 (`1000002`)** nahráním `rqblockplacements-1.0.0-beta.2.tar` přes ACP. Pokud beta.1 kvůli `template_store_missing` neumožňuje pravidla vytvořit, ověřte aktualizaci bez pravidel a zachování dat doplňte při další očíslované verzi. Opětovné nahrání stejné bety neověřuje migrace.
3. Ověřte zachování pravidel/nastavení a jediné obnovení markerů. Po další úpravě pravidla nesmějí vzniknout duplicity. Nová ikona musí být vidět i v úzkém menu ACP a běžná navigace i přímá URL musí otevřít stejnou tabulku pravidel.
4. Ověřte anglické texty a volitelný český import; po nativním importu zkontrolujte i metadata zamýšleného českého jazyka.
5. Na jednorázové testovací instalaci odinstalujte aplikaci. Vlastní tabulky/nastavení/markery mají zmizet, existující Pages bloky zůstat.

Stabilní 1.0.0 vydejte až po úspěšné instalaci, kontrole oprávnění, assetů, izolace cache a skutečné aktualizaci mezi verzemi. Před sdílením chybového hlášení odstraňte cookies, CSRF klíče, osobní údaje a obsah soukromých bloků.

| Kontrola | Výsledek | Poznámka |
| --- | --- | --- |
| Čistá instalace přes ACP | Čeká | |
| Ikona úzkého menu ACP / navigace editoru pravidel | Čeká | |
| CSS mezi kategoriemi | Čeká | |
| Nativní hook | Čeká | |
| Oprávnění / vedlejší skupiny | Čeká | |
| Platformy / URL | Čeká | |
| Výskyty / pořadí | Čeká | |
| Nativní assety / původní požadavek | Čeká | |
| CDN cache hostů | Čeká | |
| Diagnostika / omezení ACP | Čeká | |
| Aktualizace beta.1 → beta.2 | Čeká | |
| Čeština | Čeká | |
| Vypnutí / odinstalace | Čeká | |
