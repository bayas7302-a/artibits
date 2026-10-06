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
}
