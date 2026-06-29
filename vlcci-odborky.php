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
		add_action( 'template_redirect',  [ $this, 'handle_app_post' ] );
		add_shortcode( 'vlcci_app',       [ $this, 'shortcode_app' ] );
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
			datum_splneni date DEFAULT NULL,
			poznamka text,
			vedouci_id int NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY dite_ukol (dite_id, ukol_id)
		) $c;" );
		// Migrace: uvolni NOT NULL na datum_splneni pokud je to ještě třeba
		$col = $wpdb->get_row( "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$wpdb->prefix}vo_plneni' AND COLUMN_NAME='datum_splneni'" );
		if ( $col && $col->IS_NULLABLE === 'NO' ) {
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}vo_plneni MODIFY datum_splneni date DEFAULT NULL" );
		}

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

	private function is_vedouci(): bool {
		if ( ! is_user_logged_in() ) return false;
		if ( $this->is_admin() ) return true;
		$user = wp_get_current_user();
		return in_array( 'vedouc_vlat_a_svtluek', (array) $user->roles, true );
	}

	private function require_author(): void {
		if ( ! $this->is_vedouci() ) wp_die( 'Přístup odepřen.' );
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
			        p.id AS plneni_id, p.datum_splneni, p.poznamka, p.vedouci_id
			 FROM {$wpdb->prefix}vo_ukoly u
			 LEFT JOIN {$wpdb->prefix}vo_plneni p ON p.ukol_id = u.id AND p.dite_id = %d
			 WHERE u.odborka_id = %d
			 ORDER BY u.poradi", $dite_id, $odborka_id
		) ) ?: [];
		$min  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT min_ukolu FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id
		) ) ?: 8;
		$done = count( array_filter( $ukoly, fn( $u ) => $u->plneni_id !== null ) );
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
		// Při přesunu ověř oprávnění i k původní šestce
		if ( $id ) {
			$orig = $wpdb->get_var( $wpdb->prepare( "SELECT sestka_id FROM {$wpdb->prefix}vo_deti WHERE id=%d", $id ) );
			if ( $orig && ! $this->can_edit_sestka( (int) $orig ) ) wp_die( 'Přístup odepřen.' );
		}
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
		$wp_users = get_users( [ 'role__in' => [ 'administrator', 'vedouc_vlat_a_svtluek' ], 'orderby' => 'display_name' ] );

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
			if ( $edit_d ) {
				echo '<input type="hidden" name="dite_id" value="' . $edit_d->id . '">';
				echo '<label>Šestka/roj: <select name="sestka_id">';
				foreach ( $all_sestky as $s ) {
					if ( ! $this->can_edit_sestka( (int) $s->id ) ) continue;
					echo '<option value="' . $s->id . '"' . selected( (int) $edit_d->sestka_id, (int) $s->id, false ) . '>' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev ) . '</option>';
				}
				echo '</select></label>';
			} else {
				echo '<input type="hidden" name="sestka_id" value="' . $sestka_id . '">';
			}
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

	// ── FRONTEND APP ─────────────────────────────────────────────────────────

	private function app_url( string $page = 'dashboard', array $extra = [] ): string {
		global $post;
		$base  = get_permalink( $post ) ?: home_url( '/' );
		$clean = remove_query_arg( [ 'vo', 'dite_id', 'odborka_id', 'sestka_id', 'oddil_id', 'edit_id', 'edit_o', 'edit_s', 'ukol_id' ], $base );
		return add_query_arg( array_merge( [ 'vo' => $page ], $extra ), $clean );
	}

	private function app_set_flash( string $msg, string $type = 'success' ): void {
		set_transient( 'vo_app_flash_' . get_current_user_id(), compact( 'msg', 'type' ), 60 );
	}

	private function app_render_flash(): string {
		$uid = get_current_user_id();
		$t   = get_transient( 'vo_app_flash_' . $uid );
		delete_transient( 'vo_app_flash_' . $uid );
		if ( ! $t ) return '';
		$cls = $t['type'] === 'error' ? 'voa-alert-error' : 'voa-alert-success';
		return '<div class="voa-alert ' . $cls . '">' . esc_html( $t['msg'] ) . '</div>';
	}

	private function app_redirect( string $base_url, string $page, array $extra = [] ): void {
		$clean = remove_query_arg( [ 'vo', 'dite_id', 'odborka_id', 'sestka_id', 'oddil_id', 'edit_id', 'edit_o', 'edit_s', 'ukol_id' ], $base_url );
		wp_safe_redirect( add_query_arg( array_merge( [ 'vo' => $page ], $extra ), $clean ) );
		exit;
	}

	private function app_nonce( string $action ): string {
		return wp_nonce_field( 'vo_app_' . $action, '_wpnonce', true, false );
	}

	private function app_base_field(): string {
		global $post;
		$base = get_permalink( $post ) ?: home_url( '/' );
		return '<input type="hidden" name="_vo_app_base" value="' . esc_attr( $base ) . '">';
	}

	public function handle_app_post(): void {
		if ( empty( $_POST['_vo_app_action'] ) ) return;
		if ( ! $this->is_vedouci() ) return;
		$action = sanitize_key( $_POST['_vo_app_action'] );
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'vo_app_' . $action ) ) wp_die( 'Neplatný bezpečnostní token.' );
		$base = esc_url_raw( wp_unslash( $_POST['_vo_app_base'] ?? '' ) ) ?: home_url( '/' );
		switch ( $action ) {
			case 'save_plneni':   $this->app_do_save_plneni( $base );   break;
			case 'save_dite':     $this->app_do_save_dite( $base );     break;
			case 'delete_dite':   $this->app_do_delete_dite( $base );   break;
			case 'save_oddil':    $this->app_do_save_oddil( $base );    break;
			case 'delete_oddil':  $this->app_do_delete_oddil( $base );  break;
			case 'save_sestka':   $this->app_do_save_sestka( $base );   break;
			case 'delete_sestka': $this->app_do_delete_sestka( $base ); break;
			case 'save_vedouci':         $this->app_do_save_vedouci( $base );         break;
			case 'delete_plneni_odborka':  $this->app_do_delete_plneni_odborka( $base );  break;
			case 'pridat_bez_data':        $this->app_do_pridat_bez_data( $base );        break;
		}
	}

	private function app_do_save_plneni( string $base ): void {
		global $wpdb;
		$dite_id    = intval( $_POST['dite_id'] ?? 0 );
		$odborka_id = intval( $_POST['odborka_id'] ?? 0 );
		$d = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $dite_id ) );
		if ( ! $d || ! $this->can_edit_sestka( (int) $d->sestka_id ) ) wp_die( 'Přístup odepřen.' );
		$ukoly      = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $odborka_id ) ) ?: [];
		$current_uid = get_current_user_id();
		foreach ( $ukoly as $u ) {
			$datum      = sanitize_text_field( $_POST[ 'datum_' . $u->id ] ?? '' );
			$pozn       = sanitize_textarea_field( $_POST[ 'pozn_' . $u->id ] ?? '' );
			$vid_raw    = intval( $_POST[ 'vedouci_' . $u->id ] ?? 0 );
			$vedouci_id = $vid_raw > 0 ? $vid_raw : $current_uid;
			if ( $datum ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}vo_plneni (dite_id,ukol_id,datum_splneni,poznamka,vedouci_id) VALUES (%d,%d,%s,%s,%d)
					 ON DUPLICATE KEY UPDATE datum_splneni=%s,poznamka=%s,vedouci_id=%d",
					$dite_id, $u->id, $datum, $pozn, $vedouci_id, $datum, $pozn, $vedouci_id
				) );
			} else {
				$wpdb->delete( "{$wpdb->prefix}vo_plneni", [ 'dite_id' => $dite_id, 'ukol_id' => $u->id ] );
			}
		}
		$this->app_set_flash( 'Plnění uloženo.' );
		$this->app_redirect( $base, 'plneni', [ 'dite_id' => $dite_id, 'odborka_id' => $odborka_id ] );
	}

	private function app_do_pridat_bez_data( string $base ): void {
		global $wpdb;
		$dite_id    = intval( $_POST['dite_id'] ?? 0 );
		$odborka_id = intval( $_POST['odborka_id'] ?? 0 );
		$d = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $dite_id ) );
		if ( ! $d || ! $this->can_edit_sestka( (int) $d->sestka_id ) ) wp_die( 'Přístup odepřen.' );
		$ukoly      = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $odborka_id ) ) ?: [];
		$vedouci_id = get_current_user_id();
		foreach ( $ukoly as $u ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}vo_plneni (dite_id,ukol_id,datum_splneni,poznamka,vedouci_id) VALUES (%d,%d,NULL,'',%d)
				 ON DUPLICATE KEY UPDATE vedouci_id=vedouci_id",
				$dite_id, $u->id, $vedouci_id
			) );
		}
		$this->app_set_flash( 'Odborka přidělena (bez data).' );
		$this->app_redirect( $base, 'plneni', [ 'dite_id' => $dite_id, 'odborka_id' => $odborka_id ] );
	}

	private function app_do_delete_plneni_odborka( string $base ): void {
		global $wpdb;
		$dite_id    = intval( $_POST['dite_id'] ?? 0 );
		$odborka_id = intval( $_POST['odborka_id'] ?? 0 );
		$d = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $dite_id ) );
		if ( ! $d || ! $this->can_edit_sestka( (int) $d->sestka_id ) ) wp_die( 'Přístup odepřen.' );
		$ukoly = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $odborka_id ) ) ?: [];
		foreach ( $ukoly as $ukol_id ) {
			$wpdb->delete( "{$wpdb->prefix}vo_plneni", [ 'dite_id' => $dite_id, 'ukol_id' => $ukol_id ] );
		}
		$this->app_set_flash( 'Plnění odborky bylo smazáno.' );
		$this->app_redirect( $base, 'plneni', [ 'dite_id' => $dite_id ] );
	}

	private function app_do_save_dite( string $base ): void {
		global $wpdb;
		$id        = intval( $_POST['dite_id'] ?? 0 );
		$sestka_id = intval( $_POST['sestka_id'] ?? 0 );
		if ( ! $this->can_edit_sestka( $sestka_id ) ) wp_die( 'Přístup odepřen.' );
		if ( $id ) {
			$orig = (int) $wpdb->get_var( $wpdb->prepare( "SELECT sestka_id FROM {$wpdb->prefix}vo_deti WHERE id=%d", $id ) );
			if ( $orig && ! $this->can_edit_sestka( $orig ) ) wp_die( 'Přístup odepřen.' );
		}
		$jmeno     = sanitize_text_field( $_POST['jmeno'] ?? '' );
		$prijmeni  = sanitize_text_field( $_POST['prijmeni'] ?? '' );
		$prezdivka = sanitize_text_field( $_POST['prezdivka'] ?? '' );
		$aktivni   = isset( $_POST['aktivni'] ) ? 1 : 0;
		if ( ! $jmeno || ! $prijmeni || ! $prezdivka ) {
			$this->app_set_flash( 'Vyplňte jméno, příjmení i přezdívku.', 'error' );
			$this->app_redirect( $base, 'deti', $id ? [ 'edit_id' => $id ] : [] );
		}
		$data = compact( 'sestka_id', 'jmeno', 'prijmeni', 'prezdivka', 'aktivni' );
		if ( $id ) {
			$wpdb->update( "{$wpdb->prefix}vo_deti", $data, [ 'id' => $id ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}vo_deti", $data );
		}
		$this->app_set_flash( 'Dítě uloženo.' );
		$this->app_redirect( $base, 'deti', [ 'sestka_id' => $sestka_id ] );
	}

	private function app_do_delete_dite( string $base ): void {
		global $wpdb;
		$id = intval( $_POST['dite_id'] ?? 0 );
		$d  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $id ) );
		if ( ! $d || ! $this->can_edit_sestka( (int) $d->sestka_id ) ) wp_die( 'Přístup odepřen.' );
		$wpdb->delete( "{$wpdb->prefix}vo_deti", [ 'id' => $id ] );
		$this->app_set_flash( 'Dítě smazáno.' );
		$this->app_redirect( $base, 'deti', [ 'sestka_id' => $d->sestka_id ] );
	}

	private function app_do_save_oddil( string $base ): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id    = intval( $_POST['oddil_id'] ?? 0 );
		$nazev = sanitize_text_field( $_POST['nazev'] ?? '' );
		$typ   = in_array( $_POST['typ'] ?? '', [ 'vlcata', 'svetlusky' ] ) ? $_POST['typ'] : 'vlcata';
		if ( ! $nazev ) { $this->app_set_flash( 'Vyplňte název.', 'error' ); $this->app_redirect( $base, 'oddily' ); }
		if ( $id ) { $wpdb->update( "{$wpdb->prefix}vo_oddily", compact( 'nazev', 'typ' ), [ 'id' => $id ] ); }
		else        { $wpdb->insert( "{$wpdb->prefix}vo_oddily", compact( 'nazev', 'typ' ) ); }
		$this->app_set_flash( 'Oddíl uložen.' );
		$this->app_redirect( $base, 'oddily' );
	}

	private function app_do_delete_oddil( string $base ): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}vo_oddily", [ 'id' => intval( $_POST['oddil_id'] ?? 0 ) ] );
		$this->app_set_flash( 'Oddíl smazán.' );
		$this->app_redirect( $base, 'oddily' );
	}

	private function app_do_save_sestka( string $base ): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id       = intval( $_POST['sestka_id'] ?? 0 );
		$oddil_id = intval( $_POST['oddil_id'] ?? 0 );
		$nazev    = sanitize_text_field( $_POST['nazev'] ?? '' );
		if ( ! $nazev ) { $this->app_set_flash( 'Vyplňte název.', 'error' ); $this->app_redirect( $base, 'oddily', [ 'oddil_id' => $oddil_id ] ); }
		if ( $id ) { $wpdb->update( "{$wpdb->prefix}vo_sestky", compact( 'nazev', 'oddil_id' ), [ 'id' => $id ] ); }
		else        { $wpdb->insert( "{$wpdb->prefix}vo_sestky", compact( 'nazev', 'oddil_id' ) ); }
		$this->app_set_flash( 'Šestka/roj uložena.' );
		$this->app_redirect( $base, 'oddily', [ 'oddil_id' => $oddil_id ] );
	}

	private function app_do_delete_sestka( string $base ): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$id = intval( $_POST['sestka_id'] ?? 0 );
		$s  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_sestky WHERE id=%d", $id ) );
		$wpdb->delete( "{$wpdb->prefix}vo_sestky", [ 'id' => $id ] );
		$this->app_set_flash( 'Šestka/roj smazána.' );
		$this->app_redirect( $base, 'oddily', [ 'oddil_id' => $s->oddil_id ?? 0 ] );
	}

	private function app_do_save_vedouci( string $base ): void {
		if ( ! $this->is_admin() ) wp_die( 'Přístup odepřen.' );
		global $wpdb;
		$sestka_id = intval( $_POST['sestka_id'] ?? 0 );
		$oddil_id  = intval( $_POST['oddil_id'] ?? 0 );
		$wpdb->delete( "{$wpdb->prefix}vo_vedouci", [ 'sestka_id' => $sestka_id ] );
		foreach ( array_filter( array_map( 'intval', (array) ( $_POST['user_ids'] ?? [] ) ) ) as $uid ) {
			$wpdb->replace( "{$wpdb->prefix}vo_vedouci", [ 'sestka_id' => $sestka_id, 'user_id' => $uid ] );
		}
		$this->app_set_flash( 'Vedoucí uloženi.' );
		$this->app_redirect( $base, 'oddily', [ 'oddil_id' => $oddil_id ] );
	}

	// ── APP SHORTCODE & ROUTING ───────────────────────────────────────────────

	public function shortcode_app( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="voa-wrap"><div class="voa-menu"><span style="padding:8px 12px;font-size:13px;color:#555">Odborky</span></div><div class="voa-login-box"><p>Pro přístup k aplikaci se přihlaste.</p><a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="voa-btn voa-btn-primary">Přihlásit se</a></div></div>';
		}
		if ( ! $this->is_vedouci() ) {
			return '<div class="voa-wrap"><div class="voa-menu"></div><div class="voa-content"><div class="voa-alert voa-alert-error">Tento plugin je určený jen pro vedoucí vlčat a světlušek — a to ty nejsi. Díky za pochopení.</div></div></div>';
		}
		$page = sanitize_key( $_GET['vo'] ?? 'dashboard' );
		ob_start();
		echo '<script>
