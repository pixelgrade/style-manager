<?php
/**
 * Headless Customizer provider.
 *
 * Boots a WP_Customize_Manager outside customize.php so the Style Manager
 * panels, sections, settings and controls can be rendered and saved from
 * other admin surfaces (e.g. the Site Editor).
 *
 * Mirrors the core pattern used by _wp_customize_publish_changeset() when
 * publishing changesets from CLI/Cron.
 *
 * @since   2.3.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Screen\Customizer;

/**
 * Headless Customizer provider class.
 *
 * @since 2.3.0
 */
class HeadlessCustomizer {

	/**
	 * Marker title for the Site Editor's Live Site preview changesets.
	 *
	 * Frontend code (e.g. the page-transitions opt-in) identifies preview
	 * requests by the changeset they carry — the changeset uuid survives
	 * every navigation inside the preview, unlike URL markers, which core's
	 * link rewriting strips.
	 */
	const PREVIEW_CHANGESET_TITLE = 'sm_site_editor_live_preview';

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Customizer screen (for the localized config + connected fields data).
	 *
	 * @var Customizer
	 */
	protected Customizer $customizer_screen;

	/**
	 * The headless manager instance, once booted.
	 *
	 * @var \WP_Customize_Manager|null
	 */
	protected ?\WP_Customize_Manager $manager = null;

	/**
	 * @param Options    $options           Options provider.
	 * @param Customizer $customizer_screen The Customizer screen provider.
	 */
	public function __construct( Options $options, Customizer $customizer_screen ) {
		$this->options           = $options;
		$this->customizer_screen = $customizer_screen;
	}

	/**
	 * Boot (once) and return a WP_Customize_Manager with the full
	 * customize_register chain applied (core, Style Manager, theme, plugins).
	 *
	 * @return \WP_Customize_Manager
	 */
	public function get_manager(): \WP_Customize_Manager {
		if ( $this->manager instanceof \WP_Customize_Manager ) {
			return $this->manager;
		}

		$this->manager = $this->create_manager( wp_generate_uuid4(), false );

		return $this->manager;
	}

	/**
	 * Creates a WP_Customize_Manager with the full customize_register chain applied.
	 *
	 * @param string $changeset_uuid     Changeset UUID used by the manager.
	 * @param bool   $settings_previewed Whether settings should be previewed.
	 *
	 * @return \WP_Customize_Manager
	 */
	protected function create_manager( string $changeset_uuid, bool $settings_previewed ): \WP_Customize_Manager {
		if ( ! class_exists( \WP_Customize_Manager::class, false ) ) {
			require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		}

		// Keep the heavyweight core components out: we only need settings/controls.
		$disable_components = static function () {
			return [];
		};
		add_filter( 'customize_loaded_components', $disable_components, 1000 );

		$manager = new \WP_Customize_Manager(
			[
				'changeset_uuid'     => $changeset_uuid,
				'settings_previewed' => $settings_previewed,
			]
		);

		remove_filter( 'customize_loaded_components', $disable_components, 1000 );

		// Some registrants rely on the global being available.
		$GLOBALS['wp_customize'] = $manager;

		/*
		 * Manually register core controls first, then fire the action so themes
		 * and plugins (including our own customize_register hooks) register their
		 * settings on top — same ordering fix as _wp_customize_publish_changeset().
		 */
		remove_action( 'customize_register', [ $manager, 'register_controls' ] );
		$manager->register_controls();

		/** This action is documented in wp-includes/class-wp-customize-manager.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core Customizer action required for parity with WordPress changeset publishing.
		do_action( 'customize_register', $manager );

		/*
		 * Note: we intentionally do NOT call $manager->prepare_controls() — it
		 * moves paneled sections out of $manager->sections() and we want the
		 * full registry. Controls are grouped into sections by
		 * get_section_controls() instead.
		 */

		return $manager;
	}

	/**
	 * Group the registered controls by section ID, ordered like the Customizer
	 * pane would order them (priority, then registration order).
	 *
	 * @return array section_id => \WP_Customize_Control[]
	 */
	protected function get_section_controls(): array {
		$manager = $this->get_manager();

		$grouped = [];
		foreach ( $manager->controls() as $control ) {
			/** @var \WP_Customize_Control $control */
			if ( empty( $control->section ) ) {
				continue;
			}

			$grouped[ $control->section ][] = $control;
		}

		foreach ( $grouped as $section_id => $controls ) {
			$grouped[ $section_id ] = wp_list_sort(
				$controls,
				[
					'priority'        => 'ASC',
					'instance_number' => 'ASC',
				],
				'ASC',
				true
			);
		}

		return $grouped;
	}

