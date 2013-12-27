<?php

function admin_construct( &$bot, &$vars ){
    global $cfg;
    if( file_exists( "kill.code" ) )
        die( "[admin] Kill code used! Exiting...\n" );
    if( isset( $cfg['admin'] ) )
        $bot->addHandler( 'privmsg.', 'admin_privmsg' );
    return true;
}

function admin_privmsg( &$bot, &$parse ){
    global $cfg, $modVars;
    if( $parse['inChan'] )
        return;
    if( isset( $cfg['admin']['killcode'] ) && $cfg['admin']['killcode'] == $parse['cmd'] ){
        echo "[admin] Kill code received, killing the bot! CWD=".getcwd()."\n";
        touch( "kill.code" );
        $bot->quit( "Kill code used" );
        die("[admin] die()\n" );
    }
    if( isset( $cfg['admin']['restartcode'] ) && $cfg['admin']['restartcode'] == $parse['cmd'] ){
        echo "[admin] Restart code received, restarting the bot!\n";
        $bot->quit( "Restarting!" );
        die("[admin] die()\n" );
    }
    if( isset( $cfg['admin']['reloadcode'] ) && $cfg['admin']['reloadcode'] == $parse['cmd'] ){
        echo "[admin] Reload code received, reloading config file\n";
        include( $cfg['cfg_file'] );
    }
    if( isset( $cfg['admin']['showmodvars'] ) && $cfg['admin']['showmodvars'] == $parse['cmd'] ){
        echo "[admin] Displaying \$modVars\n";
        $bot->sendMsgHeaded( $parse['nick'], "admin", str_replace( "\r", "", str_replace( "\n", "", var_export( $modVars, true ) ) ) );
    }
    if( isset( $cfg['admin']['rawcode'] ) && $cfg['admin']['rawcode'] == $parse['cmd'] ){
        echo "[admin] Raw code received, running command\n";
        $bot->sendCmd( $parse['cmdtxt'] );
    }
}

