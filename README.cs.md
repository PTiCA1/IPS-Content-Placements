# Content Placements

for Invision Community 5

[Projekt na GitHubu](https://github.com/PTiCA1/IPS-Content-Placements) · [Hlášení chyb](https://github.com/PTiCA1/IPS-Content-Placements/issues)

**[Testovací vydání beta.15](https://github.com/PTiCA1/IPS-Content-Placements/releases/tag/v1.0.0-beta.15)** · **[Stáhnout TAR](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/rqblockplacements-1.0.0-beta.15.tar)** · [SHA256SUMS](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/SHA256SUMS)

Volitelná XML: [CS](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/rqblockplacements-1.0.0-beta.15-cs.xml) · [SK](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/rqblockplacements-1.0.0-beta.15-sk.xml) · [PL](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/rqblockplacements-1.0.0-beta.15-pl.xml) · [DE](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/rqblockplacements-1.0.0-beta.15-de.xml). TAR už obsahuje všechny čtyři překlady; samostatné stahování XML je volitelné.

Samostatná aplikace pro vkládání Pages bloků nebo vlastního kódu šablony IPS před cíl, dovnitř na jeho začátek, dovnitř na jeho konec nebo za cíl CSS selektoru či nativního hooku. Má vlastní pravidla pro zařízení, skupiny, aplikace a URL. Vlastní kód doplňku je pod licencí MIT. [English](README.md).

**1.0.0-beta.15 — sestavení 1000015.** Opravuje serverové parsování nativního stránkování IPS s přímými potomky `input` a `button` uvnitř `ul`. V beta.14 mohly tyto ovládací prvky způsobit odmítnutí fragmentu kontroleru, přestože CSS selektor v prohlížeči správně odpovídal cíli. Serverové vložení zpracovává pouze podporované HTML hlavního kontroleru, nikoli celou kostru stránky. Existující pravidla si ponechávají svůj režim a schéma databáze se nemění. Obsahuje úplnou češtinu, slovenštinu, polštinu a němčinu. Určeno pro **Invision Community 5.0.20+**. Kontroly API a překladače šablon proběhly proti zdrojům verze **5.0.20**. Skutečná instalace, serverové selektory, vkládání do head a aktualizace ještě čekají na ověření před stabilním vydáním. [Výsledky datovaného auditu a jeho meze](docs/SECURITY-AUDIT-2026-10-25.md). Po aktualizaci vyprázdněte existující CDN/reverzní proxy cache dotčených URL.

## Funkce této verze

- Výběr Pages bloku nebo zadání jednoho přesného odkazu, například `{block="my_new_block"}`. Blok s tímto klíčem musí existovat; jinak `my_new_block` nahraďte skutečným klíčem svého bloku.
- Výběr **CSS selektor — JavaScript**, **CSS selektor — server** nebo nativního hooku přes aplikaci → šablonu → hook a podporované polohy před cílem, uvnitř na začátku, uvnitř na konci nebo za cílem.
- Nativní hook `head` pro prvky `script`, `style`, `meta` a `link`, pouze s inside-start/end.
- První, konkrétní číslovaný nebo všechny odpovídající prvky či výskyty hooku.
- Výběr Mobilu, Tabletu a/nebo Desktopu. Prázdný výběr povoluje všechny tři třídy. Rozhoduje platforma IPS a user-agent, nikoli šířka okna.
- Povolené a vyloučené skupiny včetně vedlejších členství. Vyloučení má přednost.
- Volitelný výběr aplikací a povolených/vyloučených relativních URL. Zástupný znak je `*`; vyloučení má přednost. Query string je součástí porovnání, část za `#` nikoli.
- Nativní kontrola aktivity, oprávnění a vykreslení Pages bloku přes `IPS\cms\Blocks\Block::display()` v původním požadavku stránky. Nevzniká zvláštní HTTP požadavek pro vykreslení bloku.

Doplněk nerozšiřuje nativní reklamní systém. Hook musí v instalované šabloně existovat. CSS režimy umožňují cílit na kategorie bez vlastních hooků. Selektor závisí na skutečném HTML motivu a po aktualizaci motivu či platformy jej ověřte; následující příklady jsou výchozím bodem, nikoli zárukou pro každý motiv.

Všechny čtyři pozice platí pro CSS i nativní hooky tam, kde je dovoluje jejich rozsah a HTML kontext. Před/za vloží sourozenecký prvek; `inside-start` a `inside-end` vloží prvního nebo posledního potomka. Nativní hooky `head` a `body` povolují pouze obě vnitřní pozice. JavaScriptový CSS režim může cílit dovnitř body; do head však obsah nevkládá ani jeden CSS režim. Serverové CSS je omezené na hlavní fragment kontroleru. Nabídka hooků vychází z aktuálního katalogu zapnutých šablon veřejné části webu; `title` a nepodporované hooky uvnitř head vynechává, i když je nativní editor IPS nabízí. Názvy pozic používají překlady jádra instalovaného IPS. Ověřte, že HTML vašeho bloku odpovídá zvolenému cíli.

## JavaScriptový a serverový CSS režim

Existující pravidla `css` zůstávají v režimu **CSS selektor — JavaScript**. Cíle vyhledávají v prohlížeči, včetně obsahu později přidaného IPS, a připravený obsah vkládají JavaScriptem. Režim `server` je výslovná volba; aktualizace pravidla nepřevádí a režimy se mezi sebou skrytě nepřepínají.

Serverový režim používá podporované zpracování hlavního HTML kontroleru přes `Loader::onFinish` při běžném požadavku na úplnou HTML stránku. Fragment chápe jako obsah virtuálního `div#ipsLayout__main`; jde o rozsah selektoru, nikoli přístup k celé skutečné kostře stránky. Nemůže cílit na globální hlavičku, navigaci, patičku ani head. AJAX požadavky přeskakuje s diagnostikou. Samostatné Pages stránky nebo přímý výstup mohou tento průběh obejít a zůstat nepodporované.

Server vykreslí zvolený obsah a vloží jej do HTML odpovědi až po kontrole podmínek pravidla, nativních oprávnění bloku, nalezení cíle a ověření podporovaného kontextu vložení. Nezměněné bajty původního HTML zachovává; stránku neopravuje ani znovu neserializuje. Fragment kontroleru musí mít podporované vyvážené HTML: chybná struktura nebo vynechané nepovinné koncové značky mohou zpracování přeskočit. Serverová pravidla nepřidávají JavaScriptový payload pro vložení. Skripty vložené do HTML zpracuje prohlížeč, jakmile k nim parser dojde; to nezaručuje funkčnost každého reklamního tagu či widgetu. Synchronní skript v head stále nevidí prvky body, které parser ještě nenačetl.

Nativní stránkování IPS může mít uvnitř `ul` přímé potomky `input` a `button`. Parser tyto ovládací prvky přijímá a zachovává kontroly kontextu vložení, vnořených formulářů i obsahu. Oprava nevyžaduje úpravu pravidel ani převod režimu.

Serverové zpracování má limity: vstupní fragment 2 MiB, 50 000 prvků, hloubka vnoření 256, 10 000 vložení a výstup 8 MiB. Překročení limitu přeskočí serverové zpracování s diagnostikou. Chybné HTML vykresleného bloku/šablony, koncové značky bez odpovídající počáteční značky nebo vnořený formulář rovněž způsobí vynechání dotčeného umístění.

Oba CSS režimy počítají prvky v pořadí dokumentu, včetně skrytých kategorií. Seznam selektorů oddělených čárkou tvoří jedno sjednocení bez duplicit; první i číslovaný výskyt platí pro tento celek, nikoli pro každou větev zvlášť. Server nemá vypočtené CSS styly ani informaci o viditelnosti. Například:

```css
#ipsLayout__main .ipsBlockSpacer > li:nth-child(3)
```

Výchozí příklad pro řádek příspěvku:

```css
#ipsLayout__main .ipsEntries--topic .ipsEntry[data-ips-hook="postWrapper"]:nth-of-type(5)
```

Ověřte, že tyto třídy a atributy ve vašem výsledném HTML skutečně existují. Serverová podmnožina podporuje značky, `*`, ID, třídy, `[attr]`, `[attr=value]`, potomky a přímé potomky (`>`), sousední i následující sourozence (`+`, `~`) a seznamy oddělené čárkou. Ze strukturálních pseudotříd podporuje `:first-child`, `:last-child`, `:only-child`, jejich protějšky `-of-type`, `:nth-child()` a `:nth-of-type()` s celým číslem, `odd`, `even` nebo `an+b`. Při uložení i znovu za běhu odmítá `:is()`, `:has()`, pseudotřídy stavu prohlížeče, CSS escape sekvence, jmenné prostory a další nepodporovanou syntaxi. Po odmítnutí nenásleduje skrytý přechod na JavaScript. Před zapnutím pravidla ověřte podporovanou syntaxi i konečné umístění na skutečném fóru.

## Vlastní kód šablony IPS

Ve zdroji obsahu zvolte **Vlastní kód šablony IPS** a vložte například:

```html
{expression="'Hello world'"}
```

Ukázka vypíše **Hello world** a nevyžaduje žádné další rozšíření. Editor kódu má samostatné oprávnění `rqbp_manage_code`, které přidělujte jen důvěryhodným administrátorům: šablonové výrazy spouštějí serverový PHP kód. Superadministrátor má přístup automaticky. Pages blokům zůstává původní výběr a oprávnění.

Při uložení a diagnostice se ověří syntaxe bez volání vloženého výrazu. Vyhodnocení proběhne na veřejné stránce po kontrole pravidla. Nastavte stejné skupiny, zařízení, URL a selektor/hook jako u bloku. Vlastní kód nemá implicitní oprávnění Pages bloku. Sdílená cache se pro odpovídající stránky vypne. Používejte syntaxi šablon IPS, nikoli značky `<?php ?>`.

## Nativní vložení do head

Zvolte nativní hook, aplikaci Core, šablonu `core/front/global/globalTemplate`, hook `head` a pozici `inside-start` nebo `inside-end`. Tato podpora head platí výhradně pro nativní hook; cílení na head přes CSS zůstává nepodporované. Vlastní kód šablony IPS může například vrátit jednoduchý prvek metadat:

```html
<meta name="rqbp-test" content="head-placement">
```

Obsah do head může dodat Pages blok i vlastní kód šablony, ale skutečně vykreslený fragment musí obsahovat pouze prvky `script`, `style`, `meta` a `link`. Vloží se bez obalu `div`. Viditelné HTML/text, obal přidaný blokem nebo jiný nepodporovaný výstup způsobí vynechání celého umístění. Ukázka Hello world výše je viditelný obsah těla stránky a pro vložení do head není vhodná.

Uložení a diagnostika v ACP nevykreslují Pages blok ani nespouštějí šablonové výrazy, proto nemohou ověřit jejich výsledný výstup. Kontrola fragmentu metadat proběhne při veřejném požadavku po kontrole pravidla a nativních oprávnění bloku. Na testovací instalaci zkontrolujte zdroj výsledné stránky a diagnostiku.

## Instalace bety přes ACP

Potřebujete licencované Invision Community 5.0.20+, povolené vlastní aplikace a oprávnění instalovat je v ACP. Pages jsou potřeba pro pravidla s bloky; vlastní kód může fungovat i bez Pages. Použijte testovací instalaci a zálohu databáze i souborů.

Aktualizujte existující aplikaci, neinstalujte druhou kopii. Interní klíč `rqblockplacements` i předpona názvu balíčku zůstávají stejné. Aktualizace obnoví standardní názvy aplikace a zachová vlastní úpravy textů, pravidla i nastavení. Beta.15 nemá novou migraci schématu: `server` se vejde do existujícího osmiznakového sloupce režimu a staré hodnoty `css` a `hook` zůstávají beze změny. Při aktualizaci starší bety se nadále provede rozšíření `rqbp_rules.rule_position` z `VARCHAR(8)` na `VARCHAR(16)` zavedené v beta.12, bez přepsání existujících hodnot před/za; tuto migraci lze po částečné aktualizaci zopakovat.

1. Stáhněte [rqblockplacements-1.0.0-beta.15.tar](https://github.com/PTiCA1/IPS-Content-Placements/releases/download/v1.0.0-beta.15/rqblockplacements-1.0.0-beta.15.tar) z [testovacího vydání beta.15](https://github.com/PTiCA1/IPS-Content-Placements/releases/tag/v1.0.0-beta.15), případně použijte soubor z `dist/`. Pro ověření příkazem `shasum -a 256 -c SHA256SUMS` mějte ve stejném adresáři také `SHA256SUMS` a všechna čtyři překladová XML. Pro samotnou instalaci stačí TAR.
2. V **ACP → System → Applications** zvolte nahrání/instalaci aplikace a nahrajte **TAR**. ZIP zdrojového repozitáře se neinstaluje.
3. Otevřete **ACP → Content Placements → Pravidla umístění** (anglicky **Placement rules**). Standardní název hlavní záložky je ve všech jazycích **Content Placements**, podnabídky zůstávají přeložené. Jde o vlastní záložku aplikace poblíž spodního konce postranního menu, nikoli položku uvnitř nativních reklam nebo Pages. Dalším administrátorům případně přidělte oprávnění `rqbp_manage`.
4. V Pages vytvořte jednoduchý HTML blok a přidejte pravidlo pro jeden známý CSS selektor nebo hook. Začněte prvním výskytem, všemi platformami a bez omezení skupin či URL.
5. Projděte [testovací postup](docs/BETA-TEST.cs.md) včetně pohledu běžného člena a hosta.

Pokud menu nemůžete najít, otevřete současnou adresu svého ACP s `index.php` a nahraďte pouze část dotazu za otazníkem hodnotou `app=rqblockplacements&module=placements&controller=rules`. Zachovejte skutečný adresář ACP ze své aktuální adresy, včetně případně přejmenované složky; nenahrazujte jej odhadnutou cestou `/admin/`. Globální přepínač je v **Content Placements → Nastavení** (anglicky **Settings**). V beta.1 chyběla ikona, takže nová spodní záložka v úzkém menu vypadala prázdně; přímá URL na ikoně nezávisí.

Pro rychlé vypnutí použijte globální přepínač, vypněte konkrétní pravidlo nebo celou aplikaci. Odinstalace odstraní pravidla a generované markery doplňku; existující Pages bloky neodstraňuje.

Nativní hook je místem vložení; HTML bloku musí také odpovídat zvolenému kontextu. Katalog vynechává nepodporované kontexty; nativní `head` a `body` přijímají pouze vnitřní pozice a head navíc vyžaduje výstup tvořený jen metadaty. Ověřte i okolní HTML, CSS a skripty bloku. Některé interaktivní bloky mají pevná HTML ID nebo inicializaci určenou jen pro jeden výskyt.

## Čeština, slovenština, polština a němčina

Každý překlad obsahuje všechny texty aplikace. Instalace i aktualizace přes ACP automaticky doplní chybějící překlady do existujících jazyků podle jejich locale:

| Jazyk | Příklady rozpoznaného locale |
| --- | --- |
| Čeština | `cs`, `cs_CZ`, `cs-CZ`, `Czech_…` |
| Slovenština | `sk`, `sk_SK`, `sk-SK`, `Slovak_…` |
| Polština | `pl`, `pl_PL`, `pl-PL`, `Polish_…` |
| Němčina | `de`, `de_DE`, `de-DE`, `German_…` |

Pro tyto existující jazyky není samostatný import XML potřeba. Vlastní texty včetně záměrně prázdných hodnot se nepřepisují. Aktualizace doplní chybějící klíče; změnu existujícího překladu provedete výslovným obnovením. Beta.9 navíc přejmenuje přesně známé standardní názvy z předchozích bet; odlišné vlastní názvy zůstanou zachované. Ostatním jazykům zůstávají anglické výchozí texty. Aplikace nevytváří nový jazyk, nepřepíná výchozí jazyk webu ani nemění metadata jazyků.

Vydání obsahuje také volitelná `rqblockplacements-1.0.0-beta.15-cs.xml`, `-sk.xml`, `-pl.xml` a `-de.xml`; uvnitř TAR jsou jako `resources/lang-{cs,sk,pl,de}.xml`. Příslušné XML použijte ve správě jazyků ACP pro výslovné obnovení překladu nebo jazyk přidaný až po instalaci aplikace. Soubory překládají pouze tento doplněk. Před volitelným nativním importem zamýšlený jazyk zálohujte; zacházení s metadaty při tomto importu řídí IPS.

## Pravidla a diagnostika

Prázdný seznam platforem, povolených skupin, aplikací nebo povolených URL znamená bez omezení daným filtrem. Vyloučení skupin a URL stále platí. Pro fórum na `https://example.test/community/` zadávejte například `/topic/*`. Každý vzor patří na samostatný řádek. Regulární výrazy se nepoužívají.

Android s tokenem `Mobile` patří do Mobilu; Android bez něj, iPad a Kindle do Tabletu. Ostatní nebo nerozpoznaná zařízení patří do Desktopu. Změna šířky okna třídu nemění a iPad s desktopovým user-agentem nemusí server od Desktopu rozlišit. Staré OS volby se při čtení převedou: Android → Mobil + Tablet, iPhone → Mobil, iPad/Kindle/PlayBook → Tablet, PC/neznámé → Desktop. Uložení pravidla zapíše nové hodnoty; změna schématu databáze není potřeba.

U hooku jde o jeho vyvolání během jednoho serverového požadavku. Hook v opakované řádkové šabloně může mít více výskytů. V režimech CSS jde o odpovídající cílový prvek v dokumentu nebo podporovaném fragmentu kontroleru; vložené bloky se nepovažují za cíle. Seznamy s čárkou používají jeden seznam bez duplicit v pořadí dokumentu, včetně skrytých prvků. Pravidla se řadí podle nastaveného pořadí a ID. Chybějící Pages, blok, oprávnění, hook nebo selektor se mají projevit prázdným výstupem; příčinu ověřte v diagnostice pravidla a v IPS logu.

Stránky hostů s odlišnými platformami nesmějí sdílet jednu CDN cache variantu. Runtime pro relevantní cesty ukládání stránky potlačuje. Při změně pravidel vyprázdněte už existující cache dotčených stránek a samostatně otestujte svou CDN. Doplněk ukládá do cache konfiguraci, nikoli vykreslené HTML sdílené mezi uživateli. Pages blokům zůstává jejich vlastní nativní chování.

## Sestavení

```sh
npm ci
npm run test:php
npm run test:build
npx playwright install chromium
npm run test:browser
python3 tools/build.py --check-reproducible
python3 tools/validate.py --archive dist/rqblockplacements-1.0.0-beta.15.tar
```

Potřebujete PHP (ověřeno s 8.3), Python 3.10+ a pro test prohlížeče Node 20+. `PHP_BINARY` může určit jinou binárku PHP; `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` vlastní Chrome/Chromium. Překlady se generují z vlastních PHP polí `application/resources/lang/{en,cs,sk,pl,de}.php`.

TAR má v kořeni `data/application.json`. Pořadí souborů, práva, vlastník i časy jsou normalizované. `SOURCE_DATE_EPOCH` má výchozí hodnotu `0`; stejné zdroje tedy vytvářejí stejné bajty. `dist/SHA256SUMS` ověřuje TAR i čtyři samostatná překladová XML. Instalátor čte přibalené překlady XML. Skript balí pouze vlastní aplikační adresář. Další informace jsou v [dokumentaci vývoje](docs/DEVELOPMENT.md).

Volitelně lze jen pro čtení zkontrolovat několik API signatur podle samostatně získaných lokálních zdrojů:

```sh
python3 tools/validate.py --ips-source /absolutni/cesta/ips_v5.0.20
```

Automatické kontroly ověřují syntaxi, metadata a vybrané chování. Nepotvrzují skutečnou instalaci, aktualizaci ani kompletní vykreslení bloku v IPS.

## Podpora a hlášení chyb

Běžné chyby a návrhy hlaste přes [GitHub Issues](https://github.com/PTiCA1/IPS-Content-Placements/issues). Přidejte verzi aplikace, IPS, PHP a motivu, postup reprodukce a očištěné logy. Odstraňte přístupové údaje, cookies, CSRF tokeny a soukromý obsah. Podezření na bezpečnostní zranitelnost hlaste soukromě podle [SECURITY.md](SECURITY.md).

## Licence

[MIT](LICENSE) platí jen pro původní kód a dokumentaci tohoto doplňku. Invision Community je samostatně licencovaný proprietární software. Repozitář neobsahuje zdroje IPS, developer tools, přístupy ani obsah fóra. Jde o nezávislý komunitní doplněk.