	/**
	 * Determine the Style Manager-owned section IDs to expose outside the Customizer.
	 *
	 * Defaults to every section in the `style_manager_panel` plus any section
	 * whose ID starts with `sm_` (the sections Style Manager relocates into the
	 * Theme Options panel: color usage, fine-tune palettes, spacing, etc.).
	 *
	 * @return string[] Ordered list of section IDs.
	 */
	public function get_sm_section_ids(): array {
		$manager = $this->get_manager();

		$theme_colors_section_id = $this->get_theme_colors_section_id();
		$section_ids             = [];
		foreach ( $manager->sections() as $section_id => $section ) {
			if (
				'style_manager_panel' === $section->panel
				|| 0 === strpos( (string) $section_id, 'sm_' )
				|| $theme_colors_section_id === (string) $section_id
			) {
				$section_ids[] = (string) $section_id;
			}
		}

		/**
		 * Filter the section IDs Style Manager exposes in the Site Editor.
		 *
		 * @param string[]              $section_ids
		 * @param \WP_Customize_Manager $manager
		 */
		return apply_filters( 'style_manager/site_editor_section_ids', $section_ids, $manager );
	}

	/**
	 * Build the final Customizer section ID used by themes for per-element
	 * color controls when they register the `colors_section` Style Manager
	 * config section.
	 *
	 * @return string
	 */
	protected function get_theme_colors_section_id(): string {
		$options_key = $this->options->get_options_key();
		if ( '' === $options_key ) {
			return '';
		}

		return $options_key . '[colors_section]';
	}

	/**
	 * The persisted setting ids of the theme's per-element color targets.
	 *
	 * These are the controls a theme registers in its Style Manager `colors_section` (the "Color
	 * targets" group folded into the Color Usage tab — e.g. body/links/heading/button color toggles).
	 * Derived theme-agnostically from the live Customize registry: every actual setting attached to a
	 * control in that section, skipping the presentational `html` controls (separators/intros/titles)
	 * which carry no real setting.
	 *
	 * Used to gate + strip these targets on save when Pixelgrade Plus is absent — the whole Color
	 * Usage tab is premium (see \Pixelgrade\StyleManager\plus_gated_setting_ids()).
	 *
	 * @since 2.2.16
	 *
	 * @return string[] Setting ids (full registered form, e.g. `anima_options[body_color]`).
	 */
	public function get_theme_color_target_setting_ids(): array {
		$section_id = $this->get_theme_colors_section_id();
		if ( '' === $section_id ) {
			return [];
		}

		$manager = $this->get_manager();

		$setting_ids = [];
		foreach ( $manager->controls() as $control ) {
			/** @var \WP_Customize_Control $control */
			if ( (string) $control->section !== $section_id ) {
				continue;
			}

			// Presentational controls (group separators, section titles, intros) carry no real
			// setting to persist; skip them so only genuine color-target settings are gated.
			if ( 'html' === $control->type ) {
				continue;
			}

			foreach ( (array) $control->settings as $setting ) {
				$setting_ids[] = is_object( $setting ) ? (string) $setting->id : (string) $setting;
			}
		}

		return array_values( array_unique( array_filter( $setting_ids ) ) );
	}

	/**
	 * Collect the settings data the JS engine expects, in the exact shape of
	 * _wpCustomizeSettings.settings (value/transport/type) augmented with
	 * Style Manager's connected_fields data.
	 *
	 * @return array
	 */
	public function get_settings_data(): array {
		$manager = $this->get_manager();

		$settings_data = [];
		foreach ( $manager->settings() as $setting_id => $setting ) {
			/** @var \WP_Customize_Setting $setting */
			if ( ! $setting->check_capabilities() ) {
				continue;
			}

			$settings_data[ $setting_id ] = [
				'value'     => $setting->js_value(),
				'transport' => $setting->transport,
				'dirty'     => false,
				'type'      => $setting->type,
			];
		}

		foreach ( $this->customizer_screen->get_connected_fields_data( $manager ) as $setting_id => $connected_fields ) {
			if ( isset( $settings_data[ $setting_id ] ) ) {
				$settings_data[ $setting_id ]['connected_fields'] = $connected_fields;
			}
		}

		return $settings_data;
	}

