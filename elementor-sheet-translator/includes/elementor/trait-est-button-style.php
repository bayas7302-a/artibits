<?php
/**
 * Shared "Button style" controls for both switcher widgets: Elementor's own
 * Background / Border / Shadow controls for the Normal, Hover and Active
 * (current language, or an open dropdown) states.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

trait EST_Button_Style {

	/**
	 * Selectors for one state. "{{WRAPPER}} .est-switcher.est-switcher--list"
	 * is deliberately specific so it wins over the plugin's default CSS.
	 */
	private function est_btn_selector( $state ) {
		$w = '{{WRAPPER}} .est-switcher';
		switch ( $state ) {
			case 'hover':
				return "$w.est-switcher--list a:hover, $w.est-switcher--toggle a:hover, $w .est-switcher__current:hover";
			case 'active':
				return "$w.est-switcher--list .is-active a, $w.est-switcher--toggle .is-active a, $w.est-switcher--list .is-active a:hover, $w.est-switcher--toggle .is-active a:hover, $w.is-open .est-switcher__current";
			default:
				return "$w.est-switcher--list a, $w.est-switcher--toggle a, $w .est-switcher__current";
		}
	}

	protected function register_button_style_section() {
		$this->start_controls_section(
			'section_est_button_style',
			array(
				'label' => __( 'Button style', 'est' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'est_btn_padding',
			array(
				'label'      => __( 'Padding', 'est' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array( $this->est_btn_selector( 'normal' ) => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);

		$this->start_controls_tabs( 'est_btn_tabs' );
		foreach ( array(
			'normal' => __( 'Normal', 'est' ),
			'hover'  => __( 'Hover', 'est' ),
			'active' => __( 'Active', 'est' ),
		) as $state => $label ) {
			$sel = $this->est_btn_selector( $state );
			$this->start_controls_tab( 'est_btn_tab_' . $state, array( 'label' => $label ) );

			$this->add_control(
				'est_btn_text_' . $state,
				array(
					'label'     => __( 'Text color', 'est' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( $sel => 'color: {{VALUE}}; opacity: 1;' ),
				)
			);

			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'est_btn_bg_' . $state,
					'types'    => array( 'classic', 'gradient' ),
					'exclude'  => array( 'image' ),
					'selector' => $sel,
				)
			);

			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'est_btn_border_' . $state,
					'selector' => $sel,
				)
			);

			$this->add_responsive_control(
				'est_btn_radius_' . $state,
				array(
					'label'      => __( 'Border radius', 'est' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', '%' ),
					'selectors'  => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				)
			);

			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'est_btn_shadow_' . $state,
					'selector' => $sel,
				)
			);

			$this->end_controls_tab();
		}
		$this->end_controls_tabs();

		$this->add_control(
			'est_btn_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Active = the current language (or the dropdown button while it is open). Border Type "None" removes the border.', 'est' ),
				'content_classes' => 'elementor-descriptor',
				'separator'       => 'before',
			)
		);

		// The "Toggle buttons" layout also draws an outer frame around all buttons.
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'est_frame_border',
				'label'     => __( 'Outer frame border', 'est' ),
				'selector'  => '{{WRAPPER}} .est-switcher.est-switcher--toggle',
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	/** Colour control writing a CSS variable on the switcher box. */
	private function est_var_color( $id, $label, $var, $default = '', $condition = array() ) {
		$this->add_control(
			$id,
			array(
				'label'     => $label,
				'type'      => Controls_Manager::COLOR,
				'default'   => $default,
				'selectors' => array( '{{WRAPPER}} .est-compact' => $var . ': {{VALUE}};' ),
				'condition' => $condition,
			)
		);
	}

	/**
	 * "Dividers & underline" section for the Text with dividers layout.
	 *
	 * @param string $key The widget's layout control id.
	 */
	protected function register_minimal_section( $key ) {
		/* Dividers and underline (Text with dividers layout) */
		$min = array( $key => 'minimal' );
		$this->start_controls_section(
			'section_style_minimal',
			array(
				'label'     => __( 'Dividers & underline', 'est' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $min,
			)
		);

		$this->add_control( 'show_divider', array( 'label' => __( 'Divider lines', 'est' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'prefix_class' => 'est-divider-', 'condition' => $min ) );
		$this->est_var_color( 'divider_color', __( 'Divider color', 'est' ), '--estc-div-color', '', array( $key => 'minimal', 'show_divider' => 'yes' ) );
		$this->add_control(
			'divider_height',
			array(
				'label'      => __( 'Divider height', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 4, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-compact' => '--estc-div-h: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $key => 'minimal', 'show_divider' => 'yes' ),
			)
		);
		$this->add_control(
			'divider_width',
			array(
				'label'      => __( 'Divider thickness', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 6 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-compact' => '--estc-div-w: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( $key => 'minimal', 'show_divider' => 'yes' ),
			)
		);

		$this->add_control(
			'underline',
			array(
				'label'        => __( 'Underline', 'est' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'active',
				'options'      => array(
					'none'   => __( 'None', 'est' ),
					'active' => __( 'Active language', 'est' ),
					'hover'  => __( 'Active + on hover', 'est' ),
				),
				'prefix_class' => 'est-underline-',
				'separator'    => 'before',
				'condition'    => $min,
			)
		);
		$ul = array( $key => 'minimal', 'underline!' => 'none' );
		$this->est_var_color( 'underline_active', __( 'Active underline color', 'est' ), '--estc-ul-active', '', $ul );
		$this->est_var_color( 'underline_hover', __( 'Hover underline color', 'est' ), '--estc-ul-hover', '', array( $key => 'minimal', 'underline' => 'hover' ) );
		$this->add_control(
			'underline_thickness',
			array(
				'label'      => __( 'Underline thickness', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 8 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-compact' => '--estc-ul-h: {{SIZE}}{{UNIT}};' ),
				'condition'  => $ul,
			)
		);
		$this->add_control(
			'underline_width',
			array(
				'label'      => __( 'Underline width', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array( '%' => array( 'min' => 10, 'max' => 100 ), 'px' => array( 'min' => 4, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-compact' => '--estc-ul-w: {{SIZE}}{{UNIT}};' ),
				'condition'  => $ul,
			)
		);
		$this->add_control(
			'underline_offset',
			array(
				'label'      => __( 'Distance from text', 'est' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}} .est-compact' => '--estc-ul-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => $ul,
			)
		);

		$this->end_controls_section();
	}
}
