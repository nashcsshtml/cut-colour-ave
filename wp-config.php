<?php
define( 'WP_CACHE', true );

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
define( 'DB_NAME', 'u116345285_36NfU' );

/** Database username */
define( 'DB_USER', 'u116345285_7c6mk' );

/** Database password */
define( 'DB_PASSWORD', 'nSPbCgcWap' );

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
define( 'AUTH_KEY',          '@i7$]NfoJdg<LX|De{)EcZNWVsHZmbD|s4`BI;].u(6NUi]Y{dIgf*$1(HTz*ni{' );
define( 'SECURE_AUTH_KEY',   'X^ss}5?QOg<=DI$v2w8L ?%Zlo4q-!fgJ>An`XWngf0E.W!YL.9s&/G]n`!2p )V' );
define( 'LOGGED_IN_KEY',     'V+?Z0db;Wr@49^KGW178n~ #([(gEen*}Ke|<Ifw%$#Qnl/,G38k+]w.*:1Z1[NY' );
define( 'NONCE_KEY',         '}~1 ]=Lc6}>AHdN&(CHR[oM6v| BUunAE`*dVXX.oYk6>i(0rJ/DHsSET(2)f_2r' );
define( 'AUTH_SALT',         '3gJE+k;N6Un%#Hevdg0;lEW+#-8Y#PyBYXh90B0BabRE8|&uL/{1MyVfb_5mf<M~' );
define( 'SECURE_AUTH_SALT',  'NAD8?ET[b,5n_l;P,kJu7G`kM/xeG{gXG$[zV/l%7A#todX<(L@CjY3]7{~F>Nm_' );
define( 'LOGGED_IN_SALT',    'FM0PKc,S];dp$M}eouOrbC2D88qgZr7<^BuA<|VikX7i}Qdm,1?jQ@OqB11rc:Lx' );
define( 'NONCE_SALT',        '2QrJP ODK/XQFfv3hm.%zq~>?wI84)z(K+,!/+jVHA2_@2i}UK)-gyF~9f9Qia5i' );
define( 'WP_CACHE_KEY_SALT', 'U6^m]w>wRb!sH^2hR*yxl+G8?g@.%s>e,C:]=`k_t!/w,t7LLH U[%Y,pJx0XrTY' );


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
define( 'COOKIEHASH', '2a7ed4f27ad2bfc810fc2c6e34adef92' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
