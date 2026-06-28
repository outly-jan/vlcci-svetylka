<?php
/**
 * Plugin Name: Vlčí a světýlkové odborky
 * Description: Evidence plnění odborek pro oddíly vlčat a světlušek. Středisko Chlumec nad Cidlinou.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class VlcciOdborky {

	// ── INIT ─────────────────────────────────────────────────────────────────

	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		add_action( 'admin_menu',        [ $this, 'admin_menu' ] );
		add_action( 'admin_init',        [ $this, 'handle_post' ] );
		add_action( 'wp_head',           [ $this, 'inject_css' ] );
		add_action( 'admin_head',        [ $this, 'inject_css' ] );
		add_shortcode( 'vlcci_prehled', [ $this, 'shortcode_prehled' ] );
	}

	// ── ACTIVATION / DB ──────────────────────────────────────────────────────

	public function activate(): void {
		global $wpdb;
		$c = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_oddily (
			id int NOT NULL AUTO_INCREMENT,
			nazev varchar(200) NOT NULL,
			typ enum('vlcata','svetlusky') NOT NULL DEFAULT 'vlcata',
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_sestky (
			id int NOT NULL AUTO_INCREMENT,
			oddil_id int NOT NULL,
			nazev varchar(200) NOT NULL,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_vedouci (
			sestka_id int NOT NULL,
			user_id int NOT NULL,
			PRIMARY KEY (sestka_id, user_id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_deti (
			id int NOT NULL AUTO_INCREMENT,
			sestka_id int NOT NULL,
			jmeno varchar(100) NOT NULL,
			prijmeni varchar(100) NOT NULL,
			prezdivka varchar(100) NOT NULL,
			aktivni tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_odborky (
			id int NOT NULL AUTO_INCREMENT,
			typ enum('vlcata','svetlusky','oba') NOT NULL DEFAULT 'oba',
			nazev varchar(200) NOT NULL,
			min_ukolu tinyint NOT NULL DEFAULT 8,
			obrazek varchar(100) DEFAULT NULL,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_ukoly (
			id int NOT NULL AUTO_INCREMENT,
			odborka_id int NOT NULL,
			poradi tinyint NOT NULL DEFAULT 0,
			nazev text NOT NULL,
			PRIMARY KEY (id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}vo_plneni (
			id int NOT NULL AUTO_INCREMENT,
			dite_id int NOT NULL,
			ukol_id int NOT NULL,
			datum_splneni date NOT NULL,
			poznamka text,
			vedouci_id int NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY dite_ukol (dite_id, ukol_id)
		) $c;" );

		update_option( 'vo_db_version', '1' );
		$this->seed_odborky();
	}

	private function seed_odborky(): void {
		global $wpdb;
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vo_odborky" ) > 0 ) return;

		// [ nazev, [ ukoly... ], obrazek ]
		$odborky = [
			[ 'Divadelník', [
				'Scénka. Se svou šestkou zahrajeme scénku z historie skautingu (světového, českého nebo místního).',
				'Zvuk. Doplním scénku vhodnými zvukovými efekty (např. pokličkami bouřku, klapáním koně) nebo hrou na hudební nástroj.',
				'Maska. Vytvořím si masku pohádkové nebo nadpřirozené postavy.',
				'Fantazie. Vymyslím příběh jednoho obyčejného předmětu, který používám denně, a povyprávím ho šestce.',
				'Pantomima. Vytvořím si pro kamarády a kamarádky v šestce hádanku v pantomimě – deset různých povolání nebo pohádkových postav.',
				'Inspirace. Vyberu si oblíbenou knížku a spolu s šestkou ztvárním její část jako živé obrazy.',
				'Scéna. Vyberu si oblíbený příběh a pro jeho hlavní postavu vytvořím model prostředí, ve kterém se postava pohybuje (model znamená 3D, lze zvolit z mnoha technik, např. koláž, hlína, modelína, papír, špejle, přírodniny…).',
				'Na jevišti. Zahraju ve dvou divadelních představeních.',
				'Maňásci. Vyrobím si dva maňásky a zahraju s nimi představení.',
				'Návštěva divadla. Navštívím dvě představení v divadle. Svůj zážitek popíšu šestce.',
			] ],
			[ 'Fotograf', [
				'Fotografie místa. Najdu v obci změnu, která se mi líbí, vyfotím ji a fotku ukážu šestce (např. opravení kašny, vytvoření nového parku, hřiště). Proč si myslím, že ke změně došlo?',
				'Fotografie. Vyfotím svoji šestku, potom fotku vytisknu a vyvěsím v klubovně.',
				'Výprava. Na výpravě budu fotit a vyberu fotky, které použiji do kroniky nebo na web.',
				'Filtr. Vyfotím tři fotky a na každou použiju libovolný filtr (například černobílý, vinětace apod.).',
				'Rychlost. Zkusím si vyfotit stejnou fotku akční hry šestky s rychlým časem (pod 1/100 vteřiny) a s pomalým časem (nad 1/10 vteřiny) a popíšu rozdíl mezi výsledky.',
				'Zaostření. Vyfotím dvě stejné fotky, pokaždé se zaostřením na jinou vzdálenost. Porovnám ostrost jednotlivých předmětů.',
				'Režimy focení. Na svém nebo půjčeném fotoaparátu či smartphonu ukážu vedoucím, jak se dá změnit režim focení. Vysvětlím, kdy a proč měním režimy focení (např. v muzeu, za šera).',
				'Panorama. Vyfotím panorama z jednotlivých snímků a složím ho.',
				'Kompozice. Vyberu si tři objekty vedle sebe a vyfotím je ve třech různých uspořádáních. Vyberu to, které se mi líbí více, a vysvětlím proč.',
				'Společnost. Při výpravě nebo na táboře určím moment, kdy není vhodné fotit, a moment, který je sice vhodné vyfotit, ale fotku nezveřejňovat. Vysvětlím, proč si to myslím.',
			] ],
			[ 'Hudebník', [
				'Poslech. Se zavřenýma očima si se šestkou poslechneme písničku. Poté si vzájemně řekneme, co se nám na ní líbilo a nelíbilo.',
				'Hra na hudební nástroj. Zahraju na libovolný hudební nástroj tři jednoduché skladby.',
				'Můj nástroj. Představím šestce svůj hudební nástroj. Vysvětlím, jak o něj pečuju a jak se ladí.',
				'Pravidelně cvičím. Dva týdny hraju na svůj nástroj každý den.',
				'Noty. Zahraju skladbu z not ve správném tempu a se správnou dynamikou.',
				'Vystoupení. Zahraju skladbu přiměřené obtížnosti před ostatními (např. na koncertě pro veřejnost, u táborového ohně, na besídce apod.).',
				'Společné hraní. Vystoupím před ostatními s někým dalším, např. v triu, souboru nebo orchestru.',
				'Koncert. Navštívím dva různé koncerty, z toho jeden, kde se hraje na můj nástroj. Šestku seznámím s jejich průběhem a popíšu, jak se mi koncerty líbily a proč.',
				'Co poslouchám. Představím šestce interpreta/interpretku nebo skupinu hrající na můj nástroj včetně dvou ukázek.',
				'Hudební skladatelé. Představím šestce jednoho českého a jednoho zahraničního hudebního skladatele či skladatelku včetně ukázky z jeho tvorby.',
			] ],
			[ 'Rukodělkář', [
				'Papírová skládačka. Vyberu si a složím papírovou skládačku podle návodu (například loďku, vlaštovku, čepici, pohárek…) a vyzdobím ji.',
				'Přírodní materiál. Vytvořím tři různé výrobky z přírodního materiálu, např. vyřežu píšťalku, upletu pomlázku, vyrobím adventní věnec.',
				'Provázky a korálky. Zhotovím tři výrobky z provázků a korálků, např. udrhám náramek, navleču korále, vytvořím zvířátko z korálků, a některý z nich někomu daruji.',
				'Společná tvorba. Vyrobím s kamarádem, kamarádkou či někým z rodiny předmět, který společně využijeme (např. draka, poličku, kalendář).',
				'Pletení a háčkování. Upletu či uháčkuju si část oblečení. Pletu hladce i obrace, při háčkování využiju krátký a dlouhý sloupek.',
				'Šití. Ušiju si dvě užitečné věci, např. obal na ešus, deník nebo pouzdro na pastelky.',
				'Tuhnoucí hmota. Vyrobím z hmoty čtyři předměty, např. přívěsek z fima, užitečný předmět z keramiky, odlitek stopy ze sádry, dekoraci ze samotvrdnoucí hmoty.',
				'Ozdobení textilu. Ozdobím tři různé textilní věci, např. nabatikuji tričko, vyšiju obrázek na sáček či tašku, vyšiju obrázek na obal stezky.',
				'Nákup materiálu. Zjistím, co potřebuji na tvorbu svého výrobku, a dojdu si to s rodiči či vedoucí/m koupit.',
				'Nová rukodělka. Připravím si s kamarádem či kamarádkou v šestce na schůzku krátkou rukodělku pro ostatní.',
			] ],
			[ 'Výtvarník', [
				'Obrázek z přírodnin. Vytvořím v lese či na louce obrázek z přírodnin, které najdu okolo.',
				'Inspiruji se přírodou. Najdu si přírodninu, která mi přijde zajímavá, a nakreslím ji zvětšenou (tužkou, uhlem, křídou nebo pastelkou).',
				'Barevná paleta. Zkusím ke každé barvě palety (bílá, žlutá, oranžová, červená, růžová, fialová, zelená, modrá, hnědá, černá) najít přírodninu dané barvy.',
				'Nálady a emoce. Namaluju dva obrazy vyjadřující dvě odlišné nálady či emoce na velký papír A2 (temperou, akrylovými barvami nebo akvarelem).',
				'Fantazie. Vymyslím rostlinu nebo živočicha podle své fantazie, charakterizuji ho (kde žije, čím se živí…). Poskládám ho z papíru, nakašíruji nebo vytvořím z hlíny.',
				'Experiment. Vyrobím štětec z neobvyklých materiálů (přírodních i člověkem vytvořených) a zkouším jeho stopu při malování. Vytvořím s ním pět různých obrázků.',
				'Krása. Zvolím si místo ze svého okolí, které považuji za krásné. Pokusím se jeho krásu zachytit (např. pomocí několika fotografií, malovaných obrázků). Zaměřím se na detaily i celek. Představím místo ostatním.',
				'Inspirace. Navštívím galerii, představím šestce jedno umělecké dílo nebo umělce či umělkyni, kteří mě zaujali.',
				'Moje tvorba. Navštěvuji půl roku výtvarný či keramický kroužek.',
				'Naše šestka. Libovolnou výtvarnou technikou ztvárním naši šestku.',
			] ],
			[ 'Zpěvák', [
				'Poslech. Se zavřenýma očima si se šestkou poslechneme písničku. Poté si vzájemně řekneme, co se nám na ní líbilo a nelíbilo.',
				'Zpěv. Zazpívám deset různých písní.',
				'Zpěv s doprovodem. Zazpívám píseň s hudebním doprovodem.',
				'Druhý hlas nebo kánon. Zazpívám k písni druhý hlas nebo kánon.',
				'Nové písně. Naučím se dvě nové písně.',
				'Vystoupení. Sólově zazpívám píseň přiměřené obtížnosti před ostatními (např. na koncertě pro veřejnost, u táborového ohně, na besídce apod.).',
				'Hymny a večerka. Zazpívám českou hymnu, skautskou hymnu a večerku.',
				'Zpěvník. Vedu si zpěvník a průběžně ho doplňuju.',
				'Co poslouchám. Představím šestce oblíbeného zpěváka nebo oblíbenou zpěvačku. Pustím dvě ukázky.',
				'Hudební představení. Navštívím dvě různá hudební představení (např. koncert, operní, operetní nebo muzikálové představení). Šestce povím, o čem představení byla a jak se mi líbila.',
			] ],
			[ 'Hvězdář', [
				'Souhvězdí. S rojem/smečkou či rodinou pozoruji hvězdy. Zjistím název a tvar jednoho souhvězdí a vymyslím si jedno vlastní.',
				'Příběhy. Vyberu si jeden příběh, který se týká některého souhvězdí. Příběh libovolně ztvárním (obrázek, scénka…) nebo převyprávím šestce.',
				'Otáčivá mapa hvězdné oblohy. Vyzkouším otáčivou mapku a najdu podle ní na obloze tři souhvězdí, která ještě neznám.',
				'Venuše. Poslechnu si nebo přečtu příběh, proč má Venuše i jiné názvy. Ukážu ji na obloze a doplním to krátkým vyprávěním o planetě a jejím obrázkem.',
				'Měsíc. Večer ukážu šestce na obloze Měsíc. Pomocí koleček (třeba ze sušenek) vymodeluji jednotlivé fáze měsíce a vysvětlím, co znamenají. Společně určíme, v jaké fázi Měsíc právě teď je.',
				'Sluneční soustava. Vytvořím model sluneční soustavy.',
				'Návštěva. Navštívím hvězdárnu nebo planetárium. Připravím si dvě otázky, na které chci zjistit odpovědi. Během návštěvy se na ně zeptám.',
				'Astronomická scénka. Připravím se šestkou scénku, která vysvětluje některý z astronomických jevů. Např. zatmění Slunce, Měsíce, rovnodennost nebo slunovrat.',
				'Astronomický úkaz. Upozorním šestku na blížící se astronomický úkaz (zatmění Slunce, Měsíce, padání hvězd…). Zjistím, jak daný jev probíhá, a šestce to vysvětlím.',
				'Knížka. Přečtu si dětskou knížku, která se věnuje hvězdám nebo vesmíru (např. ABC Hvězdáře, Jak kometa šla do světa).',
			] ],
			[ 'Chovatel', [
				'Stopy zvířat. Najdu několik zvířecích stop (okousané listy nebo šišky, dutiny ve stromě, myší stezky, otisky v blátě…). Své nálezy zapíšu, nakreslím nebo vyfotím. Zjistím, kterým zvířatům by mohly patřit.',
				'Péče o zvíře. Povím své šestce, co všechno obnáší péče o mé zvíře, čím je zajímavé a převyprávím nějaký s ním spojený zážitek.',
				'Zvíře doma. Po domluvě s rodiči se dva týdny úplně sám/sama starám o naše domácí zvíře (krmení, bydlení, pohodlí…).',
				'Chovatelský kroužek. Půl roku chodím na chovatelský kroužek. Vyberu si tři zajímavé informace o zvířeti nebo zážitky, které povím šestce.',
				'Knížka. Přečtu si knížku o svém zvířeti a napíšu si pět informací, které jsem se dozvěděl/a.',
				'Pozorování. Po určitou dobu budu pečlivě sledovat své zvíře. Ze sledování si vytvořím záznam (kresby, fotografie…).',
				'Vztah zvířat k člověku. Popíšu chování svého zvířete, a pokud to lze, ukážu, co jsem ho naučil/a (naživo, na fotkách…).',
				'Příchod zvířete. Zjistím, odkud se zvíře dostalo k nám domů nebo na kroužek. S rodiči či vedoucími na kroužku si popovídám, jak moje zvířata žijí nebo žila v přírodě, proč a jak se postupně ochočila.',
				'Návštěva ZOO. S příbuznými nebo s oddílem navštívím ZOO. Vyberu si jedno zvíře a zkusím o něm zjistit, co všechno potřebuje ke svému životu, proč je chov v zajetí (ne)snadný a co ho ve volné přírodě ohrožuje.',
				'Užitková zvířata. V rámci schůzky uspořádám ukázku nebo ochutnávku alespoň tří produktů od domácích zvířat a představím, jak se produkty získávají.',
			] ],
			[ 'Přírodověděc', [
				'Co tu roste a žije. Vyberu si jedno prostředí (potok, rybník, louka, les…) a při jeho návštěvě zapíšu, nakreslím nebo vyfotím rostliny a živočichy, kteří zde žijí. Zjistím dvě zajímavé informace o organismu, který mě nejvíce zaujal.',
				'Deset druhů. Najdu deset druhů rostlin, živočichů, hub nebo stromů, které znám a ukážu je šestce. Řeknu ostatním, co o nich vím (např. k čemu se dá část rostliny použít, zda je jedovatá, čím je zajímavá).',
				'Experiment. Vyberu si přírodovědný pokus (např. klíčení semínek, mravenci měnící cestičku, otevírání šišek), který šestce ukážu a vysvětlím.',
				'Chci vědět víc! Zúčastním se výletu, ve kterém se budeme seznamovat s přírodou (může to být exkurze, procházka s přírodovědným kroužkem…). Zjistím odpověď na otázku, která mě během výletu k tématu napadne.',
				'Hledání informací. Pomocí atlasu, určovacího klíče, mobilní aplikace nebo internetu určím vybranou květinu, strom, keř, houbu, zvíře, souhvězdí, tvar mraku, horninu…, které vyberu spolu s vedoucím. Ke každému z určovaného naleznu důležitou informaci, díky které si novinku lépe zapamatuju.',
				'Sbírání. Mám sbírku pojmenovaných vybraných přírodnin (šišek, usušených rostlin, ptačích per, nerostů…), kterou postupně rozšiřuju.',
				'Přírodní zajímavost. Najdu v přírodě úkaz, který mě zaujme (zajímavě zvětralé horniny, hmyzí domeček, netradičně barevné květy, tajemné stopy…). Zkusím ho vysvětlit a ukážu ho šestce.',
				'Stopy člověka v přírodě. V průběhu výpravy ukážu šestce místa, kde člověk přírodě škodí a kde naopak pomáhá. Během výpravy navrhnu, čím malým můžeme pomoci my, a uskutečníme to.',
				'Vznik a zánik. Vytvořím několik navazujících obrázků přírody, kde něco vzniká a zaniká (např. fotky semenáčků a zetlelého stromu, vývoj žáby, hmyzu). Obrázky ukážu šestce a společně popíšeme pocity, které v nás vyvolávají.',
				'Vztahy v přírodě. Blíže se seznámím s tím, jak v přírodě fungují vztahy mezi konkrétními rostlinami a živočichy, např. mšice a mravenci, červík v ovoci, strakapoud na stromě… Vymyslím příběh, do kterého svoje pozorování promítnu.',
			] ],
			[ 'Zahradník', [
				'Barvy v přírodě. Najdu přírodniny v co nejvíce barvách. Ke které barvě se mi nepodařilo najít žádnou přírodninu a od které barvy jich bylo nejvíce?',
				'Život rostliny. Vyberu si sazeničku rostliny. Připravím si vhodný prostor na zasazení (záhon, dostatečně velký květináč), vhodnou zeminu a rostlinu zasadím. O rostlinu se správně starám. Vytvořím plakát, kde zaznamenám (zapíšu, zakreslím, vyfotím) důležité momenty v jejím životě (kvetení, plod…).',
				'Ochrana. Zjistím, co mé rostlině škodí a co jí naopak může pomoci. Informace zapracuji do krátkého příběhu, který odvyprávím šestce.',
				'Světlo a teplota. Vyberu si dvě stejné rostliny. Jednu umístím na světlo a teplo, druhou do tmy a chladu. Týden budu obě pozorovat a každý den si zaznamenám, jak vypadají. Po týdnu ukážu šestce výsledky svého pokusu. Vysvětlím, jaký vliv světlo a teplota na rostliny mají.',
				'Kypření půdy. Udělám díru do země (nebo se podívám do vykopané, např. při přípravě ohniště). Spočítám žížaly a vysvětlím, co tam dělají.',
				'Pomoc. Starám se o rostlinu, která vypadá, že by bez mé pomoci zahynula. Zaznamenám si (např. pomocí fotografie) chvíli, kdy jsem se o ni začal/a starat, druhý den, po týdnu, po deseti dnech… a sérii fotografií ukážu šestce.',
				'Neznámá. Najdu na zahradě nebo v zahradnictví rostlinu, o které toho moc nevím. Zjistím si, jak se jmenuje, co potřebuje ke svému životu a čím je zajímavá.',
				'Pozoruji vznik. Vyberu si rostlinu, kterou nechám vyklíčit (např. řeřichu) na místě, které lze dobře pozorovat. Každý den si zaznamenám (zakreslím, vyfotím…), jak klíčení probíhá.',
				'Pozoruji zánik. Vyberu si ovoce, které zahrabu do země. Vždy po týdnu zkontroluji a zaznamenám, co se se zahrabaným ovocem děje.',
				'Jídlo. Připravím pokrm z mnou vypěstovaných nebo sesbíraných plodin, např. koláč, marmeládu, zeleninový salát, a dám ochutnat šestce.',
			] ],
			[ 'Cestovatel', [
				'Výlet. S oddílem nebo rodinou se vydáme na dobrodružnou expedici někam, kde jsem ještě nebyl/a. Z výletu si můžu přinést pěknou přírodninu na památku. Návrhy, kudy a kam se vydat: podél řeky či potoka, podél okraje lesa, na rozhlednu, vyhlídku nebo vysoký kopec, podél hranice republiky, okresu nebo kraje.',
				'Mapa výletu. Najdu si mapu místa, kam půjdeme na výlet. Během výletu na ní třikrát najdu, kde právě jsme.',
				'Orientace v terénu. Při výletě popíšu šestce, podle čeho se dá orientovat, aby člověk našel správnou cestu (na turistické značce i bez ní).',
				'Kolik kilometrů? Podle trasy výletu vypočtu, kolik km půjdeme. V rámci trasy alespoň 3× odpovím správně na otázku, kolik kilometrů ještě zbývá do cíle (s přiměřenou odchylkou).',
				'GPS. Vyzkouším používat GPS při orientaci v terénu. Pomocí GPS dojdu na určené místo (např. najdu kešku) nebo projdu vyznačenou trasu.',
				'Cestovatelský deník. Půl roku si vedu cestovatelský deník. Do něj si zapisuji, jaká místa jsem navštívil/a, kolik kilometrů jsem ušel/ušla a co jsem se nového dozvěděl/a.',
				'Krabička zajímavostí. Půl roku sbírám různé zajímavosti, které na cestách potkám, a představím je šestce.',
				'Buzola. Podle buzoly zorientuji mapu. Umím určit, kde je sever, jih, východ a západ.',
				'Plánek k pokladu. V blízkosti tábořiště nebo klubovny schovám „poklad". Nakreslím plánek, podle kterého ho bude hledat moje šestka. Plánek je srozumitelný.',
				'Vybavení. Sepíšu seznam vybavení na jednodenní výlet a víkendovou výpravu. Zabalím si věci podle svého seznamu. Po výletě i výpravě seznam podle zkušeností upravím. Pak se o seznam podělím s šestkou.',
			] ],
			[ 'Kuchař', [
				'Chléb nebo jednohubky. Se šestkou nakoupíme suroviny a připravíme jednohubky nebo obložený chléb. Do rámečku nakreslím nebo jinak znázorním suroviny, které jsme použili.',
				'Vaření. Na schůzce, výpravě nebo táboře uvařím společně s ostatními jednoduché jídlo bez použití polotovarů pro celou šestku.',
				'Krájení. Nakrájím zeleninu na salát, cibuli do jídla. Rozkrojím housku, rohlík nebo bagetu. Nakrájím bochník chleba na rovné krajíce.',
				'Mazání pečiva. Namažu nakrájené krajíce chleba máslem/marmeládou/pomazánkou. Krajíce jsou namazané rovnoměrně až do okrajů, nic není upatlané.',
				'Prostření stolu. V domácích podmínkách připravím stůl na oběd nebo večeři pro rodinu, vyfotím a po jídle stůl zase uklidím.',
				'Skladování potravin. Doma nebo na táboře pomohu uklidit nákup. Vše umístím tak, jak to daná potravina vyžaduje: do chladu, do temna, do sucha, do určené nádoby. U vybraných potravin najdu datum trvanlivosti a ukážu, kterou potravinu je potřeba do kdy spotřebovat.',
				'Mytí nádobí. Doma nebo na výpravě třikrát umyju společné nádobí po hlavním jídle.',
				'Brambory. Oškrábu půl kila brambor.',
				'Vaření na ohni. Uvařím na ohni čaj a jedno jednoduché jídlo.',
				'Zelenina. Připravím dvě zeleninová jídla – jedno ze syrové zeleniny a jedno ze zeleniny po tepelné úpravě – pro rodinu nebo šestku. Použiji vhodnou zeleninu, pořádně ji omyji a odkrojím části, které se nejí.',
			] ],
			[ 'Průvodce', [
				'Zajímavost v obci. Představím šestce zajímavost z naší obce či okolí a řeknu, proč mě zaujala.',
				'Model památky. Nakreslím nebo vyrobím model pro mne nejzajímavější památky v okolí mého bydliště.',
				'Provázím. Provedu po okolí bydliště někoho z příbuzných nebo z oddílu. Ukážu jim a poutavě povyprávím o nejzajímavějších místech, která u nás jsou.',
				'Dozvídám se nové informace. Vyberu si památku (kulturní či přírodní) v okolí bydliště a zjistím o ní co nejvíce pro mě nových informací. Na místo vyrazím s příbuznými nebo oddílem a tři zajímavé věci jim řeknu.',
				'Památky. Navštívím alespoň pět památek v okolí bydliště. O každé z nich si napíšu zajímavou informaci do zápisníku.',
				'Naše kořeny v obci. Zeptám se v rodině, jak dlouho v naší obci bydlíme, kdy jsme se přistěhovali, a poslechnu si vyprávění, jak obec tehdy vypadala.',
				'Tradiční akce. Zúčastním se tradiční akce v naší obci. Vezmu na ni kamaráda či kamarádku nebo o ní vyprávím šestce.',
				'Naše obec. Navštívím místní radnici nebo městský úřad. Zjistím, kdo je starostou či starostkou. Uvedu příklad, co vedení obce rozhodlo v poslední době.',
				'Plánek. Nakreslím plánek okolí svého bydliště a zaznačím důležité budovy (např. zastávku, lékaře, potraviny, školu…).',
				'Změny v okolí. Najdu místo (např. podle fotografie), které se za posledních pár let proměnilo. Zjistím, proč se tak stalo. Místo ukážu šestce nebo někomu jinému.',
			] ],
			[ 'Táborník', [
				'Oheň. Pomůžu připravit oheň, zapálím ho a udržuji. Po ohni pomůžu uklidit.',
				'Stavba stanu. Postavíme společně stan.',
				'Zručnost. Zhotovím si pro sebe jednoduchou pomůcku na tábor (např. držák na ešus, poličku do stanu) nebo pomohu se stavbou či opravou táborové stavby.',
				'Pomoc v kuchyni. Alespoň dvakrát v průběhu tábora budu oporou táborové služby v kuchyni. Samostatně vedu přidělenou činnost (např. přípravu snídaně, doplnění dřeva, krájení zeleniny).',
				'Jak funguje táborový den. Vysvětlím, jak funguje táborový den, nástupy, jak probíhá hygiena, služby v kuchyni a další důležité věci.',
				'Noční hlídky. Vykonám noční hlídku v době, kdy je tma, dle táborových pravidel. Správně předám hlídku dalšímu.',
				'Pořádek. Během tábora si udržuji pořádek ve stanu, snažím se být ostatním vzorem. Starám se dobře o své věci. Půjčené táborové vybavení v pořádku vracím.',
				'Táborové vzpomínky. Vedu si táborový deník, do kterého zapisuji, co jsme který den zažili, nebo se podílím na tvorbě oddílové kroniky.',
				'Přespání pod širákem. Zjistím, jak lze v táborových podmínkách odhadnout počasí k přespání pod širákem. Po domluvě s vedením přespím pod širákem.',
				'Táborový zážitek. Libovolně ztvárním svůj největší táborový zážitek tak, abych si ho mohl/a uchovat až do dospělosti. Nebo pojmenuji, co jsem se během tábora naučil/a nového, a tuto novou dovednost si zakreslím či zapíšu do zápisníku.',
			] ],
			[ 'Atlet', [
				'Běh přes překážky. Zaběhnu opičí dráhu nebo překážkový běh.',
				'Atletická abeceda. Zúčastním se atletické rozcvičky, která na závěr obsahuje atletickou abecedu (např. liftink, skipink, zakopávání, předkopávání, odpichy). Dva prvky si zapamatuji a představím je šestce.',
				'Zlepšení v rychlostním běhu. Změřím si běh na vzdálenost asi 60 metrů. Startuji z nízkého startu na povely „připravit se – pozor – teď". Po dobu tří týdnů budu pravidelně trénovat. V běhu na vzdálenost 60 metrů si zlepším čas.',
				'Zlepšení ve skoku dalekém (z místa nebo s rozběhem). Změřím si, jak daleko doskočím od místa odrazu, a týden budu pravidelně trénovat, abych se zlepšil/a.',
				'Zlepšení v míření. Domluvím se s vedoucí/m a vyberu si cíl, na který budu mířit z určité vzdálenosti. Spočítám si, jakou mám úspěšnost z deseti pokusů. Po týdenním tréninku házení na cíl se v úspěšnosti zlepším.',
				'Hod do dálky. Změřím si, jak daleko dohodím míčkem velikosti tenisáku či jeho náhradou (např. improvizovanou koulí). Pravidelně 14 dní trénuji a průběžně si zapisuji výsledky. Představím je šestce.',
				'Běhací hra. Zahraji si běhací hru, při které uběhnu alespoň půl kilometru a maximálně jeden kilometr.',
				'Obratnost. S kamarády a kamarádkami pětkrát navštívím dětské nebo přírodní hřiště.',
				'Štafetový běh. Zúčastním se štafetového běhu s využitím štafetového kolíku nebo jeho obdoby. Tým má alespoň čtyři členy a délka tratě pro každého člena je minimálně 100 metrů.',
				'Skákání přes švihadlo. Po domluvě s vedoucí/m si vyberu, jestli se naučím nový způsob přeskakování přes švihadlo (např. dvojskok, vajíčko pozadu), nebo vydržím skákat v kuse minutu snožmo bez chyby.',
			] ],
			[ 'Cyklista', [
				'Dráha. Ujedu na kole, koloběžce nebo kolečkových bruslích vytyčenou dráhu.',
				'Jízda na kole. Jezdím na kole bezpečně a ve vhodném oblečení. Během všech cest mám na sobě správně nasazenou přilbu. Za šera a za tmy používám světla. Šestce předvedu, jak se nasazuje přilba, používá přehazovačka, a ukážu reflexní prvky na svém kole.',
				'Dopravní značky. Při jízdě na kole dávám pozor na značky. Podle předlohy (knížka, internet…) nakreslím dvě značky, které vidím nejčastěji.',
				'Údržba kola. Své kolo udržuji čisté. Šestce představím své kolo a popíšu jeho vybavení. Ukážu jim, jak poznají, kdy je třeba namazat řetěz, dofouknout kolo, a předvedu, jak to udělat. Vysvětlím jim, jak si nastavit výšku sedla tak, aby se na kole dobře jelo.',
				'Spadlý řetěz. Nasadím spadlý řetěz.',
				'Cyklistická zdatnost. S oddílem nebo rodinou podniknu tři cyklovýpravy. Každá bude mít trasu delší než je dvojnásobek mého věku.',
				'Cyklovýprava. Popíšu šestce, jakou výstroj potřebuji na jednodenní cyklovýpravu, a připevním ji na kolo, nebo zabalím do vhodného batohu.',
				'Dopravní nehoda. Vysvětlím šestce, jak se zachovat při dopravní nehodě. Předvedu, jak přivolat pomoc.',
				'Bezpečná jízda. Předvedu šestce, jaká nebezpečí nás můžou na trase potkat a na co si máme dát při jízdě na kole pozor. Můžu sehrát scénku, ukázat obrázky, ukázat na trase…',
				'Závod. Zúčastním se jakéhokoliv cyklistického závodu.',
			] ],
			[ 'Plavec', [
				'Plavání. Uplavu krátkou vzdálenost.',
				'Více plaveckých způsobů. Uplavu druhým plaveckým způsobem 50 metrů.',
				'Pod vodou. Uplavu pod vodou nejméně 5 metrů.',
				'Předmět z hloubky. Vylovím předmět z místa, kde nestačím.',
				'Plavání v oblečení. Uplavu v oblečení 15 metrů (dlouhý rukáv, dlouhé nohavice a lehké boty).',
				'Šlapání vody. Minutu vydržím šlapat vodu na místě.',
				'Dopomoc. Vyzkouším si dopomoc unavenému plavci (dopomoc jedním plavcem, letka, most). Zkouším to s někým, kdo je stejně velký jako já.',
				'Skok a vstup do vody. Skočím do bezpečné vody po nohou.',
				'Šipka. Skočím do bezpečné vody šipku.',
				'Bezpečnost u vody. Napíšu (nakreslím) několik zásad, jak se mám chovat bezpečně u vody a jak se zachovat, když vidím, že se někdo topí.',
			] ],
			[ 'Polárník', [
				'Proměna místa. Najdu pěkné místo v přírodě. Nakreslím, jak bude vypadat ve čtyřech ročních obdobích.',
				'Hra se sněhem. Postavím s kamarády a kamarádkami stavbu ze sněhu (např. iglú, bobovou dráhu, sochu).',
				'Bezpečnost. Na oddílovou akci v zimním počasí se správně obléknu. Popíšu, co dělat, abych neprochladl/a, a co si počít, když je mně nebo někomu jinému zima. Předvedu, jak přivolat pomoc v nouzi.',
				'Ohleduplnost. Jsem si vědom/a, že při hrátkách na sněhu musím být ohleduplná/ý k druhým a chovám se tak (neházím jim sníh za krk, neberu čepice…). Vysvětlím šestce, proč se máme chovat ohleduplně k ostatním.',
				'Zimní sportování. Půjdu bobovat či sáňkovat.',
				'Sjezdovky nebo snowboard. Samostatně si nazuji i vyzuji boty. Vím, jak je mít utažené, pokud nemám sílu na jejich zapnutí, dokážu požádat o pomoc. Nasadím i sundám lyže (snowboard) a správně nasadím helmu (příp. páteřák). Samostatně a bezpečně nastoupím na vlek či lanovku, vyjedu nahoru a vystoupím. Bezpečně sjedu modrou sjezdovku. Pokud spadnu, postavím se.',
				'Běžky. Samostatně si zapnu i sundám běžky. Během jednoho dne ujedu alespoň 12 km. Vyjdu kopec stromečkem nebo bokem, při klesání přibrzdím pluhem. Pokud spadnu, postavím se.',
				'Bruslení. Samostatně si brusle obuji a zuji. Předvedu jízdu na bruslích popředu i pozadu, zastavím bez nárazu do mantinelu.',
				'Zimní atletika. Udělám kotrmelec, uběhnu v hlubokém sněhu asi 50 metrů, hodím úspěšně sněhovou koulí na cíl, skočím do sněhu.',
				'Závod. Zúčastním se libovolného zimního závodu nebo soutěže (sjezdovky, běžky, hokej…).',
			] ],
			[ 'Sportovec', [
				'Chytání míče. Procvičím si házení a chytání míče, například při hře.',
				'Rozcvičení před sportem. Před sportovním výkonem se rozcvičím. Do rozcvičení zařadím rozehřátí (např. krátký běh), protažení a uvolnění různých částí těla.',
				'Běhání. Zahraju si se svou šestkou pět různých her, během kterých bude důležitým prvkem běh. Jednu běhací hru zorganizuji.',
				'Vodní hrátky. Půjdu se třikrát koupat a pokaždé si vyzkouším něco nového (např. stojku ve vodě, skok z bloku, vodní hru).',
				'Jízda s překážkami. Projedu překážkovou dráhu na kole, koloběžce, skejtu nebo kolečkových bruslích. Budou v ní zatáčky, brždění, objíždění překážky.',
				'Míčová hra. Vyberu si míčovou hru, kterou hraju nejčastěji, a ukážu šestce ty věci, které mi z ní jdou nejlépe (např. driblink v basketbalu, vedení míče ve fotbalu, podání v přehazované).',
				'Sport s pomůckami. Vyzkouším si sport se speciálním vybavením, např. ringo, badminton, florbal, stolní tenis, tenis, ragby, softball, petanque, lukostřelba. Spolu s vedoucí/m si stanovím malý cíl (třeba přesnost míření, délka hodu nebo podání), kterého dosáhnu.',
				'Turistika. Během tří výletů ujdu opakovaně a bez problémů více kilometrů, než kolik je mi let.',
				'Pravidelně cvičím. Pravidelně každý týden cvičím, školní tělocvik nepočítám. Šestce předvedu, co jsem se naučil/a nebo v čem jsem se zlepšil/a.',
				'Tým. Se svou šestkou si zahraju týmovou sportovní hru. Potom spolu s vedoucí/m zhodnotím, jak se mi daří být týmovou hráčkou / týmovým hráčem.',
			] ],
			[ 'Čtenář', [
				'Chování postav. V příběhu najdu příklad správného a nesprávného chování postav a napíšu je. Řeknu, jak toto chování ovlivnilo pokračování příběhu.',
				'Seznam knížek. Zaznamenávám si, které knížky přečtu (název, autora nebo autorku, kdy jsem knihu četl/a, čím mě zaujala a jednu zajímavou myšlenku). Mám takto sepsáno alespoň deset knih.',
				'Vyznám se v knihovně. Alespoň třikrát jsem navštívil/a knihovnu a půjčil/a jsem si z ní pokaždé knížku. Knížky vracím včas, pokud výjimečně ne, zaplatil/a jsem zpozdné. U libovolné knížky si prodloužím výpůjční dobu.',
				'Doporučení. Doporučím kamarádovi nebo kamarádce knížku, kterou začne číst. Společně se pobavíme o tom, co se v knížce dělo.',
				'Knižní inspirace. Přečtu si knížku, pak se podívám na její filmové nebo divadelní zpracování a vzájemně je porovnám.',
				'Poezie. Vyberu si oblíbenou básničku, přečtu ji šestce, přednesu na besídce, nebo ji výtvarně ztvárním.',
				'Jak vzniká knížka. Vyberu si knížku a zjistím, kdo se na jejím vzniku podílel (ilustrátor/ka, nakladatel, překladatel/ka…). Najdu si (např. na internetu), jak knihy vznikají.',
				'Vyprávím děj. Vyberu si libovolnou knížku a její děj převyprávím šestce.',
				'Čtu Světýlko. Vyberu z posledních dvou Světýlek zajímavý článek, který doporučím své šestce. Vysvětlím jim, proč by si ho měli přečíst.',
				'Oblíbený spisovatel / oblíbená spisovatelka. Vyberu si oblíbeného autora/autorku knížek a zjistím informace z jeho/jejího života. Vytvořím plakát, který si umístím v pokojíčku nebo v klubovně.',
			] ],
			[ 'Historik', [
				'Historický příběh. Vyberu si historický příběh nebo pověst z naší obce a převyprávím jej šestce nebo namaluji. Přečtu si nebo poslechnu jeden příběh, který jsem dosud neznal/a.',
				'Notýsek plný památek. Navštívím alespoň pět památek (např. hrad, zříceninu, zámek, klášter, synagogu, skanzen, historické centrum libovolného města…). O každé si napíšu nějaké informace a nalepím si do notýsku její obrázek.',
				'Provázení. Během procházky po našem městě či vesnici představím šestce nebo svojí rodině tři zajímavá místa, která jsou v okolí.',
				'Státní svátky. Zjistím, jaké slavíme státní svátky a proč. Vyberu si jeden a připravím pro šestku jeho připomínku nebo oslavu.',
				'Knížky o minulosti. Přečtu si dvě knížky věnující se historii (mohou být komiksové) a vyberu si alespoň pět pro mě nových informací, které řeknu ostatním.',
				'Po stopách skautingu. Poslechnu si, podívám se nebo si přečtu vyprávění o vzniku skautingu (u nás nebo ve světě), které pak převyprávím někomu dalšímu.',
				'Historická osobnost. Vyberu si nějakou historickou osobnost, která mě zaujala. Něco si o jejím životě zjistím a vysvětlím ostatním, proč bychom si z ní měli (nebo neměli) vzít příklad.',
				'Výlet do minulosti. Vyberu si, v jaké době bych chtěl/a žít, kdybych nežil/a právě teď. Napíšu si alespoň pět věcí, v čem se liší od současnosti.',
				'Stará řemesla. Vyzkouším si dvě řemesla, která dříve byla běžná a dnes už zanikla nebo zanikají. Zkusím objevit, zda se v okolí tomuto řemeslu někdo věnuje, a navštívím ho.',
				'Oddílový den. Napíšu záznam o jednom dni, který prožil náš oddíl společně. Napíšu ho tak, aby si ho mohli přečíst světlušky a vlčata za pět let a pochopili z toho, jak tenkrát oddílový den vypadal.',
			] ],
			[ 'Jazykář', [
				'Pantomima. Předvedu, jak bych beze slov vyjádřil/a tři z těchto pocitů: mám někoho rád/a; jsem zklamaný/á; jsem s někým rád/a; jsem součástí skupiny; s něčím nesouhlasím; někoho podporuji; mám strach.',
				'Návštěva cizí země. Při výletu do zahraničí použiji cizí jazyk (např. koupím vstupenku, lístek na vlak, zeptám se na cestu…).',
				'Učím se. Cizímu jazyku se věnuji i mimo školu (např. chodím půl roku na kroužek, opakovaně si povídáme v cizím jazyce se sourozenci či rodiči, podívám se na několik pohádek v cizím jazyce s titulky…).',
				'Knížka. Přečtu si jakoukoli knížku či komiks v cizím jazyce.',
				'Mluvím. Během jednoho dne na výpravě nebo táboře se snažím co nejvíce komunikovat v cizím jazyce.',
				'Jazykové pexeso. Vytvořím jednoduché pexeso, kde se budou přiřazovat slova v cizím jazyce k obrázkům. Pexeso si v šestce zahrajeme.',
				'Překlad písničky. Najdu si oblíbenou písničku v cizím jazyce a její překlad. Pustím ji šestce, nechám je hádat, o čem je, a pak jim převyprávím děj.',
				'Dívám se. Při výpravě hledám slova napsaná cizím jazykem. Zkusím je přečíst nahlas. U těch, kterým nerozumím, poprosím o překlad někoho z vedoucích nebo si ho zjistím jinak.',
				'V cizím jazyce. Na týden si nastavím mobil nebo jiné zařízení, které používám, do cizího jazyka.',
				'Jiná země. Najdu na mapě zemi, kam se chci jet podívat. S pomocí zjistím, jakým jazykem se v dané zemi mluví a naučím se 5 slovíček v daném jazyce. Tato slovíčka a 3 zajímavé informace o dané zemi představím šestce.',
			] ],
			[ 'Reportér', [
				'Hledání informací. S pomocí vedoucí/ho si vyberu otázku, na kterou neznám odpověď (např. Je vesmír nekonečný? Bude zítra pršet?). Najdu odpovědi ve dvou různých zdrojích a výsledky porovnám. Řeknu, jestli odpovědím věřím a proč.',
				'Reportáž z výpravy. Napíšu článek o výpravě. Může to být do oddílového časopisu nebo i jinam. Článek doplním obrázky, fotkami a rozhovory s ostatními členy a členkami šestky (co říkají o výpravě, jak se jim líbila, jaké z ní mají zážitky).',
				'Starám se o kroniku. Do kroniky, na oddílový web apod. zaznamenám tři akce (schůzku, den na táboře, výlet…). Záznamy dodám včas a bez faktických chyb.',
				'Rozhovor. Udělám rozhovor s člověkem, kterého si vážím. Na rozhovor se připravím: o tématu si dopředu něco zjistím, nachystám si otázky, které chci položit. Odpovědi si zaznamenám a rozhovor zveřejním (např. nástěnka, oddílový časopis).',
				'Různé pohledy. Zajímavou zprávu, kterou jsem viděl/a např. ve Zprávičkách (ČT Déčko), si najdu i v novinách nebo na internetu a obě porovnám.',
				'Aktuální zprávy. Na třech schůzkách vyprávím šestce, co se aktuálně děje v našem městě, ČR nebo ve světě. Vždy uvedu zdroj, kde jsem informaci získal/a, a co mi na ní přijde důležité.',
				'Návštěva. Navštívím budovu rozhlasu, televize nebo redakce novin. Ostatním v šestce povím, co jsem se dozvěděl/a zajímavého.',
				'Anketa. Zjistím nejméně od deseti lidí odpověď na jednu otázku, kterou řešíme v oddíle, ve škole či v mém okolí. Které odpovědi převažují? Proč to tak je?',
				'Redaktor/ka Světýlka. Pošlu příspěvek do tohoto časopisu (např. obrázek, fotka, článek).',
				'Foto a video. Pořídím z akce fotoreportáž nebo natočím video a na nejbližší schůzce výsledek předvedu šestce.',
			] ],
			[ 'Sběratel', [
				'Sbírka. Vytvořím si sbírku přírodnin různých velikostí, tvarů a barev. Nalezené přírodniny správně pojmenuji nebo jim vymyslím vlastní názvy.',
				'Moje sbírka. Povím příběh nebo sehraju scénku, jak jsem začal/a sbírat, jak dlouho už sbírám a proč.',
				'Uložení sbírky. Vytvořím si prostor, kde budu mít sbírku uloženou (pěkná krabice, polička…). Šestce vysvětlím, jak sbírku chráním před poničením.',
				'Popisky. Všechny předměty sbírky mám popsané (podle povahy sbírky např. název, kdy jsem daný exponát získal/a, proč je důležitý).',
				'Nejcennější exponát. Vyberu ze své sbírky předmět, který považuji za nejcennější, a vysvětlím šestce, proč si ho tolik vážím.',
				'Ukázka sbírky. Vezmu část své sbírky na schůzku a představím jednotlivé předměty šestce.',
				'Články. Přečtu si dva články, které se věnují tématu mé sbírky, a dozvím se z nich něco nového.',
				'Rozšiřování sbírky. Řeknu šestce, kde a jak předměty do sbírky získávám.',
				'Další sbírka. Vyberu si novou věc na sbírání a začnu ji sbírat. Vytvořím pro ni místo na uložení a připravím popisky.',
				'Návštěva sbírky. Navštívím kamaráda nebo kamarádku, který/á má sbírku se stejným tématem, nebo navštívím výstavu s tématem mé sbírky.',
			] ],
			[ 'Zdravotník', [
				'Telefonát. V hraném telefonátu si vyzkouším přivolat pomoc k různým situacím. Do telefonu řeknu vše, co je potřeba. Jsem na procházce a najdu člověka, který hodně krvácí; Jsem na výletě a vidím hořet zahradní chatku; Jedu na kole a přijedu k dopravní nehodě (srážka dvou aut).',
				'Seznam zranění. Vytvořím si seznam úrazů, které zvládnu ošetřit (vzhledem ke svému věku a dovednostem), a u kterých požádám o pomoc dospělého. Seznam ukážu vedoucím nebo rodičům.',
				'Rychlost reakce. Vím, kdy musím na zranění reagovat okamžitě a kdy počkat na dospělého. Pomocí scénky sehraju tři příklady situací se zraněním, které vyžadují moji okamžitou reakci. Stejným způsobem předvedu tři příklady, kdy počkám na dospělého a seznámím ho se situací.',
				'Předcházení nemocem. Vysvětlím šestce, proč je důležité si pravidelně čistit zuby, kdy je potřeba si mýt ruce, proč je důležitá hygiena celého těla a jaká voda a jídlo jsou bezpečné ke konzumaci.',
				'Předcházení úrazům. Zkusím přijít na to, jaké úrazy se mohou stát při poledním klidu, při oblíbené hře, sportu nebo noční bojovce. Vyberu si jeden takový úraz a zkusím vymyslet opatření, jak mu předejít.',
				'Popálenina. Na výpravě nebo na táboře najdu situace, kdy se můžeme popálit. U jedné popíšu, jak tomu předejít a jak popáleninu ošetřit.',
				'Hmyz. Poznám klíště, včelu a vosu. Popíšu, co budu dělat, pokud mě daný hmyz kousne nebo bodne. Vysvětlím šestce, čím je každý z nich nebezpečný.',
				'Alergie. Zeptám se nejbližších kamarádů a kamarádek, zda jsou na něco alergičtí. Pokud ano, nechám si od nich vyprávět, co alergii spouští, jak probíhá a jak ji ošetřit.',
				'Dívám se. Na výpravě nebo dva dny na táboře sleduju vedoucí, jak ošetřují drobná zranění ostatním. Poprosím vedoucí, aby mi ukázali, co mají v lékárničce.',
				'I smutek bolí. Mám pochopení pro kamarády a kamarádky, kterým je smutno. Zeptám se rodičů nebo vedoucích, jakými způsoby jim mohu pomoct a kdy mám o pomoc říci dospělému člověku.',
			] ],
			[ 'Ajťák', [
				'Email. Napíšu e-mail vedoucí/mu (omluvím se ze schůzky, mám dotaz k výpravě…).',
				'Z čeho je počítač. Najdu na internetu obrázky základních součástí, ze kterých se skládá stolní počítač nebo notebook. Obrázky uložím a vytvořím dokument s jejich popisky. Vše spolu s někým z vedení oddílu či příbuzných vytisknu. Na schůzce obrázky součástí počítače sestavíme k sobě, jak jsou ve skutečném počítači, a šestce řeknu, k čemu která součást slouží.',
				'Nové slovo. V článku o počítačích najdu jedno slovo, kterému nerozumím. Zkusím ho vyhledat na internetu a vysvětlit vedoucím nebo rodičům, co znamená.',
				'Počítačová hra. Najdu počítačovou hru, která pomáhá procvičovat znalosti, a zahrajeme si ji s kamarádkou nebo kamarádem.',
				'Virus. Zjistím, co je to počítačový virus. Nakreslím obrázek, sehraji scénku nebo převyprávím příběh, který ukazuje, jak se virus může dostat do počítače.',
				'Kyberšikana. Vysvětlím šestce, co je to kyberšikana, a dohromady s vedoucími vymyslíme, jak na ni reagovat.',
				'Klávesnice. Seženu starou klávesnici, vyloupu z ní písmenka a zkusíme se šestkou poskládat co nejvíce slov bez opakovaného použití. Potom zkusíme klávesnici složit do původního stavu a zkusím vysvětlit, proč jsou písmena poskládaná právě takto.',
				'Průzkum. O místě, kam půjdeme na oddílovou výpravu nebo rodinný výlet, vyhledám na internetu co nejvíce informací. Vytisknu je a ukážu ostatním.',
				'Zdraví. Ztvárním obrázkem, hranou scénkou, videem nebo i jinak, co se stane, když budu moc dlouho sedět u počítače nebo tabletu. Výsledek ukážu šestce.',
				'Programování. Vyzkouším si něco naprogramovat. Vysvětlím, jak by to, co jsem programoval/a, mělo fungovat.',
			] ],
			[ 'Kutil', [
				'Vyrábění. Společně vyrobíme předmět pro šestku.',
				'Nářadí. Nové nářadí, se kterým jsem ještě nepracoval/a, alespoň dvakrát správně použiju. Pracuju s ním bezpečně. Po práci ho vrátím na své místo. Může se jednat např. o kladivo, kleště, dláto, nebozez, šroubovák, pilu nebo sekeru.',
				'Oprava a vylepšení. Doma nebo v klubovně po dohodě s rodiči či s vedením oddílu opravím rozbitou věc nebo jinou věc vylepším.',
				'Funkční model. Vytvořím funkční model, např. draka, který bude létat, nebo mlýnek na potoce, který se bude točit.',
				'Prostorové modely. Zhotovím dva prostorové modely, třeba táborový stan nebo známý hrad.',
				'Sádra. Vytvořím sádrové odlitky a vymaluju je.',
				'Recyklace. Ze starých věcí vytvořím dva výrobky. Mohu použít PET lahve, oblečení, plechovky, sklenice, noviny a další.',
				'Řezbářství. Zhotovím dva výrobky ze dřeva, např. táborové cedulky, píšťalku.',
				'Obsluha přístroje. Vyměním či pomůžu vyměnit např. baterie (do baterky, hračky…), nástavec (v kuchyňském robotu, akušroubováku…) apod.',
				'Pracuji bezpečně. Ukážu, jak ošetřím základní poranění, která se mohou vyskytnout při práci s nástroji (říznutí, zadřená tříska, lehká popálenina…).',
			] ],
			[ 'Poutník za pravdou', [
				'Slib. Jeden den se více než obvykle soustředím na dodržování slibu. Večer se zamyslím, jak se mi ho dařilo plnit. Svá zjištění jakkoliv ztvárním. O plnění si popovídám s vedoucí/m.',
				'Pravda. Zjistím aspoň od pěti lidí, co si představují pod slovem pravda. A pak napíšu, řeknu nebo nakreslím, co si o tomto svém zjištění myslím.',
				'Články ze Světýlka. Vyhledám v časopise Světýlko články, které se věnují slibu a zákonu. Články si vystříhám, ofotím, vyfotím nebo vypíšu zajímavé myšlenky. Ukážu a vysvětlím šestce, proč jsem si je vybral/a.',
				'Pravda v písničkách. Vyberu si písničku, která se týká pravdy. Zahraju ji, nebo ji pustím šestce, a svými slovy řeknu, o čem je.',
				'Můj vzor. Vyberu si člověka, kterého si vážím pro to, že udělal něco důležitého pro ostatní. Vytvořím pomocí obrázků a textů plakát o něm. Bude na něm, proč si ho vážím a z čeho si od něj můžu vzít příklad. Plakát si pověsím na místo, kde na něj dobře uvidím.',
				'Dobro a zlo. Vyberu si pohádku a převyprávím ji šestce. Řeknu, co je v pohádce dobro a zlo a jaké poučení z ní plyne.',
				'Na straně dobra. Vyprávím příběh, který jsem zažil/a a ve kterém jsem se musel/a rozhodovat mezi dobrem a zlem. Vysvětlím, proč si myslím, že jsem se zachoval/a správně.',
				'Různé pohledy. Vyberu si jednu situaci z běžného života (např. ukončení zápasu ve vybíjené, rozhodování, kam půjdeme na výlet). Zeptám se čtyř účastníků, co o té situaci říkají, a to si zapíšu, natočím nebo nahraji. Vymyslím společně s vedoucím či rodičem, proč se názory na danou situaci liší.',
				'Vyhledávání informací. K tématu, které mě zajímá (např. květiny, rukodělky, historie místa), najdu potřebné informace ve dvou různých zdrojích a porovnám je. Šestce představím téma a řeknu, kde jsem informace k němu našel/našla.',
				'Přemýšlení. Položím si otázku, nebo mi ji položí vedoucí (např. čím bych chtěl/a být, kdy jsem někomu pomohl/a). Půjdu se na chvíli projít (např. do okolí tábora), nebo si sednu na klidné místo a budu o ní přemýšlet. Na závěr si s vedoucí/m o svých myšlenkách popovídám.',
			] ],
			[ 'Vodák', [
				'Lodička. Vyrobím lodičku z kůry či ze dřeva. Pustím ji po vodě a sleduji, kam dopluje.',
				'Pádlování. Pádluju správně na obě strany, udržím rytmus posádky. Předvedu záběry kontra, jak přitáhnout a jak odlomit při běžné plavbě na řece.',
				'Bezpečnost. Pomocí příběhu nebo obrázku ukážu šestce nebezpečí na vodě: peřeje, jezy, válce, stromy nad vodou, nízké lávky a rozlehlé vodní plochy. U každého popíšu, co dělat, abych se do tohoto nebezpečí nedostal/a.',
				'Plavba na lodi. Na lodi vždy jezdím s vestou, kterou si správně zapnu. Správně nastoupím do lodi a vystoupím z lodi. Ujedu na lodi celkem alespoň 20 kilometrů na tekoucí vodě.',
				'Úvaz. Bezpečně uvážu loď ke kůlu i okolo stromu lodní smyčkou nebo jiným úvazem.',
				'Lodní pytel. Zabalím si správně do lodního pytle vybavení na vodáckou výpravu.',
				'Převržení lodi. Předvedu, jak se chovat při převržení lodi – nepouštím pádlo a pomáhám se záchranou a vylitím lodi. Vyzkouším si to i v tekoucí vodě.',
				'Plachetnice. Zúčastním se ustrojení a odstrojení plachetnice (oplachtěné pramice).',
				'Trojúhelník. Objedu na plachetnici trojúhelník jak na hlavní plachtě, tak na kormidle.',
				'Lodní deník. Nakreslím plánek splutého úseku řeky nebo výpravy na plachetnici včetně zajímavostí, nebo napíšu záznam z plavby včetně zážitků.',
			] ],
			[ 'Deskovkář', [
				'Společenská hra. Spolu s kamarádem nebo kamarádkou si zahrajeme deskovou nebo karetní hru.',
				'Hodně her. Hrál/a jsem deset různých společenských her.',
				'Hry s tužkou a papírem. Zahraju si se šestkou tři hry, ke kterým je třeba pouze papír a tužka.',
				'Vysvětlení pravidel. Vysvětlím srozumitelně šestce nebo rodině pravidla deskové hry, kterou předtím neznali.',
				'Vlastní pomůcky. Vyrobím si vlastní figurku nebo hrací kostku.',
				'Starost o hru. Ukážu šestce, jak se k deskovkám chovat, aby se neničily, a jak správně uložit dílky do krabice. Pravidla úklidu dodržuju při každém hraní.',
				'Turnaj. Uspořádám v šestce turnaj v oblíbené deskovce.',
				'Fair play. Vysvětlím šestce, proč je důležité u her nepodvádět a umět i prohrávat. Při hraní se nehádám ani nevztekám, aby si hru užili i ostatní.',
				'Tradiční hry. Zahraju si jednu hru na šachové hrací desce a jednu hru s kartami (např. prší, kvarteto, černý petr).',
				'Vlastní hra. Vytvořím sám/sama nebo se šestkou vlastní hru (namalujeme plán a vymyslíme pravidla). Hru si zahrajeme.',
			] ],
		];

		$obrazky = [
			'Ajťák'              => 'ajtak.png',
			'Atlet'              => 'atlet.png',
			'Cestovatel'         => 'cestovatel.png',
			'Chovatel'           => 'chovatel.jpg',
			'Cyklista'           => 'cyklista.jpg',
			'Čtenář'             => 'ctenar.png',
			'Deskovkář'          => 'deskovkar.png',
			'Divadelník'         => 'divadelnik.png',
			'Fotograf'           => 'fotograf.png',
			'Historik'           => 'historik.png',
			'Hudebník'           => 'hudebnik.png',
			'Hvězdář'            => 'hvezdar.jpg',
			'Jazykář'            => 'jazykar.png',
			'Kuchař'             => 'kuchar.png',
			'Kutil'              => 'kutil.png',
			'Plavec'             => 'plavec.png',
			'Polárník'           => 'polarnik.png',
			'Poutník za pravdou' => 'poutnik.png',
			'Přírodověděc'       => 'prirodovedet.png',
			'Průvodce'           => 'pruvodce.png',
			'Reportér'           => 'reporter.png',
			'Rukodělkář'         => 'rukodelkar.png',
			'Sběratel'           => 'sberatel.png',
			'Sportovec'          => 'sportovec.jpg',
			'Táborník'           => 'tabornik.png',
			'Vodák'              => 'vodak.png',
			'Výtvarník'          => 'vytvarnik.png',
			'Zahradník'          => 'zahradnik.png',
			'Zdravotník'         => 'zdravotnik.jpg',
			'Zpěvák'             => 'zpevak.png',
		];

		foreach ( $odborky as $row ) {
			[ $nazev, $ukoly ] = $row;
			$wpdb->insert( "{$wpdb->prefix}vo_odborky", [
				'nazev'    => $nazev,
				'typ'      => 'oba',
				'min_ukolu'=> 8,
				'obrazek'  => $obrazky[ $nazev ] ?? null,
			] );
			$oid = $wpdb->insert_id;
			foreach ( $ukoly as $i => $u ) {
				$wpdb->insert( "{$wpdb->prefix}vo_ukoly", [
					'odborka_id' => $oid,
					'poradi'     => $i + 1,
					'nazev'      => $u,
				] );
			}
		}
	}

	// ── ACCESS CONTROL ───────────────────────────────────────────────────────

	private function is_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	private function require_author(): void {
		if ( ! current_user_can( 'publish_posts' ) ) wp_die( 'Přístup odepřen.' );
	}

	private function can_edit_sestka( int $id ): bool {
		if ( $this->is_admin() ) return true;
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->prefix}vo_vedouci WHERE sestka_id=%d AND user_id=%d",
			$id, get_current_user_id()
		) );
	}

	/** Šestky, které může přihlášený uživatel EDITOVAT. */
	private function my_sestky(): array {
		global $wpdb;
		if ( $this->is_admin() ) {
			return $wpdb->get_results(
				"SELECT s.*, o.nazev AS oddil_nazev, o.typ
				 FROM {$wpdb->prefix}vo_sestky s
				 LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id = s.oddil_id
				 ORDER BY o.nazev, s.nazev"
			) ?: [];
		}
		$uid = get_current_user_id();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT s.*, o.nazev AS oddil_nazev, o.typ
			 FROM {$wpdb->prefix}vo_sestky s
			 LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id = s.oddil_id
			 JOIN {$wpdb->prefix}vo_vedouci v ON v.sestka_id = s.id
			 WHERE v.user_id = %d ORDER BY o.nazev, s.nazev", $uid
		) ) ?: [];
	}

	// ── FLASH MESSAGES ───────────────────────────────────────────────────────

	private function flash( string $msg, string $type = 'success' ): void {
		set_transient( 'vo_flash_' . get_current_user_id(), compact( 'msg', 'type' ), 60 );
	}

	private function render_flash(): string {
		$key = 'vo_flash_' . get_current_user_id();
		$f   = get_transient( $key );
		if ( ! $f ) return '';
		delete_transient( $key );
		return '<div class="vo-alert vo-alert-' . esc_attr( $f['type'] ) . '">' . esc_html( $f['msg'] ) . '</div>';
	}

	// ── HELPERS ──────────────────────────────────────────────────────────────

	/** Vrací [ 'done' => int, 'total' => int, 'min' => int, 'splneno' => bool, 'ukoly' => [] ] */
	private function progress( int $dite_id, int $odborka_id ): array {
		global $wpdb;
		$ukoly = $wpdb->get_results( $wpdb->prepare(
			"SELECT u.id, u.poradi, u.nazev,
			        p.datum_splneni, p.poznamka, p.vedouci_id
			 FROM {$wpdb->prefix}vo_ukoly u
			 LEFT JOIN {$wpdb->prefix}vo_plneni p ON p.ukol_id = u.id AND p.dite_id = %d
			 WHERE u.odborka_id = %d
			 ORDER BY u.poradi", $dite_id, $odborka_id
		) ) ?: [];
		$min  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT min_ukolu FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id
		) ) ?: 8;
		$done = count( array_filter( $ukoly, fn( $u ) => ! empty( $u->datum_splneni ) ) );
		return [
			'done'    => $done,
			'total'   => count( $ukoly ),
			'min'     => $min,
			'splneno' => $done >= $min,
			'ukoly'   => $ukoly,
		];
	}

	private function img_url( string $soubor ): string {
		return plugins_url( 'images/' . $soubor, __FILE__ );
	}

	private function sestka_label( string $typ ): string {
		return $typ === 'svetlusky' ? 'roj' : 'šestka';
	}

	private function typ_label( string $typ ): string {
		return $typ === 'svetlusky' ? 'Světlušky' : 'Vlčata';
	}

	private function nonce_field( string $action ): string {
		return wp_nonce_field( $action, '_wpnonce', true, false );
	}

	private function admin_url( string $page, array $extra = [] ): string {
		return add_query_arg( array_merge( [ 'page' => $page ], $extra ), admin_url( 'admin.php' ) );
	}

	// ── ADMIN MENU ───────────────────────────────────────────────────────────

	public function admin_menu(): void {
		add_menu_page( 'Odborky', 'Odborky', 'publish_posts', 'vlcci', [ $this, 'page_dashboard' ], 'dashicons-awards', 30 );
		add_submenu_page( 'vlcci', 'Přehled', 'Přehled', 'publish_posts', 'vlcci', [ $this, 'page_dashboard' ] );
		add_submenu_page( 'vlcci', 'Plnění úkolů', 'Plnění úkolů', 'publish_posts', 'vlcci_plneni', [ $this, 'page_plneni' ] );
		add_submenu_page( 'vlcci', 'Po dětech', 'Po dětech', 'publish_posts', 'vlcci_po_detech', [ $this, 'page_po_detech' ] );
		add_submenu_page( 'vlcci', 'Po odborkách', 'Po odborkách', 'publish_posts', 'vlcci_po_odborky', [ $this, 'page_po_odborky' ] );
		add_submenu_page( 'vlcci', 'Filtr', 'Filtr', 'publish_posts', 'vlcci_filtr', [ $this, 'page_filtr' ] );
		add_submenu_page( 'vlcci', 'Děti', 'Děti', 'publish_posts', 'vlcci_deti', [ $this, 'page_deti' ] );
		add_submenu_page( 'vlcci', 'Oddíly a šestky', 'Oddíly a šestky', 'manage_options', 'vlcci_oddily', [ $this, 'page_oddily' ] );
		add_submenu_page( 'vlcci', 'Definice odborek', 'Definice odborek', 'manage_options', 'vlcci_odborky_def', [ $this, 'page_odborky_def' ] );
	}

	// ── FORM DISPATCHER ──────────────────────────────────────────────────────

	public function handle_post(): void {
		if ( empty( $_POST['vo_action'] ) ) return;
		$action = sanitize_key( $_POST['vo_action'] );

		$nonce_map = [
			'save_oddil'    => 'vo_oddily',
			'delete_oddil'  => 'vo_oddily',
			'save_sestka'   => 'vo_oddily',
			'delete_sestka' => 'vo_oddily',
			'save_dite'     => 'vo_deti',
			'delete_dite'   => 'vo_deti',
			'save_odborka'  => 'vo_odborky',
			'delete_odborka'=> 'vo_odborky',
			'save_ukol'     => 'vo_odborky',
			'delete_ukol'   => 'vo_odborky',
			'save_plneni'   => 'vo_plneni',
		];

		if ( ! isset( $nonce_map[ $action ] ) ) return;
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', $nonce_map[ $action ] ) ) wp_die( 'Bezpečnostní chyba.' );
		$this->require_author();

		switch ( $action ) {
			case 'save_oddil':     $this->do_save_oddil();    break;
			case 'delete_oddil':   $this->do_delete_oddil();  break;
			case 'save_sestka':    $this->do_save_sestka();   break;
			case 'delete_sestka':  $this->do_delete_sestka(); break;
			case 'save_dite':      $this->do_save_dite();     break;
			case 'delete_dite':    $this->do_delete_dite();   break;
			case 'save_odborka':   $this->do_save_odborka();  break;
			case 'delete_odborka': $this->do_delete_odborka();break;
			case 'save_ukol':      $this->do_save_ukol();     break;
			case 'delete_ukol':    $this->do_delete_ukol();   break;
			case 'save_plneni':    $this->do_save_plneni();   break;
		}
	}

	// ── FORM HANDLERS ────────────────────────────────────────────────────────

	private function do_save_oddil(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id    = intval( $_POST['oddil_id'] ?? 0 );
		$nazev = sanitize_text_field( $_POST['nazev'] ?? '' );
		$typ   = in_array( $_POST['typ'] ?? '', [ 'vlcata', 'svetlusky' ] ) ? $_POST['typ'] : 'vlcata';
		if ( ! $nazev ) { $this->flash( 'Název oddílu je povinný.', 'error' ); wp_safe_redirect( $this->admin_url( 'vlcci_oddily' ) ); exit; }
		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}vo_oddily", compact( 'nazev', 'typ' ), [ 'id' => $id ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}vo_oddily", compact( 'nazev', 'typ' ) );
		}
		$this->flash( 'Oddíl uložen.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_oddily' ) ); exit;
	}

	private function do_delete_oddil(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id = intval( $_POST['oddil_id'] ?? 0 );
		$wpdb->delete( "{$wpdb->prefix}vo_oddily", [ 'id' => $id ] );
		$this->flash( 'Oddíl smazán.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_oddily' ) ); exit;
	}

	private function do_save_sestka(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id       = intval( $_POST['sestka_id'] ?? 0 );
		$oddil_id = intval( $_POST['oddil_id'] ?? 0 );
		$nazev    = sanitize_text_field( $_POST['nazev'] ?? '' );
		if ( ! $nazev || ! $oddil_id ) { $this->flash( 'Vyplňte všechna pole.', 'error' ); wp_safe_redirect( $this->admin_url( 'vlcci_oddily' ) ); exit; }
		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}vo_sestky", compact( 'nazev', 'oddil_id' ), [ 'id' => $id ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}vo_sestky", compact( 'nazev', 'oddil_id' ) );
			$id = $wpdb->insert_id;
		}
		// Save vedoucí assignments
		$wpdb->delete( "{$wpdb->prefix}vo_vedouci", [ 'sestka_id' => $id ] );
		$vedouci = array_map( 'intval', (array) ( $_POST['vedouci'] ?? [] ) );
		foreach ( $vedouci as $uid ) {
			if ( $uid > 0 ) {
				$wpdb->replace( "{$wpdb->prefix}vo_vedouci", [ 'sestka_id' => $id, 'user_id' => $uid ] );
			}
		}
		$this->flash( 'Šestka/roj uložena.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_oddily' ) ); exit;
	}

	private function do_delete_sestka(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id = intval( $_POST['sestka_id'] ?? 0 );
		$wpdb->delete( "{$wpdb->prefix}vo_sestky", [ 'id' => $id ] );
		$wpdb->delete( "{$wpdb->prefix}vo_vedouci", [ 'sestka_id' => $id ] );
		$this->flash( 'Šestka/roj smazána.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_oddily' ) ); exit;
	}

	private function do_save_dite(): void {
		global $wpdb;
		$id        = intval( $_POST['dite_id'] ?? 0 );
		$sestka_id = intval( $_POST['sestka_id'] ?? 0 );
		if ( ! $this->can_edit_sestka( $sestka_id ) ) wp_die( 'Přístup odepřen.' );
		$jmeno     = sanitize_text_field( $_POST['jmeno'] ?? '' );
		$prijmeni  = sanitize_text_field( $_POST['prijmeni'] ?? '' );
		$prezdivka = sanitize_text_field( $_POST['prezdivka'] ?? '' );
		$aktivni   = isset( $_POST['aktivni'] ) ? 1 : 0;
		if ( ! $jmeno || ! $prijmeni || ! $prezdivka ) {
			$this->flash( 'Vyplňte jméno, příjmení i přezdívku.', 'error' );
			wp_safe_redirect( $this->admin_url( 'vlcci_deti', [ 'sestka_id' => $sestka_id ] ) ); exit;
		}
		$data = compact( 'sestka_id', 'jmeno', 'prijmeni', 'prezdivka', 'aktivni' );
		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}vo_deti", $data, [ 'id' => $id ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}vo_deti", $data );
		}
		$this->flash( 'Dítě uloženo.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_deti', [ 'sestka_id' => $sestka_id ] ) ); exit;
	}

	private function do_delete_dite(): void {
		global $wpdb;
		$id = intval( $_POST['dite_id'] ?? 0 );
		$d  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $id ) );
		if ( ! $d || ! $this->can_edit_sestka( (int) $d->sestka_id ) ) wp_die( 'Přístup odepřen.' );
		$wpdb->delete( "{$wpdb->prefix}vo_deti", [ 'id' => $id ] );
		$this->flash( 'Dítě smazáno.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_deti', [ 'sestka_id' => $d->sestka_id ] ) ); exit;
	}

	private function do_save_odborka(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id        = intval( $_POST['odborka_id'] ?? 0 );
		$nazev     = sanitize_text_field( $_POST['nazev'] ?? '' );
		$typ       = in_array( $_POST['typ'] ?? '', [ 'vlcata', 'svetlusky' ] ) ? $_POST['typ'] : 'vlcata';
		$min_ukolu = max( 1, intval( $_POST['min_ukolu'] ?? 8 ) );
		if ( ! $nazev ) { $this->flash( 'Název odborky je povinný.', 'error' ); wp_safe_redirect( $this->admin_url( 'vlcci_odborky_def' ) ); exit; }
		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}vo_odborky", compact( 'nazev', 'typ', 'min_ukolu' ), [ 'id' => $id ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}vo_odborky", compact( 'nazev', 'typ', 'min_ukolu' ) );
		}
		$this->flash( 'Odborka uložena.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_odborky_def' ) ); exit;
	}

	private function do_delete_odborka(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id = intval( $_POST['odborka_id'] ?? 0 );
		$wpdb->delete( "{$wpdb->prefix}vo_odborky", [ 'id' => $id ] );
		$this->flash( 'Odborka smazána.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_odborky_def' ) ); exit;
	}

	private function do_save_ukol(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id         = intval( $_POST['ukol_id'] ?? 0 );
		$odborka_id = intval( $_POST['odborka_id'] ?? 0 );
		$nazev      = sanitize_textarea_field( $_POST['nazev'] ?? '' );
		$poradi     = intval( $_POST['poradi'] ?? 0 );
		if ( ! $nazev || ! $odborka_id ) { $this->flash( 'Vyplňte znění úkolu.', 'error' ); wp_safe_redirect( $this->admin_url( 'vlcci_odborky_def', [ 'edit_odborka' => $odborka_id ] ) ); exit; }
		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}vo_ukoly", compact( 'nazev', 'poradi', 'odborka_id' ), [ 'id' => $id ] );
		} else {
			$max_poradi = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(poradi) FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $odborka_id ) );
			$poradi     = $poradi ?: $max_poradi + 1;
			$wpdb->insert( "{$wpdb->prefix}vo_ukoly", compact( 'nazev', 'poradi', 'odborka_id' ) );
		}
		$this->flash( 'Úkol uložen.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_odborky_def', [ 'edit_odborka' => $odborka_id ] ) ); exit;
	}

	private function do_delete_ukol(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id  = intval( $_POST['ukol_id'] ?? 0 );
		$oid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT odborka_id FROM {$wpdb->prefix}vo_ukoly WHERE id=%d", $id ) );
		$wpdb->delete( "{$wpdb->prefix}vo_ukoly", [ 'id' => $id ] );
		$this->flash( 'Úkol smazán.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_odborky_def', [ 'edit_odborka' => $oid ] ) ); exit;
	}

	private function do_save_plneni(): void {
		global $wpdb;
		$dite_id    = intval( $_POST['dite_id'] ?? 0 );
		$odborka_id = intval( $_POST['odborka_id'] ?? 0 );
		$d          = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $dite_id ) );
		if ( ! $d || ! $this->can_edit_sestka( (int) $d->sestka_id ) ) wp_die( 'Přístup odepřen.' );

		$ukoly = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $odborka_id
		) ) ?: [];

		$vedouci_id = get_current_user_id();
		foreach ( $ukoly as $u ) {
			$datum = sanitize_text_field( $_POST[ 'datum_' . $u->id ] ?? '' );
			$pozn  = sanitize_textarea_field( $_POST[ 'pozn_' . $u->id ] ?? '' );
			if ( $datum ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}vo_plneni (dite_id, ukol_id, datum_splneni, poznamka, vedouci_id)
					 VALUES (%d, %d, %s, %s, %d)
					 ON DUPLICATE KEY UPDATE datum_splneni=%s, poznamka=%s, vedouci_id=%d",
					$dite_id, $u->id, $datum, $pozn, $vedouci_id,
					$datum, $pozn, $vedouci_id
				) );
			} else {
				$wpdb->delete( "{$wpdb->prefix}vo_plneni", [ 'dite_id' => $dite_id, 'ukol_id' => $u->id ] );
			}
		}
		$this->flash( 'Plnění uloženo.' );
		wp_safe_redirect( $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $dite_id, 'odborka_id' => $odborka_id ] ) ); exit;
	}

	// ── ADMIN PAGES ──────────────────────────────────────────────────────────

	public function page_dashboard(): void {
		$this->require_author();
		global $wpdb;
		$sestky = $this->my_sestky();
		echo '<div class="wrap vo-wrap">';
		echo '<h1>Odborky — přehled</h1>';
		echo $this->render_flash();

		if ( empty( $sestky ) ) {
			echo '<p>Nemáte přiřazenu žádnou šestku / roj. Požádejte administrátora.</p>';
			echo '</div>';
			return;
		}

		foreach ( $sestky as $s ) {
			$label = $this->sestka_label( $s->typ );
			echo '<div class="vo-card" style="margin-bottom:20px">';
			echo '<h2>' . esc_html( $s->oddil_nazev ) . ' — ' . esc_html( ucfirst( $label ) ) . ' ' . esc_html( $s->nazev ) . '</h2>';

			$deti = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1 ORDER BY prijmeni, jmeno", $s->id
			) ) ?: [];

			if ( empty( $deti ) ) {
				echo '<p><em>Žádné děti.</em></p></div>';
				continue;
			}

			$odborky = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $s->typ
			) ) ?: [];

			echo '<table class="vo-table widefat">';
			echo '<thead><tr><th>Dítě</th>';
			foreach ( $odborky as $o ) {
				echo '<th>' . esc_html( $o->nazev ) . '</th>';
			}
			echo '</tr></thead><tbody>';

			foreach ( $deti as $d ) {
				echo '<tr>';
				$url = $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $d->id ] );
				echo '<td><a href="' . esc_url( $url ) . '">' . esc_html( $d->prezdivka ) . ' (' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . ')</a></td>';
				foreach ( $odborky as $o ) {
					$p = $this->progress( (int) $d->id, (int) $o->id );
					if ( $p['done'] === 0 ) {
						echo '<td class="vo-center">—</td>';
					} elseif ( $p['splneno'] ) {
						echo '<td class="vo-center"><span class="vo-badge vo-badge-ok" title="Splněno">✓ ' . $p['done'] . '/' . $p['total'] . '</span></td>';
					} else {
						$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
						echo '<td class="vo-center"><div class="vo-progress"><div class="vo-progress-bar" style="width:' . $pct . '%"></div></div><small>' . $p['done'] . '/' . $p['total'] . '</small></td>';
					}
				}
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '</div>';
		}
		echo '</div>';
	}

	public function page_oddily(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;

		$edit_sestka = isset( $_GET['edit_sestka'] ) ? intval( $_GET['edit_sestka'] ) : 0;
		$sestka_obj  = $edit_sestka ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_sestky WHERE id=%d", $edit_sestka ) ) : null;

		$oddily  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_oddily ORDER BY nazev" ) ?: [];
		$sestky  = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];

		// WP users author+
		$wp_users = get_users( [ 'role__in' => [ 'administrator', 'editor', 'author' ], 'orderby' => 'display_name' ] );

		echo '<div class="wrap vo-wrap">';
		echo '<h1>Oddíly a šestky/roje</h1>';
		echo $this->render_flash();

		// — Oddíly section —
		echo '<h2>Oddíly</h2>';
		echo '<table class="vo-table widefat" style="margin-bottom:16px"><thead><tr><th>Název</th><th>Typ</th><th></th></tr></thead><tbody>';
		foreach ( $oddily as $o ) {
			echo '<tr><td>' . esc_html( $o->nazev ) . '</td><td>' . esc_html( $this->typ_label( $o->typ ) ) . '</td><td>';
			echo '<a href="' . esc_url( $this->admin_url( 'vlcci_oddily', [ 'edit_oddil' => $o->id ] ) ) . '">Upravit</a> &nbsp;';
			echo '<form style="display:inline" method="post">' . $this->nonce_field( 'vo_oddily' ) . '<input type="hidden" name="vo_action" value="delete_oddil"><input type="hidden" name="oddil_id" value="' . $o->id . '"><button class="vo-btn-link vo-danger" onclick="return confirm(\'Opravdu smazat?\')">Smazat</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		$edit_oddil = isset( $_GET['edit_oddil'] ) ? intval( $_GET['edit_oddil'] ) : 0;
		$eo         = $edit_oddil ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_oddily WHERE id=%d", $edit_oddil ) ) : null;
		echo '<h3>' . ( $eo ? 'Upravit oddíl' : 'Přidat oddíl' ) . '</h3>';
		echo '<form method="post" class="vo-form-row">';
		echo $this->nonce_field( 'vo_oddily' );
		echo '<input type="hidden" name="vo_action" value="save_oddil">';
		if ( $eo ) echo '<input type="hidden" name="oddil_id" value="' . $eo->id . '">';
		echo '<input type="text" name="nazev" value="' . esc_attr( $eo->nazev ?? '' ) . '" placeholder="Název oddílu" required>';
		echo '<select name="typ"><option value="vlcata"' . selected( $eo->typ ?? '', 'vlcata', false ) . '>Vlčata</option><option value="svetlusky"' . selected( $eo->typ ?? '', 'svetlusky', false ) . '>Světlušky</option></select>';
		echo '<button type="submit" class="button button-primary">Uložit oddíl</button>';
		if ( $eo ) echo ' <a href="' . esc_url( $this->admin_url( 'vlcci_oddily' ) ) . '" class="button">Zrušit</a>';
		echo '</form>';

		// — Šestky/roje section —
		echo '<hr><h2>Šestky / Roje</h2>';
		echo '<table class="vo-table widefat" style="margin-bottom:16px"><thead><tr><th>Oddíl</th><th>Název</th><th>Vedoucí</th><th></th></tr></thead><tbody>';
		foreach ( $sestky as $s ) {
			$veds = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}vo_vedouci WHERE sestka_id=%d", $s->id ) ) ?: [];
			$vedNames = [];
			foreach ( $veds as $uid ) {
				$u = get_userdata( $uid );
				if ( $u ) $vedNames[] = esc_html( $u->display_name );
			}
			echo '<tr><td>' . esc_html( $s->oddil_nazev ) . '</td><td>' . esc_html( $s->nazev ) . '</td><td>' . implode( ', ', $vedNames ) . '</td><td>';
			echo '<a href="' . esc_url( $this->admin_url( 'vlcci_oddily', [ 'edit_sestka' => $s->id ] ) ) . '">Upravit</a> &nbsp;';
			echo '<form style="display:inline" method="post">' . $this->nonce_field( 'vo_oddily' ) . '<input type="hidden" name="vo_action" value="delete_sestka"><input type="hidden" name="sestka_id" value="' . $s->id . '"><button class="vo-btn-link vo-danger" onclick="return confirm(\'Opravdu smazat?\')">Smazat</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		// Current vedoucí for edit
		$current_veds = $sestka_obj ? ( $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}vo_vedouci WHERE sestka_id=%d", $sestka_obj->id ) ) ?: [] ) : [];

		echo '<h3>' . ( $sestka_obj ? 'Upravit šestku/roj' : 'Přidat šestku/roj' ) . '</h3>';
		echo '<form method="post" class="vo-form-block">';
		echo $this->nonce_field( 'vo_oddily' );
		echo '<input type="hidden" name="vo_action" value="save_sestka">';
		if ( $sestka_obj ) echo '<input type="hidden" name="sestka_id" value="' . $sestka_obj->id . '">';
		echo '<div class="vo-form-row">';
		echo '<label>Oddíl: <select name="oddil_id">';
		foreach ( $oddily as $o ) {
			$sel = selected( (int) ( $sestka_obj->oddil_id ?? 0 ), (int) $o->id, false );
			echo '<option value="' . $o->id . '"' . $sel . '>' . esc_html( $o->nazev ) . ' (' . esc_html( $this->typ_label( $o->typ ) ) . ')</option>';
		}
		echo '</select></label>';
		echo '<label>Název: <input type="text" name="nazev" value="' . esc_attr( $sestka_obj->nazev ?? '' ) . '" required></label>';
		echo '</div>';
		echo '<div class="vo-form-group"><label>Vedoucí (Author+):</label><div class="vo-checkboxes">';
		foreach ( $wp_users as $u ) {
			$checked = in_array( $u->ID, $current_veds ) ? 'checked' : '';
			echo '<label><input type="checkbox" name="vedouci[]" value="' . $u->ID . '" ' . $checked . '> ' . esc_html( $u->display_name ) . ' (' . esc_html( $u->user_login ) . ')</label> ';
		}
		echo '</div></div>';
		echo '<button type="submit" class="button button-primary">Uložit šestku/roj</button>';
		if ( $sestka_obj ) echo ' <a href="' . esc_url( $this->admin_url( 'vlcci_oddily' ) ) . '" class="button">Zrušit</a>';
		echo '</form>';
		echo '</div>';
	}

	public function page_deti(): void {
		$this->require_author();
		global $wpdb;

		$my_sestky  = $this->my_sestky();
		$all_sestky = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];

		$sestka_id = intval( $_GET['sestka_id'] ?? ( $my_sestky[0]->id ?? 0 ) );
		$edit_id   = intval( $_GET['edit'] ?? 0 );
		$edit_d    = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $edit_id ) ) : null;
		$can_edit  = $this->can_edit_sestka( $sestka_id );

		echo '<div class="wrap vo-wrap"><h1>Děti</h1>' . $this->render_flash();

		// Sestka selector
		echo '<form method="get" class="vo-form-row" style="margin-bottom:16px"><input type="hidden" name="page" value="vlcci_deti">';
		echo '<label>Šestka/roj: <select name="sestka_id" onchange="this.form.submit()">';
		foreach ( $all_sestky as $s ) {
			$editable = $this->can_edit_sestka( (int) $s->id ) ? '' : ' 👁';
			echo '<option value="' . $s->id . '"' . selected( $sestka_id, (int) $s->id, false ) . '>' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev . $editable ) . '</option>';
		}
		echo '</select></label><noscript><button type="submit">Zobrazit</button></noscript></form>';

		// Children list
		$deti = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d ORDER BY prijmeni, jmeno", $sestka_id
		) ) ?: [];

		echo '<table class="vo-table widefat"><thead><tr><th>Příjmení</th><th>Jméno</th><th>Přezdívka</th><th>Aktivní</th><th></th></tr></thead><tbody>';
		foreach ( $deti as $d ) {
			echo '<tr' . ( $d->aktivni ? '' : ' class="vo-inactive"' ) . '>';
			echo '<td>' . esc_html( $d->prijmeni ) . '</td>';
			echo '<td>' . esc_html( $d->jmeno ) . '</td>';
			echo '<td>' . esc_html( $d->prezdivka ) . '</td>';
			echo '<td>' . ( $d->aktivni ? 'Ano' : 'Ne' ) . '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $d->id ] ) ) . '">Plnění</a>';
			if ( $can_edit ) {
				echo ' &nbsp;<a href="' . esc_url( $this->admin_url( 'vlcci_deti', [ 'sestka_id' => $sestka_id, 'edit' => $d->id ] ) ) . '">Upravit</a> &nbsp;';
				echo '<form style="display:inline" method="post">' . $this->nonce_field( 'vo_deti' ) . '<input type="hidden" name="vo_action" value="delete_dite"><input type="hidden" name="dite_id" value="' . $d->id . '"><button class="vo-btn-link vo-danger" onclick="return confirm(\'Opravdu smazat?\')">Smazat</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		if ( $can_edit ) {
			echo '<h3 style="margin-top:20px">' . ( $edit_d ? 'Upravit dítě' : 'Přidat dítě' ) . '</h3>';
			echo '<form method="post" class="vo-form-row">';
			echo $this->nonce_field( 'vo_deti' );
			echo '<input type="hidden" name="vo_action" value="save_dite">';
			echo '<input type="hidden" name="sestka_id" value="' . $sestka_id . '">';
			if ( $edit_d ) echo '<input type="hidden" name="dite_id" value="' . $edit_d->id . '">';
			echo '<input type="text" name="jmeno" value="' . esc_attr( $edit_d->jmeno ?? '' ) . '" placeholder="Jméno" required>';
			echo '<input type="text" name="prijmeni" value="' . esc_attr( $edit_d->prijmeni ?? '' ) . '" placeholder="Příjmení" required>';
			echo '<input type="text" name="prezdivka" value="' . esc_attr( $edit_d->prezdivka ?? '' ) . '" placeholder="Přezdívka" required>';
			echo '<label><input type="checkbox" name="aktivni" value="1"' . ( ( $edit_d->aktivni ?? 1 ) ? ' checked' : '' ) . '> Aktivní</label>';
			echo '<button type="submit" class="button button-primary">Uložit</button>';
			if ( $edit_d ) echo ' <a href="' . esc_url( $this->admin_url( 'vlcci_deti', [ 'sestka_id' => $sestka_id ] ) ) . '" class="button">Zrušit</a>';
			echo '</form>';
		}
		echo '</div>';
	}

	public function page_odborky_def(): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;

		$edit_odborka = intval( $_GET['edit_odborka'] ?? 0 );
		$eo = $edit_odborka ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $edit_odborka ) ) : null;
		$odborky = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_odborky ORDER BY typ, nazev" ) ?: [];

		echo '<div class="wrap vo-wrap"><h1>Definice odborek</h1>' . $this->render_flash();

		echo '<table class="vo-table widefat" style="margin-bottom:16px"><thead><tr><th></th><th>Typ</th><th>Název</th><th>Min. úkolů</th><th>Úkolů</th><th></th></tr></thead><tbody>';
		foreach ( $odborky as $o ) {
			$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $o->id ) );
			echo '<tr>';
			echo '<td style="width:40px">' . ( $o->obrazek ? '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" style="width:32px;height:32px;object-fit:contain">' : '' ) . '</td>';
			echo '<td>' . esc_html( $this->typ_label( $o->typ ) ) . '</td>';
			echo '<td><a href="' . esc_url( $this->admin_url( 'vlcci_odborky_def', [ 'edit_odborka' => $o->id ] ) ) . '">' . esc_html( $o->nazev ) . '</a></td>';
			echo '<td>' . (int) $o->min_ukolu . '</td>';
			echo '<td>' . $cnt . '</td>';
			echo '<td>';
			echo '<a href="' . esc_url( $this->admin_url( 'vlcci_odborky_def', [ 'edit_odborka' => $o->id ] ) ) . '">Upravit úkoly</a> &nbsp;';
			echo '<form style="display:inline" method="post">' . $this->nonce_field( 'vo_odborky' ) . '<input type="hidden" name="vo_action" value="delete_odborka"><input type="hidden" name="odborka_id" value="' . $o->id . '"><button class="vo-btn-link vo-danger" onclick="return confirm(\'Opravdu smazat?\')">Smazat</button></form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		// Odborka form
		echo '<h3>' . ( $eo ? 'Upravit odborku: ' . esc_html( $eo->nazev ) : 'Přidat odborku' ) . '</h3>';
		echo '<form method="post" class="vo-form-row">';
		echo $this->nonce_field( 'vo_odborky' );
		echo '<input type="hidden" name="vo_action" value="save_odborka">';
		if ( $eo ) echo '<input type="hidden" name="odborka_id" value="' . $eo->id . '">';
		echo '<input type="text" name="nazev" value="' . esc_attr( $eo->nazev ?? '' ) . '" placeholder="Název odborky" required>';
		echo '<select name="typ"><option value="vlcata"' . selected( $eo->typ ?? '', 'vlcata', false ) . '>Vlčata</option><option value="svetlusky"' . selected( $eo->typ ?? '', 'svetlusky', false ) . '>Světlušky</option></select>';
		echo '<label>Min. úkolů: <input type="number" name="min_ukolu" value="' . (int) ( $eo->min_ukolu ?? 8 ) . '" min="1" max="20" style="width:60px"></label>';
		echo '<button type="submit" class="button button-primary">Uložit odborku</button>';
		if ( $eo ) echo ' <a href="' . esc_url( $this->admin_url( 'vlcci_odborky_def' ) ) . '" class="button">Zrušit</a>';
		echo '</form>';

		// Tasks for selected odborka
		if ( $eo ) {
			$ukoly = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d ORDER BY poradi", $eo->id
			) ) ?: [];

			echo '<hr><h3>Úkoly odborky ' . esc_html( $eo->nazev ) . '</h3>';
			echo '<table class="vo-table widefat" style="margin-bottom:12px"><thead><tr><th>#</th><th>Znění úkolu</th><th></th></tr></thead><tbody>';
			foreach ( $ukoly as $u ) {
				echo '<tr>';
				echo '<td>' . (int) $u->poradi . '</td>';
				echo '<td>';
				// Inline edit form
				echo '<form method="post" class="vo-form-row"><input type="hidden" name="vo_action" value="save_ukol">' . $this->nonce_field( 'vo_odborky' );
				echo '<input type="hidden" name="ukol_id" value="' . $u->id . '"><input type="hidden" name="odborka_id" value="' . $eo->id . '">';
				echo '<input type="number" name="poradi" value="' . (int) $u->poradi . '" style="width:55px" min="1">';
				echo '<input type="text" name="nazev" value="' . esc_attr( $u->nazev ) . '" style="width:400px" required>';
				echo '<button type="submit" class="button">Uložit</button></form>';
				echo '</td>';
				echo '<td><form method="post">' . $this->nonce_field( 'vo_odborky' ) . '<input type="hidden" name="vo_action" value="delete_ukol"><input type="hidden" name="ukol_id" value="' . $u->id . '"><button class="vo-btn-link vo-danger" onclick="return confirm(\'Smazat úkol?\')">Smazat</button></form></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';

			echo '<h4>Přidat úkol</h4>';
			echo '<form method="post" class="vo-form-row">';
			echo $this->nonce_field( 'vo_odborky' );
			echo '<input type="hidden" name="vo_action" value="save_ukol"><input type="hidden" name="odborka_id" value="' . $eo->id . '">';
			echo '<input type="text" name="nazev" placeholder="Znění nového úkolu" style="width:400px" required>';
			echo '<button type="submit" class="button button-primary">Přidat úkol</button>';
			echo '</form>';
		}
		echo '</div>';
	}

	public function page_plneni(): void {
		$this->require_author();
		global $wpdb;

		$dite_id    = intval( $_GET['dite_id'] ?? 0 );
		$odborka_id = intval( $_GET['odborka_id'] ?? 0 );

		$my_sestky  = $this->my_sestky();
		$all_sestky = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];

		// Selected sestka
		$sestka_id = 0;
		$dite      = null;
		if ( $dite_id ) {
			$dite      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $dite_id ) );
			$sestka_id = $dite ? (int) $dite->sestka_id : 0;
		}
		if ( ! $sestka_id ) {
			$sestka_id = intval( $_GET['sestka_id'] ?? ( $my_sestky[0]->id ?? 0 ) );
		}

		$can_edit = $this->can_edit_sestka( $sestka_id );
		$sestka   = $sestka_id ? $wpdb->get_row( $wpdb->prepare( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id WHERE s.id=%d", $sestka_id ) ) : null;

		echo '<div class="wrap vo-wrap"><h1>Plnění úkolů</h1>' . $this->render_flash();

		// Sestka selector
		echo '<form method="get" class="vo-form-row" style="margin-bottom:12px"><input type="hidden" name="page" value="vlcci_plneni">';
		echo '<label>Šestka/roj: <select name="sestka_id" onchange="this.form.submit()">';
		foreach ( $all_sestky as $s ) {
			$editable = $this->can_edit_sestka( (int) $s->id ) ? '' : ' 👁';
			echo '<option value="' . $s->id . '"' . selected( $sestka_id, (int) $s->id, false ) . '>' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev . $editable ) . '</option>';
		}
		echo '</select></label><noscript><button>OK</button></noscript></form>';

		// Child selector
		$deti = $sestka_id ? ( $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1 ORDER BY prijmeni, jmeno", $sestka_id
		) ) ?: [] ) : [];

		echo '<form method="get" class="vo-form-row" style="margin-bottom:20px"><input type="hidden" name="page" value="vlcci_plneni"><input type="hidden" name="sestka_id" value="' . $sestka_id . '">';
		echo '<label>Dítě: <select name="dite_id" onchange="this.form.submit()">';
		echo '<option value="">— vyber dítě —</option>';
		foreach ( $deti as $d ) {
			echo '<option value="' . $d->id . '"' . selected( $dite_id, (int) $d->id, false ) . '>' . esc_html( $d->prezdivka . ' (' . $d->jmeno . ' ' . $d->prijmeni . ')' ) . '</option>';
		}
		echo '</select></label><noscript><button>OK</button></noscript></form>';

		if ( ! $dite ) { echo '</div>'; return; }

		// Odborky for this child's troop type
		$typ     = $sestka->typ ?? 'vlcata';
		$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $typ ) ) ?: [];

		if ( empty( $odborky ) ) {
			echo '<p><em>Pro tento typ oddílu nejsou definovány žádné odborky.</em></p></div>';
			return;
		}

		// Odborka tabs (simple links)
		echo '<div class="vo-tabs" style="margin-bottom:20px">';
		foreach ( $odborky as $o ) {
			$p    = $this->progress( $dite_id, (int) $o->id );
			$cls  = 'vo-tab' . ( (int) $o->id === $odborka_id ? ' vo-tab-active' : '' ) . ( $p['splneno'] ? ' vo-tab-done' : ( $p['done'] > 0 ? ' vo-tab-partial' : '' ) );
			$url  = $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $dite_id, 'sestka_id' => $sestka_id, 'odborka_id' => $o->id ] );
			echo '<a href="' . esc_url( $url ) . '" class="' . $cls . '">';
			if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:4px">';
			echo esc_html( $o->nazev );
			if ( $p['done'] > 0 ) echo ' <small>(' . $p['done'] . '/' . $p['total'] . ')</small>';
			echo '</a> ';
		}
		echo '</div>';

		if ( ! $odborka_id ) { echo '</div>'; return; }

		$odborka = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id ) );
		if ( ! $odborka ) { echo '</div>'; return; }

		$p = $this->progress( $dite_id, $odborka_id );
		$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;

		echo '<div class="vo-card">';
		echo '<h2>';
		if ( $odborka->obrazek ) echo '<img src="' . esc_url( $this->img_url( $odborka->obrazek ) ) . '" style="width:40px;height:40px;object-fit:contain;vertical-align:middle;margin-right:10px">';
		echo esc_html( $dite->prezdivka ) . ' — ' . esc_html( $odborka->nazev ) . '</h2>';
		echo '<div class="vo-progress-wrap"><div class="vo-progress vo-progress-lg"><div class="vo-progress-bar" style="width:' . $pct . '%"></div></div>';
		echo '<span class="vo-progress-label">' . $p['done'] . ' / ' . $p['total'] . ' úkolů';
		if ( $p['splneno'] ) echo ' &nbsp;<span class="vo-badge vo-badge-ok">✓ SPLNĚNO</span>';
		elseif ( $p['total'] > 0 ) echo ' &nbsp;<small>(potřeba min. ' . $p['min'] . ')</small>';
		echo '</span></div>';

		if ( $can_edit ) {
			echo '<form method="post">';
			echo $this->nonce_field( 'vo_plneni' );
			echo '<input type="hidden" name="vo_action" value="save_plneni">';
			echo '<input type="hidden" name="dite_id" value="' . $dite_id . '">';
			echo '<input type="hidden" name="odborka_id" value="' . $odborka_id . '">';
		}

		echo '<table class="vo-table widefat vo-plneni-table" style="margin-top:16px">';
		echo '<thead><tr><th>#</th><th>Úkol</th><th>Datum splnění</th><th>Poznámka</th></tr></thead><tbody>';

		foreach ( $p['ukoly'] as $u ) {
			$done_cls = ! empty( $u->datum_splneni ) ? ' class="vo-row-done"' : '';
			echo '<tr' . $done_cls . '>';
			echo '<td>' . (int) $u->poradi . '</td>';
			echo '<td>' . esc_html( $u->nazev ) . '</td>';
			if ( $can_edit ) {
				echo '<td><input type="date" name="datum_' . $u->id . '" value="' . esc_attr( $u->datum_splneni ?? '' ) . '"></td>';
				echo '<td><input type="text" name="pozn_' . $u->id . '" value="' . esc_attr( $u->poznamka ?? '' ) . '" placeholder="Poznámka" style="width:100%"></td>';
			} else {
				echo '<td>' . ( $u->datum_splneni ? esc_html( date_format( date_create( $u->datum_splneni ), 'd. m. Y' ) ) : '—' ) . '</td>';
				echo '<td>' . esc_html( $u->poznamka ?? '' ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( $can_edit ) {
			echo '<p style="margin-top:12px"><button type="submit" class="button button-primary">Uložit plnění</button></p>';
			echo '</form>';
		}
		echo '</div></div>';
	}

	public function page_po_detech(): void {
		$this->require_author();
		global $wpdb;

		$sestky  = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];
		$filter_sestka = intval( $_GET['sestka_id'] ?? 0 );

		echo '<div class="wrap vo-wrap"><h1>Přehled po dětech</h1>';

		echo '<form method="get" class="vo-form-row" style="margin-bottom:16px"><input type="hidden" name="page" value="vlcci_po_detech">';
		echo '<label>Šestka/roj: <select name="sestka_id" onchange="this.form.submit()"><option value="">— vše —</option>';
		foreach ( $sestky as $s ) {
			echo '<option value="' . $s->id . '"' . selected( $filter_sestka, (int) $s->id, false ) . '>' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev ) . '</option>';
		}
		echo '</select></label><noscript><button>OK</button></noscript></form>';

		$where  = $filter_sestka ? $wpdb->prepare( 'AND d.sestka_id=%d', $filter_sestka ) : '';
		$deti   = $wpdb->get_results(
			"SELECT d.*, s.nazev AS sestka_nazev, o.nazev AS oddil_nazev, o.typ
			 FROM {$wpdb->prefix}vo_deti d
			 LEFT JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id
			 LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id
			 WHERE d.aktivni=1 $where ORDER BY o.nazev, s.nazev, d.prijmeni, d.jmeno"
		) ?: [];

		foreach ( $deti as $d ) {
			$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $d->typ ) ) ?: [];
			$active  = array_filter( $odborky, fn( $o ) => $this->progress( (int) $d->id, (int) $o->id )['done'] > 0 );
			if ( empty( $active ) && ! $filter_sestka ) continue; // skip untouched

			echo '<div class="vo-card" style="margin-bottom:16px">';
			echo '<strong>' . esc_html( $d->prijmeni . ' ' . $d->jmeno ) . '</strong> <em>(' . esc_html( $d->prezdivka ) . ')</em> — ' . esc_html( $d->oddil_nazev . ' / ' . $d->sestka_nazev );
			echo '<table class="vo-table" style="margin-top:8px"><thead><tr><th>Odborka</th><th>Splněno úkolů</th><th>Stav</th><th></th></tr></thead><tbody>';
			foreach ( $odborky as $o ) {
				$p = $this->progress( (int) $d->id, (int) $o->id );
				if ( $p['done'] === 0 ) continue;
				$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
				echo '<tr>';
				echo '<td>' . esc_html( $o->nazev ) . '</td>';
				echo '<td>' . $p['done'] . ' / ' . $p['total'] . '</td>';
				echo '<td>';
				if ( $p['splneno'] ) {
					echo '<span class="vo-badge vo-badge-ok">✓ Splněno</span>';
				} else {
					echo '<div class="vo-progress"><div class="vo-progress-bar" style="width:' . $pct . '%"></div></div> ' . $pct . '%';
				}
				echo '</td>';
				echo '<td><a href="' . esc_url( $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $d->id, 'sestka_id' => $d->sestka_id, 'odborka_id' => $o->id ] ) ) . '">Detail</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	public function page_po_odborky(): void {
		$this->require_author();
		global $wpdb;

		$odborky = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_odborky ORDER BY typ, nazev" ) ?: [];

		echo '<div class="wrap vo-wrap"><h1>Přehled po odborkách</h1>';

		foreach ( $odborky as $o ) {
			$deti = $wpdb->get_results( $wpdb->prepare(
				"SELECT DISTINCT d.*, s.nazev AS sestka_nazev, s.id AS sestka_id
				 FROM {$wpdb->prefix}vo_deti d
				 JOIN {$wpdb->prefix}vo_plneni p ON p.dite_id=d.id
				 JOIN {$wpdb->prefix}vo_ukoly u ON u.id=p.ukol_id AND u.odborka_id=%d
				 JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id
				 WHERE d.aktivni=1
				 ORDER BY d.prijmeni, d.jmeno", $o->id
			) ) ?: [];

			echo '<div class="vo-card" style="margin-bottom:16px">';
			echo '<h2>';
			if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" style="width:40px;height:40px;object-fit:contain;vertical-align:middle;margin-right:10px">';
			echo esc_html( $o->nazev ) . ' <small>(' . esc_html( $this->typ_label( $o->typ ) ) . ')</small></h2>';
			if ( empty( $deti ) ) { echo '<p><em>Nikdo tuto odborku neplní.</em></p></div>'; continue; }

			// Sort by progress desc
			usort( $deti, function( $a, $b ) use ( $o ) {
				return $this->progress( (int) $b->id, (int) $o->id )['done'] <=> $this->progress( (int) $a->id, (int) $o->id )['done'];
			} );

			echo '<table class="vo-table widefat"><thead><tr><th>Dítě</th><th>Šestka/roj</th><th>Postup</th><th></th></tr></thead><tbody>';
			foreach ( $deti as $d ) {
				$p   = $this->progress( (int) $d->id, (int) $o->id );
				$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
				echo '<tr>';
				echo '<td>' . esc_html( $d->prijmeni . ' ' . $d->jmeno ) . ' <em>(' . esc_html( $d->prezdivka ) . ')</em></td>';
				echo '<td>' . esc_html( $d->sestka_nazev ) . '</td>';
				echo '<td>';
				if ( $p['splneno'] ) {
					echo '<span class="vo-badge vo-badge-ok">✓ Splněno (' . $p['done'] . '/' . $p['total'] . ')</span>';
				} else {
					echo '<div class="vo-progress"><div class="vo-progress-bar" style="width:' . $pct . '%"></div></div> ' . $p['done'] . '/' . $p['total'];
				}
				echo '</td>';
				echo '<td><a href="' . esc_url( $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $d->id, 'sestka_id' => $d->sestka_id, 'odborka_id' => $o->id ] ) ) . '">Detail</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	public function page_filtr(): void {
		$this->require_author();
		global $wpdb;

		$odborka_id = intval( $_GET['odborka_id'] ?? 0 );
		$ukol_id    = intval( $_GET['ukol_id'] ?? 0 );
		$odborky    = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_odborky ORDER BY typ, nazev" ) ?: [];

		echo '<div class="wrap vo-wrap"><h1>Filtr — kdo neplní konkrétní úkol</h1>';
		echo '<p>Zobrazí děti, které plní danou odborku (mají splněný alespoň 1 úkol), ale chybí jim vybraný úkol.</p>';

		echo '<form method="get" class="vo-form-row"><input type="hidden" name="page" value="vlcci_filtr">';
		echo '<label>Odborka: <select name="odborka_id" onchange="this.form.submit()"><option value="">— vyber —</option>';
		foreach ( $odborky as $o ) {
			echo '<option value="' . $o->id . '"' . selected( $odborka_id, (int) $o->id, false ) . '>' . esc_html( $o->nazev . ' (' . $this->typ_label( $o->typ ) . ')' ) . '</option>';
		}
		echo '</select></label>';

		if ( $odborka_id ) {
			$ukoly = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d ORDER BY poradi", $odborka_id ) ) ?: [];
			echo '<label>Chybějící úkol: <select name="ukol_id" onchange="this.form.submit()"><option value="">— vyber úkol —</option>';
			foreach ( $ukoly as $u ) {
				echo '<option value="' . $u->id . '"' . selected( $ukol_id, (int) $u->id, false ) . '>' . (int) $u->poradi . '. ' . esc_html( $u->nazev ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<noscript><button type="submit" class="button">Filtrovat</button></noscript></form>';

		if ( $odborka_id && $ukol_id ) {
			$vysledky = $wpdb->get_results( $wpdb->prepare(
				"SELECT DISTINCT d.*, s.nazev AS sestka_nazev, s.id AS sestka_id, o.nazev AS oddil_nazev
				 FROM {$wpdb->prefix}vo_deti d
				 JOIN {$wpdb->prefix}vo_plneni p ON p.dite_id=d.id
				 JOIN {$wpdb->prefix}vo_ukoly u ON u.id=p.ukol_id AND u.odborka_id=%d
				 JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id
				 JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id
				 WHERE d.aktivni=1
				   AND d.id NOT IN (SELECT p2.dite_id FROM {$wpdb->prefix}vo_plneni p2 WHERE p2.ukol_id=%d)
				 ORDER BY d.prijmeni, d.jmeno",
				$odborka_id, $ukol_id
			) ) ?: [];

			$ukol_obj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE id=%d", $ukol_id ) );
			$odb_obj  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id ) );

			echo '<div class="vo-card" style="margin-top:16px">';
			echo '<h3>' . esc_html( $odb_obj->nazev ) . ' — chybí úkol č. ' . (int) $ukol_obj->poradi . ': <em>' . esc_html( $ukol_obj->nazev ) . '</em></h3>';
			if ( empty( $vysledky ) ) {
				echo '<p class="vo-alert vo-alert-success">Všichni, kdo plní tuto odborku, mají tento úkol splněný. 🎉</p>';
			} else {
				echo '<p>Celkem ' . count( $vysledky ) . ' dítě/dětí:</p>';
				echo '<table class="vo-table widefat"><thead><tr><th>Příjmení a jméno</th><th>Přezdívka</th><th>Oddíl / Šestka</th><th>Celkový postup</th><th></th></tr></thead><tbody>';
				foreach ( $vysledky as $d ) {
					$pr  = $this->progress( (int) $d->id, $odborka_id );
					$pct = $pr['total'] ? round( $pr['done'] / $pr['total'] * 100 ) : 0;
					echo '<tr>';
					echo '<td>' . esc_html( $d->prijmeni . ' ' . $d->jmeno ) . '</td>';
					echo '<td>' . esc_html( $d->prezdivka ) . '</td>';
					echo '<td>' . esc_html( $d->oddil_nazev . ' / ' . $d->sestka_nazev ) . '</td>';
					echo '<td><div class="vo-progress"><div class="vo-progress-bar" style="width:' . $pct . '%"></div></div> ' . $pr['done'] . '/' . $pr['total'] . '</td>';
					echo '<td><a href="' . esc_url( $this->admin_url( 'vlcci_plneni', [ 'dite_id' => $d->id, 'sestka_id' => $d->sestka_id, 'odborka_id' => $odborka_id ] ) ) . '">Plnění</a></td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	// ── FRONTEND SHORTCODE ───────────────────────────────────────────────────

	public function shortcode_prehled( array $atts ): string {
		$atts = shortcode_atts( [ 'oddil_id' => 0 ], $atts );
		global $wpdb;

		$where    = $atts['oddil_id'] ? $wpdb->prepare( 'WHERE o.id=%d', $atts['oddil_id'] ) : '';
		$sestky   = $wpdb->get_results(
			"SELECT s.*, o.nazev AS oddil_nazev, o.typ
			 FROM {$wpdb->prefix}vo_sestky s
			 LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id
			 $where ORDER BY o.nazev, s.nazev"
		) ?: [];

		ob_start();
		foreach ( $sestky as $s ) {
			$label = ucfirst( $this->sestka_label( $s->typ ) );
			echo '<div class="vo-sc-block">';
			echo '<h3 class="vo-sc-heading">' . esc_html( $s->oddil_nazev . ' — ' . $label . ' ' . $s->nazev ) . '</h3>';

			$deti   = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1 ORDER BY prijmeni, jmeno", $s->id
			) ) ?: [];
			$odborky = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $s->typ
			) ) ?: [];

			if ( empty( $deti ) ) { echo '<p><em>Žádné aktivní děti.</em></p></div>'; continue; }

			echo '<div class="vo-sc-grid">';
			foreach ( $deti as $d ) {
				echo '<div class="vo-sc-card">';
				echo '<h4 class="vo-sc-name">' . esc_html( $d->prezdivka ) . '</h4>';
				foreach ( $odborky as $o ) {
					$p = $this->progress( (int) $d->id, (int) $o->id );
					if ( $p['done'] === 0 ) continue;
					$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
					echo '<div class="vo-sc-odborka">';
					echo '<div class="vo-sc-odborka-name">';
					if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" style="width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:5px">';
					echo esc_html( $o->nazev );
					if ( $p['splneno'] ) echo ' <span class="vo-badge vo-badge-ok">✓</span>';
					echo '</div>';
					echo '<div class="vo-progress"><div class="vo-progress-bar" style="width:' . $pct . '%"></div></div>';
					echo '<div class="vo-sc-tasks">';
					foreach ( $p['ukoly'] as $u ) {
						if ( empty( $u->datum_splneni ) ) continue;
						$datum = date_format( date_create( $u->datum_splneni ), 'd. m. Y' );
						echo '<div class="vo-sc-task">✓ ' . esc_html( $u->nazev ) . ' <span class="vo-sc-date">(' . esc_html( $datum ) . ')</span></div>';
					}
					echo '</div></div>';
				}
				echo '</div>';
			}
			echo '</div></div>';
		}
		return ob_get_clean();
	}

	// ── CSS ──────────────────────────────────────────────────────────────────

	public function inject_css(): void {
		echo '<style>
.vo-wrap { max-width: 1200px; }
.vo-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px 20px; }
.vo-table { border-collapse: collapse; width: 100%; }
.vo-table th, .vo-table td { padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
.vo-table th { background: #f9f9f9; font-weight: 600; text-align: left; }
.vo-table tbody tr:hover { background: #fafafa; }
.vo-table tr.vo-row-done td { background: #f0fef4; }
.vo-table tr.vo-inactive td { opacity: .55; }
.vo-center { text-align: center; }
.vo-progress { display: inline-block; width: 100px; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden; vertical-align: middle; }
.vo-progress-lg { width: 200px; height: 14px; }
.vo-progress-wrap { display: flex; align-items: center; gap: 10px; margin: 10px 0; }
.vo-progress-bar { height: 100%; background: #16a34a; border-radius: 5px; transition: width .3s; }
.vo-progress-label { font-size: 14px; }
.vo-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.vo-badge-ok { background: #dcfce7; color: #15803d; }
.vo-alert { padding: 10px 16px; border-radius: 5px; margin: 10px 0; }
.vo-alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
.vo-alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #dc2626; }
.vo-form-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.vo-form-row input[type=text], .vo-form-row input[type=number], .vo-form-row select { padding: 4px 8px; }
.vo-form-block { margin-top: 8px; }
.vo-form-group { margin: 8px 0; }
.vo-checkboxes { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 4px; }
.vo-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.vo-tab { padding: 6px 14px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #444; background: #f9f9f9; font-size: 13px; }
.vo-tab:hover { background: #e8e8e8; }
.vo-tab-active { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
.vo-tab-done { border-color: #16a34a; color: #15803d; }
.vo-tab-partial { border-color: #f59e0b; color: #92400e; }
.vo-btn-link { background: none; border: none; cursor: pointer; padding: 0; font-size: inherit; }
.vo-danger { color: #dc2626; }
.vo-danger:hover { text-decoration: underline; }
.vo-plneni-table input[type=date], .vo-plneni-table input[type=text] { width: 95%; font-size: 13px; padding: 3px 6px; }

/* Frontend shortcode */
.vo-sc-block { margin: 24px 0; }
.vo-sc-heading { font-size: 1.2em; margin-bottom: 12px; }
.vo-sc-grid { display: flex; flex-wrap: wrap; gap: 16px; }
.vo-sc-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; min-width: 200px; max-width: 280px; flex: 1; }
.vo-sc-name { margin: 0 0 10px; font-size: 1.05em; color: #1d4ed8; }
.vo-sc-odborka { margin-bottom: 10px; }
.vo-sc-odborka-name { font-weight: 600; font-size: 13px; margin-bottom: 3px; }
.vo-sc-tasks { font-size: 12px; color: #555; margin-top: 4px; }
.vo-sc-task { margin: 2px 0; }
.vo-sc-date { color: #888; }
</style>';
	}
}

new VlcciOdborky();
