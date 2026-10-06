<?php
/**
 * Elementor "Language Buttons" widget - a compact header switcher that shows
 * every language as a button (EN | AR | FR) or as a dropdown, with full
 * colour control for normal, hover and active states.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class EST_Compact_Switcher_Widget extends Widget_Base {

	use EST_Button_Style;

	public function get_name() {
		return 'est-language-buttons';
	}

	public function get_title() {
		return __( 'Language Buttons', 'est' );
	}

	public function get_icon() {
		return 'eicon-button';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'language', 'switcher', 'buttons', 'header', 'translate', 'dropdown' );
	}

	public function get_style_depends() {
		return array( 'est-switcher' );
	}

	public function get_script_depends() {
		return array( 'est-switcher' );
	}

	/** Colour control writing a CSS variable on the widget box. */
	private function color( $id, $label, $var, $default = '', $condition = array() ) {
		$args = array(
			'label'     => $label,
			'type'      => Controls_Manager::COLOR,
			'default'   => $default,
			'selectors' => array( '{{WRAPPER}} .est-compact' => $var . ': {{VALUE}};' ),
		);
		if ( $condition ) {
			$args['condition'] = $condition;
		}
		$this->add_control( $id, $args );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => __( 'Language Buttons', 'est' ) ) );

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'est' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'buttons',
				'options' => array(
					'buttons'  => __( 'Show all languages', 'est' ),
					'dropdown' => __( 'Dropdown', 'est' ),
				),
			)
		);

		$this->add_control(
			'display',
			array(
				'label'   => __( 'Label', 'est' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'code',
				'options' => array(
					'code'        => __( 'Code (EN, AR, FR)', 'est' ),
					'native'      => __( 'Native name (العربية)', 'est' ),
					'name'        => __( 'English name (Arabic)', 'est' ),
					'code_native' => __( 'Code + native name', 'est' ),
				),
			)
		);

		$this->add_control(
			'open_on',
			array(
				'label'     => __( 'Open on', 'est' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'click',
				'options'   => array(
					'click' => __( 'Click', 'est' ),
					'hover' => __( 'Hover', 'est' ),
				),
				'condition' => array( 'layout' => 'dropdown' ),
			)
		);

		$this->add_control(
			'menu_align',
			array(
				'label'     => __( 'Menu opens from', 'est' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'end',
				'options'   => array(
					'start' => __( 'Start edge', 'est' ),
					'end'   => __( 'End edge', 'est' ),
				),
				'condition' => array( 'layout' => 'dropdown' ),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'est' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array( 'title' => __( 'Start', 'est' ), 'icon' => 'eicon-h-align-left' ),
					'center'     => array( 'title' => __( 'Center', 'est' ), 'icon' => 'eicon-h-align-center' ),
					'flex-end'   => array( 'title' => __( 'End', 'est' ), 'icon' => 'eicon-h-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .est-compact' => 'justify-content: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		/* Buttons: size and shape */
		$this->start_controls_section(
			'section_style_box',
			array(
				'label' => __( 'Typography & spacing', 'est' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .est-compact a, {{WRAPPER}} .est-compact .est-switcher__current',
			)
		);


		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Space between', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-compact' => '--estc-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'layout' => 'buttons' ),
			)
		);



		$this->end_controls_section();

		$this->register_button_style_section();

		/* Dropdown menu colours */
		$this->start_controls_section(
			'section_style_colors',
			array(
				'label'     => __( 'Dropdown menu', 'est' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'layout' => 'dropdown' ),
			)
		);


		$this->color( 'menu_bg', __( 'Menu background', 'est' ), '--estc-menu-bg', '', array( 'layout' => 'dropdown' ) );
		$this->color( 'menu_text', __( 'Menu text color', 'est' ), '--estc-menu-text', '', array( 'layout' => 'dropdown' ) );
		$this->color( 'menu_border', __( 'Menu border color', 'est' ), '--estc-menu-border', '', array( 'layout' => 'dropdown' ) );
		$this->color( 'menu_hover_bg', __( 'Menu item hover background', 'est' ), '--estc-menu-hover-bg', '', array( 'layout' => 'dropdown' ) );

		$this->end_controls_section();
	}

	protected function render() {
		$s        = $this->get_settings_for_display();
		$dropdown = 'dropdown' === ( $s['layout'] ?? 'buttons' );

		$html = EST_Switcher::render(
			array(
				'style'      => $dropdown ? 'dropdown' : 'list',
				'display'    => $s['display'] ?? 'code',
				'open_on'    => $s['open_on'] ?? 'click',
				'menu_align' => $s['menu_align'] ?? 'end',
			)
		);

		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<em>' . esc_html__( 'Add a language under Sheet Translator > Languages to see the buttons.', 'est' ) . '</em>';
		}
		echo '<div class="est-compact est-compact--' . ( $dropdown ? 'dropdown' : 'buttons' ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
