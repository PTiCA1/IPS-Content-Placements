# Test bety přes ACP

Stav: majitel potvrdil **funkční editor pravidel v beta.2** po opravě ACP chyb beta.1. **Cílení na zařízení, překlady a skutečná aktualizace ještě čekají na živé ověření.** Beta.16 opravuje serverové parsování duplicitních atributů a vynechaného ukončení `li` metadat v nativním výstupu Pages databáze; průběh zpracování kontroleru, selektory a skripty ještě čekají na nativní ověření. Znovu ověřte také nativní hook head. Zveřejnění zdrojů je od tohoto ověření oddělené. Automatické mocky a test prohlížeče nepotvrzují skutečnou instalaci ani aktualizaci. Výsledky zaznamenejte před označením verze za stabilní.

Použijte privátní licencovanou testovací instalaci **IPS 5.0.20+** s aplikací **Pages** a zálohou databáze i souborů. Zdrojové ověření vychází z verze **5.0.20**; při testu novějšího vydání zaznamenejte přesnou verzi. Poznamenejte také verzi PHP, build IPS, motiv, prohlížeč, CDN/cache a SHA256 balíčku. Aktuální balíček je **`1.0.0-beta.16`, build `1000016`**; ověřte beta.15 → beta.16 i starší verzi s existujícími migracemi. Beta.16 nemá nové databázové změny; pro starší aktualizace zůstává migrace šířky pozice z beta.12.

## Základní instalace

