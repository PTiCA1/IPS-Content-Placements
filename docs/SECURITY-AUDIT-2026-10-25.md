# Bezpečnostní audit a refaktor — 25. 10. 2026

Rozsah: původní aplikace `rqblockplacements` ve verzi beta.5, opravy vydané jako **1.0.0-beta.6 / 1000006**. Proběhla ruční kontrola serveru, ACP a klientského kódu s nezávislou kontrolou oprav a automatickými regresními testy. Relevantní chování bylo ověřeno proti lokálním zdrojům IPS 5.0.20; tyto zdroje nejsou součástí repozitáře ani balíčku.

## Opravené nálezy

| Nález | Dopad a předpoklad | Oprava |
| --- | --- | --- |
| Vysoká priorita: varianty bloků podle skupin hostů mohly zůstat veřejně cachovatelné. | Nativní ověřený Facebook bot může mít při `member_id=0` jinou skupinu než běžný host. Skupinové filtry i oprávnění bloku mohou vytvořit jiný obsah na stejném URL. Předchozí guard nepokrýval všechna bloková pravidla. | Cache se vypíná pro každé aktivní pravidlo odpovídající aplikaci a URL, ještě před vyhodnocením publika. |
| Vysoká priorita: explicitní veřejné hlavičky IPS mohly přebít vypnutí cache. | Vyhledávání a archivovaná témata používají veřejné `Cache-Control` v instanci Output; hlavičky instance mají při odesílání přednost. U archivovaných témat může jít o 12 hodin. Reprodukce použila skutečnou metodu IPS `Output::sendOutput`, nikoli pouze napodobeninu merge. | `PageCache` nastaví i výsledné hlavičky `private, no-store`, odstraní konfliktní varianty názvů a neutralizuje přítomné CDN override hlavičky. |
| Střední priorita: omezený správce mohl globálně znovu aktivovat kód. | Správce s `rqbp_manage`, bez `rqbp_manage_code`, mohl změnit hlavní přepínač z vypnuto na zapnuto při existujícím aktivním template pravidle. Nepovolovalo to vložit nový PHP kód, ale obcházelo to ochranu jeho aktivace. Reprodukce využila řádný token omezeného správce. | `RuleAccess` ověřuje přechod vypnuto → zapnuto před první mutací nastavení. Nouzové vypnutí zůstává dostupné. |
| Střední priorita: pokračování starého skriptu po změně URL. | Během čekání na externí skript mohla následná inline část staré reklamy běžet na novém URL a starý obal zůstával připojený. | Jednotný kontext stránky, okamžité odstranění starých obalů, zrušení čekání a kontrola před každým dalším skriptem i po čekání. Již předaný externí skript nelze spolehlivě odvolat. |
| Střední priorita, dostupnost: `nomodule` blokovalo moderní prohlížeče. | Moderní prohlížeč ignoruje externí `nomodule` a nemusí vyvolat očekávanou událost; další skripty čekaly až 15 sekund. | Správná volba module/nomodule bez čekání na nepoužitelný skript. |

U prvních dvou nálezů byly reprodukovány podmínky vzniku veřejné varianty a skutečná priorita hlaviček. Neproběhl útok na živou CDN ani nebyl prokázán již uskutečněný únik dat na produkci.

## Refaktor a další zpevnění

- `RuleAccess`: jedno místo pro pravidla oprávnění včetně změny pořadí a globální aktivace. Kontrola předchází všem zápisům.
- `PageCache`: jedno místo pro soukromé odpovědi; guard se označí za dokončený až po úspěchu. Regrese pokrývá jednorázové i trvalé selhání hlaviček.
- Klientský kontext: rušení starého čekání a sloučení opakovaných `contentChange` událostí do jednoho průchodu.
- Balíček: explicitní kontrolovaný manifest souborů. Neočekávané soubory, chybějící položky a symlinky zastaví build. Nejde o nález již uniklých údajů, ale o prevenci nechtěného přibalení.

## Ověření

- PHP: **493 kontrol** politik, runtime, cache, šablon, ACP, překladů a migrací s původními testovacími napodobeninami rozhraní.
- Navíc skutečné lokální API IPS 5.0.20: **19 kontrol překladače šablon a 10 kontrol odesílání hlaviček**.
- Chromium: **17 scénářů**, včetně pozdrženého externího skriptu při změně URL, module/nomodule, neaktivních datových skriptů a dávky 100 událostí.
- Python: **7 testů** sestavení a hranic balíčku, reprodukovatelný TAR, porovnání archivu se zdroji a SHA256.
- `npm audit --json --ignore-scripts`: **0 hlášených známých zranitelností** k datu auditu. Npm závislosti slouží testům; nejsou dodávané v instalačním TAR.
- Nativní deklarace ACP oprávnění, CSRF formulářů a akcí, parametrizované dotazy, JSON transport, escapování názvů a diagnostiky byly zkontrolovány. Další reprodukovatelný průnik těchto hranic v prověřeném rozsahu nebyl nalezen.

## Meze a ověření na fóru

Vlastní šablona záměrně může spouštět PHP a reklamní HTML záměrně může obsahovat JavaScript. Tuto důvěru nelze nahradit validací syntaxe. Audit neprověřuje samotnou třídu MafraApi, jiné doplňky, konfiguraci serveru, všechny verze IPS ani pravidla CDN. Není důkazem absolutní nepřítomnosti zranitelností.

Po aktualizaci na beta.6 vyprázdněte existující CDN/reverzní proxy cache dotčených URL; nová hlavička neodstraní již uložené staré odpovědi. Na skutečném fóru ověřte výsledné `Cache-Control` a střídání skupin/zařízení na stejném URL, včetně archivovaného tématu. Stránky s odpovídajícím aktivním pravidlem nově obcházejí sdílenou cache i tehdy, když konkrétní návštěvník blok neuvidí; to je záměrná výměna části výkonu za oddělení variant.
