<?php
/**
 * Based on and inspired by khromov's "Query Monitor: Included files"
 * http://github.com/khromov/wp-query-monitor-included-files
 */

class QMX_Output_Html_IncludedFiles extends QM_Output_Html {

    var $data = array();

    private $hide_qm = false;
    private $hide_qmx = false;
    private $hide_core = false;

    public function __construct( QM_Collector $collector ) {
        parent::__construct( $collector );

        $this->hide_qm = defined( 'QMX_HIDE_INCLUDED_QM_FILES' ) ? QMX_HIDE_INCLUDED_QM_FILES : defined( 'QM_HIDE_SELF' ) and QM_HIDE_SELF;
        $this->hide_qmx = defined( 'QMX_HIDE_INCLUDED_SELF_FILES' ) and QMX_HIDE_INCLUDED_SELF_FILES;
        $this->hide_core = defined( 'QMX_HIDE_INCLUDED_CORE_FILES' ) && QMX_HIDE_INCLUDED_CORE_FILES;

        $this->get_user_pref_sort(
            $collector->id . '/sort', // user preference key name
            array(
                'col' => 'i',       // default column sorted
                'order' => 'asc'      // default sort order
            )
        );

        add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 101 );
        add_filter( 'qm/output/title', array( $this, 'admin_title' ), 101 );
    }

    public function collect_data() {
        $this->data['components'] = array();

        foreach ( get_included_files() as $i => $file ) {

            $component = QM_Util::get_file_component( $file )->name;

            if ( $this->hide_core && 'Core' === $component )
                continue;

            if (
                ( $this->hide_qm && 'Plugin: query-monitor' === $component )
                || ( $this->hide_qmx && 'Plugin: query-monitor-extend' === $component )
            )
                continue;

            if (array_key_exists($component,$this->data['components']))
                $this->data['components'][$component]++;
            else
                $this->data['components'][$component] = 1;

            $this->data['files'][$file] = array(
                'file' => $file,
                'i' => $i,
                'component' => $component,
                'filesize' => filesize( $file ),
                'selectors' => $this->get_selectors( $file ),
            );
        }

        $php_errors = QM_Collectors::get( 'php_errors' )->get_data();

        if (
            array_key_exists( 'errors', $php_errors )
            && is_array( $php_errors['errors'] )
            && array_key_exists( 'warning', $php_errors['errors'] )
            && is_array( $php_errors['errors']['warning'] )
            && count( $php_errors['errors']['warning'] )
        )
            foreach ( $php_errors['errors']['warning'] as $error ) {

                $component = QM_Util::get_file_component( $error->file )->name;

                if (false !== stripos( $error->message, 'No such file or directory' ) ) {
                    foreach ( array(
                        'include(',
                        'include_once(',
                    ) as $function ) {
                        $start = stripos( $error->message, $function );
                        if ( false !== $start ) break;
                    }

                    if ( false === $start )
                        continue;

                    $start = $start + strlen( $function );
                    $length = strpos( $error->message, ')', $start ) - $start;

                    if ( false === $length )
                        continue;

                    $include_file = substr( $error->message, $start, $length );

                    $selectors = array();
                    foreach ( array(
                        'including' => $error->file,
                        'included'  => $include_file,
                    ) as $status => $file )
                        $selectors = array_merge(
                            $this->get_selectors( $file, 'including' === $status ),
                            $selectors
                        );

                    $this->data['errors'][$include_file] = array(
                        'filesize'       => filesize( $error->file ),
                        'component'      => $component,
                        'including'      => $error->file,
                        'including_line' => $error->line,
                        'selectors'      => $selectors,
                    );
                }
            }
    }

    public function get_selectors( $file, $above_or_root = true ) {
        $path = dirname( str_replace( ABSPATH, '', $file ) );
        $selectors = array();

        if ( $above_or_root && strlen( trailingslashit( dirname( $file ) ) ) < strlen( ABSPATH ) ) {
            $selectors['__above-install'] = 1;
            $path = $file;
        } else {
            if ( $above_or_root && ABSPATH === trailingslashit( dirname( $file ) ) )
                $selectors['__root'] = 1;
            $path = str_replace( DIRECTORY_SEPARATOR, '-', $path );
            $path = str_replace( '_', '-', $path );
            $path = explode( '-', $path );

            if ( is_array( $path ) && count( $path ) )
                foreach ( $path as $piece ) {
                    if ( in_array( $piece, array( '', '.', ' ' ) ) )
                        continue;
                    $selectors[$piece] = 1;
                }
        }

        return $selectors;
    }

    /**
    * Adapted from:
    * http://stackoverflow.com/a/2510459
    */
    private function format_bytes_to_kb( $bytes, $precision = 2 ) {
        $bytes = max( $bytes, 0 );
        $bytes /= pow( 1000, 1 );

        return round( $bytes, $precision );
    }

    public function output() {
        $this->collect_data();

        $switched_qm = $this->get_user_pref( $this->collector->id . '/switch/includedfilescomponent/plugin-query-monitor', true );
        $switched_qmx = $this->get_user_pref( $this->collector->id . '/switch/includedfilescomponent/plugin-query-monitor-extend', true );
        $switched_core = $this->get_user_pref( $this->collector->id . '/switch/includedfilescomponent/core', true );
        $switched_theme = $this->get_user_pref( $this->collector->id . '/switch/includedfilescomponent/theme', true );
        $switched_plugins = $this->get_user_pref( $this->collector->id . '/switch/includedfilescomponent/plugin', true );

        if (
            (
                !array_key_exists( 'files', $this->data )
                || !is_array( $this->data['files'] )
                || !count( $this->data['files'] )
            ) && (
                !array_key_exists( 'errors', $this->data )
                || !is_array( $this->data['errors'] )
                || !count( $this->data['errors'] )
            )
        )
            return;

        $selectors = array();

        foreach ( array( 'files', 'errors' ) as $status ) {
            if (
                array_key_exists( $status, $this->data )
                && is_array( $this->data[$status] )
                && count( $this->data[$status] )
            )
                foreach ( $this->data[$status] as $details )
                    if (
                        array_key_exists( 'selectors', $details )
                        && is_array( $details['selectors'] )
                        && count( $details['selectors'] )
                    )
                        $selectors = array_merge( $details['selectors'], $selectors );
        }

        echo '<div id="' . esc_attr( $this->collector->id() ) . '" class="qm">';

			echo '<table cellspacing="0" class="qm-sortable">' .
				'<thead>' .
					'<tr>' .
						'<th colspan="4">Included Files' .
                            '<span class="qmx-switches">' .
                                $this->build_switch( 'includedfilescomponent', 'Core', 'Core', $this->hide_core, ( $this->hide_core ? false : $switched_core ) ) .
                                $this->build_switch( 'includedfilescomponent', 'Plugin: query-monitor', 'QM', $this->hide_qm, ( $this->hide_qm ? false : $switched_qm ) ) .
                                $this->build_switch( 'includedfilescomponent', 'Plugin: query-monitor-extend', 'QMX', $this->hide_qmx, ( $this->hide_qmx ? false : $switched_qmx ) );
                                if ( array_key_exists( 'Theme', $this->data['components'] ) )
                                    echo $this->build_switch( 'includedfilescomponent', 'Theme', 'Theme', false, $switched_theme );
                                if ( preg_grep( "/^Plugin: .*$/", array_keys( $this->data['components'] ) ) )
                                    echo $this->build_switch( 'includedfilescomponent', 'Plugin: ', 'Plugins', false, $switched_plugins );
                            echo '</span>' .
                        '</th>' .
					'</tr>' .
					'<tr>' .
                        '<th class="qm-num' . $this->get_user_pref_sort_class( 'i', ' qm-sorted-asc' ) . '"><br />' . $this->build_sorter( 'i' ) . '</th>' .
						'<th class="' . $this->get_user_pref_sort_class( 'file' ) . '">File' .
                            '<span class="flex">' .
                                '<span>' .
        							str_replace(
        								'class="qm-sort-controls"',
        								'class="qm-sort-controls" style="text-align: left !important;"',
        								$this->build_sorter( 'file' )
        							) .
                                '</span>' .
                                '<span>' .
                                    $this->build_filter( 'includedfilespath', array_keys( $selectors ), '' ) .
                                '</span>' .
                            '</span>' .
                        '</th>' .
						'<th class="qm-num qmx-includedfiles-filesize' . $this->get_user_pref_sort_class( 'filesize' ) . '">Filesize' . $this->build_sorter( 'filesize' ) . '</th>' .
						'<th class="qmx-includedfiles-component">Component' .
                            $this->build_filter( 'includedfilescomponent', array_keys( $this->data['components'] ), '' ) .
						'</th>' .
					'</tr>' .
				'</thead>' .
                '<tbody>';

                    $this->data['files'] = $this->get_data_sorted_by_user_pref( $this->data['files'] );

                    $filesize = $visible = 0;

                    foreach ( array( 'errors', 'files' ) as $status )
                        if (
                            array_key_exists( $status, $this->data )
                            && is_array( $this->data[$status] )
                            && count( $this->data[$status] )
                        )
                            foreach ( $this->data[$status] as $path => $details ) {
                                $classes = array();

                                if ( 'files' === $status ) {
                                    $filesize = $filesize + intval( $details['filesize'] );

                                    if ( !$switched_plugins && false !== strpos( $details['component'], 'Plugin: ' ) )
                                        $classes['qm-hide'] = 1;

                                    if ( !$switched_qm && 'Plugin: query-monitor' === $details['component'] )
                                        $classes['qm-hide'] = 1;

                                    if ( !$switched_qmx && 'Plugin: query-monitor-extend' === $details['component'] )
                                            $classes['qm-hide'] = 1;

                                    if ( !$switched_core && 'Core' === $details['component'] )
                                        $classes['qm-hide'] = 1;

                                    if ( !$switched_theme && 'Theme' === $details['component'] )
                                        $classes['qm-hide'] = 1;

                                    if ( !array_key_exists( 'qm-hide', $classes ) )
                                        $visible++;
                                } else if ( 'errors' === $status )
                                    $classes['qm-warn'] = 1;

                                echo '<tr ' .
                                        'data-qm-includedfilespath="' . esc_attr( implode( ' ', array_keys( $details['selectors'] ) ) ) . '" ' .
                                        'data-qm-includedfilescomponent="' . esc_attr( $details['component'] ) . '"' .
                                        ( count( $classes )
                                            ? ' class="' . implode( ' ', array_keys( $classes ) ) . '"'
                                            : '' ) .
                                    '>' .
                                        '<td class="qm-num" data-qm-sort-weight="' . ( 'errors' === $status ? 0 : ( $details['i'] + 1 ) ) . '">' .
                                            ( 'errors' === $status ? ' ' : ( $details['i'] + 1 ) ) .
                                        '</td>' .
                                        '<td data-qm-sort-weight="' . esc_attr( str_replace( '.php', '', strtolower( $path ) ) ) . '">' .
                                            (
                                                'errors' === $status
                                                    || strlen( trailingslashit( dirname( $path ) ) ) < strlen( ABSPATH )
                                                ? esc_html( $path ) .
                                                    (
                                                        array_key_exists( 'including', $details)
                                                            && array_key_exists( 'including_line', $details )
                                                        ? '<br /><span class="qm-info">&nbsp;' . esc_html( $details['including'] . ':' . $details['including_line'] )
                                                        : ''
                                                    )
                                                : '<abbr title="' . esc_attr( $path ) . '">' .
                                                    esc_html( false === $this->get_relative_path( $path ) ? $path : './' . $this->get_relative_path( $path ) ) .
                                                '</abbr>'
                                            ) .
                                        '</td>' .
                                        '<td ' .
                                            'class="qm-num qmx-includedfiles-filesize" ' .
                                            'data-qm-sort-weight="' .
                                                (
                                                    'errors' === $status
                                                    ? '0'
                                                    : esc_attr( $details['filesize'] )
                                                ) .
                                            '"' .
                                        '>' .
                                            (
                                                'errors' === $status
                                                ? ' '
                                                : esc_html( number_format_i18n( $details['filesize'] / 1024, 2 ) ) . ' KB'
                                            ) .
                                        '</td>' .
                                        '<td class="qmx-includedfiles-component">' . esc_html( $details['component'] ) . '</td>' .
                                '</tr>';
                            }

				echo '</tbody>' .
				'<tfoot>' .
                    '<tr class="qm-items-shown' . ( !$this->has_user_pref( $this->collector->id . '/show' ) && count( $this->data['files'] ) === $visible ? ' qm-hide' : '' ) . '">' .
                        '<td colspan="4">Files in filter: <span class="qm-items-number">' . $visible . '</span></td>' .
                        '<td colspan="2" class="qm-hide">Total in filter: <span class="qm-items-filesize">0<span></td>' .
                    '</tr>' .
					'<tr>' .
                        '<td colspan="2">Total files: ' . count( $this->data['files'] ) . '</td>' .
                        '<td colspan="2">Total: ' . number_format_i18n( $filesize / 1024, 2) . ' KB Average: ' . number_format_i18n( ( $filesize / count( $this->data['files'] ) ) / 1024, 2) . ' KB</td>' .
                    '</tr>' .
				'</tfoot>' .

            '</table>' .
        '</div>';
    }

    public function get_user_pref_sorter( $data, $user_pref ) {
        $sorter = array();
        foreach ( $this->data['files'] as $details )
            $sorter[] = str_replace( '.php', '', strtolower( $details[$user_pref['col']] ) );

        return $sorter;
    }

    public function build_switch( $filter, $value, $label, $disabled = false, $checked = true ) {
        $user_pref_key = $this->collector->id . '/switch/' . esc_attr( strtolower( $filter ) ) . '/' . esc_attr( strtolower( sanitize_title_with_dashes( $value ) ) );
        return '<label class="qmx-switch">' .
            '<input type="checkbox" ' .
                'data-filter="' . esc_attr( $filter ) . '" ' .
                'value="' . esc_attr( $value ) . '" ' .
                'data-qm-user-option-key="' . esc_attr( $user_pref_key ) . '"' .
                disabled( true, $disabled, false ) .
                checked( true, $checked, false ) .
            ' /><span class="slider"></span><span>' . esc_html( $label ) . '</span>' .
        '</label>';
    }

    public function get_relative_path( $path ) {
        if ( false === stripos( $path, ABSPATH ) )
            return false;

        return str_replace( ABSPATH, '', $path );
    }

    public function admin_title( array $title ) {

        $title[] = sprintf(
            _x( '%s%s<small>F</small>', 'number of included files', 'query-monitor' ),
            (
                array_key_exists( 'files', $this->data ) && is_array( $this->data['files'] )
                ? count( $this->data['files'] )
                : 0
            ),
            (
                array_key_exists( 'errors', $this->data )
                    && is_array( $this->data['errors'] )
                    && 0 !== count( $this->data['errors'] )
                ? '/' . count( $this->data['errors'] )
                : ''
            )
        );

        return $title;
    }

    public function admin_menu( array $menu ) {

        $add = array(
            'title' => sprintf(
                __( 'Included files (%s%s)', 'query-monitor' ),
                (
                    array_key_exists( 'files', $this->data )
                        && is_array( $this->data['files'] )
                    ? count( $this->data['files'] )
                    : 0
                ),
                (
                    array_key_exists( 'errors', $this->data )
                        && is_array( $this->data['errors'] )
                        && 0 !== count( $this->data['errors'] )
                    ? '/' . count( $this->data['errors'] )
                    : ''
                )
            )
        );

        if (
            array_key_exists( 'errors', $this->data )
            && is_array( $this->data['errors'] )
            && 0 !== count( $this->data['errors'] )
        )
            $add['meta']['classname'] = 'qm-alert';

        $menu[] = $this->menu( $add );

        return $menu;
    }
}

function register_qmx_output_html_includedfiles( array $output, QM_Collectors $collectors ) {
	if ( $collector = QM_Collectors::get( 'qmx-included_files' ) )
		$output['qmx-included_files'] = new QMX_Output_Html_IncludedFiles( $collector );
	return $output;
}
