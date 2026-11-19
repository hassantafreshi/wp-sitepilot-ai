<?php
/**
 * The copilot's EFB knowledge (F01–F21, SPLIT, payments, portal, renewal, spam) answers from Easy Form Builder's own
 * registry on this site, never from a remembered list.
 */

declare(strict_types=1);

namespace AISAAutoPilot\Tests;

use AISAAutoPilot\Efb\EfbCapabilities;
use AISAAutoPilot\Efb\EfbFormReview;
use AISAAutoPilot\Efb\EfbKnowledge;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/EfbRegistryFixture.php';

final class EfbKnowledgeRegistryTest extends TestCase {

	protected function tearDown(): void {
		EfbRegistryFixture::reset();
	}

	/** @return array<string,array{0:string,1:string}> */
	public static function topics(): array {
		return array(
			'F01 masks'              => array( 'how do I add a postal code mask', 'input_masks' ),
			'F02 otp'                => array( 'send a one time code to the mobile', 'verification_otp' ),
			'F03 text rules'         => array( 'no duplicate answers, unique values and word count', 'text_validation' ),
			'F04 calc'               => array( 'how do I add a calculation formula', 'numbers_calculations' ),
			'F05 choices'            => array( 'shuffle the options and add other option', 'choices_advanced' ),
			'F06 calendar'           => array( 'use the persian jalali calendar', 'advanced_dates' ),
			'F07 uploads'            => array( 'allow multiple files and camera', 'file_uploads_advanced' ),
			'F08 hidden'             => array( 'hidden field with utm prefill', 'hidden_smart_tags' ),
			'F09 repeater'           => array( 'repeater to add another guest row', 'repeater_group' ),
			'F10 layout'             => array( 'review page before submit and sections', 'layout_special' ),
			'F11 limits'             => array( 'close the form after a deadline schedule', 'limits_schedule' ),
			'F12 spam'               => array( 'add captcha spam protection', 'captcha' ),
			'F13 privacy'            => array( 'gdpr retention delete entries after days', 'privacy_retention' ),
			'F14 entries'            => array( 'filter entries and export csv', 'entries_management' ),
			'F15 versions'           => array( 'undo and restore an earlier version', 'undo_versions_package' ),
			'F16 inline'             => array( 'inline validation and autocomplete', 'inline_validation' ),
			'F17 address'            => array( 'iranian address format with province', 'name_address_formats' ),
			'F18 plans'              => array( 'what does free plus upgrade add', 'plans_upgrade' ),
			'F19 backgrounds'        => array( 'gradient step background', 'step_backgrounds' ),
			'F20 portal'             => array( 'customer portal my submissions', 'customer_portal' ),
			'F21 renewal'            => array( 'when does my pro subscription renewal expire', 'pro_renewal' ),
			'SPLIT add-ons'          => array( 'which add-on do I need to install', 'addons_split' ),
			'payments'               => array( 'add a mollie payment gateway', 'payment_gateways' ),
			'data tools'             => array( 'import from contact form 7', 'data_tools' ),
			'confirmation e-mail'    => array( 'email notification to the visitor', 'email_notifications' ),
			'logic jump'             => array( 'conditional logic jump to step', 'conditional_logic' ),
		);
	}

