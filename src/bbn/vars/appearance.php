<?php
/**
 * @package bbn\var
 */
namespace bbn\vars;
/**
 * Class for using themes and fonts
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class appearance 
{
	/**
	 * jQuery UI themes
	 * @var array
	 */
	private static $themes = array(
		'black-tie',
		'blitzer',
		'cupertino',
		'dark-hive',
		'dot-luv',
		'eggplant',
		'excite-bike',
		'flick',
		'hot-sneaks',
		'humanity',
		'le-frog',
		'mint-choc' => 'mint_choco',
		'overcast',
		'pepper-grinder',
		'redmond' => 'windoze',
		'smoothness',
		'south-street',
		'start' => 'start_menu',
		'sunny',
		'swanky-purse',
		'trontastic',
		'ui-darkness' => 'ui_dark',
		'ui-lightness' => 'ui_light',
		'vader' => 'black_matte');

	/**
	 * CodeMirror themes
	 * @var array
	 */
	private static $ide_themes = array(
		'ambiance',
		'blackboard',
		'cobalt',
		'eclipse',
		'elegant',
		'erlang-dark',
		'lesser-dark',
		'monokai',
		'neat',
		'night',
		'rubyblue',
		'vibrant-ink',
		'xq-dark');

	/**
	 * Google fonts
	 * @var array
	 */
	private static $gfonts=array('Cedarville Cursive','Zeyada','Kameron','Shadows Into Light','La Belle Aurore','VT323','Cedarville Cursive','Lora','Artifika','Wire One','Muli','Tenor Sans','Maven Pro','Limelight','Nunito','Playfair Display','Goudy Bookletter 1911','Brawler','Metrophobic','Play','Carter One','Podkova','Jura','Cabin Sketch','Megrim','The Girl Next Door','Caudex','Josefin Slab','Ruslan Display','Oswald','Dawning of a New Day','Waiting for the Sunrise','Judson','Shanti','Wallpoet','Rokkitt','Raleway','Arvo','Ultra','Copse','Didact Gothic','Pacifico','Francois One','Paytone One','Holtwood One SC','Kreon','Open Sans','Michroma','Quattrocento','Kristi','Over the Rainbow','Vibur','Special Elite','Miltonian','Smythe','Irish Grover','Allerta','Bevan','Nova','Expletus Sans','Kenia','Quattrocento Sans','Sniglet','Bangers','Damion','Amaranth','EB Garamond','Droid Sans','Droid Serif','Aclonica','Anton','Yanone Kaffeesatz','Bentham','Corben','PT Serif','Sunshiney','Tangerine','Schoolbell','Crushed','Old Standard TT','Bigshot One','Arimo','Lato','Syncopate','News Cycle','Kranky','Architects Daughter','Permanent Marker','Dancing Script','Homemade Apple','Cousine','Just Another Hand','Anonymous Pro','Gruppo','Crafty Girls','Chewy','Josefin Sans','Merriweather','Neuton','Cherry Cream Soda','Unkempt','Crimson Text','PT Sans','Radley','Neucha','League Script','Luckiest Guy','UnifrakturMaguntia','Cantarell','Covered By Your Grace','Allerta Stencil','Astloch','Droid Sans Mono','Calligraffitti','Inconsolata','Cardo','Mountains of Christmas','Rock Salt','Tinos','Cabin','Sigmar One','Vollkorn','Coda','Puritan','Indie Flower','IM Fell','OFL Sorts Mill Goudy TT','Molengo','Lekton','Mako','Six Caps','Lobster','Ubuntu','Reenie Beanie','MedievalSharp','Just Me Again Down Here','Terminal Dosis Light','Geo','Slackey','Monofett','Coming Soon','Meddon','Allan','Fontdiner Swanky','Nobile','Walter Turncoat','Buda','Candal','Philosopher','Sue Ellen Francisco','UnifrakturCook','Cuprum','Maiden Orange','Orbitron','Swanky and Moo Moo','Annie Use Your Telescope');

	/**
	 * @var bool
	 */
	private static $gfonts_sorted=false;


	/**
	 * @return void 
	 */
	public static function get_themes()
	{
		return self::$themes;
	}

	/**
	 * @return void 
	 */
	public static function get_ide_themes()
	{
		return self::$ide_themes;
	}

	/**
	 * @return void 
	 */
	public static function get_gfonts()
	{
		if ( !self::$gfonts_sorted )
		{
			sort(self::$gfonts);
			self::$gfonts_sorted = 1;
		}
	}

}
?>