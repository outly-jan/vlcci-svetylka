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
		add_action( 'admin_menu',  [ $this, 'admin_menu' ] );
		add_action( 'admin_init',  [ $this, 'handle_post' ] );
		add_action( 'wp_head',     [ $this, 'inject_css' ] );
		add_action( 'admin_head',  [ $this, 'inject_css' ] );
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
			typ enum('vlcata','svetlusky') NOT NULL,
			nazev varchar(200) NOT NULL,
			min_ukolu tinyint NOT NULL DEFAULT 8,
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
				"SELECT * FROM {$wpdb->prefix}vo_odborky WHERE typ=%s ORDER BY nazev", $s->typ
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

		echo '<table class="vo-table widefat" style="margin-bottom:16px"><thead><tr><th>Typ</th><th>Název</th><th>Min. úkolů</th><th>Úkolů</th><th></th></tr></thead><tbody>';
		foreach ( $odborky as $o ) {
			$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}vo_ukoly WHERE odborka_id=%d", $o->id ) );
			echo '<tr>';
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
		$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE typ=%s ORDER BY nazev", $typ ) ) ?: [];

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
			echo '<a href="' . esc_url( $url ) . '" class="' . $cls . '">' . esc_html( $o->nazev );
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
		echo '<h2>' . esc_html( $dite->prezdivka ) . ' — ' . esc_html( $odborka->nazev ) . '</h2>';
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
			$odborky = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}vo_odborky WHERE typ=%s ORDER BY nazev", $d->typ ) ) ?: [];
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
			echo '<h2>' . esc_html( $o->nazev ) . ' <small>(' . esc_html( $this->typ_label( $o->typ ) ) . ')</small></h2>';
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
				"SELECT * FROM {$wpdb->prefix}vo_odborky WHERE typ=%s ORDER BY nazev", $s->typ
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
					echo '<div class="vo-sc-odborka-name">' . esc_html( $o->nazev );
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
