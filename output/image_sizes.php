<?php

if (!defined('ABSPATH') || !function_exists('add_filter')) {
    header( 'Status: 403 Forbidden' );
    header( 'HTTP/1.1 403 Forbidden' );
    exit();
}

class QMX_Output_Html_ImageSizes extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );

        $this->get_user_pref_sort(
            $this->collector->id . '/sort',
            array(
                'col' => 'num',
                'order' => 'asc'
            )
        );
	}

	public function output() {

		$data = $this->collector->get_data();

        $data['imagesizes'] = apply_filters( 'qmx/collect/before_output/imagesizes', $data['imagesizes'] );

		ksort($data);

        $new_data = array();
        foreach ( $data['imagesizes'] as $size => $sizes )
            foreach ( $sizes as $details ) {
                $gcd = 1;
                if ( 0 !== $details['height'] ) {
                    $num1 = $details['width'];
                    $num2 = $details['height'];

                    while ( 0 !== $num2 ) {
                           $t = $num1 % $num2;
                        $num1 = $num2;
                        $num2 = $t;
                    }

                    $gcd = $num1; // greatest common denominator
                    unset($num1,$num2);
                }

                $new_data[$details['num']] = array_merge( array(
                    'name' => $size,
                    'ratio' => 0 === $details['height'] ? 0 : $details['width'] / $details['height'],
                    'gcd' => $gcd,
                ), $details );
            }

        $data = $temp = $new_data;
        unset( $new_data );

        $data = $this->get_data_sorted_by_user_pref( $data );

        $origins = array();
        foreach ( $data as $size )
            $origins[$size['origin']] = !array_key_exists( $size['origin'], $origins )
                ? 0
                : ( $origins[$size['origin']] + 1 );

		echo '<div id="' . esc_attr( $this->collector->id() ) . '" class="qm qm-clear qm-half">';

			echo '<table cellspacing="0" class="qm-sortable">' .
				'<thead>' .
					'<tr>' .
						'<th colspan="6">Registered Image Sizes</th>' .
					'</tr>' .
					'<tr>' .
                        '<th class="qm-num' . $this->get_user_pref_sort_class( 'num' ) . '"><br />' . $this->build_sorter( 'num' ) . '</th>' .
						'<th class="' . $this->get_user_pref_sort_class( 'name', 'qm-sorted-asc' ) . '">Name' . $this->build_sorter( 'name' ) . '</th>' .
						'<th class="qm-num qm-imagesize-width' . $this->get_user_pref_sort_class( 'width' ) . '">Width' . $this->build_sorter( 'width' ) . '</th>' .
						'<th class="qm-num qm-imagesize-height' . $this->get_user_pref_sort_class( 'height' ) . '">Height' . $this->build_sorter( 'height' ) . '</th>' .
                        '<th class="qm-num qm-imagesize-ratio' . $this->get_user_pref_sort_class( 'ratio' ) . '">Ratio' . $this->build_sorter( 'ratio' ) . '</th>' .
						'<th style="width: 65px;">' .
							'<span style="white-space: nowrap;">Origin</span>' .
                            $this->build_filter( 'imagesize-origin', array_keys( $origins ), '' ) .
						'</th>' .
					'</tr>' .
				'</thead>' .
                '<tbody>';

                    $count = 0;

					foreach ( $data as $name => $size ) {
                        $origins = array();
                        $origins[$size['origin']] = 1;
                        $count++;
                        echo '<tr ' .
                            'data-id="qmx-imagesize-' . esc_attr( $size['name'] ) . '"' .
                            'data-qm-imagesize-origin="' . esc_attr( implode( ' ', array_keys( $origins ) ) ) . '" ' .
                            'data-qm-subject="' . esc_attr( $size['origin'] ) . '"' .
                        '>' .
                            '<td class="qm-num">' . esc_html( $size['num'] ) . '</td>' .
                            '<td class="qm-ltr qm-imagesize-name">' . esc_html( $size['name'] ) . '</td>' .
                            self::output_size_details( $size ) .
                        '</tr>';
					}

				echo '</tbody>' .
				'<tfoot>' .
                    '<tr class="qm-items-highlighted qm-hide"><td colspan="6">Image Sizes highlighted: <span class="qm-items-number">0</span></td></tr>' .
					'<tr><td colspan="6">Total Image Sizes: ' . $count . '</td></tr>' .
				'</tfoot>' .

            '</table>' .
        '</div>';

	}

    protected static function output_size_details( $details ) {
        return
            '<td class="qm-num qm-imagesize-width' . ( false === $details['crop'] ? ' qm-info' : '' ) . '">' .
                esc_html( $details['width'] ) .
            '</td>' .
            '<td class="qm-num qm-imagesize-height' . ( false === $details['crop'] ? ' qm-info' : '' ) . '">' .
                esc_html( $details['height'] ) .
            '</td>' .
            '<td ' .
                'title="' .
                    $details['width'] . ' / ' . $details['height'] . ' = ' .
                    ( $details['width'] / $details['gcd'] ) . ' / ' . ( $details['height'] / $details['gcd'] ) . ' = ' .
                    $details['ratio'] .
                '" ' .
                'data-qm-sort-weight="' . esc_attr( $details['ratio'] ) . '" ' .
                'class="qm-num qm-imagesize-ratio"' .
            '>' .
                (
                    ( $details['width'] / $details['gcd'] ) . ':' . ( $details['height'] / $details['gcd'] )
                        !== $details['width'] . ':' . $details['height']
                    ? esc_html( ( $details['width'] / $details['gcd'] ) . ':' . ( $details['height'] / $details['gcd'] ) )
                    : '&mdash;'
                ) .
            '</td>' .
            '<td class="qm-ltr">' . esc_html( $details['origin'] ) . '</td>';
    }

}

function register_qmx_output_html_imagesizes( array $output, QM_Collectors $collectors ) {
	if ( $collector = QM_Collectors::get( 'qmx-image_sizes' ) )
		$output['qmx-image_sizes'] = new QMX_Output_Html_ImageSizes( $collector );
	return $output;
}

?>
