<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'vayutech' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'Vayutech@123' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '=2x0z[vd#I!Wl4?@-kjtSU.b/1w;}sHFrNlMM?u3XjRO.lR{*UDml=,>7*QUL&9v' );
define( 'SECURE_AUTH_KEY',   ';o#)S&MkkI0Uv)usj2qM~URsbB4p?|6|tF~1[?wq0|$ue-/_7`g|uhB]^S23x{,%' );
define( 'LOGGED_IN_KEY',     'fJhY3/M |$Pi :7Y6@{Xd~$x^5KPg;6v^|{=FsrPhly%<t(B-OYP.a<(#zTtz>k+' );
define( 'NONCE_KEY',         'B?:>VsU6G {Z6N|e[ag[k18fg=bt*c]U?rS8.+{O,U#FNvgDGi/4fhEZRXOaQKP)' );
define( 'AUTH_SALT',         'Sx2M~[AKSb5]XS>J;8P>UpU$0<Lb);Y}tg>1MdE[Lvl[?bi%AtZ91tF1!&,a{:BN' );
define( 'SECURE_AUTH_SALT',  '&y(]DW;E&q4OmPM||h=uTt |[,Gp{YdtW1p+k!rrr+XD*^l6M36Z:a(PNo=,wH.q' );
define( 'LOGGED_IN_SALT',    'Xp2Y73;EV>2V#J27-gg5])MMHw<Fj$$lnAW0b~w9yKWkU}bL5E#=^L2sz90M<Gn`' );
define( 'NONCE_SALT',        '(AoZ94WH[!YJ>cvuU7},w}.Q|M,mgeo;P}dJ(a~8)1=-sU//d@#<$$j,$%s}4I7(' );
define( 'WP_CACHE_KEY_SALT', 'bb`)wa|a:dMIH&`(l<7k/5zTR3B`*%:Nz0?fWPP ,t1}j+l*GjNCtamLT`g,83&P' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '4a5e1ac64a2537d3ad147d6558593617' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
