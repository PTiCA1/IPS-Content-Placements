# Pages Block Placements

Samostatná aplikace pro vkládání existujících Pages bloků před nebo za CSS selektor či nativní hook Invision Community. Má vlastní pravidla pro platformy, skupiny, aplikace a URL. Zdrojový kód doplňku je pod licencí MIT. [English](README.md).

**1.0.0-beta.4 — sestavení 1000004.** Připraveno podle Invision Community **5.0.20**. Při instalaci a aktualizaci se nyní automaticky doplní české texty aplikace do existujících českých jazyků. Platformy nabízejí pouze **Mobil, Tablet a Desktop**. Majitel potvrdil funkční editor pravidel v beta.2; cílení na zařízení, automatická čeština a aktualizace na běžícím fóru **ještě čekají na ověření**. Zveřejnění na GitHubu a stabilní 1.0.0 následují po dokončení testu.

## Funkce první verze

- Výběr Pages bloku nebo zadání jednoho přesného odkazu, například `{block="welcome"}`.
- Výběr CSS selektoru nebo dostupného nativního hooku šablony a polohy před / za prvkem.
- První, konkrétní číslovaný nebo všechny odpovídající prvky či výskyty hooku.
- Výběr Mobilu, Tabletu a/nebo Desktopu. Prázdný výběr povoluje všechny tři třídy. Rozhoduje platforma IPS a user-agent, nikoli šířka okna.
- Povolené a vyloučené skupiny včetně vedlejších členství. Vyloučení má přednost.
- Volitelný výběr aplikací a povolených/vyloučených relativních URL. Zástupný znak je `*`; vyloučení má přednost. Query string je součástí porovnání, část za `#` nikoli.
- Nativní kontrola aktivity, oprávnění a vykreslení Pages bloku přes `IPS\cms\Blocks\Block::display()` v původním požadavku stránky. Nevzniká zvláštní HTTP požadavek pro vykreslení bloku.

Doplněk nerozšiřuje nativní reklamní systém. Hook musí v instalované šabloně existovat. V režimu CSS lze cílit i na kategorii bez vlastního hooku, například `li[data-categoryID="4"]`; číslo `4` nahraďte ID skutečně přítomným na svém fóru. CSS selektor závisí na HTML motivu a po aktualizaci motivu či platformy jej ověřte.

## Instalace bety přes ACP

Potřebujete licencované IPS 5.0.20 se zapnutými **Pages**, povolené vlastní aplikace a oprávnění instalovat je v ACP. Použijte testovací instalaci a před instalací nebo aktualizací zálohujte databázi i soubory.

1. Připravte `rqblockplacements-1.0.0-beta.4.tar` a `SHA256SUMS` z adresáře `dist/`. Ve společném adresáři souborů vydání je můžete ověřit příkazem `shasum -a 256 -c SHA256SUMS`. Samostatný český XML je volitelný.
2. V **ACP → System → Applications** zvolte nahrání/instalaci aplikace a nahrajte **TAR**. ZIP zdrojového repozitáře se neinstaluje.
3. Otevřete **ACP → Block placements → Placement rules**. Při použití existujícího českého jazyka je cesta **Umístění bloků → Pravidla umístění**, bez samostatného importu XML. Jde o vlastní záložku aplikace poblíž spodního konce postranního menu, nikoli položku uvnitř nativních reklam nebo Pages. Dalším administrátorům případně přidělte oprávnění `rqbp_manage`.
4. V Pages vytvořte jednoduchý HTML blok a přidejte pravidlo pro jeden známý CSS selektor nebo hook. Začněte prvním výskytem, všemi platformami a bez omezení skupin či URL.
5. Projděte [testovací postup](docs/BETA-TEST.cs.md) včetně pohledu běžného člena a hosta.

Pokud menu nemůžete najít, otevřete současnou adresu svého ACP s `index.php` a nahraďte pouze část dotazu za otazníkem hodnotou `app=rqblockplacements&module=placements&controller=rules`. Zachovejte skutečný adresář ACP ze své aktuální adresy, včetně případně přejmenované složky; nenahrazujte jej odhadnutou cestou `/admin/`. Globální přepínač je v **Block placements → Settings**, česky **Umístění bloků → Nastavení**. V beta.1 chyběla ikona, takže nová spodní záložka v úzkém menu vypadala prázdně; přímá URL na ikoně nezávisí.

Pro rychlé vypnutí použijte globální přepínač, vypněte konkrétní pravidlo nebo celou aplikaci. Odinstalace odstraní pravidla a generované markery doplňku; existující Pages bloky neodstraňuje.

Nativní hook nezaručuje, že je do jeho místa vhodné vložit celý blok. Nevkládejte celý blok dovnitř `head`, `title`, odkazů ani jiných nevhodných HTML prvků. Ověřte i CSS a skripty bloku. Některé interaktivní bloky mají pevná HTML ID nebo inicializaci určenou jen pro jeden výskyt.

