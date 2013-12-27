<?php // v1.0.3
date_default_timezone_set( "America/Anchorage" );
set_time_limit( 0 );
/* Load Config Info */
$args = $argv;
if (count($args) < 2){
    echo ("Syntax: php phpbot.php config_file.inc\n");
    exit;
}else{
    $cfg = array();
    $cfgFile = $args[1];
    echo ("Using config file: $cfgFile\n");
    //require( (string) $cfgFile ) or die( "Failed to load config file '$cfgFile'.\n" );
    include( './config.php' );
    if( count( $cfg ) == 0 )
        die( "Failed to load config file, or configuration.\n" );
    $cfg['cfg_file'] = $cfgFile;
}

file_put_contents( "phpBot.pid", getmypid() );

/* Initialize IRC library */
include_once( 'class.akIRC.php' );
$bot = new akIRC();

//$bot->debugOn();

foreach( $cfg['servers'] as $label => $server )
    $bot->addServer( $label, $server );
foreach( $cfg['nicks'] as $nick )
    $bot->addNick( $nick );

/* */
$modVars = array();

/* Load persistance information */
$persistVars = loadPersist();
if( $persistVars !== false )
    extract( $persistVars );
unset( $persistVars );

if( isset( $cfg['loadModules'] ) ){

	/* Load modules */
	if( ! file_exists( "modules" ) )
		die( syntax() );
/*
	$handle = opendir( './modules/' );
	$files = array();

	while( false !== ( $file = readdir( $handle ) ) ){
		if( in_array( strtolower( end( explode( '.', $file ) ) ), array( 'php', 'php4', 'php5' ) ) )
			$files[] = $file;
	}
	closedir( $handle );
*/
	$deps = array(); // Dependancy tracking...
	$modules = array();
	foreach( $cfg['loadModules'] as $base ){
		if( file_exists( "./modules/$base.php" ) ){
			$modules[] = $base;
			include( "./modules/$base.php" );
		}
	}

	// Format: $deps['moduleName'] = array('MySQL', 'auth'); etc...
	global $bot, $modVars, $modules;
	$loadedDeps = array();
	loadDependancies( $deps, $loadedDeps );

	/* Call module constructors */
	foreach( $modules as $mod ){
		$tmp = explode( '/', $mod );
		$mod = end( $tmp );
		if( ! in_array( $mod, $loadedDeps ) ){
			echo "[loadModules] Loading $mod\n";
			if( true !== callFunction( $mod.'_construct', $bot, $modVars[$mod] ) )
				echo "[LoadModules] !!! Initializing module failed for: $mod\n";
		}
	}
} // End loadModules

// when bot connects to server
$bot->addHandler( 'onConnect', 'onConnect' );
$bot->addHandler( 'quit', 'onQuit' );

/***********\
| MAIN LOOP |
\***********/
while( true ){
    /* Main Parsing Loop */
    $bot->loop();
    
    /* Store persistant copy of some variables between sessions */
    savePersist( array(
                        'modVars' => $modVars,
                        ) );
}

exit;

/***********\
| FUNCTIONS |
\***********/
function onConnect( &$bot, $p ){
    // Show it is a bot, and hide it has IRCOps
    $bot->mode( $bot->getNick(), "+BH" );// +BH-hoOaANC ???
}
function onQuit( &$bot, $p ){ // I don't think this ever gets called...
    global $modules, $modVars;
    // Did the bot just quit?
    if( strtolower( $p['src'] ) != strtolower( $bot->getNick() ) )
        return;
    // Unload each module *nicely*
    foreach( $modules as $mod )
        callFunction( $mod.'_destruct', $bot, $modVars[ $mod ] );
    exit; // Should it actually exit?
}
function syntax(){
    global $argc,$argv;
    echo "Usage: ".$argv[0]." configFile.php\n";
    echo "Directory ./modules/ must exist to continue operation if \$cfg['loadModules'] is defined.\n";
}
function callFunction( $func, &$arg1 = null, &$arg2 = null ){
    if( function_exists( $func ) )
        return $func( $arg1, $arg2 );
    return false;
}
/*************\
* Persistance *
\*************/
function loadPersist(){
    global $cfg;
    if( ! file_exists( $cfg['persistFile'] ) )
        return false;
    $data = file_get_contents( $cfg['persistFile'] );
    if( $data === false )
        return false;
    return unserialize( $data );
}

function savePersist( $persistArray ){
    global $cfg;
    return file_put_contents( $cfg['persistFile'], serialize( $persistArray ) );
}

// TODO: Add support for requiring minimum versions...
function loadDependancies( &$depsList, &$loaded, $curDep = null, $depTrail = array() ){
    global $bot, $modVars, $modules;

    if( empty( $depsList ) )
        return true;
    if( null == $curDep )// Pick a dependancy to start with...
        $curDep = key( $depsList );
    if( isset( $depsList[ $curDep ] ) ){
        foreach( $depsList[ $curDep ] as $key => $dep ){
            if( in_array( $dep, $depTrail ) ){
                // !!! Comment out the 2 lines for the soft-fail method to use the hard-fail method !!!
                // This method will fail soft, loading both modules in pseudo-arbitrary order
                echo "[Dependancies] !!! Circular dependancies! Loading in order...\n";
                continue;
                // This method will fail hard, not loading either module
                echo "[Dependancies] !!! Circular dependancies! Loading in order...\n";
                $loaded[] = $curDep;
                unset( $depsList[ $curDep ] );
                $prev = array_pop( $depTrail );
                $loaded[] = $prev;
                unset( $depsList[ $prev ] );
                return false;
            }
            if( ! in_array( $dep, $modules ) ){
                echo "[loadModules] !!! Dependancy does not exist: $dep\n";
                $loaded[] = $curDep;
                unset( $depsList[ $curDep ] );
                return false;
            }
            if( ! loadDependancies( $depsList, $loaded, $dep, array_merge( $depTrail, array( $curDep ) ) ) ){
                echo "[LoadModules] !!! Dependancies do not exist\n";
                return false;
            }
        }
    }
    
    $loaded[] = $curDep;
    unset( $depsList[ $curDep ] );
    echo "[LoadModules] Loading dependancies $curDep\n";
    if( true === callFunction( $curDep.'_construct', $bot, $modVars[ $curDep ] ) )
        return true;
    echo "[LoadModules] !!! Initializing module failed for: $curDep\n";
    return false;
}
