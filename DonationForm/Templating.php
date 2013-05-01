<?php namespace DonationForm;

class Templating {
	static public function render( $name, $data ) {
		$template = new TwigTemplate( $name );
		return $template->render( $data );
	}
}

class TwigTemplate {
	static protected $twig;

	protected $template;

	static protected function getTwig() {
		if ( !self::$twig ) {
			if ( !class_exists( 'Twig_Autoloader' ) ) {
				//FIXME:
				$twigLib = __DIR__ . "/lib/twig";
				$templateDir = __DIR__ . "/templates";
				$cacheDir = "/tmp/twig/cache";

				require_once "$twigLib/lib/Twig/Autoloader.php";
				Twig_Autoloader::register();

				$loader = new Twig_Loader_Filesystem( $templateDir );
				self::$twig = new Twig_Environment( $loader, array(
					'cache' => $cacheDir,
					'auto_reload' => true,
					'charset' => 'utf-8',
				) );
			}
		}
		return self::$twig;
	}

	function __construct( $name ) {
		$this->template = self::getTwig()->loadTemplate( "{$name}.html" );
	}

	function render( $data ) {
		return $this->template->render( $data );
	}
}
