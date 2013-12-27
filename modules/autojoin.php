<?php
function autojoin_construct( &$bot, &$vars ){
    global $cfg;
    if( ! isset( $cfg['autojoin'] ) )
        return false;
    $bot->addHandler( 'onConnect', 'autojoin_connect' );
    $bot->addHandler( 'kick', 'autojoin_connect' );
    $bot->addHandler( 'notice.', 'autojoin_notice' );
    return true;
}

// Upon receiving certain NickServ notices, [re] join the channel[s]
function autojoin_notice( &$bot, $parse ){
    global $cfg;
    if( $parse['nick'] == "NickServ" ){
        $tmp = explode( ' ', $parse['full'], 4 );
        if( count( $tmp ) > 3 ){
            // We just finished registering with services, lets try [re] joining channels!
            if( strpos( $tmp[3], 'Your password is' ) !== false && strpos( $tmp[3], '- remember this for later use.' ) !== false )
                autojoin_connect( $bot, $parse );
            // We just finished authenticating with services, lets try [re] joining channels!
            else if( strpos( $tmp[3], 'Password accepted - you are now recognized.' ) !== false )
                autojoin_connect( $bot, $parse );
        }
    }
}

// Automatically attempts to [re] join all channels
function autojoin_connect( &$bot, $parse ){
    global $cfg;

    foreach( $cfg['autojoin'] as $chan ){
        $bot->join( $chan );
        if( in_array( $parse['act'], array( 'kick' ) ) ){
	    if( function_exists( 'queueMessage' ) )
               queueMessage( time() + 6, "JOIN $chan" );
	}
    }
}
?>
