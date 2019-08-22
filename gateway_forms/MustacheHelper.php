<?php

class MustacheHelper {

	/**
	 * Do the rendering. Can be made protected when we're off PHP 5.3.
	 *
	 * @param string $fileName full path to template file
	 * @param array $data rendering context
	 * @param array $options options for LightnCandy::compile function
	 * @return string rendered template
	 */
	public static function render( $fileName, $data, $options = [] ) {
		$defaultOptions = [
			'flags' => LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ADVARNAME,
		];
		if ( isset( $options['flags'] ) ) {
			$options['flags'] = $options['flags'] | $defaultOptions['flags'];
		} else {
			$options = $options + $defaultOptions;
		}

		if ( !file_exists( $fileName ) ) {
			throw new RuntimeException( "Template file unavailable: [$fileName]" );
		}
		$template = file_get_contents( $fileName );
		if ( $template === false ) {
			throw new RuntimeException( "Template file unavailable: [$fileName]" );
		}

		// TODO: Use MW-core implementation once it allows helper functions
		$code = LightnCandy::compile( $template, $options );
		if ( !$code ) {
			throw new RuntimeException( 'Couldn\'t compile template!' );
		}
		if ( substr( $code, 0, 5 ) === '<?php' ) {
			$code = substr( $code, 5 );
		}
		$renderer = eval( $code );
		if ( !is_callable( $renderer ) ) {
			throw new RuntimeException(
				"Can't run compiled template! Template: '$code'"
			);
		}

		$html = call_user_func( $renderer, $data, [] );

		return $html;
	}
}
