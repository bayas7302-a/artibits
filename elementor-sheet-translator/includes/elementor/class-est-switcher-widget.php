<?php
/**
 * Elementor "Language Switcher" widget - drop it into the header template.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Widget_Base;

class EST_Switcher_Widget extends Widget_Base {

	public function get_name() {
		return 'est-language-switcher';
	}

	public function get_title() {
		return __( 'Language Switcher', 'est' );
	}

	public function get_icon() {
		return 'eicon-globe';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'language', 'switcher', 'translate', 'multilingual', 'rtl', 'arabic' );
	}

	public function get_style_depends() {
		return array( 'est-switcher' );
	}

	public function get_script_depends() {
		return array( 'est-switcher' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_switcher', array( 'label' => __( 'Language Switcher', 'est' ) ) );

		$this->add_control(
			'style',
			array(
				'label'   => __( 'Layout', 'est' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'dropdown',
				'options' => array(
					'dropdown' => __( 'Dropdown', 'est' ),
					'list'     => __( 'Inline list', 'est' ),
				),
			)
		);

		$this->add_control(
			'display',
			array(
				'label'   => __( 'Show', 'est' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'native',
				'options' => array(
					'native'      => __( 'Native name (العربية)', 'est' ),
					'name'        => __( 'English name (Arabic)', 'est' ),
					'code'        => __( 'Code (AR)', 'est' ),
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
				'condition' => array( 'style' => 'dropdown' ),
			)
		);

		$this->add_control(
			'menu_align',
			array(
				'label'     => __( 'Menu opens from', 'est' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'start',
				'options'   => array(
					'start' => __( 'Start edge', 'est' ),
					'end'   => __( 'End edge', 'est' ),
				),
				'condition' => array( 'style' => 'dropdown' ),
			)
		);

		$this->add_control(
			'show_current',
			array(
				'label'        => __( 'Show current language', 'est' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
				'condition'    => array( 'style' => 'list' ),
			)
		);

		$this->add_control(
			'separator',
			array(
				'label'     => __( 'Separator', 'est' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'condition' => array( 'style' => 'list' ),
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
				'selectors' => array(
					'{{WRAPPER}} .est-switcher-wrap' => 'display: flex; justify-content: {{VALUE}};',
					'{{WRAPPER}} .est-switcher--list' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* Style: links / button */
		$this->start_controls_section(
			'section_style_links',
			array(
				'label' => __( 'Links', 'est' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .est-switcher--list a, {{WRAPPER}} .est-switcher__current',
			)
		);

		$this->add_control(
			'color',
			array(
				'label'     => __( 'Color', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher--list a, {{WRAPPER}} .est-switcher__current' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'hover_color',
			array(
				'label'     => __( 'Hover color', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher--list a:hover, {{WRAPPER}} .est-switcher__current:hover' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'active_color',
			array(
				'label'     => __( 'Active language color', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher--list .is-active a' => 'color: {{VALUE}};' ),
				'condition' => array( 'style' => 'list' ),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Space between', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-switcher--list' => 'gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'style' => 'list' ),
			)
		);

		$this->end_controls_section();

		/* Style: dropdown menu */
		$this->start_controls_section(
			'section_style_menu',
			array(
				'label'     => __( 'Dropdown menu', 'est' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'style' => 'dropdown' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'menu_typography',
				'selector' => '{{WRAPPER}} .est-switcher__menu a',
			)
		);

		$this->add_control(
			'menu_color',
			array(
				'label'     => __( 'Text color', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher__menu a' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'menu_hover_color',
			array(
				'label'     => __( 'Hover text color', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher__menu a:hover' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'menu_bg',
			array(
				'label'     => __( 'Background', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher__menu' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'menu_hover_bg',
			array(
				'label'     => __( 'Hover background', 'est' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .est-switcher__menu a:hover' => 'background: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'menu_width',
			array(
				'label'      => __( 'Min width', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 80, 'max' => 400 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-switcher__menu' => 'min-width: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'item_padding',
			array(
				'label'      => __( 'Item padding', 'est' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( '{{WRAPPER}} .est-switcher__menu a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'menu_border',
				'selector' => '{{WRAPPER}} .est-switcher__menu',
			)
		);

		$this->add_control(
			'menu_radius',
			array(
				'label'      => __( 'Border radius', 'est' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px' ),
				'selectors'  => array( '{{WRAPPER}} .est-switcher__menu' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'menu_shadow',
				'selector' => '{{WRAPPER}} .est-switcher__menu',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$html = EST_Switcher::render(
			array(
				'style'        => $s['style'] ?? 'dropdown',
				'display'      => $s['display'] ?? 'native',
				'show_current' => $s['show_current'] ?? 'yes',
				'separator'    => $s['separator'] ?? '',
				'open_on'      => $s['open_on'] ?? 'click',
				'menu_align'   => $s['menu_align'] ?? 'start',
			)
		);

		if ( '' === $html && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$html = '<em>' . esc_html__( 'Add a language under Sheet Translator > Languages to see the switcher.', 'est' ) . '</em>';
		}
		echo '<div class="est-switcher-wrap">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
