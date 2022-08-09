<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'tiligre_bd' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'JT4-fWW2SdftI=<dS<ERSKii[CloyOyrFoJGnr.5%Fa0BDHKqBejRo}3Cb;NSAvp' );
define( 'SECURE_AUTH_KEY',  '#GBv8UfUJ)0v[-7fY4Re]<>_M4lhwaN9~]K56c$zN=r<` XF7#VtV]&y4/r8VB^,' );
define( 'LOGGED_IN_KEY',    '+$Z,f@wXmdmSt|/#3h;WAe78V`$w bZz--pyIH8$>0Ivgk?OyLJ$#i8{ByryFqw2' );
define( 'NONCE_KEY',        '~h(f;_nE#QK[6+XmqM;&a#|-rI(7W+<.>[<w~ZMLq9-XT>Q9mr#r@ShHnQ=x{UV$' );
define( 'AUTH_SALT',        'i+}~l9wKBKseek|0OT)S=,t,pQqTXzio[$AGm$>h`riQs+:,MEPxiXB{:dpUFK#l' );
define( 'SECURE_AUTH_SALT', 'pA%RC_e ?Kd.xlaR%0QKTd21=8 bXZ}: !!gLi$)Cf|HD}}{BiUo#14Aiid:zR,0' );
define( 'LOGGED_IN_SALT',   '7FA*oD0WO,?B&lTu-<R08H>6Qal3z#(;7s#O9(@Q0f=S5AAyk[.1/]J QBO:{ZeA' );
define( 'NONCE_SALT',       '~1AoV:uF4PEs#4Dh)&O__3ZW^W]j9^0h8aFB_yR,k!zy).p<LF`R|ikamzMaBMFl' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'tiligre_';

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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