	/**
	 * Build the Style Manager panels/sections structure with the rendered
	 * control markup — the same markup WP would render in the Customizer pane.
	 *
	 * @return array {
	 *     @type array $panels   panel_id => [ title, description, priority ]
	 *     @type array $sections Ordered list of [ id, title, description, priority, panel, controls => [ [ id, type, html, active ] ] ]
	 * }
	 */
	public function get_structure(): array {
		$manager     = $this->get_manager();
		$section_ids = $this->get_sm_section_ids();

		$panels = [];
		foreach ( $manager->panels() as $panel_id => $panel ) {
			$panels[ $panel_id ] = [
				'id'          => (string) $panel_id,
				'title'       => $panel->title,
				'description' => $panel->description,
				'priority'    => $panel->priority,
			];
		}

		$section_controls = $this->get_section_controls();

		$sections = [];
		foreach ( $section_ids as $section_id ) {
			$section = $manager->get_section( $section_id );
			if ( ! $section || ! $section->check_capabilities() ) {
				continue;
			}

			$controls = [];
			foreach ( $section_controls[ $section_id ] ?? [] as $control ) {
				/** @var \WP_Customize_Control $control */
				ob_start();
				$control->maybe_render();
				$html = trim( (string) ob_get_clean() );

				if ( '' === $html ) {
					continue;
				}

				$controls[] = [
					'id'     => $control->id,
					'type'   => $control->type,
					'html'   => $html,
					'active' => (bool) $control->active(),
				];
			}

			$sections[] = [
				'id'          => (string) $section_id,
				'title'       => $section->title,
				'description' => $section->description,
				'priority'    => $section->priority,
				'panel'       => (string) $section->panel,
				'controls'    => $controls,
			];
		}

		return [
			'panels'   => $panels,
			'sections' => $sections,
		];
	}

	/**
	 * Let every control in the exposed sections enqueue its own assets
	 * (color pickers, media, etc.). Call this from an enqueue context.
	 */
	public function enqueue_controls_assets() {
		$section_ids      = $this->get_sm_section_ids();
		$section_controls = $this->get_section_controls();

		foreach ( $section_ids as $section_id ) {
			foreach ( $section_controls[ $section_id ] ?? [] as $control ) {
				/** @var \WP_Customize_Control $control */
				if ( $control->check_capabilities() ) {
					$control->enqueue();
				}
			}
		}
	}

	/**
	 * Plain map of setting_id => current JS value for every registered setting
	 * (the `settings` shape of the Site Editor settings entity record).
	 *
	 * @return array
	 */
	public function get_settings_values(): array {
		$values = [];
		foreach ( $this->get_settings_data() as $setting_id => $data ) {
			$values[ $setting_id ] = $data['value'];
		}

		return $values;
	}

	/**
	 * Re-evaluate the active state of every Style Manager control with a set
	 * of not-yet-saved values previewed on top of the saved state — the same
	 * server-side evaluation the Customizer performs on preview refresh.
	 *
	 * @param array $values setting_id => raw JS value (the client's unsaved deviations).
	 *
	 * @return array control_id => bool
	 */
	public function get_active_states( array $values ): array {
		$manager = $this->get_manager();

		foreach ( $values as $setting_id => $value ) {
			$setting = $manager->get_setting( (string) $setting_id );
			if ( ! $setting || ! $setting->check_capabilities() ) {
				continue;
			}

			$manager->set_post_value( (string) $setting_id, $value );
			// Filters option/theme_mod reads so active callbacks see the previewed value.
			$setting->preview();
		}

		$section_ids      = $this->get_sm_section_ids();
		$section_controls = $this->get_section_controls();

		$active_states = [];
		foreach ( $section_ids as $section_id ) {
			foreach ( $section_controls[ $section_id ] ?? [] as $control ) {
				/** @var \WP_Customize_Control $control */
				$active_states[ $control->id ] = (bool) $control->active();
			}
		}

		return $active_states;
	}

