# Test bety přes ACP

Stav: majitel potvrdil **funkční editor pravidel v beta.2** po opravě ACP chyb beta.1. **Cílení na zařízení, překlady a skutečná aktualizace ještě čekají na živé ověření.** Beta.12 přidává čtyři pozice vložení, postupný výběr hooku a migraci sloupce pozice; jejich nativní ověření zatím čeká. Zveřejnění zdrojů je od tohoto ověření oddělené. Automatické mocky a test prohlížeče nepotvrzují skutečnou instalaci ani aktualizaci. Výsledky zaznamenejte před označením verze za stabilní.

Použijte privátní licencovanou testovací instalaci **IPS 5.0.20+** s aplikací **Pages** a zálohou databáze i souborů. Zdrojové ověření vychází z verze **5.0.20**; při testu novějšího vydání zaznamenejte přesnou verzi. Poznamenejte také verzi PHP, build IPS, motiv, prohlížeč, CDN/cache a SHA256 balíčku. Aktuální balíček je **`1.0.0-beta.12`, build `1000012`**; ověřte beta.11 → beta.12 i starší verzi s existujícími migracemi. Beta.12 rozšiřuje uloženou pozici z 8 na 16 znaků.

## Základní instalace

1. V ACP nahrajte `rqblockplacements-1.0.0-beta.12.tar`. Ověřte dokončení instalace, verzi/build `1.0.0-beta.12` / `1000012` a viditelnou ikonu mřížky poblíž spodního konce běžného úzkého menu ACP. Otevřete **Content Placements → Pravidla umístění** a **Content Placements → Nastavení** (anglicky **Placement rules / Settings**). Hlavní záložka používá značku ve všech jazycích, podnabídky zůstávají přeložené. Ověřte podtitul **for Invision Community 5**. Jde o vlastní záložku aplikace, nikoli podmenu nativních reklam nebo Pages.
2. V Pages vytvořte veřejný HTML blok s klíčem `my_new_block`, jednoznačným textem `RQBP TEST` a odkazem. Pokud použijete existující blok, nahraďte `my_new_block` v příkladu jeho skutečným klíčem. Nejprve ověřte běžné nativní zobrazení bloku.
3. Přidejte CSS pravidlo za skutečnou kategorii, například `li[data-categoryID="4"]`, poloha **za**, první výskyt, bez omezení. Číslo nahraďte ID ze svého fóra. Blok má být mezi kategoriemi, ne mezi příspěvky v tématu.
4. Na vhodném kontejneru vyzkoušejte **před**, **inside-start**, **inside-end** a **za**. Před/za musí vytvořit sourozence, inside-start/end prvního/posledního potomka. Ověřte pořadí vůči cíli či kategorii a absenci duplicit.
5. Samostatné hook pravidlo vytvořte přes **aplikaci → šablonu → hook → typ vložení**. Změna aplikace musí změnit nabídku šablon a změna šablony nabídku hooků. Použijte bezpečné dostupné hooky v `core/front/global/globalTemplate`: `main` s vhodnými pozicemi a `body` pouze s inside-start/end. Hook, který v instalaci není, nemá být nabízen.
6. Tentýž blok zvolte pickerem a pak přesný zápis `{block="my_new_block"}`. Blok s tímto klíčem musí existovat; jinak použijte skutečný klíč svého bloku. Dva tagy, přidané HTML a jiné šablonové výrazy musí formulář odmítnout.
7. Ověřte, že odkaz na projekt aplikace vede na [PTiCA1/IPS-Content-Placements](https://github.com/PTiCA1/IPS-Content-Placements) a běžné chyby se hlásí přes jeho [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues). Bezpečnostní problémy mají postupovat podle soukromého hlášení v SECURITY.md.

Přímý přístup: zachovejte skutečný adresář ACP ze své aktuální adresy a otevřete jeho `index.php` s dotazem `?app=rqblockplacements&module=placements&controller=rules`. Přejmenovaný adresář ACP se nesmí změnit. V beta.1 mohla aplikaci otevřít i prázdná položka dole v úzkém menu; beta.2 musí ikonu zobrazit bez nutnosti najetí myší nebo obrazovky širší než 1 900 px.

## Čtyři pozice a HTML kontexty

- V CSS i nativním hooku ověřte všechny čtyři pozice s jednoduchým blokem i vlastním kódem Hello world. Zkontrolujte první/číslovaný/všechny výskyty a pořadí dvou pravidel ve stejné pozici.
- V CSS použijte cíl `body` a podle dostupnosti také nativní hook `body`. Inside-start/end musí zůstat uvnitř těla dokumentu. Před/za nesmí vložit obsah mimo něj. Podvržená nevhodná pozice nativního hooku se musí při uložení odmítnout; CSS musí nevhodnou kombinaci cíle a pozice přeskočit.
- `head`, `title` ani další nepodporované hooky pro obsah bloku nesmějí být v nabídce doplňku, i když je nativní editor vlastních šablon IPS nabízí. Nepodporovaná kombinace cíle a pozice CSS nesmí vložit neplatné HTML bloku.
- Znovu otevřete a uložte hook pravidlo. Aplikace, šablona, hook i pozice musí zůstat vybrané; změna nadřazené volby nesmí skrytě zachovat hook z jiné šablony. Názvy pozic musí používat překlady jádra instalovaného IPS, nikoli nevyhodnocené jazykové klíče.
- Hodnoty inside-start/end musí přežít uložení, načtení i další úpravu bez zkrácení. Zachovejte dříve uložená pravidla před/za a ověřte jejich stejné umístění.

## Automatická čeština, slovenština, polština a němčina

- Před instalací či aktualizací ponechte existující češtinu, slovenštinu, polštinu a němčinu i anglický/nepodporovaný jazyk. Ověřte `cs`, `sk`, `pl`, `de` a regionální tvary jako `cs_CZ`, `sk-SK`, `pl_PL`, `de-DE`, také Windows názvy locale Czech/Slovak/Polish/German. Každý podporovaný jazyk musí získat vlastní překlad aplikace bez samostatného XML; ostatním jazykům zůstane anglický výchozí text.
- V každém překladu ověřte všechny klíče aplikace: menu, názvy oprávnění, pole pravidel, zařízení, nápovědu, chyby i diagnostiku. Příklady kódu, zástupné symboly a ikona menu musí zůstat funkční. Přepínání existujících jazyků musí zobrazovat odpovídající texty.
- Před aktualizací upravte jeden text aplikace a jiný nastavte na záměrně prázdnou hodnotu v každém podporovaném jazyce. Obojí musí zůstat zachované. Chybějící překlady musí získat příslušný text. Nesmí vzniknout nový jazyk ani se změnit výchozí jazyk webu, názvy jazyků, locale či ostatní metadata.
- Ověřte jeden TAR, čtyři volitelná `-cs.xml`, `-sk.xml`, `-pl.xml`, `-de.xml` a SHA256SUMS pro všech pět souborů vydání. Volitelně obnovte existující překlad příslušným XML. Přidejte podporovaný jazyk až po instalaci aplikace a ověřte dokumentovanou cestu importu XML. Tento výslovný nativní import je oddělený od automatické aktualizace aplikace.

## Zachování stávajícího názvu

- Aktualizujte beta.11 na beta.12 přes existující položku aplikace. Klíč `rqblockplacements`, pravidla, nastavení i oprávnění administrátorů musí zůstat zachované; nesmí vzniknout druhá aplikace.
- Zachovejte standardní název aplikace a hlavní záložky **Content Placements**, podtitul **for Invision Community 5** i přeložené podnabídky. Vlastní názvy administrátora včetně záměrně prázdného textu musí zůstat nedotčené.

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

1. V instalované betě uložte reprezentativní CSS/hook pravidla, pořadí a skupiny. V beta.2 ponechte i staré OS volby (Android, iPhone, iPad, Windows a Neznámá); v beta.3 zachovejte volby zařízení. Zazálohujte databázi.
2. Ověřte **beta.11 (`1000011`) → beta.12 (`1000012`)** i starší verzi, například beta.2 (`1000002`) → beta.12, nahráním `rqblockplacements-1.0.0-beta.12.tar` přes ACP. Opětovné nahrání stejné bety neověřuje aktualizaci. Zkontrolujte rozšíření `rule_position` z `VARCHAR(8)` na `VARCHAR(16)` bez změny existujících hodnot a pravidel. Zopakujte částečnou aktualizaci i stav, kdy už je sloupec rozšířený; žádné řádky se nesmějí ztratit či resetovat. Dřívější aditivní migrace musí stále zachovat data. Přístup do editoru beta.2 je potvrzený, pozdější živé ověření stále čeká.
3. Ověřte zachování pravidel/nastavení a jediné obnovení markerů. Android se musí převést na Mobil + Tablet, iPhone na Mobil, iPad/Kindle/PlayBook na Tablet a Windows/Neznámá na Desktop. Otevření pravidla normalizuje staré hodnoty platforem; uložení těchto tříd zařízení nevyžaduje další změnu schématu. Po další úpravě nesmějí vzniknout duplicity. Běžná navigace i přímá URL musí otevřít stejnou tabulku pravidel.
4. Bez importu XML ověřte všechny čtyři automatické překlady a zachování vlastních textů podle oddílu výše. Nepodporované jazyky musí nadále používat anglické výchozí texty. Pozdější výslovné obnovení XML má překládat jen tento doplněk; po nativním importu zkontrolujte zamýšlený jazyk.
5. Na jednorázové testovací instalaci odinstalujte aplikaci. Vlastní tabulky/nastavení/markery mají zmizet, existující Pages bloky zůstat.

Stabilní 1.0.0 vydejte až po úspěšné instalaci, kontrole oprávnění, assetů, izolace cache a skutečné aktualizaci mezi verzemi. Před sdílením chybového hlášení odstraňte cookies, CSRF klíče, osobní údaje a obsah soukromých bloků.

| Kontrola | Výsledek | Poznámka |
| --- | --- | --- |
| Čistá instalace přes ACP | Čeká | |
| Ikona úzkého menu ACP / navigace editoru pravidel | Čeká | |
| CSS mezi kategoriemi | Čeká | |
| Nativní hook | Čeká | |
| Čtyři pozice / omezení body | Čeká | |
| Výběr aplikace → šablona → hook | Čeká | |
| Oprávnění / vedlejší skupiny | Čeká | |
| Platformy / URL | Čeká | |
| Výskyty / pořadí | Čeká | |
| Nativní assety / původní požadavek | Čeká | |
| CDN cache hostů | Čeká | |
| Diagnostika / omezení ACP | Čeká | |
| Aktualizace starší bety → beta.12 / převod platforem | Čeká | |
| Rozšíření sloupce pozice / opakování / zachování pravidel | Čeká | |
| Metadata projektu / hlášení chyb | Čeká | |
| Identita aplikace / název produktu / vlastní názvy | Čeká | |
| Automatické překlady CS / SK / PL / DE | Čeká | |
| Zachování vlastních textů / záměrně prázdných hodnot | Čeká | |
| Anglické výchozí texty / nastavení jazyků beze změny | Čeká | |
| Vypnutí / odinstalace | Čeká | |

## Kód šablony (přidaný v beta.5)

- Ověřte aktualizaci beta.4 → beta.12 se zachováním všech blokových pravidel přes aditivní migraci kódu šablony. Opakování částečné migrace nesmí ztratit data.
- Zvolte vlastní kód šablony a vložte ukázku Hello world z README. Povolená veřejná stránka musí zobrazit Hello world bez dalšího rozšíření. Uložení a ACP diagnostika mají pouze ověřit syntaxi.
- Ověřte kód přes CSS i hook, skupiny/zařízení/URL, vyřazeného hosta, všechny čtyři pozice, opakování a chybějící selektor. Kód se připravuje serverově před hledáním CSS cíle.
- Omezený administrátor bez rqbp_manage_code nesmí číst kód ani odeslat podvržený formulář, zapnout, duplikovat nebo upravit pravidlo s kódem. Bloková pravidla smí dál spravovat.
- Vlastní kód musí fungovat bez Pages; pravidlo s Pages blokem má bez Pages zůstat neaktivní. Syntax error ani výjimka API nesmí uniknout do výstupu stránky.

## Bezpečnostní ověření beta.6

- Po aktualizaci vyprázdněte cache CDN/reverzní proxy na dotčených URL. Ověřte výsledné `private, no-store` i na archivovaném tématu a ve vyhledávání, pro povoleného i odmítnutého návštěvníka. Nová odpověď musí zakázat sdílené ukládání.
- Vyzkoušejte odlišné skupiny hostů/bota a blok s vlastními Pages oprávněními, i bez explicitního filtru zařízení.
- Správce bloků bez oprávnění ke kódu nesmí hlavním přepínačem znovu aktivovat zapnuté code pravidlo. Nouzové vypnutí musí fungovat.
- Změna URL během čekání na reklamní skript musí ihned odstranit starý obal a zastavit následné staré skripty. Již připojený externí skript může prohlížeč ještě vykonat.
