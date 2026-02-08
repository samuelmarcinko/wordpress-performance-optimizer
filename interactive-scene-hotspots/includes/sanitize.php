<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize the entire project data payload.
 *
 * @param array $data Raw project data.
 * @return array
 */
function ish_sanitize_project_data( $data ) {
	$sanitized = array(
		'scenes' => array(),
	);

	if ( empty( $data['scenes'] ) || ! is_array( $data['scenes'] ) ) {
		return $sanitized;
	}

	foreach ( $data['scenes'] as $scene ) {
		$scene_id = isset( $scene['id'] ) ? sanitize_key( $scene['id'] ) : '';
		$scene_title = isset( $scene['title'] ) ? sanitize_text_field( $scene['title'] ) : '';
		$image_id = isset( $scene['imageId'] ) ? absint( $scene['imageId'] ) : 0;
		$image_url = isset( $scene['imageUrl'] ) ? esc_url_raw( $scene['imageUrl'] ) : '';

		$scene_entry = array(
			'id'       => $scene_id,
			'title'    => $scene_title,
			'imageId'  => $image_id,
			'imageUrl' => $image_url,
			'zoom'     => array(),
			'hotspots' => array(),
		);

		if ( isset( $scene['zoom'] ) && is_array( $scene['zoom'] ) ) {
			$scene_entry['zoom'] = array(
				'x' => isset( $scene['zoom']['x'] ) ? floatval( $scene['zoom']['x'] ) : 0,
				'y' => isset( $scene['zoom']['y'] ) ? floatval( $scene['zoom']['y'] ) : 0,
				'z' => isset( $scene['zoom']['z'] ) ? floatval( $scene['zoom']['z'] ) : 1,
			);
		}

		if ( isset( $scene['hotspots'] ) && is_array( $scene['hotspots'] ) ) {
			foreach ( $scene['hotspots'] as $hotspot ) {
				$hotspot_id = isset( $hotspot['id'] ) ? sanitize_key( $hotspot['id'] ) : '';
				$type       = isset( $hotspot['type'] ) ? sanitize_key( $hotspot['type'] ) : 'polygon';
				$label      = isset( $hotspot['label'] ) ? sanitize_text_field( $hotspot['label'] ) : '';
				$tooltip    = isset( $hotspot['tooltip'] ) ? wp_kses_post( $hotspot['tooltip'] ) : '';

				$hover_style = array(
					'fill'        => '#00aaff',
					'fillOpacity' => 0.3,
					'stroke'      => '#0088cc',
				);

				if ( isset( $hotspot['hover_style'] ) && is_array( $hotspot['hover_style'] ) ) {
					$hover_style['fill']        = isset( $hotspot['hover_style']['fill'] ) ? sanitize_hex_color( $hotspot['hover_style']['fill'] ) : $hover_style['fill'];
					$hover_style['fillOpacity'] = isset( $hotspot['hover_style']['fillOpacity'] ) ? floatval( $hotspot['hover_style']['fillOpacity'] ) : $hover_style['fillOpacity'];
					$hover_style['stroke']      = isset( $hotspot['hover_style']['stroke'] ) ? sanitize_hex_color( $hotspot['hover_style']['stroke'] ) : $hover_style['stroke'];
				}

				$action = array(
					'type'            => 'noop',
					'target_scene_id' => '',
					'url'             => '',
					'target_blank'    => false,
					'modal_content'   => '',
				);

				if ( isset( $hotspot['action'] ) && is_array( $hotspot['action'] ) ) {
					$action['type']            = isset( $hotspot['action']['type'] ) ? sanitize_key( $hotspot['action']['type'] ) : 'noop';
					$action['target_scene_id'] = isset( $hotspot['action']['target_scene_id'] ) ? sanitize_key( $hotspot['action']['target_scene_id'] ) : '';
					$action['url']             = isset( $hotspot['action']['url'] ) ? esc_url_raw( $hotspot['action']['url'] ) : '';
					$action['target_blank']    = ! empty( $hotspot['action']['target_blank'] );
					$action['modal_content']   = isset( $hotspot['action']['modal_content'] ) ? wp_kses_post( $hotspot['action']['modal_content'] ) : '';
				}

				$coordinates = ish_sanitize_coordinates( $type, isset( $hotspot['coordinates'] ) ? $hotspot['coordinates'] : array() );

				$scene_entry['hotspots'][] = array(
					'id'          => $hotspot_id,
					'type'        => $type,
					'coordinates' => $coordinates,
					'label'       => $label,
					'tooltip'     => $tooltip,
					'hover_style' => $hover_style,
					'action'      => $action,
				);
			}
		}

		$sanitized['scenes'][] = $scene_entry;
	}

	return $sanitized;
}

/**
 * Sanitize coordinates for a hotspot type.
 *
 * @param string $type Hotspot type.
 * @param mixed  $coordinates Raw coordinates.
 * @return array
 */
function ish_sanitize_coordinates( $type, $coordinates ) {
	switch ( $type ) {
		case 'rect':
			return array(
				'x' => ish_clamp_coordinate( isset( $coordinates['x'] ) ? $coordinates['x'] : 0 ),
				'y' => ish_clamp_coordinate( isset( $coordinates['y'] ) ? $coordinates['y'] : 0 ),
				'w' => ish_clamp_coordinate( isset( $coordinates['w'] ) ? $coordinates['w'] : 0 ),
				'h' => ish_clamp_coordinate( isset( $coordinates['h'] ) ? $coordinates['h'] : 0 ),
			);
		case 'circle':
			return array(
				'cx' => ish_clamp_coordinate( isset( $coordinates['cx'] ) ? $coordinates['cx'] : 0 ),
				'cy' => ish_clamp_coordinate( isset( $coordinates['cy'] ) ? $coordinates['cy'] : 0 ),
				'r'  => ish_clamp_coordinate( isset( $coordinates['r'] ) ? $coordinates['r'] : 0 ),
			);
		case 'polygon':
		default:
			$points = array();
			if ( is_array( $coordinates ) ) {
				foreach ( $coordinates as $point ) {
					$points[] = array(
						'x' => ish_clamp_coordinate( isset( $point['x'] ) ? $point['x'] : 0 ),
						'y' => ish_clamp_coordinate( isset( $point['y'] ) ? $point['y'] : 0 ),
					);
				}
			}
			return $points;
	}
}

/**
 * Clamp normalized coordinate values between 0 and 1.
 *
 * @param mixed $value Coordinate value.
 * @return float
 */
function ish_clamp_coordinate( $value ) {
	$float = floatval( $value );
	if ( $float < 0 ) {
		return 0;
	}
	if ( $float > 1 ) {
		return 1;
	}
	return $float;
}
