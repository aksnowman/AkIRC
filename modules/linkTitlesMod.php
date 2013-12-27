<?php
function linkTitlesModLink( $link ){
    list( $proto, $domainplus ) = explode( "://", $link, 2 );
    list( $domain, $args ) = explode( "/", $domainplus, 2 );
    echo "[TitleMod] Proto: $proto Domain: $domain Args: $args\n";

    // Convert imgur direct links to "gallery"/html versions
    if( "i.imgur.com" == $domain ){
        $tmp = explode( '.', strtolower( $link ) );
        $ext = end( $tmp );
        if( in_array( $ext, array( "jpg", "png", "gif", "jpeg") ) ){
            $link = substr( $link, 0, -( 1 + strlen( $ext ) ) );
            $link = str_replace( "://i.imgur", "://imgur", $link );
            echo "[TitleMod] Link: $link\n";
        }
    }
    return $link;
}

function linkTitlesModTitle( $title, $link = '' ){
    list( $proto, $domainplus ) = explode( "://", $link, 2 );
    list( $domain, $args ) = explode( "/", $domainplus, 2 );

    // Google search results
    if( in_array( $domain, array( "google.com", "www.google.com" ) ) ){
	if( false !== strpos( $args, "&q=" ) )
	    $seperator = "&q=";
	else if( false !== strpos( $args, "#q=" ) )
	    $seperator = "#q=";
	else
	    $seperator = null;
	if( null != $seperator ){
            $tmp = explode( $seperator, $args, 2 );
            if( 1 < count( $tmp ) && "Google" == $title ){
                $tmp = explode( "&", $tmp[1] );
                echo "[TitleMod] Google query: ".urldecode( $tmp[0] )."\n";
                $title .= " - Query: ".urldecode( $tmp[0] );
            }
        }
    }
    if( in_array( $domain, array( "imgur.com", "i.imgur.com" ) ) ){
    	if( "imgur: the simple image sharer" == $title )
    	    $title = '';
    }
    return $title;
}
