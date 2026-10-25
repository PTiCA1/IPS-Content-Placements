# Test bety přes ACP

Stav: majitel potvrdil **funkční editor pravidel v beta.2** po opravě ACP chyb beta.1. **Cílení na zařízení, překlady, skutečná aktualizace i přejmenování na Content Placements v beta.9 ještě čekají na živé ověření.** Automatické mocky a test prohlížeče nepotvrzují skutečnou instalaci ani aktualizaci. Výsledky zaznamenejte před zveřejněním a označením verze za stabilní.

Použijte privátní licencovanou testovací instalaci **IPS 5.0.20 + Pages**, se zálohou databáze i souborů. Poznamenejte verzi PHP, build IPS, motiv, prohlížeč, CDN/cache a SHA256 balíčku. Aktuální balíček je **`1.0.0-beta.9`, build `1000009`**; ověřte beta.8 → beta.9 i starší verzi s existujícími migracemi. Beta.9 nemění schéma databáze.

## Základní instalace

1. V ACP nahrajte `rqblockplacements-1.0.0-beta.9.tar`. Ověřte dokončení instalace, verzi/build `1.0.0-beta.9` / `1000009` a viditelnou ikonu mřížky poblíž spodního konce běžného úzkého menu ACP. Otevřete **Content Placements → Pravidla umístění** a **Content Placements → Nastavení** (anglicky **Placement rules / Settings**). Hlavní záložka používá značku ve všech jazycích, podnabídky zůstávají přeložené. Ověřte podtitul **for Invision Community 5**. Jde o vlastní záložku aplikace, nikoli podmenu nativních reklam nebo Pages.
2. V Pages vytvořte veřejný HTML blok s jednoznačným textem `RQBP TEST` a odkazem. Nejprve ověřte jeho běžné nativní zobrazení.
3. Přidejte CSS pravidlo za skutečnou kategorii, například `li[data-categoryID="4"]`, poloha **za**, první výskyt, bez omezení. Číslo nahraďte ID ze svého fóra. Blok má být mezi kategoriemi, ne mezi příspěvky v tématu.
4. Přepněte na polohu **před** a ověřte pořadí i absenci duplicit.
5. Samostatně vyzkoušejte bezpečný dostupný nativní hook, například `main` v `core/front/global/globalTemplate`, před/za. Zkontrolujte i okolní HTML. Hook, který v instalaci není, nemá být nabízen.
6. Tentýž blok zvolte pickerem a pak přesný zápis `{block="skutecny_klic"}`. Dva tagy, přidané HTML a jiné šablonové výrazy musí formulář odmítnout.

Přímý přístup: zachovejte skutečný adresář ACP ze své aktuální adresy a otevřete jeho `index.php` s dotazem `?app=rqblockplacements&module=placements&controller=rules`. Přejmenovaný adresář ACP se nesmí změnit. V beta.1 mohla aplikaci otevřít i prázdná položka dole v úzkém menu; beta.2 musí ikonu zobrazit bez nutnosti najetí myší nebo obrazovky širší než 1 900 px.

## Automatická čeština, slovenština, polština a němčina

- Před instalací či aktualizací ponechte existující češtinu, slovenštinu, polštinu a němčinu i anglický/nepodporovaný jazyk. Ověřte `cs`, `sk`, `pl`, `de` a regionální tvary jako `cs_CZ`, `sk-SK`, `pl_PL`, `de-DE`, také Windows názvy locale Czech/Slovak/Polish/German. Každý podporovaný jazyk musí získat vlastní překlad aplikace bez samostatného XML; ostatním jazykům zůstane anglický výchozí text.
- V každém překladu ověřte všechny klíče aplikace: menu, názvy oprávnění, pole pravidel, zařízení, nápovědu, chyby i diagnostiku. Příklady kódu, zástupné symboly a ikona menu musí zůstat funkční. Přepínání existujících jazyků musí zobrazovat odpovídající texty.
- Před aktualizací upravte jeden text aplikace a jiný nastavte na záměrně prázdnou hodnotu v každém podporovaném jazyce. Obojí musí zůstat zachované. Chybějící překlady musí získat příslušný text. Nesmí vzniknout nový jazyk ani se změnit výchozí jazyk webu, názvy jazyků, locale či ostatní metadata.
- Ověřte jeden TAR, čtyři volitelná `-cs.xml`, `-sk.xml`, `-pl.xml`, `-de.xml` a SHA256SUMS pro všech pět souborů vydání. Volitelně obnovte existující překlad příslušným XML. Přidejte podporovaný jazyk až po instalaci aplikace a ověřte dokumentovanou cestu importu XML. Tento výslovný nativní import je oddělený od automatické aktualizace aplikace.

