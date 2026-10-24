# Test bety přes ACP

Stav: majitel potvrdil **funkční editor pravidel v beta.2** po opravě ACP chyb beta.1. **Cílení na zařízení a aktualizace beta.3 ještě čekají na ověření.** Automatické mocky a test prohlížeče nepotvrzují skutečnou instalaci ani aktualizaci. Výsledky zaznamenejte před zveřejněním a označením verze za stabilní.

Použijte privátní licencovanou testovací instalaci **IPS 5.0.20 + Pages**, se zálohou databáze i souborů. Poznamenejte verzi PHP, build IPS, motiv, prohlížeč, CDN/cache a SHA256 balíčku. Aktuální balíček je **`1.0.0-beta.3`, build `1000003`**; aktualizujte z `1.0.0-beta.2`, build `1000002`.

## Základní instalace

1. V ACP nahrajte `rqblockplacements-1.0.0-beta.3.tar`. Ověřte dokončení instalace, verzi/build `1.0.0-beta.3` / `1000003` a viditelnou ikonu mřížky poblíž spodního konce běžného úzkého menu ACP. Otevřete **Block placements → Placement rules** a **Block placements → Settings**. Po importu češtiny jsou cesty **Umístění bloků → Pravidla umístění / Nastavení**. Jde o vlastní záložku aplikace, nikoli podmenu nativních reklam nebo Pages.
2. V Pages vytvořte veřejný HTML blok s jednoznačným textem `RQBP TEST` a odkazem. Nejprve ověřte jeho běžné nativní zobrazení.
3. Přidejte CSS pravidlo za skutečnou kategorii, například `li[data-categoryID="4"]`, poloha **za**, první výskyt, bez omezení. Číslo nahraďte ID ze svého fóra. Blok má být mezi kategoriemi, ne mezi příspěvky v tématu.
4. Přepněte na polohu **před** a ověřte pořadí i absenci duplicit.
5. Samostatně vyzkoušejte bezpečný dostupný nativní hook, například `main` v `core/front/global/globalTemplate`, před/za. Zkontrolujte i okolní HTML. Hook, který v instalaci není, nemá být nabízen.
6. Tentýž blok zvolte pickerem a pak přesný zápis `{block="skutecny_klic"}`. Dva tagy, přidané HTML a jiné šablonové výrazy musí formulář odmítnout.

Přímý přístup: zachovejte skutečný adresář ACP ze své aktuální adresy a otevřete jeho `index.php` s dotazem `?app=rqblockplacements&module=placements&controller=rules`. Přejmenovaný adresář ACP se nesmí změnit. V beta.1 mohla aplikaci otevřít i prázdná položka dole v úzkém menu; beta.2 musí ikonu zobrazit bez nutnosti najetí myší nebo obrazovky širší než 1 900 px.

## Filtry a opakování

- Platformy musí obsahovat právě **Mobil, Tablet, Desktop** (anglicky **Mobile, Tablet, Desktop**), bez OS a volby Neznámá. Ověřte prázdný výběr, každou třídu zvlášť a Mobil + Tablet společně. Použijte iPhone, Android telefon s `Mobile` v user-agentu, Android tablet bez tohoto tokenu, iPad/Kindle, desktop a nerozpoznaný user-agent (Desktop). Změna šířky okna třídu nesmí měnit. iPad s desktopovým user-agentem může být rozpoznán jako Desktop.
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

1. V beta.2 uložte reprezentativní CSS/hook pravidla, pořadí, skupiny a staré OS volby (Android, iPhone, iPad, Windows a Neznámá); zazálohujte databázi.
2. Výslovně ověřte **beta.2 (`1000002`) → beta.3 (`1000003`)** nahráním `rqblockplacements-1.0.0-beta.3.tar` přes ACP. Opětovné nahrání stejné bety neověřuje migrace. U předchozí aktualizace beta.1 → beta.2 byly nahlášeny chybějící ikona a `template_store_missing`; přístup do editoru beta.2 je nyní potvrzený.
3. Ověřte zachování pravidel/nastavení a jediné obnovení markerů. Android se musí převést na Mobil + Tablet, iPhone na Mobil, iPad/Kindle/PlayBook na Tablet a Windows/Neznámá na Desktop. Otevření pravidla normalizuje staré hodnoty; uložení zapíše nové bez změny schématu databáze. Po další úpravě nesmějí vzniknout duplicity. Běžná navigace i přímá URL musí otevřít stejnou tabulku pravidel.
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
| Aktualizace beta.2 → beta.3 / převod platforem | Čeká | |
| Čeština | Čeká | |
| Vypnutí / odinstalace | Čeká | |