	/**
	 * @dataProvider topics
	 */
	public function test_every_efb_feature_has_a_grounded_answer( string $question, string $id ): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$answer = EfbKnowledge::answer( $question );
		self::assertTrue( $answer['ok'], $question );
		self::assertSame( $id, $answer['id'], $question );
	}

	public function test_answers_say_what_this_site_lacks(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus', array( 'AdnCLC' => false ) ) );
		$calc = EfbKnowledge::answer( 'how do I add a calculation formula' )['answer'];
		self::assertStringContainsString( 'On this site: Calculation field: needs the Numbers & Calculations add-on (Free Plus)', $calc );
		self::assertStringContainsString( 'Calculated amount to pay: needs Pro', $calc );

		$otp = EfbKnowledge::answer( 'send a one time code to the mobile' )['answer'];
		self::assertStringContainsString( 'WP SMS', $otp );
		self::assertStringContainsString( 'SMS code: needs Pro', $otp );

		$addons = EfbKnowledge::answer( 'which add-on do I need to install' )['answer'];
		self::assertStringContainsString( 'Numbers & Calculations (Free Plus): not installed', $addons );
		self::assertStringContainsString( 'Typed Signature (Free Plus): active', $addons );
		self::assertStringContainsString( 'Booking (Pro): needs a higher plan', $addons );
	}

	public function test_the_portal_is_a_free_plugin_on_every_plan_and_is_not_ready_until_it_is_active(): void {
		// EFB portal contract 1.1+: capabilities.portal_core names the free plugin, never an add-on or a package.
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$portal = EfbCapabilities::portal();
		self::assertFalse( $portal['ready'] );
		self::assertSame( 'efb-customer-portal', $portal['plugin']['slug'] );
		self::assertArrayNotHasKey( 'addon', $portal );
		self::assertArrayNotHasKey( 'package', $portal );
		self::assertSame( 'inactive', EfbCapabilities::portal_state()['state'] );
		$answer = EfbKnowledge::answer( 'customer portal my submissions' )['answer'];
		self::assertStringContainsString( 'never asks for or enters a sign-in code', $answer );
		self::assertStringContainsString( 'the customer portal is not ready: the free EFB Customer Portal plugin is installed but not active', $answer );
		self::assertStringContainsString( 'free plugin EFB Customer Portal (not an add-on), on every plan', $answer );
		foreach ( array( 'Pro add-on', 'add-on on Pro', 'Customer Portal add-on' ) as $wrong ) {
			self::assertStringNotContainsString( $wrong, $answer );
		}

		// Not installed, on the Free plan: still the free plugin, never "needs Pro".
		$caps                                       = EfbRegistryFixture::site( 'free' );
		$caps['portal_core']['plugin']['installed'] = false;
		EfbRegistryFixture::apply( $caps );
		$answer = EfbKnowledge::answer( 'customer portal' )['answer'];
		self::assertStringContainsString( 'the customer portal is not ready: it needs the free EFB Customer Portal plugin, on every plan', $answer );
		self::assertStringNotContainsString( 'needs Pro', $answer );

		// The site's own efb_portal_plan_ok switched the contract off; EFB is still building its owner index.
		$caps['portal_core']['reason'] = 'plan';
		EfbRegistryFixture::apply( $caps );
		self::assertStringContainsString( 'this site has switched the portal off', EfbKnowledge::answer( 'customer portal' )['answer'] );
		$caps['portal_core']['reason'] = 'index';
		EfbRegistryFixture::apply( $caps );
		self::assertStringContainsString( 'Easy Form Builder is still preparing it', EfbKnowledge::answer( 'customer portal' )['answer'] );

		// A contract 1.0 registry (an unreleased EFB build: addon + package, reason plan) is not read as "switched off".
		$caps                = EfbRegistryFixture::site( 'free' );
		$caps['portal_core'] = array(
			'version' => 1,
			'addon'   => 'AdnPRT',
			'package' => 'pro',
			'ready'   => false,
			'reason'  => 'plan',
		);
		EfbRegistryFixture::apply( $caps );
		self::assertSame( 'not_installed', EfbCapabilities::portal_state()['state'] );
		self::assertStringContainsString( 'it needs the free EFB Customer Portal plugin', EfbKnowledge::answer( 'customer portal' )['answer'] );

		$caps                                    = EfbRegistryFixture::site( 'free' );
		$caps['portal_core']['ready']            = true;
		$caps['portal_core']['reason']           = null;
		$caps['portal_core']['plugin']['active'] = true;
		EfbRegistryFixture::apply( $caps );
		self::assertStringContainsString( 'the customer portal is ready', EfbKnowledge::answer( 'customer portal' )['answer'] );
	}

	public function test_the_site_cards_portal_row_names_the_free_plugin(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$row = \AISAAutoPilot\Admin\EasyFormBuilderPage::portal_row();
		self::assertSame( 'Free plugin, not active', $row['tag'] );
		self::assertSame( 'warn', $row['tone'] );
		self::assertSame( 'The portal is the free EFB Customer Portal plugin, on every plan', $row['note'] );

		$caps                                       = EfbRegistryFixture::site( 'free' );
		$caps['portal_core']['plugin']['installed'] = false;
		EfbRegistryFixture::apply( $caps );
		self::assertSame( 'Free plugin, not installed', \AISAAutoPilot\Admin\EasyFormBuilderPage::portal_row()['tag'] );

		$caps['portal_core']['ready'] = true;
		EfbRegistryFixture::apply( $caps );
		$row = \AISAAutoPilot\Admin\EasyFormBuilderPage::portal_row();
		self::assertSame( array( 'Ready', 'ok', '' ), array( $row['tag'], $row['tone'], $row['note'] ) );

		$caps = EfbRegistryFixture::site( 'free_plus' );
		unset( $caps['portal_core'] );
		EfbRegistryFixture::apply( $caps );
		self::assertNull( \AISAAutoPilot\Admin\EasyFormBuilderPage::portal_row() );
	}

	public function test_the_prompt_facts_call_the_portal_a_free_plugin(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$facts = EfbCapabilities::prompt_facts();
		self::assertStringContainsString( 'CUSTOMER_PORTAL=not ready (installed, not active); it is the free plugin EFB Customer Portal (efb-customer-portal) on every plan, not an add-on', $facts );
		self::assertStringNotContainsString( 'AdnPRT', $facts );
		self::assertStringNotContainsString( 'Pro add-on', $facts );
	}

	public function test_plans_and_one_time_purchase_on_persian_sites(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free' ) );
		self::assertStringContainsString( 'this site is on Free', EfbKnowledge::answer( 'what does free plus upgrade add' )['answer'] );
		self::assertStringContainsString( 'yearly subscription', EfbCapabilities::plan_note( 'pro' ) );
		self::assertStringContainsString( 'activates it in Easy Form Builder', EfbCapabilities::plan_note( 'free_plus' ) );
		// F21 does nothing on fa_IR: the answer says so (the knowledge text and the registry's `known` false).
		$renewal = EfbKnowledge::answer( 'pro renewal reminder' )['answer'];
		self::assertStringContainsString( 'one-time purchase, so there is no renewal', $renewal );
	}

	public function test_spam_advice_names_googles_three_modes_and_the_captcha_add_ons(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free_plus' ) );
		$advice = EfbFormReview::spam_advice();
		self::assertStringContainsString( 'honeypot', $advice );
		self::assertStringContainsString( 'v2, v2 invisible or v3', $advice );
		foreach ( array( 'Cloudflare Turnstile', 'hCaptcha', 'ALTCHA', 'Question CAPTCHA', 'Friendly Captcha' ) as $service ) {
			self::assertStringContainsString( $service, $advice );
		}
		$findings = EfbFormReview::review( array( array( 'type' => 'text', 'label' => 'Name' ) ), array( 'captcha' => false ) );
		self::assertSame( $advice, array_column( $findings, 'message', 'code' )['spam_protection'] );
	}

	public function test_the_model_gets_the_sites_registry_facts(): void {
		EfbRegistryFixture::apply( EfbRegistryFixture::site( 'free', array( 'AdnCLC' => false ) ) );
		$facts = EfbCapabilities::prompt_facts();
		self::assertStringContainsString( 'EFB_REGISTRY=yes', $facts );
		self::assertStringContainsString( 'EFB_PLAN_NAME=free', $facts );
		self::assertStringContainsString( 'Numbers & Calculations (free_plus):', $facts );
		self::assertStringContainsString( 'calc (', $facts );
		self::assertStringContainsString( 'VISITOR_CONFIRMATION_EMAIL_DEFAULT=on', $facts );
		self::assertStringContainsString( 'CUSTOMER_PORTAL=not ready', $facts );
		self::assertStringContainsString( 'STEP_LIMIT=2', $facts );
		self::assertStringNotContainsString( 'Key', $facts );

		EfbRegistryFixture::apply( null );
		self::assertStringContainsString( 'EFB_REGISTRY=no', EfbCapabilities::prompt_facts() );
	}
}