## Čeština

TAR obsahuje české texty aplikace a při instalaci i aktualizaci je automaticky doplní do existujících jazyků s českým locale, například `cs_CZ`, `cs-CZ` nebo `Czech_…`. Samostatný import XML není potřeba. Vlastní úpravy textů včetně záměrně prázdné hodnoty zůstanou zachované. Aktualizace doplní nové klíče; změnu existujícího přeloženého textu provedete volitelným importem XML. Ostatní jazyky používají anglické výchozí texty; aplikace nepřepíná výchozí jazyk webu ani nemění metadata jazyků.

Samostatný `rqblockplacements-1.0.0-beta.4-cs.xml` (uvnitř TAR jako `resources/lang-cs.xml`) zůstává pro výslovné obnovení překladu nebo český jazyk přidaný až po instalaci aplikace. Přes správu jazyků v ACP jej importujte do zamýšleného jazyka. Soubor překládá jen tento doplněk. Před volitelným nativním importem XML jazyk zálohujte; jeho zacházení s metadaty řídí IPS.

## Pravidla a diagnostika

Prázdný seznam platforem, povolených skupin, aplikací nebo povolených URL znamená bez omezení daným filtrem. Vyloučení skupin a URL stále platí. Pro fórum na `https://example.test/community/` zadávejte například `/topic/*`. Každý vzor patří na samostatný řádek. Regulární výrazy se nepoužívají.

Android s tokenem `Mobile` patří do Mobilu; Android bez něj, iPad a Kindle do Tabletu. Ostatní nebo nerozpoznaná zařízení patří do Desktopu. Změna šířky okna třídu nemění a iPad s desktopovým user-agentem nemusí server od Desktopu rozlišit. Staré OS volby se při čtení převedou: Android → Mobil + Tablet, iPhone → Mobil, iPad/Kindle/PlayBook → Tablet, PC/neznámé → Desktop. Uložení pravidla zapíše nové hodnoty; změna schématu databáze není potřeba.

U hooku jde o jeho vyvolání během jednoho serverového požadavku. Hook v opakované řádkové šabloně může mít více výskytů. V režimu CSS jde o odpovídající cílový prvek v dokumentu; vložené bloky se nepovažují za cíle. Pravidla se řadí podle nastaveného pořadí a ID. Chybějící Pages, blok, oprávnění, hook nebo selektor se mají projevit prázdným výstupem; příčinu ověřte v diagnostice pravidla a v IPS logu.

Stránky hostů s odlišnými platformami nesmějí sdílet jednu CDN cache variantu. Runtime pro relevantní cesty ukládání stránky potlačuje. Při změně pravidel vyprázdněte už existující cache dotčených stránek a samostatně otestujte svou CDN. Doplněk ukládá do cache konfiguraci, nikoli vykreslené HTML sdílené mezi uživateli. Pages blokům zůstává jejich vlastní nativní chování.

## Sestavení

```sh
npm ci
npm run test:php
npm run test:build
npx playwright install chromium
npm run test:browser
python3 tools/build.py --check-reproducible
python3 tools/validate.py --archive dist/rqblockplacements-1.0.0-beta.4.tar
```

Potřebujete PHP (ověřeno s 8.3), Python 3.10+ a pro test prohlížeče Node 20+. `PHP_BINARY` může určit jinou binárku PHP; `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` vlastní Chrome/Chromium. Překlady se generují z vlastních PHP polí `application/resources/lang/{en,cs}.php`.

TAR má v kořeni `data/application.json`. Pořadí souborů, práva, vlastník i časy jsou normalizované. `SOURCE_DATE_EPOCH` má výchozí hodnotu `0`; stejné zdroje tedy vytvářejí stejné bajty. `dist/SHA256SUMS` ověřuje TAR i samostatný český XML. Skript balí pouze vlastní aplikační adresář. Další informace jsou v [dokumentaci vývoje](docs/DEVELOPMENT.md).

Volitelně lze jen pro čtení zkontrolovat několik API signatur podle samostatně získaných lokálních zdrojů:

```sh
python3 tools/validate.py --ips-source /absolutni/cesta/ips_v5.0.20
```

Automatické kontroly ověřují syntaxi, metadata a vybrané chování. Nepotvrzují skutečnou instalaci, aktualizaci ani kompletní vykreslení bloku v IPS.

## Licence

[MIT](LICENSE) platí jen pro původní kód a dokumentaci tohoto doplňku. Invision Community je samostatně licencovaný proprietární software. Repozitář neobsahuje zdroje IPS, developer tools, přístupy ani obsah fóra. Jde o nezávislý komunitní doplněk.