	/**
	 * The allowlisted, option-driven body class prefixes that are safe and
	 * meaningful to mirror onto the editor canvas body. (Classes like
	 * `is-loading` or intro-animation states would break the canvas — they
	 * rely on frontend scripts that never run there.)
	 *
	 * @return string[]
	 */
	public function get_preview_body_class_prefixes(): array {
		/**
		 * Filter the body class prefixes mirrored onto the Site Editor canvas.
		 *
		 * @param string[] $prefixes
		 */
		return apply_filters( 'style_manager/site_editor_preview_body_class_prefixes', [ 'u-collection-' ] );
	}

	/**
	 * Body classes computed with the (already previewed) values, narrowed to
	 * the allowlisted option-driven prefixes — themes hang visual treatments
	 * like collection title position and hover effects off body_class.
	 *
	 * @return string[]
	 */
	public function get_preview_body_classes(): array {
		$prefixes = $this->get_preview_body_class_prefixes();

		try {
			$classes = array_map( 'strval', get_body_class() );
		} catch ( \Throwable $e ) {
			return [];
		}

		$matching = array_filter( $classes, static function ( $class ) use ( $prefixes ) {
			foreach ( $prefixes as $prefix ) {
				if ( 0 === strpos( $class, $prefix ) ) {
					return true;
				}
			}

			return false;
		} );

		return array_values( array_unique( $matching ) );
	}

	/**
	 * Create or update the draft changeset used for the Live Site preview —
	 * loading the frontend with ?customize_changeset_uuid=<uuid> previews the
	 * unsaved values through core's own changeset preview mechanics.
	 *
	 * @param array       $values setting_id => raw JS value.
	 * @param string|null $uuid   An existing preview changeset to update.
	 *
	 * @return array|\WP_Error { uuid, previewUrl }
	 */
	public function upsert_preview_changeset( array $values, ?string $uuid = null ) {
		if ( ! $uuid || ! wp_is_uuid( $uuid ) ) {
			$uuid   = wp_generate_uuid4();
		}

		$data = [];
		foreach ( $values as $setting_id => $value ) {
			$data[ (string) $setting_id ] = [ 'value' => $value ];
		}

		$manager = $this->create_manager( $uuid, false );
		$result  = $manager->save_changeset_post(
			[
				'status' => '',
				'title'  => self::PREVIEW_CHANGESET_TITLE,
				'data'   => $data,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'uuid'       => $uuid,
			'previewUrl' => add_query_arg( 'customize_changeset_uuid', $uuid, home_url( '/' ) ),
		];
	}

	/**
	 * Persist a map of setting_id => value through a published changeset —
	 * the exact same sanitization and persistence semantics as hitting
	 * "Publish" in the Customizer.
	 *
	 * @param array $values setting_id => raw JS value.
	 *
	 * @return array|\WP_Error {
	 *     @type string[] $saved              Setting IDs accepted for save.
	 *     @type string[] $skipped            Setting IDs that are unknown or not allowed.
	 *     @type array    $setting_validities Validity report from save_changeset_post().
	 * }
	 */
	public function save( array $values ) {
		$manager = $this->get_manager();

		// _wp_customize_publish_changeset() consumes the global when the
		// changeset transitions to publish.
		$GLOBALS['wp_customize'] = $manager;

		$data    = [];
		$skipped = [];
		foreach ( $values as $setting_id => $value ) {
			$setting = $manager->get_setting( (string) $setting_id );
			if ( ! $setting || ! $setting->check_capabilities() ) {
				$skipped[] = (string) $setting_id;
				continue;
			}

			$data[ (string) $setting_id ] = [ 'value' => $value ];
		}

		if ( empty( $data ) ) {
			return new \WP_Error(
				'style_manager_site_editor_nothing_to_save',
				esc_html__( 'None of the provided settings could be saved.', '__plugin_txtd' ),
				[ 'skipped' => $skipped ]
			);
		}

		$result = $manager->save_changeset_post(
			[
				'status' => 'publish',
				'title'  => 'style_manager_site_editor',
				'data'   => $data,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Proactively make sure subsequent reads in this request see fresh values.
		$this->options->invalidate_all_caches();

		return [
			'saved'              => array_keys( $data ),
			'skipped'            => $skipped,
			'setting_validities' => isset( $result['setting_validities'] )
				? array_map(
					static function ( $validity ) {
						return true === $validity;
					},
					$result['setting_validities']
				)
				: [],
		];
	}
}
