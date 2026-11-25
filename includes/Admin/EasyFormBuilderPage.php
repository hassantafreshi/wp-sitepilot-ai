<?php
declare(strict_types=1);

namespace AISAAutoPilot\Admin;

use AISAAutoPilot\Connectors\EasyFormBuilderConnector;
use AISAAutoPilot\Efb\EfbCapabilities;
use AISAAutoPilot\Efb\EfbCustomizeSkill;
use AISAAutoPilot\Efb\EfbPayments;
use AISAAutoPilot\Logging\Logger;

/**
 * Easy Form Builder copilot details: what the copilot may change in the form open in the
 * Easy Form Builder editor, what it may never touch, and the last change it applied.
 *
 * Proposals live in the Easy Form Builder editor, never on this page. The operation list is the
 * one the customise skill executes, and the counts and refused areas come from the capability
 * catalog the server checks every proposal against.
 */
final class EasyFormBuilderPage {

	private const CATALOG = 'includes/Efb/efb-capability-catalog.json';

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ai-site-assistant-autopilot' ) );
		}

		$health   = ( new EasyFormBuilderConnector() )->get_health();
		$ready    = ! empty( $health['available'] );
		$catalog  = $this->catalog_summary();
		$writable = $catalog['field_properties'] + $catalog['form_properties'];
		$last     = $this->last_apply();
		$check    = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5 13 4.5 4.5L19 7"/></svg>';

		Ui::grad_defs();
		?>
		<div class="wrap sp-app" data-sp-efb data-sp-efb-state="<?php echo esc_attr( $ready ? 'available' : 'unavailable' ); ?>">
			<section class="sp-screen">
				<?php
				Ui::page_header(
					__( 'Easy Form Builder copilot', 'ai-site-assistant-autopilot' ),
					__( 'What the copilot may change in the form open in the Easy Form Builder editor, and what it applied last.', 'ai-site-assistant-autopilot' ),
					array(
						'chip'   => ConnectionChip::derive(),
						'action' => array(
							'label' => __( 'Back to Integrations', 'ai-site-assistant-autopilot' ),
							'url'   => admin_url( 'admin.php?page=' . AdminMenu::SLUG_INTEGRATIONS ),
						),
					)
				);
				?>
				<div class="sp-pagebody">
				<?php PluginDependency::render_result_notice(); ?>
				<div class="sp-efbx">

				<?php if ( $ready ) : ?>
					<section class="sp-efbx-status is-ready" data-sp-efb-status aria-labelledby="sp-efbx-status-title">
						<span class="sp-efbx-status__icon"><?php echo $check; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static icon markup. ?></span>
						<div class="sp-efbx-status__body">
							<h2 class="sp-efbx-status__title" id="sp-efbx-status-title">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: Easy Form Builder version. */
									__( 'Connected to Easy Form Builder %s', 'ai-site-assistant-autopilot' ),
									(string) ( $health['version'] ?? '' )
								)
							);
							?>
							</h2>
							<div class="sp-efbx-chips">
								<span class="sp-efbx-chip is-brand" data-sp-efb-writable="<?php echo esc_attr( (string) $writable ); ?>">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of Easy Form Builder properties the AI may change. */
										_n( '%s AI-writable property', '%s AI-writable properties', $writable, 'ai-site-assistant-autopilot' ),
										number_format_i18n( $writable )
									)
								);
								?>
								</span>
								<span class="sp-efbx-chip"><?php esc_html_e( 'Changes the open editor only', 'ai-site-assistant-autopilot' ); ?></span>
							</div>
						</div>
						<a class="sp-efbx-button" href="<?php echo esc_url( admin_url( 'admin.php?page=Emsfb' ) ); ?>"><?php esc_html_e( 'Open EFB forms', 'ai-site-assistant-autopilot' ); ?></a>
					</section>
				<?php else : ?>
					<?php
					PluginDependency::render_required(
						PluginDependency::status( PluginDependency::EFB, AdminMenu::SLUG_EFB ),
						array(
							/* translators: Used in "Install Easy Form Builder to use its AI copilot". */
							'feature'     => __( 'its AI copilot', 'ai-site-assistant-autopilot' ),
							'lead'        => __( 'The copilot works inside the Easy Form Builder editor, version 4.1 or newer. The list below shows what it will be allowed to change.', 'ai-site-assistant-autopilot' ),
							'reassurance' => __( 'Simple forms still work with Form Studio.', 'ai-site-assistant-autopilot' ),
							'attrs'       => array( 'data-sp-efb-status' => '' ),
						)
					);
					?>
				<?php endif; ?>

					<div class="sp-efbx-grid">
						<section class="sp-efbx-card sp-efbx-ops<?php echo $ready ? '' : ' is-muted'; ?>" aria-labelledby="sp-efbx-ops-title">
							<div class="sp-efbx-card__head">
								<h2 class="sp-efbx-card__title" id="sp-efbx-ops-title"><?php esc_html_e( 'What the AI may change', 'ai-site-assistant-autopilot' ); ?></h2>
								<p class="sp-efbx-card__text"><?php esc_html_e( 'The server checks every proposal against this list before the editor shows it.', 'ai-site-assistant-autopilot' ); ?></p>
							</div>
							<?php foreach ( $this->operation_groups( $catalog ) as $group ) : ?>
								<h3 class="sp-efbx-group"><?php echo esc_html( $group['title'] ); ?></h3>
								<ul class="sp-efbx-rows">
								<?php foreach ( $group['rows'] as $row ) : ?>
									<?php if ( $row['blocked'] ) : ?>
									<li class="sp-efbx-row" data-sp-efb-blocked>
									<?php else : ?>
									<li class="sp-efbx-row" data-sp-efb-op="<?php echo esc_attr( $row['op'] ); ?>">
										<code class="sp-efbx-row__op" dir="ltr"><?php echo esc_html( $row['op'] ); ?></code>
									<?php endif; ?>
										<span class="sp-efbx-row__label"><?php echo esc_html( $row['label'] ); ?></span>
										<span class="sp-efbx-tag is-<?php echo esc_attr( $row['tone'] ); ?>"><?php echo esc_html( $row['tag'] ); ?></span>
									</li>
								<?php endforeach; ?>
								</ul>
							<?php endforeach; ?>
						</section>

						<div class="sp-efbx-side">
							<section class="sp-efbx-card sp-efbx-last" aria-labelledby="sp-efbx-last-title" data-sp-efb-last>
								<h2 class="sp-efbx-card__title" id="sp-efbx-last-title"><?php esc_html_e( 'Last change applied', 'ai-site-assistant-autopilot' ); ?></h2>
								<?php if ( $last['applied'] ) : ?>
									<p class="sp-efbx-last__message" dir="auto"><?php echo esc_html( $last['message'] ); ?></p>
									<p class="sp-efbx-last__meta">
										<span><?php echo esc_html( $last['when'] ); ?></span>
										<?php if ( '' !== $last['trace'] ) : ?>
											<code dir="ltr">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: trace identifier of the applied change. */
													__( 'Trace %s', 'ai-site-assistant-autopilot' ),
													$last['trace']
												)
											);
											?>
											</code>
										<?php endif; ?>
									</p>
								<?php else : ?>
									<p class="sp-efbx-last__empty"><?php esc_html_e( 'Nothing applied yet. Proposals are made from the chat button inside the Easy Form Builder editor.', 'ai-site-assistant-autopilot' ); ?></p>
								<?php endif; ?>
								<a class="sp-efbx-button is-block" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LOGS ) ); ?>"><?php esc_html_e( 'Open activity log', 'ai-site-assistant-autopilot' ); ?></a>
							</section>
							<?php $this->render_site_card(); ?>
							<section class="sp-efbx-card sp-efbx-note" aria-labelledby="sp-efbx-note-title">
								<h2 class="sp-efbx-note__title" id="sp-efbx-note-title"><?php esc_html_e( 'Nothing is saved on its own', 'ai-site-assistant-autopilot' ); ?></h2>
								<p class="sp-efbx-note__text"><?php esc_html_e( 'The copilot only changes the form open in the editor. Your form is saved when you press Save in Easy Form Builder.', 'ai-site-assistant-autopilot' ); ?></p>
							</section>
						</div>
					</div>
				</div>
				</div>
			</section>
			<?php Ui::overlays(); ?>
		</div>
		<?php
	}

	/**
	 * What Easy Form Builder itself reports about this site (its capability registry): the plan, how new forms are
	 * created, the confirmation e-mail default, the add-ons that left its core, the payment gateways and the CAPTCHA
	 * services. Read only; installing or upgrading stays a person's click in Easy Form Builder.
	 */
	private function render_site_card(): void {
		if ( ! EfbCapabilities::available() ) {
			if ( null !== EfbCapabilities::all() || ! defined( 'EMSFB_PLUGIN_VERSION' ) ) {
				return;
			}
			?>
			<section class="sp-efbx-card sp-efbx-site" aria-labelledby="sp-efbx-site-title" data-sp-efb-site="old">
				<h2 class="sp-efbx-card__title" id="sp-efbx-site-title"><?php esc_html_e( 'On this site', 'ai-site-assistant-autopilot' ); ?></h2>
				<p class="sp-efbx-card__text"><?php echo esc_html( EasyFormBuilderConnector::update_message() ); ?></p>
			</section>
			<?php
			return;
		}
		$plan   = EfbCapabilities::plan();
		$groups = array();

		$general   = array();
		$general[] = array(
			'label' => __( 'Plan', 'ai-site-assistant-autopilot' ),
			'tag'   => EfbCapabilities::plan_label( 'pro_pending' === $plan ? 'free' : $plan ),
			'tone'  => 'ok',
			'note'  => EfbCapabilities::one_time_purchase() ? __( 'Pro is a one-time purchase on this site', 'ai-site-assistant-autopilot' ) : '',
			'key'   => 'plan',
		);
		$package   = EfbCapabilities::form_package_version();
		$general[] = array(
			'label' => __( 'New forms are created through Easy Form Builder’s own save path', 'ai-site-assistant-autopilot' ),
			'tag'   => $package > 0 ? sprintf( 'v%s', (string) $package ) : __( 'Update needed', 'ai-site-assistant-autopilot' ),
			'tone'  => $package > 0 ? 'ok' : 'err',
			'note'  => '',
			'key'   => 'form_package',
		);
		$general[] = array(
			'label' => __( 'Confirmation e-mail to the visitor on new forms', 'ai-site-assistant-autopilot' ),
			'tag'   => EfbCapabilities::email_confirmation_default_on() ? __( 'On by default', 'ai-site-assistant-autopilot' ) : __( 'Off by default', 'ai-site-assistant-autopilot' ),
			'tone'  => 'ok',
			'note'  => '',
			'key'   => 'email_confirmation',
		);
		$groups[] = array(
			'title' => __( 'Easy Form Builder', 'ai-site-assistant-autopilot' ),
			'rows'  => $general,
		);

		$addons = array();
		foreach ( (array) ( EfbCapabilities::block( 'split' )['addons'] ?? array() ) as $code => $info ) {
			// The gateways' add-ons are listed with the gateways below.
			if ( is_array( $info ) && 'legacy_gateway' === ( $info['contract'] ?? '' ) ) {
				continue;
			}
			// The customer portal is out of scope here (owner decision): never listed.
			if ( EfbCapabilities::is_portal_addon( (string) $code, $info ) ) {
				continue;
			}
			$addons[] = $this->addon_row( (string) $code );
		}
		if ( array() !== $addons ) {
			$groups[] = array(
				'title' => __( 'Features that are add-ons', 'ai-site-assistant-autopilot' ),
				'rows'  => $addons,
			);
		}

		$gateways = array();
		foreach ( EfbPayments::registry_status() as $id => $row ) {
			$gateways[] = $this->addon_row( (string) ( $row['addon'] ?? '' ), (string) $row['label'], 'gw-' . $id );
		}
		if ( array() !== $gateways ) {
			$groups[] = array(
				'title' => __( 'Payment gateways', 'ai-site-assistant-autopilot' ),
				'rows'  => $gateways,
			);
		}

		$captcha = array();
		foreach ( EfbCapabilities::captcha_providers() as $provider ) {
			$on        = ! empty( $provider['available'] );
			$captcha[] = array(
				'label' => (string) ( $provider['label'] ?? $provider['id'] ?? '' ),
				'tag'   => $on ? ( ! empty( $provider['ready'] ) ? __( 'Ready', 'ai-site-assistant-autopilot' ) : __( 'Keys not set', 'ai-site-assistant-autopilot' ) ) : __( 'Add-on', 'ai-site-assistant-autopilot' ),
				'tone'  => $on && ! empty( $provider['ready'] ) ? 'ok' : 'warn',
				'note'  => '',
				'key'   => 'captcha-' . (string) ( $provider['id'] ?? '' ),
			);
		}
		if ( array() !== $captcha ) {
			$groups[] = array(
				'title' => __( 'CAPTCHA services', 'ai-site-assistant-autopilot' ),
				'rows'  => $captcha,
			);
		}
		?>
		<section class="sp-efbx-card sp-efbx-site" aria-labelledby="sp-efbx-site-title" data-sp-efb-site="registry">
			<h2 class="sp-efbx-card__title" id="sp-efbx-site-title"><?php esc_html_e( 'On this site', 'ai-site-assistant-autopilot' ); ?></h2>
			<p class="sp-efbx-card__text"><?php esc_html_e( 'As Easy Form Builder reports it. The copilot never promises a field this site cannot show: it names the add-on or plan and uses the closest field instead.', 'ai-site-assistant-autopilot' ); ?></p>
			<?php foreach ( $groups as $group ) : ?>
				<h3 class="sp-efbx-group"><?php echo esc_html( $group['title'] ); ?></h3>
				<ul class="sp-efbx-rows">
				<?php foreach ( $group['rows'] as $row ) : ?>
					<li class="sp-efbx-row" data-sp-efb-site-row="<?php echo esc_attr( $row['key'] ); ?>">
						<span class="sp-efbx-row__label">
							<bdi><?php echo esc_html( $row['label'] ); ?></bdi>
							<?php if ( '' !== $row['note'] ) : ?>
								<small class="sp-efbx-row__note"><?php echo esc_html( $row['note'] ); ?></small>
							<?php endif; ?>
						</span>
						<span class="sp-efbx-tag is-<?php echo esc_attr( $row['tone'] ); ?>"><bdi><?php echo esc_html( $row['tag'] ); ?></bdi></span>
					</li>
				<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
			<a class="sp-efbx-button is-block" href="<?php echo esc_url( EfbCapabilities::addons_page() ); ?>"><?php esc_html_e( 'Open EFB Add-ons', 'ai-site-assistant-autopilot' ); ?></a>
		</section>
		<?php
	}

	/**
	 * One add-on as a row: its name and whether it is active, needs installing (with its plan), a higher plan or an update.
	 *
	 * @return array{label:string,tag:string,tone:string,note:string,key:string}
	 */
	private function addon_row( string $code, string $label = '', string $key = '' ): array {
		$addon = EfbCapabilities::addon( $code );
		$plan  = EfbCapabilities::plan_label( $addon['package'] );
		if ( $addon['active'] ) {
			$tag  = __( 'Active', 'ai-site-assistant-autopilot' );
			$tone = 'ok';
		} elseif ( 'update' === $addon['state'] ) {
			$tag  = __( 'Update needed', 'ai-site-assistant-autopilot' );
			$tone = 'err';
		} elseif ( 'plan' === $addon['state'] || ! $addon['can_install'] ) {
			/* translators: %s: plan name. */
			$tag  = sprintf( __( 'Needs %s', 'ai-site-assistant-autopilot' ), $plan );
			$tone = 'warn';
		} else {
			/* translators: %s: plan name. */
			$tag  = 'free' === $addon['package'] ? __( 'Install (every plan)', 'ai-site-assistant-autopilot' ) : sprintf( __( 'Install (%s)', 'ai-site-assistant-autopilot' ), $plan );
			$tone = 'warn';
		}

		return array(
			'label' => '' !== $label ? $label : $addon['name'],
			'tag'   => $tag,
			'tone'  => $tone,
			'note'  => '',
			'key'   => '' !== $key ? $key : $code,
		);
	}

	/**
	 * Counts and refused areas from the capability catalog the server checks proposals against.
	 *
	 * @return array{field_properties:int,form_properties:int,field_types:int,blocked:array<int,string>}
	 */
	private function catalog_summary(): array {
		$summary = array(
			'field_properties' => 0,
			'form_properties'  => 0,
			'field_types'      => 0,
			'blocked'          => array(),
		);
		$file    = AISAAUTOPILOT_DIR . self::CATALOG;
		if ( ! is_readable( $file ) ) {
			return $summary;
		}
		$catalog = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled plugin asset, not a remote request.
		if ( ! is_array( $catalog ) ) {
			return $summary;
		}
		foreach ( array(
			'field_properties' => 'ai_writable',
			'form_properties'  => 'ai_writable',
			'field_types'      => 'ai_create',
		) as $section => $flag ) {
			foreach ( (array) ( $catalog[ $section ] ?? array() ) as $entry ) {
				if ( is_array( $entry ) && true === ( $entry[ $flag ] ?? false ) ) {
					++$summary[ $section ];
				}
			}
		}
		$blocked            = (array) ( $catalog['safe_ai_contract']['blocked'] ?? array() );
		$summary['blocked'] = array_values( array_filter( array_map( 'strval', $blocked ) ) );

		return $summary;
	}

	/**
	 * Operations the copilot executes, grouped for reading, followed by the areas it never touches.
	 *
	 * @param array{field_properties:int,form_properties:int,field_types:int,blocked:array<int,string>} $catalog
	 * @return array<int, array{title:string, rows:array<int, array{op:string,label:string,tag:string,tone:string,blocked:bool}>}>
	 */
	private function operation_groups( array $catalog ): array {
		$allowed = __( 'Allowed', 'ai-site-assistant-autopilot' );
		$checked = __( 'Checked', 'ai-site-assistant-autopilot' );
		$labels  = array(
			'set_form_name'      => array( 'form', __( 'Rename the form', 'ai-site-assistant-autopilot' ), $checked ),
			'set_confirmation'   => array( 'form', __( 'Change the message shown after sending', 'ai-site-assistant-autopilot' ), $checked ),
			'set_form_property'  => array(
				'form',
				sprintf(
					/* translators: %s: number of form settings the AI may change. */
					_n( 'Change the %s form setting marked AI-writable', 'Change one of %s form settings marked AI-writable', $catalog['form_properties'], 'ai-site-assistant-autopilot' ),
					number_format_i18n( $catalog['form_properties'] )
				),
				$checked,
			),
			'add_step'           => array( 'form', __( 'Add a step to a multi-step form', 'ai-site-assistant-autopilot' ), $allowed ),
			'add_field'          => array(
				'fields',
				sprintf(
					/* translators: %s: number of field types the AI may create. */
					_n( 'Add a field of the %s allowed type', 'Add a field of one of %s allowed types', $catalog['field_types'], 'ai-site-assistant-autopilot' ),
					number_format_i18n( $catalog['field_types'] )
				),
				$allowed,
			),
			'set_field'          => array( 'fields', __( 'Change a field’s label, placeholder, help text, width or whether it is required', 'ai-site-assistant-autopilot' ), $checked ),
			'set_field_property' => array(
				'fields',
				sprintf(
					/* translators: %s: number of field settings the AI may change. */
					_n( 'Change the %s field setting marked AI-writable', 'Change one of %s field settings marked AI-writable', $catalog['field_properties'], 'ai-site-assistant-autopilot' ),
					number_format_i18n( $catalog['field_properties'] )
				),
				$checked,
			),
			'add_payment'        => array( 'fields', __( 'Add online payment through a gateway add-on (Stripe, PayPal, ZarinPal, Mollie, Payplug, Tap, Omise, PayTabs, Paymob or Xendit): priced choices or an amount, the total and the gateway field, using only prices and a currency you gave. Needs the gateway’s add-on and the plan Easy Form Builder names; never touches keys', 'ai-site-assistant-autopilot' ), $allowed ),
			'move_field'         => array( 'fields', __( 'Move a field before or after another field', 'ai-site-assistant-autopilot' ), $checked ),
			'remove_field'       => array( 'fields', __( 'Remove a field and its settings', 'ai-site-assistant-autopilot' ), $checked ),
			'add_option'         => array( 'choices', __( 'Add a choice to a select, radio or checkbox', 'ai-site-assistant-autopilot' ), $allowed ),
			'remove_option'      => array( 'choices', __( 'Remove a choice', 'ai-site-assistant-autopilot' ), $checked ),
			'rename_option'      => array( 'choices', __( 'Rename a choice; answers and logic that point at it stay linked', 'ai-site-assistant-autopilot' ), $checked ),
			'add_logic_rule'     => array( 'logic', __( 'Show, hide, require or skip to a field or step from another answer, when Easy Form Builder’s Conditional Logic add-on is active', 'ai-site-assistant-autopilot' ), $checked ),
		);
		$groups  = array(
			'form'    => array(
				'title' => __( 'Form', 'ai-site-assistant-autopilot' ),
				'rows'  => array(),
			),
			'fields'  => array(
				'title' => __( 'Fields', 'ai-site-assistant-autopilot' ),
				'rows'  => array(),
			),
			'choices' => array(
				'title' => __( 'Choices', 'ai-site-assistant-autopilot' ),
				'rows'  => array(),
			),
			'logic'   => array(
				'title' => __( 'Conditional logic', 'ai-site-assistant-autopilot' ),
				'rows'  => array(),
			),
		);

		foreach ( EfbCustomizeSkill::OPS as $op ) {
			// $labels covers every operation the skill executes; static analysis flags a new one without a label.
			list( $group, $label, $tag ) = $labels[ $op ];
			$groups[ $group ]['rows'][]  = array(
				'op'      => $op,
				'label'   => $label,
				'tag'     => $tag,
				'tone'    => $allowed === $tag ? 'ok' : 'warn',
				'blocked' => false,
			);
		}

		$never = array(
			'title' => __( 'Only in Easy Form Builder', 'ai-site-assistant-autopilot' ),
			'rows'  => array(),
		);
		foreach ( $catalog['blocked'] as $item ) {
			$never['rows'][] = array(
				'op'      => '',
				'label'   => $this->blocked_label( $item ),
				'tag'     => __( 'Never', 'ai-site-assistant-autopilot' ),
				'tone'    => 'err',
				'blocked' => true,
			);
		}

		$groups = array_values( $groups );
		if ( array() !== $never['rows'] ) {
			$groups[] = $never;
		}

		return $groups;
	}

	private function blocked_label( string $item ): string {
		$labels = array(
			'global settings'                              => __( 'Easy Form Builder global settings', 'ai-site-assistant-autopilot' ),
			'add-on activation'                            => __( 'Turning add-ons on or off', 'ai-site-assistant-autopilot' ),
			'credentials and API keys'                     => __( 'Credentials and API keys', 'ai-site-assistant-autopilot' ),
			'payment keys and account credentials'         => __( 'Payment keys and account details', 'ai-site-assistant-autopilot' ),
			'unfiltered HTML (scripts, iframes, event attributes)' => __( 'Scripts and other unfiltered HTML', 'ai-site-assistant-autopilot' ),
			'URLs and phone numbers the admin did not type' => __( 'Web addresses and phone numbers you did not type', 'ai-site-assistant-autopilot' ),
			'notification, confirmation, webhook and pricing conditional rules' => __( 'Conditional rules for notifications, confirmations, webhooks and prices', 'ai-site-assistant-autopilot' ),
			'logic actions that write values or calculate' => __( 'Logic that writes values or calculates', 'ai-site-assistant-autopilot' ),
			'notification recipients chosen by AI'         => __( 'Choosing who receives notifications', 'ai-site-assistant-autopilot' ),
			'submission data'                              => __( 'Submitted entries', 'ai-site-assistant-autopilot' ),
		);

		return $labels[ $item ] ?? $item;
	}

	/**
	 * The most recent Easy Form Builder apply recorded in the activity log.
	 *
	 * @return array{applied:bool,message:string,when:string,trace:string}
	 */
	private function last_apply(): array {
		foreach ( ( new Logger() )->recent( 100 ) as $entry ) {
			if ( 0 !== strpos( (string) ( $entry['event'] ?? '' ), 'efb.apply' ) ) {
				continue;
			}
			$context   = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();
			$timestamp = strtotime( (string) ( $entry['time'] ?? '' ) );
			$message   = trim( (string) ( $entry['message'] ?? '' ) );

			return array(
				'applied' => true,
				'message' => '' !== $message ? $message : __( 'A change was applied in the editor.', 'ai-site-assistant-autopilot' ),
				'when'    => false !== $timestamp ? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '',
				'trace'   => (string) ( $context['trace_id'] ?? '' ),
			);
		}

		return array(
			'applied' => false,
			'message' => '',
			'when'    => '',
			'trace'   => '',
		);
	}
}