1. V ACP nahrajte `rqblockplacements-1.0.0-beta.16.tar`. Ověřte dokončení instalace, verzi/build `1.0.0-beta.16` / `1000016` a viditelnou ikonu mřížky poblíž spodního konce běžného úzkého menu ACP. Otevřete **Content Placements → Pravidla umístění** a **Content Placements → Nastavení** (anglicky **Placement rules / Settings**). Hlavní záložka používá značku ve všech jazycích, podnabídky zůstávají přeložené. Ověřte podtitul **for Invision Community 5**. Jde o vlastní záložku aplikace, nikoli podmenu nativních reklam nebo Pages.
2. V Pages vytvořte veřejný HTML blok s klíčem `my_new_block`, jednoznačným textem `RQBP TEST` a odkazem. Pokud použijete existující blok, nahraďte `my_new_block` v příkladu jeho skutečným klíčem. Nejprve ověřte běžné nativní zobrazení bloku.
3. Přidejte pravidlo **CSS selektor — JavaScript** pro skutečnou kategorii, například `#ipsLayout__main .ipsBlockSpacer > li:nth-child(3)`, poloha **za**, první výskyt, bez omezení. Ověřte skutečné HTML a umístění mezi zamýšlenými kategoriemi. Pro stejný cíl vytvořte samostatné pravidlo **CSS selektor — server** a vyzkoušejte je s vypnutým JavaScriptem; pokud nechcete opakování, nezapínejte obě testovací pravidla současně.
4. Na vhodném kontejneru vyzkoušejte **před**, **inside-start**, **inside-end** a **za**. Před/za musí vytvořit sourozence, inside-start/end prvního/posledního potomka. Ověřte pořadí vůči cíli či kategorii a absenci duplicit.
5. Samostatné hook pravidlo vytvořte přes **aplikaci → šablonu → hook → typ vložení**. Změna aplikace musí změnit nabídku šablon a změna šablony nabídku hooků. Použijte bezpečné dostupné hooky v `core/front/global/globalTemplate`: `main` s vhodnými pozicemi a `body` pouze s inside-start/end. Hook, který v instalaci není, nemá být nabízen.
6. Tentýž blok zvolte pickerem a pak přesný zápis `{block="my_new_block"}`. Blok s tímto klíčem musí existovat; jinak použijte skutečný klíč svého bloku. Dva tagy, přidané HTML a jiné šablonové výrazy musí formulář odmítnout.
7. Ověřte, že odkaz na projekt aplikace vede na [PTiCA1/IPS-Content-Placements](https://github.com/PTiCA1/IPS-Content-Placements) a běžné chyby se hlásí přes jeho [Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues). Bezpečnostní problémy mají postupovat podle soukromého hlášení v SECURITY.md.

Přímý přístup: zachovejte skutečný adresář ACP ze své aktuální adresy a otevřete jeho `index.php` s dotazem `?app=rqblockplacements&module=placements&controller=rules`. Přejmenovaný adresář ACP se nesmí změnit. V beta.1 mohla aplikaci otevřít i prázdná položka dole v úzkém menu; beta.2 musí ikonu zobrazit bez nutnosti najetí myší nebo obrazovky širší než 1 900 px.

## Čtyři pozice a HTML kontexty

- V obou CSS režimech i běžném nativním hooku ověřte všechny čtyři pozice s jednoduchým blokem i vlastním kódem Hello world. Pro tento viditelný výstup použijte vhodný cíl v těle stránky. Zkontrolujte první/číslovaný/všechny výskyty a pořadí dvou pravidel ve stejné pozici.
- V JavaScriptovém CSS režimu použijte cíl `body` a podle dostupnosti také nativní hook `body`. Serverové CSS musí zůstat ve virtuálním rozsahu hlavního kontroleru a nemůže cílit na body ani globální kostru stránky. Inside-start/end musí zůstat uvnitř těla dokumentu. Před/za nesmí vložit obsah mimo něj. Podvržená nevhodná pozice nativního hooku se musí při uložení odmítnout; CSS musí nevhodnou kombinaci cíle a pozice přeskočit.
- `title` a nepodporované hooky uvnitř head nesmějí být v nabídce doplňku, i když je nativní editor vlastních šablon IPS nabízí. Nepodporovaná kombinace cíle a pozice CSS, včetně cílení na head, nesmí vložit obsah.
- Znovu otevřete a uložte hook pravidlo. Aplikace, šablona, hook i pozice musí zůstat vybrané; změna nadřazené volby nesmí skrytě zachovat hook z jiné šablony. Názvy pozic musí používat překlady jádra instalovaného IPS, nikoli nevyhodnocené jazykové klíče.
- Hodnoty inside-start/end musí přežít uložení, načtení i další úpravu bez zkrácení. Zachovejte dříve uložená pravidla před/za a ověřte jejich stejné umístění.

## Nativní hook head pouze pro metadata

- Vyberte aplikaci Core, šablonu `core/front/global/globalTemplate` a hook `head`. Vyzkoušejte inside-start i inside-end; přímé odeslání před/za se musí odmítnout stejně jako u hooku body.
- Jako vlastní kód šablony použijte ukázku metadat z README a zkontrolujte zdroj skutečného dokumentu. Prvek musí být uvnitř head bez přidaného obalu `div`. Vyzkoušejte výstup s každým povoleným typem prvku: `script`, `style`, `meta` a `link`.
- Totéž zopakujte s Pages blokem, jehož vykreslený výstup tvoří pouze metadata. Rich-text/feed blok, který přidá viditelný obal, se musí vynechat; samotný typ zdroje nezaručuje vhodné výsledné HTML.
- Vyzkoušejte viditelný text, `div`, `p`, `title` a kombinaci metadat s viditelným HTML. Celé umístění se musí přeskočit, bez částečného výstupu či přesunu do body. Ověřte diagnostiku administrátora a logy.
- Uložte kód šablony vytvářející výstup a otevřete diagnostiku ACP bez jeho spuštění. Výsledný fragment se smí ověřit až při veřejném požadavku po filtrech publika a nativních oprávněních bloku. Odmítnutému návštěvníkovi se nesmí obsah vykreslit ani kód spustit.
- CSS selektory cílené na head nebo jeho potomky musí zůstat nepodporované. Znovu ověřte běžné vložení do body a přes CSS, aby podpora head nezměnila jejich obaly či viditelnost.

## Serverové CSS vložení (přidané v beta.14)

- Použijte vlastní testovací HTML ve stylu Pages databáze s odkazem na obrázek záznamu a opakovanými atributy `aria-hidden`/`tabindex` včetně variant velikosti písmen. Při porovnání musí platit první hodnota stejně jako v prohlížeči a původní bajty se nesmějí změnit. Vynechané ukončení `li` ověřte pouze při začátku přímého sourozence `li` nebo ukončení odpovídajícího `ul`/`ol`/`menu`, když je `li` nejvýše otevřeným prvkem. Platný selektor má nyní vložit serverový obsah namísto `server_html_unsupported`; skutečný výstup nativní Pages databáze ověřte zvlášť. Jiné implicitní uzavírání, nevhodné kontexty, triky s raw script a vnořené formuláře musí zůstat odmítané. Soukromé HTML fóra, zdroje IPS ani tokeny nepatří do veřejných testovacích podkladů.
- Použijte stránku s nativním stránkováním IPS, které má uvnitř `ul` přímé potomky `input` a `button`, a skutečným cílem serverového pravidla. Ověřte shodu selektoru v prohlížeči, že server již neodmítá celý fragment jako `server_html_unsupported`, funkčnost stránkování i zachování nedotčeného HTML. Vyzkoušejte platný blok/kód šablony a zachovejte odmítání nevhodných cílů, chybného obsahu a vnořených formulářů. Použijte vlastní nebo licencované lokální testovací podklady; soukromé HTML fóra nekopírujte do veřejného repozitáře. Nativní ověření stále čeká.
- Ověřte samostatné názvy **CSS selektor — JavaScript** a **CSS selektor — server**. Existující CSS pravidla musí zachovat JavaScriptový režim po aktualizaci, úpravě i duplikaci; automatický převod ani záložní přechod neexistuje.
- Zkontrolujte běžné požadavky na úplnou HTML stránku kategorií a témat. Serverové vložení musí pracovat pouze s fragmentem kontroleru reprezentovaným virtuálním `div#ipsLayout__main` přes podporované `Loader::onFinish`. Nesmí zachytávat ani přepisovat celou globální odpověď. Hlavička, navigace, patička, head i skutečné body jsou mimo rozsah. Výstup nativní Pages databáze patří do rozsahu, pokud jeho konečné HTML projde touto fází Loaderu. Globální postranní panel se vykresluje později mimo fragment kontroleru a nesmí se na něj cílit. Samostatnou Pages stránku/přímý výstup ověřte zvlášť: tento průběh mohou obejít a nelze tvrdit univerzální podporu.
- Porovnejte původní HTML kolem vložení po bajtech: uvozovky, entity, mezery, komentáře, atributy a nesouvisející obsah script/style musí zůstat beze změny. Vyzkoušejte podporované HTML, dokumentované omezené vynechání ukončení `li`, jiné nepodporované implicitní uzavření i chybné vnoření. Nepodporovaný vstup se musí přeskočit bez opravy dokumentu a částečného vykreslení obsahu. Ověřte odpovídající diagnostiku včetně limitů zpracování.
- Proti skutečnému motivu vyzkoušejte výchozí příklady `#ipsLayout__main .ipsBlockSpacer > li:nth-child(3)` a `#ipsLayout__main .ipsEntries--topic .ipsEntry[data-ips-hook="postWrapper"]:nth-of-type(5)`. Jejich syntaxe patří do dokumentované podmnožiny; skutečné živé shody jsou předmětem nativního testu, nikoli univerzální zárukou pro všechny motivy.
- Ověřte značky, univerzální selektor, ID, třídy, přítomnost/rovnost atributu, kombinátory potomků/přímých potomků/sousedních i následujících sourozenců, seznamy s čárkou a každou dokumentovanou strukturální pseudotřídu s celým číslem, odd/even a `an+b`. Při uložení i za běhu odmítněte `:is()`, `:has()`, stav prohlížeče, escape sekvence, jmenné prostory a nepodporované operátory; nesmí se přejít na JavaScript.
- S opakovanými kategoriemi a skrytými prvky ověřte první/číslované/všechny výskyty nad jedním seznamem bez duplicit v pořadí dokumentu. Obraťte pořadí větví seznamu s čárkou a zahrňte jeden cíl v obou větvích: pořadí i číslovaný výskyt musí zůstat společné. Chybějící číslovaný výskyt nesmí spustit obsah. Server nemůže používat vypočtenou viditelnost/styly.
- Neškodným instrumentovaným kódem šablony či testovacím blokem ověřte nulové vykonání pro odmítnuté skupiny, nevyhovující zařízení/aplikaci/URL, zamítnutá nativní oprávnění bloku, chybějící cíl a nevhodný kontext. Při platném cíli ověřte nativní assety Pages, původní požadavek a pořadí výstupu. Serverové pravidlo nesmí přidávat vkládací payload ani montování v prohlížeči.
- S vypnutým JavaScriptem zkontrolujte zdroj stránky, poté se zapnutým JavaScriptem použijte neškodné počitadlo inline/externího skriptu. Vložené HTML musí být součástí odpovědi před parsováním prohlížečem a skripty se mají spustit běžným parsováním bez druhého JavaScriptového vložení. Neočekávejte, že synchronní skript v head uvidí pozdější body, ani univerzální funkčnost každého reklamního tagu/widgetu.
- Vyžádejte AJAX/dynamické odpovědi: serverová pravidla se musí přeskočit s výslovnou diagnostikou administrátora a bez spuštění. Běžný úplný požadavek může obsah vložit; pozdější IPS události změny obsahu nesmějí serverové pravidlo znovu připojit. Ověřte diagnostiku chybějící shody/výskytu, nepodporovaného HTML/kontextu/selektoru, nezpracovaného pravidla i obecné chyby bez zpřístupnění obsahu neoprávněným návštěvníkům.

## Automatická čeština, slovenština, polština a němčina

- Před instalací či aktualizací ponechte existující češtinu, slovenštinu, polštinu a němčinu i anglický/nepodporovaný jazyk. Ověřte `cs`, `sk`, `pl`, `de` a regionální tvary jako `cs_CZ`, `sk-SK`, `pl_PL`, `de-DE`, také Windows názvy locale Czech/Slovak/Polish/German. Každý podporovaný jazyk musí získat vlastní překlad aplikace bez samostatného XML; ostatním jazykům zůstane anglický výchozí text.
- V každém překladu ověřte všechny klíče aplikace: menu, názvy oprávnění, pole pravidel, zařízení, nápovědu, chyby i diagnostiku. Příklady kódu, zástupné symboly a ikona menu musí zůstat funkční. Přepínání existujících jazyků musí zobrazovat odpovídající texty.
- Před aktualizací upravte jeden text aplikace a jiný nastavte na záměrně prázdnou hodnotu v každém podporovaném jazyce. Obojí musí zůstat zachované. Chybějící překlady musí získat příslušný text. Nesmí vzniknout nový jazyk ani se změnit výchozí jazyk webu, názvy jazyků, locale či ostatní metadata.
- Ověřte jeden TAR, čtyři volitelná `-cs.xml`, `-sk.xml`, `-pl.xml`, `-de.xml` a SHA256SUMS pro všech pět souborů vydání. Volitelně obnovte existující překlad příslušným XML. Přidejte podporovaný jazyk až po instalaci aplikace a ověřte dokumentovanou cestu importu XML. Tento výslovný nativní import je oddělený od automatické aktualizace aplikace.

## Zachování stávajícího názvu

- Aktualizujte beta.15 na beta.16 přes existující položku aplikace. Klíč `rqblockplacements`, pravidla, nastavení i oprávnění administrátorů musí zůstat zachované; nesmí vzniknout druhá aplikace.
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
2. Ověřte **beta.15 (`1000015`) → beta.16 (`1000016`)** nahráním `rqblockplacements-1.0.0-beta.16.tar` přes ACP: tato aktualizace nemá databázovou migraci ani změny pravidel a musí zachovat existující 16znakový sloupec pozice, osmiznakový sloupec režimu i pravidla. Existující `css` musí zůstat JavaScriptovým režimem; `server` a `hook` si musí zachovat režim i hodnoty. Pravidla se nesmějí automaticky převádět ani přepisovat. Nové pravidlo `server` uložte a načtěte bez zkrácení. Vyzkoušejte také starší verzi, například beta.2 (`1000002`) → beta.16, při níž se musí provést rozšíření `VARCHAR(8)` → `VARCHAR(16)` z beta.12 bez změny hodnot a pravidel. Zopakujte částečnou aktualizaci i již rozšířený sloupec; žádné řádky se nesmějí ztratit či resetovat. Opětovné nahrání stejné bety neověřuje aktualizaci. Přístup do editoru beta.2 je potvrzený, pozdější živé ověření stále čeká.
3. Ověřte zachování pravidel/nastavení a jediné obnovení markerů. Android se musí převést na Mobil + Tablet, iPhone na Mobil, iPad/Kindle/PlayBook na Tablet a Windows/Neznámá na Desktop. Otevření pravidla normalizuje staré hodnoty platforem; uložení těchto tříd zařízení nevyžaduje další změnu schématu. Po další úpravě nesmějí vzniknout duplicity. Běžná navigace i přímá URL musí otevřít stejnou tabulku pravidel.
4. Bez importu XML ověřte všechny čtyři automatické překlady a zachování vlastních textů podle oddílu výše. Nepodporované jazyky musí nadále používat anglické výchozí texty. Pozdější výslovné obnovení XML má překládat jen tento doplněk; po nativním importu zkontrolujte zamýšlený jazyk.
5. Na jednorázové testovací instalaci odinstalujte aplikaci. Vlastní tabulky/nastavení/markery mají zmizet, existující Pages bloky zůstat.

Stabilní 1.0.0 vydejte až po úspěšné instalaci, kontrole oprávnění, assetů, izolace cache a skutečné aktualizaci mezi verzemi. Před sdílením chybového hlášení odstraňte cookies, CSRF klíče, osobní údaje a obsah soukromých bloků.

| Kontrola | Výsledek | Poznámka |
| --- | --- | --- |
| Čistá instalace přes ACP | Čeká | |
| Ikona úzkého menu ACP / navigace editoru pravidel | Čeká | |
| JavaScriptové CSS mezi kategoriemi | Čeká | |
| Serverové CSS / rozsah / selektory / původní HTML / odložené vykreslení | Čeká | |
| Serverové skripty / přeskočení AJAX / diagnostika | Čeká | |
| Nativní stránkování input/button / kontroly vložení | Čeká | |
| Pages databáze / duplicitní atributy / omezené ukončení li / původní bajty | Čeká | |
| Nativní hook | Čeká | |
| Čtyři pozice / omezení body | Čeká | |
| Nativní head / kontrola metadat / odmítnutí CSS head | Čeká | |
| Výběr aplikace → šablona → hook | Čeká | |
| Oprávnění / vedlejší skupiny | Čeká | |
| Platformy / URL | Čeká | |
| Výskyty / pořadí | Čeká | |
| Nativní assety / původní požadavek | Čeká | |
| CDN cache hostů | Čeká | |
| Diagnostika / omezení ACP | Čeká | |
| Aktualizace starší bety → beta.16 / převod platforem | Čeká | |
| Rozšíření sloupce pozice / opakování / zachování pravidel | Čeká | |
| Metadata projektu / hlášení chyb | Čeká | |
| Identita aplikace / název produktu / vlastní názvy | Čeká | |
| Automatické překlady CS / SK / PL / DE | Čeká | |
| Zachování vlastních textů / záměrně prázdných hodnot | Čeká | |
| Anglické výchozí texty / nastavení jazyků beze změny | Čeká | |
| Vypnutí / odinstalace | Čeká | |

## Kód šablony (přidaný v beta.5)

- Ověřte aktualizaci beta.4 → beta.16 se zachováním všech blokových pravidel přes aditivní migraci kódu šablony. Opakování částečné migrace nesmí ztratit data.
- Zvolte vlastní kód šablony a vložte ukázku Hello world z README. Povolená veřejná stránka musí zobrazit Hello world bez dalšího rozšíření. Uložení a ACP diagnostika mají pouze ověřit syntaxi.
- Ověřte kód ve všech třech režimech, skupiny/zařízení/URL, vyřazeného hosta, všechny čtyři podporované pozice, opakování a chybějící selektor. JavaScriptové CSS připravuje kód před hledáním cíle v prohlížeči; serverové CSS jej nesmí spustit, dokud nenajde cíl a podporovaný kontext.
- Omezený administrátor bez rqbp_manage_code nesmí číst kód ani odeslat podvržený formulář, zapnout, duplikovat nebo upravit pravidlo s kódem. Bloková pravidla smí dál spravovat.
- Vlastní kód musí fungovat bez Pages; pravidlo s Pages blokem má bez Pages zůstat neaktivní. Syntax error ani výjimka API nesmí uniknout do výstupu stránky.

## Bezpečnostní ověření beta.6

- Po aktualizaci vyprázdněte cache CDN/reverzní proxy na dotčených URL. Ověřte výsledné `private, no-store` i na archivovaném tématu a ve vyhledávání, pro povoleného i odmítnutého návštěvníka. Nová odpověď musí zakázat sdílené ukládání.
- Vyzkoušejte odlišné skupiny hostů/bota a blok s vlastními Pages oprávněními, i bez explicitního filtru zařízení.
- Správce bloků bez oprávnění ke kódu nesmí hlavním přepínačem znovu aktivovat zapnuté code pravidlo. Nouzové vypnutí musí fungovat.
- Změna URL během čekání na reklamní skript musí ihned odstranit starý obal a zastavit následné staré skripty. Již připojený externí skript může prohlížeč ještě vykonat.