## Aktualizace názvu

- Aktualizujte beta.8 na beta.9 přes existující položku aplikace. Klíč `rqblockplacements`, pravidla, nastavení i oprávnění administrátorů musí zůstat zachované; nesmí vzniknout druhá aplikace.
- V každém jazyce se musí neupravený standardní název aplikace a hlavní záložky změnit na **Content Placements**. Zachovejte podtitul **for Invision Community 5** a přeložené podnabídky. Vlastní názvy administrátora včetně záměrně prázdného textu musí zůstat nedotčené.

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
2. Ověřte **beta.8 (`1000008`) → beta.9 (`1000009`)** i starší verzi, například beta.2 (`1000002`) → beta.9, nahráním `rqblockplacements-1.0.0-beta.9.tar` přes ACP. Opětovné nahrání stejné bety neověřuje aktualizaci. Beta.9 nemění schéma; dřívější aditivní migrace musí zachovat existující data. Přístup do editoru beta.2 je potvrzený, pozdější živé ověření stále čeká.
3. Ověřte zachování pravidel/nastavení a jediné obnovení markerů. Android se musí převést na Mobil + Tablet, iPhone na Mobil, iPad/Kindle/PlayBook na Tablet a Windows/Neznámá na Desktop. Otevření pravidla normalizuje staré hodnoty; uložení zapíše nové bez změny schématu databáze. Po další úpravě nesmějí vzniknout duplicity. Běžná navigace i přímá URL musí otevřít stejnou tabulku pravidel.
4. Bez importu XML ověřte všechny čtyři automatické překlady a zachování vlastních textů podle oddílu výše. Nepodporované jazyky musí nadále používat anglické výchozí texty. Pozdější výslovné obnovení XML má překládat jen tento doplněk; po nativním importu zkontrolujte zamýšlený jazyk.
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
| Aktualizace starší bety → beta.9 / převod platforem | Čeká | |
| Nový název / identita aplikace / vlastní názvy | Čeká | |
| Automatické překlady CS / SK / PL / DE | Čeká | |
| Zachování vlastních textů / záměrně prázdných hodnot | Čeká | |
| Anglické výchozí texty / nastavení jazyků beze změny | Čeká | |
| Vypnutí / odinstalace | Čeká | |

## Kód šablony (přidaný v beta.5)

- Ověřte aktualizaci beta.4 → beta.9 se zachováním všech blokových pravidel přes aditivní migraci kódu šablony. Opakování částečné migrace nesmí ztratit data.
- Zvolte vlastní kód šablony a vložte ukázku Hello world z README. Povolená veřejná stránka musí zobrazit Hello world bez dalšího rozšíření. Uložení a ACP diagnostika mají pouze ověřit syntaxi.
- Ověřte kód přes CSS i hook, skupiny/zařízení/URL, vyřazeného hosta, před/za, opakování a chybějící selektor. Kód se připravuje serverově před hledáním CSS cíle.
- Omezený administrátor bez rqbp_manage_code nesmí číst kód ani odeslat podvržený formulář, zapnout, duplikovat nebo upravit pravidlo s kódem. Bloková pravidla smí dál spravovat.
- Vlastní kód musí fungovat bez Pages; pravidlo s Pages blokem má bez Pages zůstat neaktivní. Syntax error ani výjimka API nesmí uniknout do výstupu stránky.

## Bezpečnostní ověření beta.6

- Po aktualizaci vyprázdněte cache CDN/reverzní proxy na dotčených URL. Ověřte výsledné `private, no-store` i na archivovaném tématu a ve vyhledávání, pro povoleného i odmítnutého návštěvníka. Nová odpověď musí zakázat sdílené ukládání.
- Vyzkoušejte odlišné skupiny hostů/bota a blok s vlastními Pages oprávněními, i bez explicitního filtru zařízení.
- Správce bloků bez oprávnění ke kódu nesmí hlavním přepínačem znovu aktivovat zapnuté code pravidlo. Nouzové vypnutí musí fungovat.
- Změna URL během čekání na reklamní skript musí ihned odstranit starý obal a zastavit následné staré skripty. Již připojený externí skript může prohlížeč ještě vykonat.
