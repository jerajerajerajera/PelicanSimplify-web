<!-- GEMEENSCHAPPELIJK DEEL: in alle 6 Simplify-repo's letterlijk gelijk. Wijzig het overal tegelijk. -->
# Werkwijze Simplify

Simplify is één product: de kern `simplify` plus de add-ons `simplify-servermaker`, `-toolkit`, `-eggupdates`,
`-explain` en `-hosting`. De splitsing is alleen verpakking. Elke add-on volgt dezelfde UI en dezelfde regels.
Schrijf met de eigenaar in het Nederlands; code, README, CHANGELOG, commits en PR's in het Engels.

## 0. Designfilosofie en doel

- Simplify heeft een designfilosofie: snel, overzichtelijk, duidelijk en overal hetzelfde. §4 zijn die regels.
- De werking van 0.7.0 is leidend. Bij twijfel: vragen aan de eigenaar (via de regisseur), niet raden.
- Simplify neemt alle functies over: niemand hoeft terug naar Pelicans admin, tenzij er echt iets breekt.
- Iconen: alleen Tabler. Servericoon: eigen server-icon.png > Pelican-servericoon > egg-icoon > grasblok (Minecraft) > kubus.
- Zo klein mogelijk: geen dubbele of onnodige functies, niet meer code dan nodig. Na een aantal wijzigingen een
  opschoonronde (wat kan weg, samen of korter) als eigen PR.
- Veiligheid staat voorop: rechten server-side, invoer valideren, geen secrets, geen open routes zonder controle.
- Testfase: de eerste Hub-versie moet echt goed zijn, zodat er daarna weinig updates nodig zijn.

## 1. De opdracht: 0.7.0 is de norm

Simplify is een herbouw van PelicanSimplify 0.7.0 (snapshot.10) **met alle functionaliteit**. "Kleiner geschreven"
betekent minder code, nooit minder functies.

- De functielijst staat in de kernrepo: `docs/REFERENCE-070.md`. Elke regel is een functie uit 0.7.0, met de repo die
  hem levert en een status: `aanwezig`, `bewust weg (datum, akkoord eigenaar)` of `ontbreekt`.
- Deze lijst is de norm voor wat er moet bestaan. Een functie is pas `aanwezig` met een test die hem echt
  gebruikt (kolom **test**); zonder test is hij niet af.
- Een functie mag alleen `bewust weg` of `anders` zijn met het akkoord van de eigenaar erbij. Jij beslist dat nooit zelf.
- Raakt je wijziging een scherm of functie uit die lijst, vergelijk dan met 0.7.0 vóór je "klaar" zegt: dezelfde
  knoppen, menu-items, tabbladen en instellingen, en maten niet groter dan 0.7.0 + 10%.
- Bouw je iets opnieuw, maak dan eerst de lijst van wat het oude scherm kon, en pas dan de code.

## 2. Definitie van klaar

Een wijziging is pas klaar als alles hieronder waar is. Meld nooit "gebouwd" of "klaar" zonder dit.

1. **Lokale checks groen:** `bash tests/run.sh`, `pint --test`, `python3 tests/hub-check.py`.
2. **CI groen:** `checks.yml` met panel-smoke op elke Pelican-versie in de matrix.
3. **Updatepad getest** als er een migratie of instelling verandert: vanaf de oudste ondersteunde versie
   (en vanaf 0.7.0-bestanden voor de kern) naar de nieuwe, met ingevulde instellingen, niet alleen een verse installatie.
4. **Doorgeklikt in een echt paneel**, met data die op het echte paneel lijkt (veel servers, lange namen, een plugin die
   niet van Simplify is, een server die nooit gestart is, een draaiende console). Elke knop die je aanraakte is echt
   **geklikt**, niet alleen bekeken: het gevolg van de klik klopt, er ligt niets overheen (ook niet boven de console),
   en een klik in het menu laadt alleen het contentvak.
5. **Stijlaudit groen** (kernrepo, `audit.yml`) als je UI raakt, op d1440, laag1366 en phone390, licht én donker.
   Met `section=compare` geen scherm groter dan 0.7.0 + 10%.
6. **Screenshot van het echte scherm** na de wijziging, bij de melding. Een mock-up of demo is geen bewijs.

Kan een stap niet (geen toegang, CI-minuten op), zeg dan precies welke stap ontbreekt. Nooit "zou moeten werken".

## 3. Elke fout van het echte paneel: eerst een rode test

Vindt de eigenaar (of jij) een fout op het echte paneel, dan:

1. Schrijf eerst een test (unit, panel-smoke of audit) die de fout laat zien. Laat zien dat hij **rood** is op de
   versie met de fout.
2. Fix de fout. Laat zien dat dezelfde test nu **groen** is.
3. Zet bij de fix in de PR: welke test, rood op welke commit, groen op welke commit.

