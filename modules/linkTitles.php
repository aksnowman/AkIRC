<?php

function linkTitles_construct( &$bot, &$vars ){
    global $cfg;
    if( ! isset( $cfg['linkTitles'], $cfg['linkTitles']['channels'] ) )
        return false;
    $vars = array();
    foreach( $cfg['linkTitles']['channels'] as $chan => $vals )
        $bot->addHandler( 'privmsg.'.strtolower( $chan ), 'linkTitles_privmsg' );
    if( file_exists( "linkTitles.mod.php" ) )
        include_once( "linkTitles.mod.php" );
    return true;
}

function linkTitles_privmsg( &$bot, $parse ){
    global $cfg, $linkTitlesReuse;
    
    $linkReuseTimeout = 60; // Seconds until the same links title will be re-displayed
    
    if( $parse['inPM'] )
        return;
    
    if( ! isset( $linkTitlesReuse ) ) $linkTitlesReuse = array(); // Used to track repeat links
    $matches = array();
    preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $parse['msg'], $match);

    // Delete duplicates, and any that have non-ascii characters
    $tmpUsed = array();
    foreach( $match[0] as $matchKey => $link ){
        if( false !== strpos( $link, '<' ) || false !== strpos( $link, '>' ) ){ // contains < or >
            echo "[linkTitles] URL With potential HTML tag!\n";
            unset( $match[0][ $matchKey ] );
        }else if( 0 != preg_match('/[^\x20-\x7f]/', $string ) ){ // Contains non-printable/ASCII chars
            echo "[linkTitles] URL With Non-Printable/ASCII Characters!\n";
            unset( $match[0][ $matchKey ] );
        }else if( in_array( strtolower( $link ), $tmpUsed ) ){ // Duplicates
            echo "[linkTitles] Duplicate URL!\n";
            unset( $match[0][ $matchKey ] );
        }else
            $tmpUsed[] = strtolower( $link );
    }
    unset( $tmpUsed );
    // Track repeated links
    foreach( $linkTitlesReuse as $link => $timestamp ){
        if( $timestamp + $linkReuseTimeout < time() )
            unset( $linkTitlesReuse[ $link ] );
        else{
            foreach( $match[0] as $matchKey => $newLink ){
                if( 0 == strcasecmp( $newLink, $link ) ){
                    echo "[linkTitles] String used recently!\n";
                    unset( $match[0][ $matchKey ] );
                }
            }
        }
    }

    // Only use up to 3 links from a line, and verify against blacklist
    if( 3 < count( $match[0] ) ){
        echo "[linkTitles] More than 3 links, skipping...\n";
        return;
    }
    if( isset( $cfg['linkTitles'] ) ){
        foreach( $match[0] as $matchKey => $link ){
            foreach( $cfg['linkTitles']['blacklist'] as $blVal ){
                if( false !== stripos( $link, $blVal ) ){
                    echo( "[linkTitles] blacklisted: $blVal -> $link\n" );
                    unset( $match[ 0 ][ $matchKey ] );
                }
            }
        }
    }
    // Clean things up a little, and spit out the links
    foreach( $match[0] as $link ){
        if( function_exists( "linkTitlesModLink" ) )
            $link = linkTitlesModLink( $link );
        $title = linkTitles_getTitle( $link );
        if( false !== strpos( $title, "</title>" ) )
            $title = substr( $title, 0, strpos( $title, "</title>" ) );
        if( function_exists( "linkTitlesModTitle" ) )
            $title = linkTitlesModTitle( $title, $link );
        $title = substr( $title, 0, 200 );
        $linkTitlesReuse[ $link ] = time(); // Remember that we showed this link so we don't show again too soon
        if( 0 < strlen( $title ) && $parse['inChan'] )
            $bot->sendMsgHeaded( $parse['src'], "LinkInfo", "\x02Title:\x0f $title".( 1 < count( $match[0] ) ? " Link: $link" : '' ) );
    }
}

function linkTitles_getTitle($Url){
    $ctx = stream_context_create( array(
                'http'=> array(
                        'timeout' => 5
                )
            )
    );
    $str = file_get_contents( $Url, false, $ctx, -1, 5*1024 );
	$str = html_entity_decode( $str, ENT_QUOTES | ENT_HTML5 );
    $str = str_replace( "\n", "", $str );
    $str = str_replace( "\r", "", $str );
    if(strlen($str)>0){
        preg_match("/\<title\>(.*)\<\/title\>/",$str,$title);
        echo "TITLE: {$title[1]}\n";
        return trim( $title[1] );
    }
}
