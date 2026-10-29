<?php
/**
 * Local WordPress checks: text that visitors read on a generated form follows the site
 * language, even when the administrator works the dashboard in another language.
 */

declare(strict_types=1);

$wp_root = getenv( 'AISAAUTOPILOT_WP_ROOT' ) ?: 'C:\\xampp\\htdocs\\wp';
require $wp_root . '/wp-load.php';

use AISAAutoPilot\Core\SiteLanguage;
use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbFormConverter;
use AISAAutoPilot\Efb\EfbFormReview;
use AISAAutoPilot\Efb\EfbFormSkill;
use AISAAutoPilot\Providers\ProviderInterface;
use AISAAutoPilot\Skills\CreateFormSkill;
use AISAAutoPilot\Skills\FormPlanValidator;

$checks = array();
$check  = static function ( bool $ok, string $name ) use ( &$checks ): void {
	$checks[] = array( 'ok' => $ok, 'name' => $name );
};

/** A provider that records the system prompt and answers with an empty plan. */
final class AISAAutoPilot_Language_Probe implements ProviderInterface {
	public string $system = '';
	public function generate_structured_output( string $system_prompt, string $user_prompt ): array {
		$this->system = $system_prompt;
		return array( 'operations' => array() );
	}
	public function generate_text( string $system_prompt, string $user_prompt ): array {
		$this->system = $system_prompt;
		return array( 'content' => '', 'usage' => array( 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0 ) );
	}
	public function generate_embedding( string $text ): array {
		return array( 'embedding' => array(), 'usage' => array( 'prompt_tokens' => 0, 'total_tokens' => 0 ) );
	}
	public function health_check(): array {
		return array( 'ok' => true );
	}
}

$reload_translations = static function (): void {
	// A real request captures its original language when WordPress boots; this script
	// changes the user and site language afterwards, so the switcher starts over.
	$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
	$GLOBALS['wp_locale_switcher']->init();
	unload_textdomain( 'ai-site-assistant-autopilot' );
	EfbFormConverter::reset_texts();
	$mofile = plugin_dir_path( AISAAUTOPILOT_FILE ) . 'languages/ai-site-assistant-autopilot-' . determine_locale() . '.mo';
	if ( is_readable( $mofile ) ) {
		load_textdomain( 'ai-site-assistant-autopilot', $mofile );
	}
};

// A real `_locale=user` REST request: the administrator's own language comes from their
// profile, the site language from the `locale` filter (priority 5, so WordPress's own
// locale switcher at 10 still wins while a switch is active).
$original_user_id = get_current_user_id();
$admin_id         = wp_insert_user(
	array(
		'user_login' => 'aisa_lang_probe_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 24 ),
		'role'       => 'administrator',
		'locale'     => 'en_US',
	)
);
wp_set_current_user( (int) $admin_id );
$_GET['_locale']        = 'user';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$site_locale            = static fn (): string => 'fa_IR';
add_filter( 'locale', $site_locale, 5 );
$reload_translations();

$english_for_admin = __( 'Consent', 'ai-site-assistant-autopilot' );
$persian_for_site  = SiteLanguage::for_site( static fn (): string => __( 'Consent', 'ai-site-assistant-autopilot' ) );
$check(
	'Consent' === $english_for_admin && 'رضایت' === $persian_for_site && 'en_US' === determine_locale() && 'fa_IR' === get_locale(),
	'for_site() answers in the site language while the admin keeps the dashboard language'
);