Een fix zonder test die eerst rood was, is niet af. Zo komt dezelfde fout niet terug.

## 4. UI-regels (hard; een overtreding is een fout, geen smaak)

- De pagina scrolt nooit; alleen het contentvak scrolt, binnen zijn ronding.
- Het contentvak is een kaart; knoppen en Save staan in de titelbalk. Geen vak-in-vak.
- Alleen Tabler-iconen. Geen grote rode knoppen; gevaarlijke acties in het ⋯-menu.
- Laden met placeholders, nooit een leeg of springend scherm.
- Menu-items en tabbladen laden alleen het contentvak (`wire:navigate`, console en websocket blijven staan).
  Een volledige paginalading na een menuklik is een fout.
- Menu's, pop-ups en dropdowns liggen boven alles: de console (xterm) gebruikt lagen tot z-index 10, dus ≥ 20.
  Test met een klik, niet met een screenshot: onder het midden van de knop moet de knop zelf liggen.
- Scrollbalken: dun (11px, duim 4px), binnen de ronding (14px boven/onder, 4px rand), in de kleur van de achtergrond
  (wit 16% donker, zwart 16% licht).
- Paginatitel: `[servernaam] - Simplify`.
- Servericoon: eigen server-icon.png > Pelican-servericoon > egg-icoon > grasblok (Minecraft) > kubus.
- **Create server** opent vanaf elke ingang (servermenu, overzicht, admin Servers) de Server Maker als Simplify-popup,
  nooit het formulier van Pelican.
- Groepen in het admin-menu (zoals "Simplify" en "Other plugins") klappen uit; een losse pagina draagt de pluginnaam.
- Serveroverzicht minstens zo compact als 0.7.0.
- Nieuwe UI of een gewijzigd scherm: eerst een proefscherm (artifact of demo) laten zien, pas bouwen na akkoord.

## 5. Code

- PSR-12 en `pint`. Elke functie in een eigen bestand. Schrijf zoals de code eromheen.
- Migraties: idempotent (`hasTable`/`hasColumn`), bestandsnaam bevat `simplify`, en **nooit** plugin-klassen
  (zoals `PanelSettings`) gebruiken: tijdens een update zijn die niet geladen. Alleen `DB`/`Schema`.
- Laravel-jobs: noem een methode nooit `queue()` (botst met de Dispatcher).
- Hub-regels: geen verboden functienamen (`tests/hub-check.py`), geen eigen updater of pluginbeheer, updates alleen
  via de Hub. De knop-klasse heet `Button`, niet `Link`.
- Teksten: alle 32 talen in dezelfde PR (`tests/lang.php`).
- Na een merge in CSS: accolades tellen.
- Geen `composer install` of `yarn install` in de cloudcontainer; het echte paneel draait op GitHub Actions.
- Schrijf in README, CHANGELOG, commits en PR's neutraal; nooit over "AI" of hoe de code tot stand kwam.

## 6. GitHub en releases

- Elke repo heeft één vaste thread die de eigenaar is. Alleen die thread merget in deze repo.
- Werk op een branch, open een PR, squash-merge met een nette titel. Nooit direct op `main`. Eén wijziging per PR.
- De repo-thread mag zelf mergen als CI groen is en de definitie van klaar (§2) gehaald is. Wijzigingen verzamelen
  zich op `main` onder "Unreleased" in de CHANGELOG.
- Weinig pushes: push pas als een wijziging lokaal af en getest is. Geen nieuwe versie per kleine wijziging.
- Een release (kern én add-on) alleen met een uitdrukkelijk ja van de eigenaar; de regisseur van het project stelt
  een release voor als er genoeg wijzigingen zijn. Daarna tagt de repo-thread (Hub-zip `<id>-v<versie>.zip`) en meldt
  de zip-link; de Hub-upload doet de eigenaar.
- Elke zichtbare wijziging, ook een kleine, eerst als voorbeeld (artifact, vóór/na) laten goedkeuren; pas dan bouwen.
- De kern vereist vóór een release: stijlaudit
  groen, upgradetest groen, functielijst bijgewerkt (`docs/REFERENCE-070.md`), en screenshots van de gewijzigde schermen.
- CI kost de eigenaar mails bij elke rode run: niet per poging pushen om CI te testen; test eerst lokaal.
- Nooit secrets, tokens, wachtwoorden of interne adressen in code, issues, PR's of output.
- Een PR die niet doorgaat sluit je zelf, met een korte reden. Geen oude branches laten slingeren.

## 7. Melden

- Lever eerst een **volledige** lijst van bevindingen, niet in stukjes.
- Een vraag aan de eigenaar is één ja/nee-vraag met een aanbeveling.
- Geen tijdschattingen.
- Zeg eerlijk wat niet getest is.