(function(){
  var key="voa_scroll";
  var y=sessionStorage.getItem(key);
  if(y!==null){window.scrollTo(0,parseInt(y,10));sessionStorage.removeItem(key);}
  document.addEventListener("submit",function(e){
    if(e.target.closest(".voa-wrap")){sessionStorage.setItem(key,window.scrollY);}
  });
})();
</script>';
		echo '<div class="voa-wrap">';
		echo $this->app_nav( $page );
		echo '<div class="voa-content">';
		echo $this->app_render_flash();
		switch ( $page ) {
			case 'plneni':        $this->app_page_plneni();       break;
			case 'dite':          $this->app_page_dite();          break;
			case 'po_detech':     $this->app_page_po_detech();     break;
			case 'po_odborkach':  $this->app_page_po_odborkach();  break;
			case 'deti':          $this->app_page_deti();          break;
			case 'oddily':        $this->app_page_oddily();        break;
			case 'filtr':         $this->app_page_filtr();         break;
			case 'napoveda':      $this->app_page_napoveda();      break;
			default:              $this->app_page_dashboard();     break;
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	private function app_nav( string $active ): string {
		$items = [
			'dashboard'    => '🏠 Přehled',
			'plneni'       => '✏️ Plnění',
			'po_detech'    => '👤 Po dětech',
			'po_odborkach' => '🏅 Po odborkách',
			'deti'         => '👶 Správa dětí',
			'napoveda'     => '❓ Nápověda',
		];
		if ( $this->is_admin() ) {
			$items['oddily'] = '🏕️ Oddíly';
			$items['filtr']  = '🔍 Filtr';
		}
		$html = '<nav class="voa-menu">';
		foreach ( $items as $key => $label ) {
			$cls   = $key === $active ? ' voa-active' : '';
			$html .= '<a href="' . esc_url( $this->app_url( $key ) ) . '" class="' . $cls . '">' . $label . '</a>';
		}
		$html .= '<span class="voa-menu-user">👤 ' . esc_html( wp_get_current_user()->display_name ) . '</span>';
		$html .= '</nav>';
		return $html;
	}

	// ── APP PAGES ─────────────────────────────────────────────────────────────

	private function app_page_napoveda(): void {
		echo '<h1 class="voa-page-title">Nápověda</h1>';
		echo '<div class="voa-help">';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">Co je tato aplikace?</h2>';
		echo '<p>Aplikace slouží k evidenci plnění vlčáckých a světluškovských odborek. Každé dítě může plnit různé odborky — každá odborka má sadu úkolů a ke splnění odborky je potřeba splnit stanovený minimální počet úkolů.</p>';
		echo '</div>';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">🏠 Přehled</h2>';
		echo '<p>Úvodní stránka po přihlášení. Zobrazuje přehled dětí ve tvé šestce / roji a jejich postup u jednotlivých odborek.</p>';
		echo '</div>';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">✏️ Plnění</h2>';
		echo '<p>Zde zaznamenáváš, které úkoly dítě splnilo.</p>';
		echo '<ol class="voa-help-steps">';
		echo '<li>Vyber šestku / roj a klikni na dítě.</li>';
		echo '<li>Klikni na odborku, které se chceš věnovat.</li>';
		echo '<li>U každého splněného úkolu vyplň <strong>datum</strong>, volitelně poznámku, a vyber vedoucího, který splnění uznal.</li>';
		echo '<li>Klikni <strong>Uložit plnění</strong>. Pokud datum smažeš a uložíš, záznam se odstraní.</li>';
		echo '</ol>';
		echo '<h3 class="voa-help-h3">✏️ Vyplnit víc úkolů najednou</h3>';
		echo '<p>Pokud chceš zadat stejné datum a vedoucího pro více úkolů najednou, klikni na tlačítko <em>Vyplnit víc úkolů najednou</em>. Zadej datum, vyber vedoucího, zaškrtni příslušné úkoly (nebo klikni <em>Označit všechny nesplněné</em>) a stiskni <em>Použít na označené</em>. Datum se propíše do vybraných políček — stále je potřeba nakonec kliknout <strong>Uložit plnění</strong>.</p>';
		echo '<h3 class="voa-help-h3">✅ Přidělit bez dat</h3>';
		echo '<p>Pokud dítě odborku splnilo ještě před zavedením této aplikace a nechceš dohledávat jednotlivá data, zaškrtni <em>Přidělit bez uvedení data — má ji z dřívějška</em> a klikni <em>Přidělit bez dat</em>. Všechny úkoly odborky se označí jako splněné bez data. Datum pak můžeš kdykoli doplnit ručně.</p>';
		echo '<h3 class="voa-help-h3">🗑 Smazat veškeré plnění odborky</h3>';
		echo '<p>Pokud jsi omylem přidělil/a odborku špatnému dítěti, najdeš dole červené tlačítko <em>Smazat veškeré plnění odborky</em>. Zobrazí se jen tehdy, pokud je u dítěte evidován alespoň jeden splněný úkol.</p>';
		echo '</div>';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">👤 Po dětech</h2>';
		echo '<p>Přehled všech dětí seřazených podle šestky / roje. Vidíš, které odborky každé dítě plní a jak daleko je. Kliknutím na dítě se dostaneš na detail jeho plnění.</p>';
		echo '</div>';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">🏅 Po odborkách</h2>';
		echo '<p>Přehled odborek — vidíš, které děti danou odborku plní a jak jsou daleko.</p>';
		echo '<p>Kliknutím na název odborky se dostaneš na seznam jejích úkolů. U každého úkolu je vidět, kolik dětí ho ještě nemá splněný.</p>';
		echo '<p>Kliknutím na konkrétní úkol zobrazíš seznam dětí, které ho <strong>nemají splněný</strong>. To se hodí třeba tehdy, když chceš vzít na výpravu děti, kterým daný úkol ještě chybí.</p>';
		echo '</div>';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">👶 Správa dětí</h2>';
		echo '<p>Zde spravuješ seznam dětí ve své šestce / roji.</p>';
		echo '<ul class="voa-help-list">';
		echo '<li><strong>Přidat dítě</strong> — vyplň jméno, příjmení a přezdívku.</li>';
		echo '<li><strong>Upravit</strong> — klikni na <em>Upravit</em> u daného dítěte. Lze změnit jméno i přesunout dítě do jiné šestky.</li>';
		echo '<li><strong>Aktivní / neaktivní</strong> — neaktivní děti se nezobrazují v přehledech plnění, ale jejich záznamy zůstávají zachovány.</li>';
		echo '<li><strong>Smazat</strong> — trvale odstraní dítě i veškeré záznamy o plnění.</li>';
		echo '</ul>';
		echo '</div>';

		echo '<div class="voa-help-section">';
		echo '<h2 class="voa-help-h2">Časté dotazy</h2>';
		echo '<dl class="voa-help-faq">';
		echo '<dt>Můžu upravit datum nebo vedoucího u již splněného úkolu?</dt>';
		echo '<dd>Ano — jednoduše přejdi do Plnění, najdi dítě a odborku a přepiš datum nebo vedoucího. Po uložení se záznamy aktualizují.</dd>';
		echo '<dt>Jak odeberu splnění jednoho konkrétního úkolu?</dt>';
		echo '<dd>Smaž datum u daného úkolu a klikni Uložit plnění. Prázdné datum = záznam se smaže.</dd>';
		echo '<dt>Odborka je splněná, ale já vidím jen část úkolů jako hotové — je to v pořádku?</dt>';
		echo '<dd>Ano — ke splnění odborky stačí minimální počet úkolů (většinou 8 z 10). Jakmile je splněn minimální počet, odborka se označí jako splněná.</dd>';
		echo '<dt>Přihlašuji se a vidím hlášku „Tento plugin je určený jen pro vedoucí vlčat a světlušek".</dt>';
		echo '<dd>Tvůj účet nemá přiřazenou roli vedoucího. Požádej administrátora, aby ti ji přidělil.</dd>';
		echo '</dl>';
		echo '</div>';

		echo '</div>';
	}

	private function app_page_dashboard(): void {
		global $wpdb;
		$sestky = $this->my_sestky();
		echo '<h1 class="voa-page-title">Přehled</h1>';
		if ( empty( $sestky ) ) {
			echo '<div class="voa-empty">Nemáte přiřazenu žádnou šestku. Kontaktujte administrátora.</div>';
			return;
		}
		foreach ( $sestky as $s ) {
			$label    = ucfirst( $this->sestka_label( $s->typ ) );
			$can_edit = $this->can_edit_sestka( (int) $s->id );
			echo '<div class="voa-card">';
			echo '<div class="voa-card-head">';
			echo '<h2 class="voa-card-title" style="margin-bottom:0;border-bottom:none">' . esc_html( $s->oddil_nazev . ' — ' . $label . ' ' . $s->nazev ) . '</h2>';
			if ( $can_edit ) echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'sestka_id' => $s->id ] ) ) . '" class="voa-btn voa-btn-sm voa-btn-white">✏️ Zapsat plnění</a>';
			echo '</div>';
			$deti    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1 ORDER BY prijmeni, jmeno", $s->id ) ) ?: [];
			$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $s->typ ) ) ?: [];
			if ( empty( $deti ) ) {
				echo '<div style="padding:16px 20px"><p class="voa-muted">Žádné aktivní děti.</p></div>';
			} else {
				echo '<div class="voa-children-grid">';
				foreach ( $deti as $d ) {
					$splneno = 0; $rozp = 0;
					foreach ( $odborky as $o ) { $p = $this->progress( (int)$d->id, (int)$o->id ); if ( $p['splneno'] ) $splneno++; elseif ( $p['done'] > 0 ) $rozp++; }
					echo '<a href="' . esc_url( $this->app_url( 'dite', [ 'dite_id' => $d->id ] ) ) . '" class="voa-child-card">';
					echo '<div class="voa-child-nickname">' . esc_html( $d->prezdivka ) . '</div>';
					echo '<div class="voa-child-name">' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . '</div>';
					echo '<div class="voa-child-stats">';
					if ( $splneno ) echo '<span class="voa-badge-count voa-badge-count--green">' . $splneno . ' splněno</span>';
					if ( $rozp )    echo '<span class="voa-badge-count voa-badge-count--orange">' . $rozp . ' rozp.</span>';
					echo '</div></a>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		if ( $this->is_admin() ) {
			$all     = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];
			$my_ids  = array_map( fn($s) => (int)$s->id, $sestky );
			$others  = array_filter( $all, fn($s) => ! in_array( (int)$s->id, $my_ids ) );
			if ( ! empty( $others ) ) {
				echo '<h3 class="voa-section-title">Ostatní šestky a roje</h3><div class="voa-sestky-list">';
				foreach ( $others as $s ) {
					$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1", $s->id ) );
					echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'sestka_id' => $s->id ] ) ) . '" class="voa-sestka-item"><strong>' . esc_html( $s->oddil_nazev . ' — ' . ucfirst( $this->sestka_label( $s->typ ) ) . ' ' . $s->nazev ) . '</strong><span class="voa-muted">' . $cnt . ' dětí</span></a>';
				}
				echo '</div>';
			}
		}
	}

	private function app_page_dite(): void {
		global $wpdb;
		$dite_id = intval( $_GET['dite_id'] ?? 0 );
		if ( ! $dite_id ) { echo '<div class="voa-empty">Vyberte dítě.</div>'; return; }
		$d = $wpdb->get_row( $wpdb->prepare(
			"SELECT d.*, s.nazev AS sestka_nazev, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_deti d
			 LEFT JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id WHERE d.id=%d", $dite_id
		) );
		if ( ! $d ) { echo '<div class="voa-empty">Dítě nenalezeno.</div>'; return; }
		$can_edit = $this->can_edit_sestka( (int) $d->sestka_id );
		echo '<div class="voa-page-header"><a href="' . esc_url( $this->app_url( 'dashboard' ) ) . '" class="voa-back">← Přehled</a>';
		echo '<h1 class="voa-page-title" style="margin:4px 0">' . esc_html( $d->prezdivka ) . '</h1>';
		echo '<p class="voa-muted">' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . ' — ' . esc_html( $d->oddil_nazev . ' / ' . $d->sestka_nazev ) . '</p></div>';
		$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $d->typ ) ) ?: [];
		echo '<div class="voa-odborky-grid">';
		foreach ( $odborky as $o ) {
			$p   = $this->progress( $dite_id, (int)$o->id );
			$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
			$cls = 'voa-odborka-card' . ( $p['splneno'] ? ' voa-odborka-card--done' : ( $p['done'] > 0 ? ' voa-odborka-card--partial' : '' ) );
			$url = $can_edit ? $this->app_url( 'plneni', [ 'dite_id' => $dite_id, 'odborka_id' => $o->id ] ) : '#';
			echo '<a href="' . esc_url( $url ) . '" class="' . $cls . '">';
			if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" class="voa-odborka-img" alt="">';
			echo '<div class="voa-odborka-name">' . esc_html( $o->nazev ) . '</div>';
			if ( $p['splneno'] ) echo '<div class="voa-splneno-badge">✓ Splněno</div>';
			elseif ( $p['done'] > 0 ) echo '<div class="voa-odborka-progress"><div class="voa-progress-bar-wrap"><div class="voa-progress-bar-fill voa-progress-bar-fill--orange" style="width:' . $pct . '%"></div></div><span class="voa-progress-text">' . $p['done'] . '/' . $p['total'] . '</span></div>';
			else echo '<div class="voa-odborka-empty">Nezahájeno</div>';
			echo '</a>';
		}
		echo '</div>';
	}

	private function app_page_plneni(): void {
		global $wpdb;
		$dite_id    = intval( $_GET['dite_id'] ?? 0 );
		$odborka_id = intval( $_GET['odborka_id'] ?? 0 );
		$sestka_id  = intval( $_GET['sestka_id'] ?? 0 );
		$my_sestky  = $this->my_sestky();
		$all_sestky = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];

		if ( ! $dite_id ) {
			if ( ! $sestka_id && ! empty( $my_sestky ) ) $sestka_id = (int)$my_sestky[0]->id;
			echo '<h1 class="voa-page-title">Plnění — vyber dítě</h1>';
			echo '<div class="voa-tabs" style="margin-bottom:20px">';
			foreach ( $all_sestky as $s ) {
				$cls = (int)$s->id === $sestka_id ? ' voa-tab--active' : '';
				echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'sestka_id' => $s->id ] ) ) . '" class="voa-tab' . $cls . '">' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev ) . '</a>';
			}
			echo '</div>';
			if ( $sestka_id ) {
				$sestka  = $wpdb->get_row( $wpdb->prepare( "SELECT s.*, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id WHERE s.id=%d", $sestka_id ) );
				$deti    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1 ORDER BY prijmeni, jmeno", $sestka_id ) ) ?: [];
				$odborky = $sestka ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $sestka->typ ) ) ?: [] : [];
				echo '<div class="voa-children-grid">';
				foreach ( $deti as $d ) {
					$spl = 0; $roz = 0;
					foreach ( $odborky as $o ) { $p = $this->progress( (int)$d->id, (int)$o->id ); if ( $p['splneno'] ) $spl++; elseif ( $p['done'] > 0 ) $roz++; }
					echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $d->id, 'sestka_id' => $sestka_id ] ) ) . '" class="voa-child-card">';
					echo '<div class="voa-child-nickname">' . esc_html( $d->prezdivka ) . '</div><div class="voa-child-name">' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . '</div>';
					echo '<div class="voa-child-stats">';
					if ( $spl ) echo '<span class="voa-badge-count voa-badge-count--green">' . $spl . ' splněno</span>';
					if ( $roz ) echo '<span class="voa-badge-count voa-badge-count--orange">' . $roz . ' rozp.</span>';
					echo '</div></a>';
				}
				if ( empty( $deti ) ) echo '<p class="voa-muted">Žádné aktivní děti.</p>';
				echo '</div>';
			}
			return;
		}

		$d = $wpdb->get_row( $wpdb->prepare(
			"SELECT d.*, s.nazev AS sestka_nazev, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_deti d
			 LEFT JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id WHERE d.id=%d", $dite_id
		) );
		if ( ! $d ) { echo '<div class="voa-empty">Dítě nenalezeno.</div>'; return; }
		$can_edit = $this->can_edit_sestka( (int) $d->sestka_id );

		echo '<div class="voa-page-header"><a href="' . esc_url( $this->app_url( 'plneni', [ 'sestka_id' => $d->sestka_id ] ) ) . '" class="voa-back voa-back--bold">← Zpět na šestku / roj</a>';
		echo '<h1 class="voa-page-title" style="margin:4px 0">' . esc_html( $d->prezdivka ) . '</h1>';
		echo '<p class="voa-muted">' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . ' — ' . esc_html( $d->sestka_nazev ) . '</p></div>';

		$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $d->typ ) ) ?: [];

		if ( ! $odborka_id ) {
			echo '<div class="voa-odborky-grid">';
			foreach ( $odborky as $o ) {
				$p   = $this->progress( $dite_id, (int)$o->id );
				$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
				$cls = 'voa-odborka-card' . ( $p['splneno'] ? ' voa-odborka-card--done' : ( $p['done'] > 0 ? ' voa-odborka-card--partial' : '' ) );
				echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $dite_id, 'odborka_id' => $o->id ] ) ) . '" class="' . $cls . '">';
				if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" class="voa-odborka-img" alt="">';
				echo '<div class="voa-odborka-name">' . esc_html( $o->nazev ) . '</div>';
				if ( $p['splneno'] ) echo '<div class="voa-splneno-badge">✓ Splněno</div>';
				elseif ( $p['done'] > 0 ) echo '<div class="voa-odborka-progress"><div class="voa-progress-bar-wrap"><div class="voa-progress-bar-fill voa-progress-bar-fill--orange" style="width:' . $pct . '%"></div></div><span class="voa-progress-text">' . $p['done'] . '/' . $p['total'] . '</span></div>';
				echo '</a>';
			}
			echo '</div>';
			return;
		}

		$odborka = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id ) );
		if ( ! $odborka ) { echo '<div class="voa-empty">Odborka nenalezena.</div>'; return; }
		$p   = $this->progress( $dite_id, $odborka_id );
		$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;

		echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $dite_id ] ) ) . '" class="voa-back voa-back--bold" style="display:block;margin-bottom:16px">← Zpět na odborky — ' . esc_html( $d->prezdivka ) . '</a>';
		echo '<div class="voa-plneni-header">';
		if ( $odborka->obrazek ) echo '<img src="' . esc_url( $this->img_url( $odborka->obrazek ) ) . '" class="voa-plneni-img" alt="">';
		echo '<div class="voa-plneni-info"><h2>' . esc_html( $odborka->nazev ) . '</h2><p class="voa-muted" style="margin:2px 0">' . esc_html( $d->prezdivka ) . '</p>';
		echo '<div class="voa-progress-big"><div class="voa-progress-bar-wrap voa-progress-bar-wrap--lg"><div class="voa-progress-bar-fill ' . ( $p['splneno'] ? 'voa-progress-bar-fill--green' : 'voa-progress-bar-fill--orange' ) . '" style="width:' . $pct . '%"></div></div>';
		echo '<span class="voa-progress-text">' . $p['done'] . '/' . $p['total'] . ' úkolů';
		if ( $p['splneno'] ) echo ' &nbsp;<strong class="voa-green">✓ SPLNĚNO</strong>';
		else echo ' <span class="voa-muted">(min. ' . $p['min'] . ')</span>';
		echo '</span></div></div></div>';

		if ( $can_edit ) {
			// Přidělit bez data — samostatný formulář PŘED hlavním formulářem (vnořené formy jsou zakázané)
			if ( $p['done'] === 0 ) {
				echo '<div class="voa-extra-actions voa-extra-actions--top">';
				echo '<form method="post">';
				echo $this->app_nonce( 'pridat_bez_data' ) . $this->app_base_field();
				echo '<input type="hidden" name="_vo_app_action" value="pridat_bez_data">';
				echo '<input type="hidden" name="dite_id" value="' . $dite_id . '">';
				echo '<input type="hidden" name="odborka_id" value="' . $odborka_id . '">';
				echo '<label class="voa-checkbox-label"><input type="checkbox" id="vo-bez-data-check"> Přidělit bez uvedení data — má ji z dřívějška</label>';
				echo '<button type="submit" id="vo-bez-data-btn" class="voa-btn voa-btn-secondary" onclick="if(!document.getElementById(\'vo-bez-data-check\').checked){alert(\'Napřed zaškrtni Přidělit bez uvedení data.\');return false;}return confirm(\'Přidělit odborku ' . esc_js( $odborka->nazev ) . ' pro ' . esc_js( $d->prezdivka ) . ' bez data?\')">✅ Přidělit bez dat</button>';
				echo '</form>';
				echo '</div>';
			}

			echo '<form method="post" class="voa-form" id="vo-plneni-form">';
			echo $this->app_nonce( 'save_plneni' ) . $this->app_base_field();
			echo '<input type="hidden" name="_vo_app_action" value="save_plneni"><input type="hidden" name="dite_id" value="' . $dite_id . '"><input type="hidden" name="odborka_id" value="' . $odborka_id . '">';
			$sestka_vedouci_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}vo_vedouci WHERE sestka_id=%d", $d->sestka_id
			) ) ?: [];
			$wp_users = $sestka_vedouci_ids
				? get_users( [ 'include' => $sestka_vedouci_ids, 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name' ] ] )
				: get_users( [ 'orderby' => 'display_name', 'fields' => [ 'ID', 'display_name' ] ] );

			// Hromadné zadání
			$users_opts = '';
			foreach ( $wp_users as $wu ) {
				$sel = (int)$wu->ID === get_current_user_id() ? ' selected' : '';
				$users_opts .= '<option value="' . (int)$wu->ID . '"' . $sel . '>' . esc_html( $wu->display_name ) . '</option>';
			}
			echo '<div class="voa-bulk-panel" id="vo-bulk-panel">';
			echo '<div class="voa-bulk-toggle"><button type="button" class="voa-btn voa-btn-secondary" id="vo-bulk-btn">✏️ Vyplnit víc úkolů najednou</button></div>';
			echo '<div class="voa-bulk-body" id="vo-bulk-body" style="display:none">';
			echo '<div class="voa-bulk-row">';
			echo '<label class="voa-bulk-label">Datum <input type="date" id="vo-bulk-date" class="voa-input-date"></label>';
			echo '<label class="voa-bulk-label">Uznal/a <select id="vo-bulk-vedouci" class="voa-input-vedouci">' . $users_opts . '</select></label>';
			echo '<button type="button" class="voa-btn voa-btn-primary" id="vo-bulk-apply">Použít na označené</button>';
			echo '</div>';
			echo '<div class="voa-bulk-checks" style="margin-top:8px">';
			echo '<label class="voa-bulk-check-all"><input type="checkbox" id="vo-bulk-all"> <strong>Označit všechny nesplněné</strong></label>';
			echo '</div></div></div>';
			echo '<script>
(function(){
  var btn=document.getElementById(\'vo-bulk-btn\');
  var body=document.getElementById(\'vo-bulk-body\');
  btn.addEventListener(\'click\',function(){body.style.display=body.style.display===\'none\'?\'\':\'none\';});
  document.getElementById(\'vo-bulk-all\').addEventListener(\'change\',function(){
    document.querySelectorAll(\'.vo-ukol-check\').forEach(function(cb){
      if(!cb.dataset.done||cb.dataset.done===\'0\') cb.checked=this.checked;
    }.bind(this));
  });
  document.getElementById(\'vo-bulk-apply\').addEventListener(\'click\',function(){
    var d=document.getElementById(\'vo-bulk-date\').value;
    var v=document.getElementById(\'vo-bulk-vedouci\').value;
    if(!d){alert(\'Zadej datum.\');return;}
    document.querySelectorAll(\'.vo-ukol-check:checked\').forEach(function(cb){
      var id=cb.dataset.ukol;
      document.querySelector(\'[name="datum_\'+id+\'"]\').value=d;
      document.querySelector(\'[name="vedouci_\'+id+\'"]\').value=v;
    });
    document.getElementById(\'vo-bulk-all\').checked=false;
    document.querySelectorAll(\'.vo-ukol-check\').forEach(function(cb){cb.checked=false;});
  });
})();
</script>';
		}

		echo '<div class="voa-ukoly-list">';
		foreach ( $p['ukoly'] as $u ) {
			$done     = $u->plneni_id !== null;
			$has_date = ! empty( $u->datum_splneni );
			echo '<div class="voa-ukol' . ( $done ? ' voa-ukol--done' : '' ) . '">';
			if ( $can_edit ) {
				echo '<div class="voa-ukol-check-wrap"><input type="checkbox" class="vo-ukol-check" data-ukol="' . (int)$u->id . '" data-done="' . ( $done ? '1' : '0' ) . '"></div>';
			}
			echo '<div class="voa-ukol-num">' . (int)$u->poradi . '</div>';
			echo '<div class="voa-ukol-body"><div class="voa-ukol-nazev">' . esc_html( $u->nazev ) . '</div>';
			if ( $can_edit ) {
				$sel_vid = $done && $u->vedouci_id ? (int)$u->vedouci_id : get_current_user_id();
				echo '<div class="voa-ukol-inputs"><label>Datum <input type="date" name="datum_' . $u->id . '" value="' . esc_attr( $u->datum_splneni ?? '' ) . '" class="voa-input-date"></label>';
				echo '<label>Poznámka <input type="text" name="pozn_' . $u->id . '" value="' . esc_attr( $u->poznamka ?? '' ) . '" class="voa-input-note" placeholder="volitelná"></label>';
				echo '<label>Uznal/a <select name="vedouci_' . $u->id . '" class="voa-input-vedouci">';
				foreach ( $wp_users as $wu ) {
					$sel = (int)$wu->ID === $sel_vid ? ' selected' : '';
					echo '<option value="' . (int)$wu->ID . '"' . $sel . '>' . esc_html( $wu->display_name ) . '</option>';
				}
				echo '</select></label></div>';
			} elseif ( $done ) {
				$vedouci_str = '';
				if ( $u->vedouci_id ) { $v = get_userdata( (int) $u->vedouci_id ); if ( $v ) $vedouci_str = ' · Uznal/a: ' . $v->display_name; }
				if ( $has_date ) {
					echo '<div class="voa-ukol-datum">' . esc_html( date_format( date_create( $u->datum_splneni ), 'd. m. Y' ) ) . ( $u->poznamka ? ' — ' . esc_html( $u->poznamka ) : '' ) . esc_html( $vedouci_str ) . '</div>';
				} else {
					echo '<div class="voa-ukol-datum voa-ukol-datum--bezdata">z dřívějška' . esc_html( $vedouci_str ) . '</div>';
				}
			}
			echo '</div></div>';
		}
		echo '</div>';

		if ( $can_edit ) {
			echo '<div class="voa-form-actions"><button type="submit" class="voa-btn voa-btn-primary">Uložit plnění</button></div></form>';
			echo '<div class="voa-extra-actions">';
			if ( $p['done'] > 0 ) {
				echo '<form method="post">';
				echo $this->app_nonce( 'delete_plneni_odborka' ) . $this->app_base_field();
				echo '<input type="hidden" name="_vo_app_action" value="delete_plneni_odborka">';
				echo '<input type="hidden" name="dite_id" value="' . $dite_id . '">';
				echo '<input type="hidden" name="odborka_id" value="' . $odborka_id . '">';
				echo '<button type="submit" class="voa-btn voa-btn-danger" onclick="return confirm(\'Opravdu smazat veškeré plnění odborky ' . esc_js( $odborka->nazev ) . ' pro ' . esc_js( $d->prezdivka ) . '?\')">🗑 Smazat veškeré plnění odborky</button>';
				echo '</form>';
			}
			echo '</div>';
		}
	}

	private function app_page_po_detech(): void {
		global $wpdb;
		$sestka_id_f = intval( $_GET['sestka_id'] ?? 0 );
		$sestky      = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];
		echo '<h1 class="voa-page-title">Přehled po dětech</h1>';
		echo '<div class="voa-tabs" style="margin-bottom:20px"><a href="' . esc_url( $this->app_url( 'po_detech' ) ) . '" class="voa-tab' . ( ! $sestka_id_f ? ' voa-tab--active' : '' ) . '">Vše</a>';
		foreach ( $sestky as $s ) echo '<a href="' . esc_url( $this->app_url( 'po_detech', [ 'sestka_id' => $s->id ] ) ) . '" class="voa-tab' . ( (int)$s->id === $sestka_id_f ? ' voa-tab--active' : '' ) . '">' . esc_html( $s->nazev ) . '</a>';
		echo '</div>';
		$where = $sestka_id_f ? $wpdb->prepare( 'AND d.sestka_id=%d', $sestka_id_f ) : '';
		$deti  = $wpdb->get_results( "SELECT d.*, s.nazev AS sestka_nazev, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_deti d LEFT JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id WHERE d.aktivni=1 $where ORDER BY o.nazev, s.nazev, d.prijmeni, d.jmeno" ) ?: [];
		$shown = 0;
		foreach ( $deti as $d ) {
			$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE (typ='oba' OR typ=%s) ORDER BY nazev", $d->typ ) ) ?: [];
			$active  = array_filter( $odborky, fn($o) => $this->progress( (int)$d->id, (int)$o->id )['done'] > 0 );
			if ( empty( $active ) ) continue;
			$shown++;
			echo '<div class="voa-card" style="margin-bottom:12px"><div class="voa-card-head"><a href="' . esc_url( $this->app_url( 'dite', [ 'dite_id' => $d->id ] ) ) . '" class="voa-dite-link"><strong>' . esc_html( $d->prezdivka ) . '</strong> <span class="voa-muted">' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . '</span></a><span class="voa-muted">' . esc_html( $d->oddil_nazev . ' / ' . $d->sestka_nazev ) . '</span></div>';
			echo '<div class="voa-odborky-row">';
			foreach ( $active as $o ) {
				$p   = $this->progress( (int)$d->id, (int)$o->id );
				$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
				echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $d->id, 'odborka_id' => $o->id ] ) ) . '" class="voa-odborka-mini' . ( $p['splneno'] ? ' voa-odborka-mini--done' : '' ) . '">';
				if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" style="width:28px;height:28px;object-fit:contain" alt="">';
				echo '<div>' . esc_html( $o->nazev ) . '</div>';
				if ( $p['splneno'] ) echo '<div class="voa-green" style="font-size:11px;font-weight:700">✓ Splněno</div>';
				else { echo '<div class="voa-progress-bar-wrap" style="width:100%;margin-top:2px"><div class="voa-progress-bar-fill voa-progress-bar-fill--orange" style="width:' . $pct . '%"></div></div><div style="font-size:11px">' . $p['done'] . '/' . $p['total'] . '</div>'; }
				echo '</a>';
			}
			echo '</div></div>';
		}
		if ( ! $shown ) echo '<div class="voa-empty">Žádná data k zobrazení.</div>';
	}

	private function app_page_po_odborkach(): void {
		global $wpdb;
		$odborka_id = intval( $_GET['odborka_id'] ?? 0 );
		$ukol_id    = intval( $_GET['ukol_id'] ?? 0 );

		// === Úroveň 3: detail úkolu — kdo ho nemá splněný ===
		if ( $odborka_id && $ukol_id ) {
			$odborka = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id ) );
			$ukol    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE id=%d AND odborka_id=%d", $ukol_id, $odborka_id ) );
			if ( ! $odborka || ! $ukol ) { echo '<div class="voa-empty">Nenalezeno.</div>'; return; }
			echo '<a href="' . esc_url( $this->app_url( 'po_odborkach', [ 'odborka_id' => $odborka_id ] ) ) . '" class="voa-back" style="display:block;margin-bottom:16px">← ' . esc_html( $odborka->nazev ) . '</a>';
			echo '<h1 class="voa-page-title">' . esc_html( $ukol->nazev ) . '</h1>';
			echo '<p class="voa-muted" style="margin-bottom:16px">Děti, které tento úkol nemají splněný</p>';
			$deti_chybi = $wpdb->get_results( $wpdb->prepare(
				"SELECT d.*, s.nazev AS sestka_nazev FROM {$wpdb->prefix}vo_deti d
				 LEFT JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id
				 WHERE d.aktivni=1
				   AND (d.typ=%s OR d.typ IS NULL OR d.typ='')
				   AND d.id NOT IN (
				       SELECT dite_id FROM {$wpdb->prefix}vo_plneni
				       WHERE ukol_id=%d AND datum_splneni IS NOT NULL AND datum_splneni != \'\'
				   )
				 ORDER BY s.nazev, d.prijmeni, d.jmeno",
				$odborka->typ === 'oba' ? 'vlci' : $odborka->typ, $ukol_id
			) ) ?: [];
			if ( $odborka->typ === 'oba' ) {
				$deti_chybi = $wpdb->get_results( $wpdb->prepare(
					"SELECT d.*, s.nazev AS sestka_nazev FROM {$wpdb->prefix}vo_deti d
					 LEFT JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id
					 WHERE d.aktivni=1
					   AND d.id NOT IN (
					       SELECT dite_id FROM {$wpdb->prefix}vo_plneni
					       WHERE ukol_id=%d AND datum_splneni IS NOT NULL AND datum_splneni != \'\'
					   )
					 ORDER BY s.nazev, d.prijmeni, d.jmeno",
					$ukol_id
				) ) ?: [];
			}
			if ( empty( $deti_chybi ) ) {
				echo '<div class="voa-empty">Všechny aktivní děti mají tento úkol splněný. 🎉</div>';
				return;
			}
			$cur_sestka = null;
			echo '<div class="voa-card">';
			foreach ( $deti_chybi as $d ) {
				if ( $d->sestka_nazev !== $cur_sestka ) {
					if ( $cur_sestka !== null ) echo '</div>';
					echo '<div class="voa-ukol-sestka-group"><span class="voa-muted" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">' . esc_html( $d->sestka_nazev ) . '</span></div><div>';
					$cur_sestka = $d->sestka_nazev;
				}
				$p = $this->progress( (int)$d->id, $odborka_id );
				echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $d->id, 'odborka_id' => $odborka_id ] ) ) . '" class="voa-progress-row">';
				echo '<span class="voa-progress-name"><strong>' . esc_html( $d->prezdivka ) . '</strong> <span class="voa-muted">' . esc_html( $d->prijmeni ) . '</span></span>';
				$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
				echo '<div class="voa-progress-bar-wrap voa-progress-bar-wrap--md"><div class="voa-progress-bar-fill voa-progress-bar-fill--orange" style="width:' . $pct . '%"></div></div>';
				echo '<span style="font-size:12px;white-space:nowrap">' . $p['done'] . '/' . $p['total'] . '</span>';
				echo '</a>';
			}
			echo '</div></div>';
			return;
		}

		// === Úroveň 2: detail odborky — seznam úkolů s počtem chybějících ===
		if ( $odborka_id ) {
			$odborka = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id ) );
			if ( ! $odborka ) { echo '<div class="voa-empty">Odborka nenalezena.</div>'; return; }
			echo '<a href="' . esc_url( $this->app_url( 'po_odborkach' ) ) . '" class="voa-back" style="display:block;margin-bottom:16px">← Přehled po odborkách</a>';
			echo '<div class="voa-plneni-header">';
			if ( $odborka->obrazek ) echo '<img src="' . esc_url( $this->img_url( $odborka->obrazek ) ) . '" class="voa-plneni-img" alt="">';
			echo '<div class="voa-plneni-info"><h1 class="voa-page-title" style="margin:0">' . esc_html( $odborka->nazev ) . '</h1>';
			echo '<p class="voa-muted">Klikni na úkol a uvidíš, komu chybí</p></div></div>';
			$ukoly = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d ORDER BY poradi", $odborka_id
			) ) ?: [];
			$celkem_aktivnich = (int) $wpdb->get_var( $wpdb->prepare(
				$odborka->typ === 'oba'
					? "SELECT COUNT(*) FROM {$wpdb->prefix}vo_deti WHERE aktivni=1"
					: "SELECT COUNT(*) FROM {$wpdb->prefix}vo_deti WHERE aktivni=1 AND typ=%s",
				$odborka->typ
			) );
			echo '<div class="voa-card"><div class="voa-ukoly-list">';
			foreach ( $ukoly as $u ) {
				$splneno_count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT dite_id) FROM {$wpdb->prefix}vo_plneni
					 WHERE ukol_id=%d AND datum_splneni IS NOT NULL AND datum_splneni != \'\'", $u->id
				) );
				$chybi = $celkem_aktivnich - $splneno_count;
				echo '<a href="' . esc_url( $this->app_url( 'po_odborkach', [ 'odborka_id' => $odborka_id, 'ukol_id' => $u->id ] ) ) . '" class="voa-ukol voa-ukol-link">';
				echo '<div class="voa-ukol-num">' . (int)$u->poradi . '</div>';
				echo '<div class="voa-ukol-body"><div class="voa-ukol-nazev">' . esc_html( $u->nazev ) . '</div></div>';
				echo '<div class="voa-ukol-chybi">';
				if ( $chybi > 0 ) echo '<span class="voa-badge-count voa-badge-count--orange">' . $chybi . ' chybí</span>';
				else echo '<span class="voa-badge-count voa-badge-count--green">všichni ✓</span>';
				echo '</div></a>';
			}
			echo '</div></div>';
			return;
		}

		// === Úroveň 1: přehled všech odborek ===
		$odborky = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_odborky ORDER BY typ, nazev" ) ?: [];
		echo '<h1 class="voa-page-title">Přehled po odborkách</h1>';
		foreach ( $odborky as $o ) {
			$deti = $wpdb->get_results( $wpdb->prepare(
				"SELECT DISTINCT d.*, s.nazev AS sestka_nazev, s.id AS sestka_id FROM {$wpdb->prefix}vo_deti d
				 JOIN {$wpdb->prefix}vo_plneni p ON p.dite_id=d.id JOIN {$wpdb->prefix}vo_ukoly u ON u.id=p.ukol_id AND u.odborka_id=%d
				 JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id WHERE d.aktivni=1 ORDER BY d.prijmeni, d.jmeno", $o->id
			) ) ?: [];
			if ( empty( $deti ) ) continue;
			usort( $deti, fn($a,$b) => $this->progress( (int)$b->id, (int)$o->id )['done'] <=> $this->progress( (int)$a->id, (int)$o->id )['done'] );
			echo '<div class="voa-po-odb-block">';
			echo '<div class="voa-po-odb-heading">';
			echo '<a href="' . esc_url( $this->app_url( 'po_odborkach', [ 'odborka_id' => $o->id ] ) ) . '" class="voa-po-odb-title">';
			if ( $o->obrazek ) echo '<img src="' . esc_url( $this->img_url( $o->obrazek ) ) . '" class="voa-po-odb-img" alt="">';
			echo esc_html( $o->nazev ) . '</a>';
			echo '<span class="voa-muted" style="font-size:12px">' . count( $deti ) . ' dětí</span>';
			echo '</div>';
			echo '<div class="voa-children-grid">';
			foreach ( $deti as $d ) {
				$p   = $this->progress( (int)$d->id, (int)$o->id );
				$pct = $p['total'] ? round( $p['done'] / $p['total'] * 100 ) : 0;
				$cls = $p['splneno'] ? ' voa-child-card--done' : '';
				echo '<a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $d->id, 'odborka_id' => $o->id ] ) ) . '" class="voa-child-card' . $cls . '">';
				echo '<div class="voa-child-nickname">' . esc_html( $d->prezdivka ) . '</div>';
				echo '<div class="voa-child-name">' . esc_html( $d->jmeno . ' ' . $d->prijmeni ) . '</div>';
				echo '<div class="voa-child-stats" style="margin-top:6px">';
				if ( $p['splneno'] ) {
					echo '<span class="voa-badge-count voa-badge-count--green">✓ Splněno</span>';
				} else {
					echo '<div class="voa-progress-bar-wrap" style="width:100%;margin-bottom:3px"><div class="voa-progress-bar-fill voa-progress-bar-fill--orange" style="width:' . $pct . '%"></div></div>';
					echo '<span class="voa-muted" style="font-size:11px">' . $p['done'] . ' / ' . $p['total'] . ' úkolů</span>';
				}
				echo '</div></a>';
			}
			echo '</div></div>';
		}
	}

	private function app_page_deti(): void {
		global $wpdb;
		$all_sestky = $wpdb->get_results( "SELECT s.*, o.nazev AS oddil_nazev, o.typ FROM {$wpdb->prefix}vo_sestky s LEFT JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id ORDER BY o.nazev, s.nazev" ) ?: [];
		$my_sestky  = $this->my_sestky();
		$sestka_id  = intval( $_GET['sestka_id'] ?? ( $my_sestky[0]->id ?? ( $all_sestky[0]->id ?? 0 ) ) );
		$edit_id    = intval( $_GET['edit_id'] ?? 0 );
		$edit_d     = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE id=%d", $edit_id ) ) : null;
		$can_edit   = $this->can_edit_sestka( $sestka_id );

		echo '<h1 class="voa-page-title">Správa dětí</h1>';
		echo '<div class="voa-tabs" style="margin-bottom:20px">';
		foreach ( $all_sestky as $s ) echo '<a href="' . esc_url( $this->app_url( 'deti', [ 'sestka_id' => $s->id ] ) ) . '" class="voa-tab' . ( (int)$s->id === $sestka_id ? ' voa-tab--active' : '' ) . '">' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev ) . '</a>';
		echo '</div>';

		$deti = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d ORDER BY prijmeni, jmeno", $sestka_id ) ) ?: [];
		echo '<div class="voa-card"><div style="overflow-x:auto"><table class="voa-table"><thead><tr><th>Příjmení</th><th>Jméno</th><th>Přezdívka</th><th>Aktivní</th><th></th></tr></thead><tbody>';
		foreach ( $deti as $d ) {
			echo '<tr' . ( $d->aktivni ? '' : ' style="opacity:.55"' ) . '><td>' . esc_html( $d->prijmeni ) . '</td><td>' . esc_html( $d->jmeno ) . '</td><td><strong>' . esc_html( $d->prezdivka ) . '</strong></td><td>' . ( $d->aktivni ? '<span class="voa-green">✓</span>' : '–' ) . '</td>';
			echo '<td class="voa-table-actions"><a href="' . esc_url( $this->app_url( 'dite', [ 'dite_id' => $d->id ] ) ) . '" class="voa-link">Detail</a>';
			if ( $can_edit ) {
				echo ' <a href="' . esc_url( $this->app_url( 'deti', [ 'sestka_id' => $sestka_id, 'edit_id' => $d->id ] ) ) . '" class="voa-link">Upravit</a>';
				echo ' <form style="display:inline" method="post">' . $this->app_nonce( 'delete_dite' ) . $this->app_base_field() . '<input type="hidden" name="_vo_app_action" value="delete_dite"><input type="hidden" name="dite_id" value="' . $d->id . '"><button class="voa-link voa-link-danger" onclick="return confirm(\'Smazat?\')">Smazat</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div></div>';

		if ( $can_edit ) {
			$edit_sestky = $this->is_admin() ? $all_sestky : array_filter( $all_sestky, fn($s) => $this->can_edit_sestka( (int)$s->id ) );
			echo '<div class="voa-card"><h3 class="voa-card-title">' . ( $edit_d ? '✏️ Upravit: ' . esc_html( $edit_d->prezdivka ) : '➕ Přidat dítě' ) . '</h3>';
			echo '<form method="post" class="voa-form-grid">';
			echo $this->app_nonce( 'save_dite' ) . $this->app_base_field();
			echo '<input type="hidden" name="_vo_app_action" value="save_dite">';
			if ( $edit_d ) echo '<input type="hidden" name="dite_id" value="' . $edit_d->id . '">';
			echo '<label>Jméno<input type="text" name="jmeno" value="' . esc_attr( $edit_d->jmeno ?? '' ) . '" class="voa-input" required></label>';
			echo '<label>Příjmení<input type="text" name="prijmeni" value="' . esc_attr( $edit_d->prijmeni ?? '' ) . '" class="voa-input" required></label>';
			echo '<label>Přezdívka<input type="text" name="prezdivka" value="' . esc_attr( $edit_d->prezdivka ?? '' ) . '" class="voa-input" required></label>';
			if ( $edit_d ) {
				echo '<label>Šestka/roj<select name="sestka_id" class="voa-input">';
				foreach ( $edit_sestky as $s ) echo '<option value="' . $s->id . '"' . selected( (int)$edit_d->sestka_id, (int)$s->id, false ) . '>' . esc_html( $s->oddil_nazev . ' — ' . $s->nazev ) . '</option>';
				echo '</select></label>';
			} else {
				echo '<input type="hidden" name="sestka_id" value="' . $sestka_id . '">';
			}
			echo '<label class="voa-checkbox" style="align-self:end"><input type="checkbox" name="aktivni" value="1"' . ( ( $edit_d->aktivni ?? 1 ) ? ' checked' : '' ) . '> Aktivní</label>';
			echo '<div class="voa-form-actions"><button type="submit" class="voa-btn voa-btn-primary">Uložit</button>';
			if ( $edit_d ) echo ' <a href="' . esc_url( $this->app_url( 'deti', [ 'sestka_id' => $sestka_id ] ) ) . '" class="voa-btn voa-btn-secondary">Zrušit</a>';
			echo '</div></form></div>';
		}
	}

	private function app_page_oddily(): void {
		if ( ! $this->is_admin() ) { echo '<div class="voa-empty">Přístup odepřen.</div>'; return; }
		global $wpdb;
		$oddil_id = intval( $_GET['oddil_id'] ?? 0 );
		$edit_o   = intval( $_GET['edit_o'] ?? 0 );
		$edit_s   = intval( $_GET['edit_s'] ?? 0 );
		$eo       = $edit_o ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_oddily WHERE id=%d", $edit_o ) ) : null;
		$es       = $edit_s ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_sestky WHERE id=%d", $edit_s ) ) : null;
		$oddily   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_oddily ORDER BY nazev" ) ?: [];
		$authors  = get_users( [ 'role__in' => [ 'administrator', 'vedouc_vlat_a_svtluek' ], 'orderby' => 'display_name' ] );

		echo '<h1 class="voa-page-title">Oddíly a šestky</h1>';
		echo '<div class="voa-tabs" style="margin-bottom:20px">';
		foreach ( $oddily as $o ) echo '<a href="' . esc_url( $this->app_url( 'oddily', [ 'oddil_id' => $o->id ] ) ) . '" class="voa-tab' . ( (int)$o->id === $oddil_id ? ' voa-tab--active' : '' ) . '">' . esc_html( $o->nazev ) . '</a>';
		echo '</div>';

		echo '<div class="voa-card"><h3 class="voa-card-title">' . ( $eo ? '✏️ Upravit oddíl' : '➕ Přidat oddíl' ) . '</h3>';
		echo '<form method="post" class="voa-form-row">';
		echo $this->app_nonce( 'save_oddil' ) . $this->app_base_field();
		echo '<input type="hidden" name="_vo_app_action" value="save_oddil">';
		if ( $eo ) echo '<input type="hidden" name="oddil_id" value="' . $eo->id . '">';
		echo '<input type="text" name="nazev" value="' . esc_attr( $eo->nazev ?? '' ) . '" class="voa-input" placeholder="Název oddílu" required>';
		echo '<select name="typ" class="voa-input"><option value="vlcata"' . selected( $eo->typ ?? '', 'vlcata', false ) . '>Vlčata</option><option value="svetlusky"' . selected( $eo->typ ?? '', 'svetlusky', false ) . '>Světlušky</option></select>';
		echo '<button type="submit" class="voa-btn voa-btn-primary">💾 Uložit oddíl</button>';
		if ( $eo ) echo ' <a href="' . esc_url( $this->app_url( 'oddily', [ 'oddil_id' => $oddil_id ] ) ) . '" class="voa-btn voa-btn-secondary">Zrušit</a>';
		echo '</form></div>';

		if ( ! $oddil_id ) return;
		$oddil  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_oddily WHERE id=%d", $oddil_id ) );
		$sestky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_sestky WHERE oddil_id=%d ORDER BY nazev", $oddil_id ) ) ?: [];

		echo '<div class="voa-card">';
		echo '<div class="voa-card-head"><h3 class="voa-card-title" style="margin-bottom:0;border-bottom:none">🏕️ Šestky / roje — ' . esc_html( $oddil->nazev ) . '</h3>';
		echo '<span class="voa-muted">' . $this->typ_label( $oddil->typ ) . '</span></div>';
		echo '<div style="padding:4px 0">';
		foreach ( $sestky as $s ) {
			$vedouci_ids = array_column( $wpdb->get_results( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}vo_vedouci WHERE sestka_id=%d", $s->id ) ) ?: [], 'user_id' );
			$cnt         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}vo_deti WHERE sestka_id=%d AND aktivni=1", $s->id ) );
			echo '<div class="voa-sestka-row"><div class="voa-sestka-info"><strong>' . esc_html( $s->nazev ) . '</strong> <span class="voa-muted">(' . $cnt . ' dětí)</span>';
			if ( ! empty( $vedouci_ids ) ) {
				$names = array_filter( array_map( fn($uid) => get_userdata($uid)->display_name ?? null, $vedouci_ids ) );
				echo '<br><span class="voa-muted" style="font-size:13px">Vedoucí: ' . esc_html( implode( ', ', $names ) ) . '</span>';
			}
			echo '</div><div class="voa-sestka-actions"><a href="' . esc_url( $this->app_url( 'oddily', [ 'oddil_id' => $oddil_id, 'edit_s' => $s->id ] ) ) . '" class="voa-link">Upravit</a>';
			echo '<form style="display:inline" method="post">' . $this->app_nonce( 'delete_sestka' ) . $this->app_base_field() . '<input type="hidden" name="_vo_app_action" value="delete_sestka"><input type="hidden" name="sestka_id" value="' . $s->id . '"><input type="hidden" name="oddil_id" value="' . $oddil_id . '"><button class="voa-link voa-link-danger" onclick="return confirm(\'Smazat šestku?\')">Smazat</button></form></div></div>';
			if ( $es && (int)$es->id === (int)$s->id ) {
				echo '<div class="voa-sestka-edit-form"><form method="post" class="voa-form-row">';
				echo $this->app_nonce( 'save_sestka' ) . $this->app_base_field() . '<input type="hidden" name="_vo_app_action" value="save_sestka"><input type="hidden" name="sestka_id" value="' . $s->id . '"><input type="hidden" name="oddil_id" value="' . $oddil_id . '">';
				echo '<input type="text" name="nazev" value="' . esc_attr( $s->nazev ) . '" class="voa-input"><button type="submit" class="voa-btn voa-btn-primary">Uložit název</button></form>';
				echo '<form method="post" style="margin-top:12px">' . $this->app_nonce( 'save_vedouci' ) . $this->app_base_field();
				echo '<input type="hidden" name="_vo_app_action" value="save_vedouci"><input type="hidden" name="sestka_id" value="' . $s->id . '"><input type="hidden" name="oddil_id" value="' . $oddil_id . '">';
				echo '<div style="font-size:13px;font-weight:600;margin-bottom:6px">Vedoucí:</div><div class="voa-checkboxes">';
				foreach ( $authors as $u ) {
					$chk = in_array( $u->ID, array_map('intval', $vedouci_ids) ) ? ' checked' : '';
					echo '<label style="display:flex;gap:5px;align-items:center;font-size:13px"><input type="checkbox" name="user_ids[]" value="' . $u->ID . '"' . $chk . '> ' . esc_html( $u->display_name ) . '</label>';
				}
				echo '</div><div class="voa-form-actions"><button type="submit" class="voa-btn voa-btn-primary">Uložit vedoucí</button> <a href="' . esc_url( $this->app_url( 'oddily', [ 'oddil_id' => $oddil_id ] ) ) . '" class="voa-btn voa-btn-secondary">Zrušit</a></div></form></div>';
			}
		}
		echo '<div style="border-top:1px solid #F0E8DC;padding-top:12px;margin-top:4px"><form method="post" class="voa-form-row">';
		echo $this->app_nonce( 'save_sestka' ) . $this->app_base_field() . '<input type="hidden" name="_vo_app_action" value="save_sestka"><input type="hidden" name="oddil_id" value="' . $oddil_id . '">';
		echo '<input type="text" name="nazev" class="voa-input" placeholder="Název nové šestky/roje" required><button type="submit" class="voa-btn voa-btn-primary">Přidat</button></form></div>';
		echo '</div></div>';

		echo '<div class="voa-card"><div style="padding:16px 20px;display:flex;gap:8px">';
		echo '<a href="' . esc_url( $this->app_url( 'oddily', [ 'oddil_id' => $oddil_id, 'edit_o' => $oddil_id ] ) ) . '" class="voa-btn voa-btn-secondary">Přejmenovat oddíl</a>';
		echo '<form method="post">' . $this->app_nonce( 'delete_oddil' ) . $this->app_base_field() . '<input type="hidden" name="_vo_app_action" value="delete_oddil"><input type="hidden" name="oddil_id" value="' . $oddil_id . '"><button type="submit" class="voa-btn voa-btn-danger" onclick="return confirm(\'Smazat celý oddíl?\')">Smazat oddíl</button></form>';
		echo '</div></div>';
	}

	private function app_page_filtr(): void {
		global $wpdb;
		$odborka_id = intval( $_GET['odborka_id'] ?? 0 );
		$ukol_id    = intval( $_GET['ukol_id'] ?? 0 );
		$odborky    = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}vo_odborky ORDER BY typ, nazev" ) ?: [];
		echo '<h1 class="voa-page-title">🔍 Filtr</h1><p class="voa-muted" style="margin-bottom:16px">Děti, které plní odborku, ale chybí jim konkrétní úkol.</p>';
		echo '<div class="voa-card"><div class="voa-form-row" style="margin-bottom:0"><div class="voa-form-group" style="margin-bottom:0"><label>Odborka</label><select class="voa-input" style="width:auto;min-width:180px" onchange="location.href=\'' . esc_js( $this->app_url( 'filtr' ) ) . '&odborka_id=\'+this.value"><option value="">— vyber —</option>';
		foreach ( $odborky as $o ) echo '<option value="' . $o->id . '"' . selected( $odborka_id, (int)$o->id, false ) . '>' . esc_html( $o->nazev ) . '</option>';
		echo '</select></label>';
		if ( $odborka_id ) {
			$ukoly = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d ORDER BY poradi", $odborka_id ) ) ?: [];
			echo '<div class="voa-form-group" style="margin-bottom:0"><label>Chybějící úkol</label><select class="voa-input" style="width:auto;min-width:220px" onchange="location.href=\'' . esc_js( $this->app_url( 'filtr', [ 'odborka_id' => $odborka_id ] ) ) . '&ukol_id=\'+this.value"><option value="">— vyber —</option>';
			foreach ( $ukoly as $u ) echo '<option value="' . $u->id . '"' . selected( $ukol_id, (int)$u->id, false ) . '>' . (int)$u->poradi . '. ' . esc_html( $u->nazev ) . '</option>';
			echo '</select></div>';
		}
		echo '</div></div>';
		if ( $odborka_id && $ukol_id ) {
			$vysledky = $wpdb->get_results( $wpdb->prepare(
				"SELECT DISTINCT d.*, s.nazev AS sestka_nazev, s.id AS sestka_id, o.nazev AS oddil_nazev
				 FROM {$wpdb->prefix}vo_deti d JOIN {$wpdb->prefix}vo_plneni p ON p.dite_id=d.id
				 JOIN {$wpdb->prefix}vo_ukoly u ON u.id=p.ukol_id AND u.odborka_id=%d
				 JOIN {$wpdb->prefix}vo_sestky s ON s.id=d.sestka_id JOIN {$wpdb->prefix}vo_oddily o ON o.id=s.oddil_id
				 WHERE d.aktivni=1 AND d.id NOT IN (SELECT p2.dite_id FROM {$wpdb->prefix}vo_plneni p2 WHERE p2.ukol_id=%d)
				 ORDER BY d.prijmeni, d.jmeno", $odborka_id, $ukol_id
			) ) ?: [];
			$ukol_obj = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_ukoly WHERE id=%d", $ukol_id ) );
			$odb_obj  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE id=%d", $odborka_id ) );
			echo '<div class="voa-card" style="margin-top:16px">';
			echo '<div class="voa-card-head"><h3 class="voa-card-title" style="margin-bottom:0;border-bottom:none">' . esc_html( $odb_obj->nazev ) . '</h3><span class="voa-muted">chybí: ' . esc_html( $ukol_obj->nazev ) . '</span></div>';
			if ( empty( $vysledky ) ) {
				echo '<div class="voa-alert voa-alert-success">✅ Všichni mají tento úkol splněný.</div>';
			} else {
				echo '<div style="overflow-x:auto"><table class="voa-table"><thead><tr><th>Přezdívka</th><th>Příjmení, Jméno</th><th>Oddíl / Šestka</th><th>Postup</th><th></th></tr></thead><tbody>';
				foreach ( $vysledky as $d ) {
					$pr  = $this->progress( (int)$d->id, $odborka_id );
					$pct = $pr['total'] ? round( $pr['done'] / $pr['total'] * 100 ) : 0;
					echo '<tr><td><strong>' . esc_html( $d->prezdivka ) . '</strong></td><td>' . esc_html( $d->prijmeni . ' ' . $d->jmeno ) . '</td><td>' . esc_html( $d->oddil_nazev . ' / ' . $d->sestka_nazev ) . '</td>';
					echo '<td style="min-width:120px"><div class="voa-progress-bar-wrap voa-progress-bar-wrap--md"><div class="voa-progress-bar-fill voa-progress-bar-fill--orange" style="width:' . $pct . '%"></div></div><span class="voa-progress-text">' . $pr['done'] . '/' . $pr['total'] . '</span></td>';
					echo '<td><a href="' . esc_url( $this->app_url( 'plneni', [ 'dite_id' => $d->id, 'odborka_id' => $odborka_id ] ) ) . '" class="voa-link">✏️ Plnění</a></td></tr>';
				}
				echo '</tbody></table></div>';
			}
			echo '</div>';
		}
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

/* ── Frontend App ── */
.voa-wrap{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;font-size:14px;color:#333;max-width:1000px;margin:0}
.voa-menu{display:flex;flex-wrap:wrap;gap:4px;border-bottom:3px solid #1a5c2a}
.voa-menu a{font-size:12px;padding:7px 13px;background:#f0f0f0;color:#444;border:1px solid #ccc;border-bottom:none;border-radius:4px 4px 0 0;white-space:nowrap;text-decoration:none;transition:background .15s;margin-bottom:-1px;display:inline-block}
.voa-menu a:hover{background:#e0e0e0;color:#333}
.voa-menu a.voa-active{background:#1a5c2a;color:#fff!important;border-color:#1a5c2a;font-weight:600}
.voa-menu-user{font-size:12px;color:#555;padding:7px 4px 7px 12px;margin-left:auto;align-self:flex-end}
.voa-content{padding:20px;background:#fff;border:1px solid #1a5c2a;border-top:none}
.voa-login-box{padding:60px 20px;text-align:center;background:#fff;border:1px solid #1a5c2a;border-top:none}
.voa-login-box p{margin-bottom:16px;font-size:15px;color:#555}
/* Alerts */
.voa-alert{padding:10px 14px;border-radius:3px;margin-bottom:16px;font-size:13px;border-left:4px solid}
.voa-alert-success{background:#f0faf0;border-color:#2e7d32;color:#1b5e20}
.voa-alert-error{background:#fff0f0;border-color:#d63638;color:#7b1c1e}
/* Buttons */
.voa-btn{display:inline-block;padding:6px 14px;font-size:13px;font-weight:500;border:none;border-radius:3px;cursor:pointer;text-decoration:none;line-height:1.6;transition:filter .15s;vertical-align:middle}
.voa-btn:hover{filter:brightness(.88)}
.voa-btn-primary{background:#1a5c2a;color:#fff!important}
.voa-btn-danger{background:#c0392b;color:#fff!important;border-color:#a93226}
.voa-extra-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid #e0e0e0}
.voa-extra-actions--top{margin-top:0;padding-top:0;border-top:none;margin-bottom:12px}
.voa-extra-actions form{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.voa-checkbox-label{font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.voa-ukol-datum--bezdata{font-style:italic;color:#888}
.voa-btn-secondary{background:#757575;color:#fff!important}
.voa-btn-white{background:#fff;color:#1a5c2a!important;border:1px solid #1a5c2a}
.voa-btn-sm{padding:3px 9px;font-size:12px}
/* Cards */
.voa-card{background:#fafafa;border:1px solid #ddd;border-radius:4px;padding:18px 20px;margin-bottom:20px}
.voa-card-title{margin:0 0 14px;padding-bottom:8px;border-bottom:2px solid #1a5c2a;color:#1a5c2a;font-size:15px;font-weight:600}
.voa-card-head{display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap}
.voa-card-head h2,.voa-card-head h3{margin:0;flex:1}
.voa-section-title{margin:16px 0 12px;font-size:16px;color:#222;font-weight:600}
.voa-page-header{margin-bottom:20px}
.voa-page-title{font-size:18px;color:#222;font-weight:600;margin:0 0 4px}
.voa-help{max-width:720px}
.voa-help-section{background:#fff;border:1px solid #dde8df;border-radius:6px;padding:18px 20px;margin-bottom:16px}
.voa-help-h2{font-size:15px;color:#1a5c2a;font-weight:700;margin:0 0 10px;padding-bottom:6px;border-bottom:1px solid #e0eee2}
.voa-help-h3{font-size:13px;color:#333;font-weight:700;margin:14px 0 4px}
.voa-help-section p{font-size:13px;color:#444;margin:0 0 8px;line-height:1.6}
.voa-help-steps{font-size:13px;color:#444;margin:0 0 8px;padding-left:20px;line-height:1.8}
.voa-help-list{font-size:13px;color:#444;margin:0 0 8px;padding-left:20px;line-height:1.8}
.voa-help-faq dt{font-size:13px;font-weight:700;color:#222;margin-top:12px}
.voa-help-faq dd{font-size:13px;color:#555;margin:3px 0 0 0;line-height:1.6}
/* Tabs (URL-based) */
.voa-tabs{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:16px;border-bottom:2px solid #ddd;padding-bottom:0}
.voa-tab{display:inline-block;font-size:12px;padding:6px 12px;background:#f5f5f5;color:#444;border:1px solid #ccc;border-bottom:none;border-radius:3px 3px 0 0;text-decoration:none;transition:background .15s;margin-bottom:-2px}
.voa-tab:hover{background:#e5e5e5;color:#222}
.voa-tab--active{background:#1a5c2a;color:#fff!important;border-color:#1a5c2a;font-weight:600}
/* Tables */
.voa-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:4px}
.voa-table th{background:#e8f2ea;color:#222;border:1px solid #b8d4be;padding:7px 10px;text-align:left;font-weight:600}
.voa-table td{border:1px solid #e0e0e0;padding:7px 10px;vertical-align:middle}
.voa-table tbody tr:hover td{background:#f5faf5}
.voa-table tr.voa-inactive td{opacity:.55}
.voa-table-actions{white-space:nowrap}
/* Forms */
.voa-form-group{margin-bottom:14px}
.voa-form-group>label,.voa-form-group label{display:block;font-weight:600;margin-bottom:4px;font-size:13px;color:#444}
.voa-form-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.voa-form-actions{margin-top:16px;display:flex;gap:8px;align-items:center}
.voa-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end}
.voa-input{width:100%;padding:6px 9px;border:1px solid #ccc;border-radius:3px;font-size:13px;box-sizing:border-box}
.voa-input:focus{outline:none;border-color:#1a5c2a;box-shadow:0 0 0 2px rgba(26,92,42,.15)}
.voa-checkboxes{display:flex;flex-wrap:wrap;gap:6px 16px;margin:6px 0}
.voa-checkbox{display:flex;gap:5px;align-items:center;font-size:13px;font-weight:normal!important;cursor:pointer}
/* Links & misc */
.voa-link{color:#1a5c2a;text-decoration:none;font-size:13px}
.voa-link:hover{text-decoration:underline}
.voa-link-danger{color:#d63638}
.voa-back{display:inline-block;color:#555;text-decoration:none;font-size:13px;margin-bottom:12px}
.voa-back:hover{color:#222;text-decoration:underline}
.voa-back--bold{font-size:14px;font-weight:600;color:#1a5c2a;background:#f0faf2;border:1px solid #b8d4be;border-radius:4px;padding:5px 12px}
.voa-back--bold:hover{background:#e0f0e5;color:#144a22;text-decoration:none}
.voa-muted{color:#666;font-size:13px}
.voa-green{color:#1a5c2a;font-weight:600}
.voa-empty{color:#888;font-style:italic;margin:12px 0;font-size:13px}
/* Children grid */
.voa-children-grid{display:flex;flex-wrap:wrap;gap:8px;padding:4px 0}
.voa-child-card{background:#fff;border:1px solid #ddd;border-radius:4px;padding:12px 14px;min-width:130px;max-width:180px;flex:0 1 160px;text-decoration:none;color:#333;transition:border-color .15s,box-shadow .15s;display:block}
.voa-child-card--done{border-color:#1a5c2a;background:#f4fbf6}
.voa-po-odb-block{margin-bottom:24px}
.voa-po-odb-heading{display:flex;align-items:center;gap:10px;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid #e0eee2}
.voa-po-odb-title{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:#1a5c2a;text-decoration:none;flex:1}
.voa-po-odb-title:hover{text-decoration:underline}
.voa-po-odb-img{width:28px;height:28px;object-fit:contain}
.voa-child-card:hover{border-color:#1a5c2a;box-shadow:0 2px 6px rgba(0,0,0,.07)}
.voa-child-nickname{font-weight:700;font-size:14px;color:#1a5c2a}
.voa-child-name{font-size:12px;color:#666;margin:2px 0 6px}
.voa-child-stats{display:flex;gap:5px;flex-wrap:wrap}
.voa-badge-count{display:inline-block;border-radius:3px;padding:1px 7px;font-size:11px;font-weight:600}
.voa-badge-count--green{background:#d1e7dd;color:#0a5b2e;border:1px solid #a3cfbb}
.voa-badge-count--orange{background:#fff3cd;color:#856404;border:1px solid #ffc107}
/* Badge grid */
.voa-odborky-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.voa-odborka-card{background:#fff;border:1px solid #ddd;border-radius:4px;padding:12px 8px;width:140px;text-align:center;text-decoration:none;color:#333;transition:border-color .15s;display:block}
.voa-odborka-card:hover{border-color:#1a5c2a}
.voa-odborka-card--done{border-color:#1a5c2a;background:#f0faf0}
.voa-odborka-card--partial{border-color:#f59e0b}
.voa-odborka-img{width:52px;height:52px;object-fit:contain;display:block;margin:0 auto 6px}
.voa-odborka-name{font-size:12px;font-weight:600;color:#333;line-height:1.3}
.voa-splneno-badge{display:inline-block;margin-top:4px;padding:2px 8px;background:#d1e7dd;color:#0a5b2e;border-radius:3px;font-size:11px;font-weight:700}
.voa-odborka-empty{font-size:11px;color:#999;margin-top:4px}
/* Progress */
.voa-progress-bar-wrap{background:#e0e0e0;border-radius:2px;height:6px;overflow:hidden;margin:4px 0}
.voa-progress-bar-wrap--md{height:8px;flex:1}
.voa-progress-bar-wrap--lg{height:10px;width:100%;max-width:280px}
.voa-progress-bar-fill{height:100%;border-radius:2px;transition:width .3s}
.voa-progress-bar-fill--green{background:#1a5c2a}
.voa-progress-bar-fill--orange{background:#f59e0b}
.voa-progress-text{font-size:12px;color:#555}
.voa-progress-big{display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap}
/* Plnění */
.voa-plneni-header{display:flex;gap:14px;align-items:flex-start;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #e0e0e0;flex-wrap:wrap}
.voa-plneni-img{width:60px;height:60px;object-fit:contain;flex-shrink:0}
.voa-plneni-info{flex:1;min-width:180px}
.voa-ukoly-list{border:1px solid #ddd;border-radius:4px;overflow:hidden;margin-bottom:16px}
.voa-ukol{display:flex;border-bottom:1px solid #eee}
.voa-ukol:last-child{border-bottom:none}
.voa-ukol--done{background:#f0faf0}
.voa-ukol-link{display:flex;align-items:center;text-decoration:none;color:inherit;border-bottom:1px solid #eee;transition:background .15s}
.voa-ukol-link:hover{background:#f5faf6}
.voa-ukol-chybi{padding:0 12px;white-space:nowrap}
.voa-ukol-sestka-group{padding:8px 12px 2px;background:#f5f5f5;border-bottom:1px solid #eee}
.voa-card-head-link{display:flex;align-items:center;gap:8px;text-decoration:none;color:inherit;flex:1}
.voa-ukol-num{width:34px;min-width:34px;background:#f5f5f5;display:flex;align-items:flex-start;justify-content:center;padding:10px 0;font-size:12px;color:#888;font-weight:600;border-right:1px solid #eee}
.voa-ukol--done .voa-ukol-num{background:#d1e7dd;color:#1a5c2a}
.voa-ukol-body{flex:1;padding:10px 12px}
.voa-ukol-nazev{font-size:13px;color:#333;margin-bottom:6px}
.voa-ukol-inputs{display:flex;gap:10px;flex-wrap:wrap}
.voa-ukol-inputs label{display:flex;flex-direction:column;gap:3px;font-size:12px;font-weight:600;color:#555;flex:1;min-width:120px}
.voa-input-date,.voa-input-note{padding:4px 7px;border:1px solid #ccc;border-radius:3px;font-size:12px}
.voa-input-date:focus,.voa-input-note:focus{outline:none;border-color:#1a5c2a}
.voa-ukol-datum{font-size:12px;color:#555;margin-top:4px}
.voa-ukol-vedouci{font-size:11px;color:#888;margin-top:2px}
.voa-input-vedouci{font-size:13px;padding:3px 6px;border:1px solid #b8d4be;border-radius:4px;background:#fff;color:#222;cursor:pointer}
.voa-bulk-panel{margin-bottom:12px}
.voa-bulk-body{background:#f5faf6;border:1px solid #b8d4be;border-radius:6px;padding:12px;margin-top:8px}
.voa-bulk-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end}
.voa-bulk-label{display:flex;flex-direction:column;gap:3px;font-size:12px;font-weight:600;color:#555}
.voa-bulk-check-all{font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px}
.voa-ukol-check-wrap{display:flex;align-items:flex-start;padding:12px 8px 0;min-width:28px}
.voa-ukol-check-wrap input{width:16px;height:16px;cursor:pointer;accent-color:#1a5c2a}
/* Dashboard rows */
.voa-sestky-list{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.voa-sestka-item{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#f8faf8;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;transition:border-color .15s}
.voa-sestka-item:hover{border-color:#1a5c2a}
.voa-sestka-row{padding:10px 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
.voa-sestka-row:last-child{border-bottom:none}
.voa-sestka-info{flex:1}
.voa-sestka-actions{display:flex;gap:10px;align-items:center;flex-shrink:0}
.voa-sestka-edit-form{background:#f0faf0;border:1px solid #b8d4be;border-radius:4px;padding:14px;margin:8px 0 4px}
/* Po dětech */
.voa-odborky-row{display:flex;flex-wrap:wrap;gap:6px;padding:8px 0 4px}
.voa-odborka-mini{display:flex;flex-direction:column;align-items:center;text-align:center;width:86px;padding:7px 5px;background:#fff;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#333;font-size:11px;transition:border-color .15s;gap:3px}
.voa-odborka-mini:hover{border-color:#1a5c2a}
.voa-odborka-mini--done{border-color:#1a5c2a;background:#f0faf0}
.voa-dite-link{text-decoration:none;color:#1a5c2a;font-weight:600}
/* Po odborkách */
.voa-children-progress{display:flex;flex-direction:column;gap:2px;margin-top:8px}
.voa-progress-row{display:flex;align-items:center;gap:10px;padding:5px 0;text-decoration:none;color:#333;border-bottom:1px solid #f0f0f0}
.voa-progress-row:last-child{border-bottom:none}
.voa-progress-row:hover{background:#f8faf8}
.voa-progress-name{width:130px;font-size:13px;flex-shrink:0}
@media(max-width:640px){
  .voa-menu{flex-direction:column;border-bottom:none;gap:2px}
  .voa-menu a{border-radius:3px;border-bottom:1px solid #ccc;margin-bottom:0}
  .voa-menu-user{margin-left:0;padding:4px 0}
  .voa-content{padding:14px 12px}
  .voa-odborka-card{width:120px}
  .voa-progress-name{width:90px}
}
</style>';
	}
}

new VlcciOdborky();