$items = ( new EfbFormConverter() )->convert(
	array(
		'title'  => 'فرم تماس',
		'fields' => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => 'نام', 'required' => true, 'placeholder' => '', 'options' => array() ),
			array( 'key' => 'p2', 'type' => 'steps', 'label' => '', 'required' => false, 'placeholder' => '', 'options' => array() ),
			array( 'key' => 'msg', 'type' => 'textarea', 'label' => 'پیام', 'required' => false, 'placeholder' => '', 'options' => array() ),
		),
	)
);
$steps = array_values( array_filter( $items, static fn ( array $i ): bool => 'step' === $i['type'] ) );
$check(
	'ارسال' === $items[0]['button_single_text'] && 'بعدی' === $items[0]['button_Next_text']
	&& preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $items[0]['thank_you_message']['thankYou'] )
	&& preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $steps[1]['name'] ),
	'an Easy Form Builder form gets Persian buttons, thank-you lines and page titles on a Persian site'
);

$plan = ( new FormPlanValidator() )->validate(
	array(
		'form' => array(
			'title'  => 'فرم تماس',
			'fields' => array( array( 'key' => 'email', 'type' => 'email', 'label' => 'ایمیل', 'required' => true ) ),
		),
	)
);
$check(
	$plan->ok && 'ارسال' === $plan->plan['submit_button'] && preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $plan->plan['confirmation_message'] ),
	'a native form plan without a button text or confirmation gets Persian defaults'
);

$findings = EfbFormReview::review(
	array( array( 'id' => 'email', 'label' => 'ایمیل', 'type' => 'email', 'required' => true ) ),
	array()
);
$consent  = array_values( array_filter( $findings, static fn ( array $f ): bool => 'personal_data_without_consent' === $f['code'] ) );
$check(
	1 === count( $consent )
	&& 'رضایت' === $consent[0]['fix']['field']['label']
	&& preg_match( '/[\x{0600}-\x{06FF}]/u', (string) $consent[0]['fix']['field']['options'][0] )
	&& false !== strpos( (string) $consent[0]['message'], 'consent' ),
	'the review explains in the admin language and offers a consent checkbox in the site language'
);

$probe = new AISAAutoPilot_Language_Probe();
( new CreateFormSkill( $probe ) )->plan( 'Build a contact form' );
$native_prompt = $probe->system;
( new EfbFormSkill( $probe, false ) )->plan( 'Build a contact form' );
$efb_prompt = $probe->system;
( new EfbCustomizeSkill( $probe ) )->plan( 'add a phone field', array( array( 'id' => 'email', 'label' => 'Email', 'type' => 'email' ) ) );
$customize_prompt = $probe->system;
$rule = 'site language "fa-ir" (a right-to-left language)';
$check(
	false !== strpos( $native_prompt, $rule ) && false !== strpos( $efb_prompt, $rule ) && false !== strpos( $customize_prompt, $rule ),
	'Form Studio, the EFB form skill and the EFB customize skill all tell the model to write form text in the site language'
);

// The other way round: an English site run by a Persian-speaking administrator stays English.
remove_filter( 'locale', $site_locale, 5 );
$site_locale = static fn (): string => 'en_US';
add_filter( 'locale', $site_locale, 5 );
update_user_meta( (int) $admin_id, 'locale', 'fa_IR' );
clean_user_cache( (int) $admin_id );
wp_set_current_user( 0 ); // wp_set_current_user() keeps the loaded object for the same id.
wp_set_current_user( (int) $admin_id );
$reload_translations();
$items = ( new EfbFormConverter() )->convert( array( 'title' => 'Contact', 'fields' => array() ) );
$check(
	'fa_IR' === determine_locale() && 'رضایت' === __( 'Consent', 'ai-site-assistant-autopilot' ) && 'Submit' === $items[0]['button_single_text'],
	'on an English site the form stays English even though the admin reads the dashboard in Persian'
);
remove_filter( 'locale', $site_locale, 5 );
unset( $_GET['_locale'], $_SERVER['HTTP_ACCEPT'] );
wp_set_current_user( $original_user_id );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $admin_id );
$reload_translations();

$failed = 0;
foreach ( $checks as $c ) {
	echo ( $c['ok'] ? 'PASS ' : 'FAIL ' ) . $c['name'] . "\n";
	$failed += $c['ok'] ? 0 : 1;
}
echo count( $checks ) . " checks, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