## 8. Samenwerken en vaste afspraken

- **Add-ons werken los, maar als de kern geïnstalleerd is openen ze altijd binnen Simplify** (frame, servermenu,
  hostmodus of Simplify-popup), nooit als losse Pelican-pagina. Test daarom altijd met én zonder de kern.
- **Altijd het Simplify-design**: §4 geldt voor elke add-on precies zoals voor de kern.
- Admin-menu groep "Simplify", volgorde: kern 1/2, Egg Updates 20, Explain 40, Hosting 50, Server Maker 80, Toolkit 95.
- Plugin-id's `simplify-<naam>` zonder streepje tussen twee woorden. Announcements, Embed en Subdomains zijn kernfuncties.
- EULA: optioneel bij aanmaken; het verplichte vinkje komt pas bij de eerste start, in de titelkaart, met de knop
  "First start" (play-icoon). Geen Restart zolang de server nooit gestart is.
- "Automatic backups" is een tab van "Backups & tasks".
- Seizoensversiering is stil: nooit in CHANGELOG, releasenotes, README of Hub-teksten, en verborgen op screenshots.
- Screenshots en Hub: nooit Pelicans gewone beheerschermen (zoals People & roles in Filament-stijl); add-ons in
  hostmodus; elke add-on-banner "Works best with Simplify"; eerste Hub-changelog "First version on the Hub".
- Een check heeft altijd de uitkomst ok of fout tegen een regel, nooit alleen informatie. De vaste controles staan in
  de kernrepo (`tests/audit`): functielijst tegenover 0.7.0, klik-test op elke knop, navigatie zonder herlaad, maten
  tegenover 0.7.0 + 10%, een testpaneel dat op het echte paneel lijkt, en het updatepad met echte instellingen.
- Bij testen meteen screenshots van de kernfuncties die je raakte in `docs/screenshots/` van de repo (vaste naam per
  scherm, licht en donker, desktop en telefoon), zodat documentatie en releases ze hergebruiken.
- Werk dat een andere repo raakt: vraag de thread van die repo; bouw het nooit zelf daar. Overleg onderling als er
  iets is, en meld zichtbare wijzigingen altijd aan de documentatie-thread en release-kandidaten aan de release-thread.
- Melden gaat via de regisseur van het project. Teksten voor de eigenaar als Engels codeblok. Commando's in één
  codeblok, met erboven op welk systeem ze draaien. Op het paneel draait artisan altijd als
  `sudo -u www-data php artisan …`.
- De eigenaar zo min mogelijk zelf laten doen. Bij twijfel kort vragen, niet raden.

## Deze repo: website-plugin (`PelicanSimplify-web`, plugin-id `server-directory`)

- Dit is alleen de plugin voor de eigen Deckzwabbers-website van de eigenaar (deckzwabbers.nl), geen Simplify-add-on
  en niet voor andere Simplify-gebruikers. Hij staat niet op de Hub en werkt bij via `update.json` op `main` (zie
  README, "Release"). Hub-regels, 32 talen en algemene instellingen voor anderen gelden hier dus niet. Een release
  komt er pas na het ja van de eigenaar via de regisseur; uitrollen op het paneel en de website doet de eigenaar.
- Wat hij doet: per server kiest een admin wat de website toont (zichtbaar, beschrijving, modpack, screenshots,
  join-adres). De website haalt de lijst op met een token (`directory:token`); interne adressen (`targetHost`) gaan
  alleen naar de relay van de website, nooit naar bezoekers.
- Join-adres, in deze volgorde: eigen adres > werkend Simplify-subdomein > game-adres > (alleen in testmodus) de
  allocatie. Privé-, Tailscale- en loopbackadressen komen nooit op de website (`tests/public-address.php`).
- De kern is optioneel. Alleen publieke kernmethodes gebruiken (`Simplify\Core\Subdomains\Subdomains`, zie `ADDONS.md`
  van de kern), altijd achter `class_exists`/`method_exists`; zonder kern valt hij terug op het IP-adres en de poort.
- Wat Simplify zelf al doet (bijv. de schakelaar "op de website"), neemt hij over van de kern in plaats van het
  dubbel te bewaren. Zijn admin-pagina staat met de kern onder "Other plugins" en volgt het Simplify-design (§4).
- Lokale checks hier: `bash tests/run.sh` (PHP-syntaxis, alle `tests/*.php`, package-layout). Er is nog geen pint,
  panel-smoke, `hub-check.py` of vertaling in deze repo; zeg dat eerlijk bij "klaar".
- Doorklikken: admin › Server directory, een server bewerken en opslaan; daarna de JSON-feed met token bekijken,
  met én zonder Simplify-kern (met kern: een server met werkend subdomein toont dat adres).
