<?php

/*
"exchange functions" args: ( $from, $to )
    Returns: false on error, else
            array( 'last' => '??????.????', 'high' => '??????.????', 'low' => '??????.????', 'avg' => '??????.????' )

*/

$deps['virtCur'][] = 'help';
$vers['virtCur'][] = '1.0.1'; // Implement later?

function virtCur_construct( &$bot, &$vars ){
    global $cfg,$help,$virtCur;
    // MtGox Currencies: USD, AUD, CAD, CHF, CNY, DKK, EUR, GBP, HKD, NZD, PLN, RUB, SGD, THB, NOK, CZK, JPY, SEK
    // vircurex currencies: ANC, BTC, DGC, DVC, FRC, FTC, I0C, IXC, LTC, NMC, NVC, PPC, TRC, WDC, XPM
    // btc-e currencies: BTC, LTC, NMC, NVC, TRC, PPC, FTC, XPM, USD, RUR, EUR
    $bot->addHandler( 'privmsg', 'virtCur_privmsg' );

    $virtCur = array(
        'from' => array(
            'btc' => array(
                'to' => array(
                    'usd' => array(
                        'mtgox',
                        'btc-e',
                        'vircurex',
                    ),
                    'eur' => array(
                        'mtgox',
                        'btc-e',
                    ),
                    'aud' => array(
                        'mtgox',
                    ),
                    'cad' => array(
                        'mtgox',
                    ),
                    'anc' => array(
                        'vircurex',
                    ),
                    'dgc' => array(
                        'vircurex',
                    ),
                    'dvc' => array(
                        'vircurex',
                    ),
                    'frc' => array(
                        'vircurex',
                    ),
                    'ftc' => array(
                        'vircurex',
                    ),
                    'i0c' => array(
                        'vircurex',
                    ),
                    'ixc' => array(
                        'vircurex',
                    ),
                    'ltc' => array(
                        'vircurex',
                        'btc-e',
                    ),
                    'nmc' => array(
                        'vircurex',
                    ),
                    'nvc' => array(
                        'vircurex',
                    ),
                    'ppc' => array(
                        'vircurex',
                    ),
                    'trc' => array(
                        'vircurex',
                    ),
                    'wdc' => array(
                        'vircurex',
                    ),
                    'xpm' => array(
                        'vircurex',
                    ),
                ),
            ),
            'ltc' => array(
                'to' => array(
                    'btc' => array(
                        'vircurex',
                        'btc-e',
                    ),
                    'eur' => array(
                        'btc-e',
                    ),
                    'usd' => array(
                        'btc-e',
                        'vircurex',
                    ),
                ),
            ),
            'ftc' => array(
                'to' => array(
                    'btc' => array(
                        'vircurex',
                    ),
                    'usd' => array(
                        'vircurex',
                    ),
                ),
            ),
        ),
        'exchange' => array(
            'mtgox' => array(
                'function' => 'virtCur_mtgox',
            ),
            'vircurex' => array(
                'function' => 'virtCur_vircurex',
            ),
            'btc-e' => array(
                'function' => 'virtCur_btc_e',
            ),
            'coinbase' => array(
                'function' => 'virtCur_coinbase',
            ),
        ),
    );
    $coinbase_currencies = virtCur_coinbase_get_currencies();
    if( is_array( $coinbase_currencies ) ){
        foreach( $coinbase_currencies as $pair ){
            $tmp = explode( "_to_", $pair );
            if( 2 != count( $tmp ) )
                continue;
            // Only adding exchange pairs where both from & to are in our "accepted" list
            $coinbase_used_currencies = array( 'btc', 'ltc', 'ftc', 'usd' );
            if( isset( $cfg['virtCur'], $cfg['virtCur']['coinbase_used_currencies'] ) )
                $coinbase_used_currencies = $cfg['virtCur']['coinbase_used_currencies'];
            if( ! in_array( $tmp[0], $coinbase_used_currencies ) || ! in_array( $tmp[1], $coinbase_used_currencies ) )
                continue;
            $virtCur['from'][$tmp[0]]['to'][$tmp[1]][] = 'coinbase';
        }
    }
    $helpTemp = array();
    foreach( $virtCur['from'] as $from => $fromData ){
        if( isset( $fromData['to'] ) ){
            foreach( $fromData['to'] as $to => $exchanges ){
                foreach( $exchanges as $exchange ){
                    $helpTemp["$exchange"]["{$from}_{$to}"] = strtoupper($from).">".strtoupper($to);
                }
            }
        }
    }

    if( ! isset( $help ) ) $help = array();
    $help[] = ".btc\tReturns current Last, High, Low, and Avg Bitcoin rates from Mt. Gox";
    $help['.vc'] = array(
        ".vc <from> [quantity] [to] [exchange]",
        "Displays virtual currency exchange rates from various exchanges",
        'ex. [mtgox BTC-USD] Last: $xx.xx High: $xx.xx Low: $xx.xx Avg: $xx.xx'
    );
    foreach( $helpTemp as $exchange => $list ){
        $nextLine = "";
        $i = 0;
        foreach( $list as $exPair ){
            if( "" == $nextLine )
                $nextLine = "$exPair";
            else
                $nextLine = implode( ", ", array( $nextLine, $exPair ) );
            $i++;
            if( $i >= 20 ){
                $help['.vc'][] = "$exchange: $nextLine";
                $nextLine = "";
                $i = 0;
            }
        }
        if( "" != $nextLine )
            $help['.vc'][] = "$exchange: $nextLine";
//        $help['.vc'][] = "$exchange: ".implode( ", ", $list );
    }
    return true;
}

function virtCur_error( &$bot, $dest ){
    $bot->sendMsgHeaded( $dest, "VirtCur", "Syntax: '.vc <from> [quantity] [to] [exchange]' For full explanation, '.help .vc'" );
}

function virtCur_privmsg( &$bot, $parse ){
    global $cfg, $virtCur;
    static $cache = array();
    static $lastPublic = array();

    if( ".vc" != $parse['cmd'] )
        return;
    $dest = $parse['nick'];

    $fQuantity = floatval( 1.0 );
    $to = null;
    $sExchange = null;

    // .vc <from> [quantity] [to] [exchange]
    if( 1 <= count( $parse['cmdargs'] ) ){
        if( ! isset( $virtCur['from'][ strtolower( $parse['cmdargs'][0] ) ] ) ){
            virtCur_error( $bot, $dest );
            return;
        }
        $from = strtolower( $parse['cmdargs'][0] );
        if( 2 <= count( $parse['cmdargs'] ) ){
            if( ! is_numeric( $parse['cmdargs'][1] ) ){
                virtCur_error( $bot, $dest );
                return;
            }
            $fQuantity = floatval( $parse['cmdargs'][1] );
            if( $fQuantity < 0 || $fQuantity > 10000000 )
                return;
            if( 3 <= count( $parse['cmdargs'] ) ){
                if( ! isset( $virtCur['from'][$from]['to'], $virtCur['from'][$from]['to'][ strtolower( $parse['cmdargs'][2] ) ] ) ){
                    virtCur_error( $bot, $dest );
                    return;
                }
                $to = strtolower( $parse['cmdargs'][2] );
                if( 4 <= count( $parse['cmdargs'] ) ){
                    if( ! in_array( strtolower( $parse['cmdargs'][3] ), $virtCur['from'][$from]['to'][ $to ] ) ){
                        virtCur_error( $bot, $dest );
                        return;
                    }
                    $sExchange = strtolower( $parse['cmdargs'][3] );
                    if( 5 <= count( $parse['cmdargs'] ) ){
                        virtCur_error( $bot, $dest );
                        return;
                    }
                }
            }
        }
    }else{
        virtCur_error( $bot, $dest );
        return;
    }
    if( null === $to ){
        if( ! isset( $virtCur['from'][$from]['to'] ) ){
            virtCur_error( $bot, $dest );
            return;
        }
        if( isset( $virtCur['from'][$from]['to']['usd'] ) ){
            $to = 'usd';
        }else{
            $tmp = array_keys( $virtCur['from'][$from]['to'] );
            $to = $tmp[0];
        }
    }

    if( null === $sExchange ){
        if( 0 == count( $virtCur['from'][$from]['to'][$to] ) ){
            virtCur_error( $bot, $dest );
            return;
        }
        $sExchange = $virtCur['from'][$from]['to'][$to][0];
    }
    if( ! isset( $virtCur['exchange'], $virtCur['exchange'][$sExchange] ) ){
        virtCur_error( $bot, $dest );
        return;
    }
    if ( $parse['inChan'] ) {
        if(
            ! isset( $lastPublic[ "{$parse['src']}_$from_$to_$sExchange" ] )
            || $lastPublic[ "{$parse['src']}_$from_$to_$sExchange" ] < time() - ( isset( $virtCur['chan_timeout'] ) ? $virtCur['chan_timeout'] : 60 )
        ) {
            $dest = $parse['src'];
        }
        $lastPublic[ "{$parse['src']}_$from_$to_$sExchange" ] = time();
    }
    $cached = false;
    if (
        isset( $cache["$from_$to_$sExchange"] )
        && $cache["$from_$to_$sExchange"]["time"] > time() - ( isset( $virtCur['cache_time'] ) ? $virtCur['cache_time'] : 60 )
    ) {
        $fLast = $cache["$from_$to_$sExchange"]["last"];
        $fAvg  = $cache["$from_$to_$sExchange"]["avg"];
        $fHigh = $cache["$from_$to_$sExchange"]["high"];
        $fLow  = $cache["$from_$to_$sExchange"]["low"];
        $cached = true;
    } else {
        $mReturn = call_user_func_array( $virtCur['exchange'][$sExchange]['function'], array( $from, $to ) );

        if( false === $mReturn ){
            $bot->sendMsgHeaded( $dest, "VirtCur", "Unable to retrieve rates, please try again later." );
            $lastPublic[ "{$parse['src']}_$from_$to_$sExchange" ] = time() - ( ( isset( $virtCur['chan_timeout'] ) ? $virtCur['chan_timeout'] : 60 ) - ( isset( $virtCur['retry_time'] ) ? $virtCur['retry_time'] : 5 ) );
            return;
        }
        $fLast = isset( $mReturn['last'] ) ? floatval( $mReturn['last'] ) : 0.0;
        $fAvg  = isset( $mReturn['avg'] ) ? floatval( $mReturn['avg'] ) : 0.0;
        $fHigh = isset( $mReturn['high'] ) ? floatval( $mReturn['high'] ) : 0.0;
        $fLow  = isset( $mReturn['low'] ) ? floatval( $mReturn['low'] ) : 0.0;
        $cache["$from_$to_$sExchange"]["time"] = time();
        $cache["$from_$to_$sExchange"]["last"] = $fLast;
        $cache["$from_$to_$sExchange"]["avg"]  = $fAvg;
        $cache["$from_$to_$sExchange"]["high"] = $fHigh;
        $cache["$from_$to_$sExchange"]["low"]  = $fLow;
    }

    $bot->sendMsgHeaded( $dest, "VirtCur ".strtoupper($from)."_".strtoupper($to),
        "\x02Exch:\x0f $sExchange ".
        "\x02Last:\x0f $fLast ".
        "\x02Avg:\x0f $fAvg ".
        "\x02High:\x0f $fHigh ".
        "\x02Low:\x0f $fLow ".
        ( 1.0 == $fQuantity
            ? ''
            : "\x02Qty:\x0f $fQuantity \x02Your Value:\x0f ".( $fQuantity *$fLast )
        ).
        ( $cached ? " Updated ".(time()-$cache["$from_$to_$sExchange"]["time"])."s ago" : '' )
    );
    return;
}

function virtCur_mtgox( $from, $to ) {
	global $cfg;
    $path = '1/'.strtoupper($from).strtoupper($to).'/ticker';

    // API settings
    extract( $cfg['virtCur']['mtgox'] );

    // generate a nonce as microtime, with as-string handling to avoid problems with 32bits systems
    $mt = explode(' ', microtime());
    $req = array( 'nonce' => $mt[1].substr($mt[0], 2, 6) );

    // generate the POST data string
    $post_data = http_build_query($req, '', '&');

    $prefix = '';
    if (substr($path, 0, 2) == '2/'){
        $prefix = substr($path, 2)."\0";
    }

    // generate the extra headers
    $headers = array(
        'Rest-Key: '.$key,
        'Rest-Sign: '.base64_encode(hash_hmac('sha512', $prefix.$post_data, base64_decode($secret), true)),
    );

    // our curl handle (initialize if required)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MtGox PHP client; '.php_uname('s').'; PHP/'.phpversion().')');

    curl_setopt($ch, CURLOPT_URL, 'https://data.mtgox.com/api/'.$path);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);

    // run the query
    $res = curl_exec($ch);
    if ($res === false){
        echo('Could not get reply: '.curl_error($ch));
        return false;
    }
    $dec = json_decode($res, true);
    if (!$dec){
        echo('Invalid data received, please make sure mtgox connection is working and requested API exists');
        return false;
    }
    if( ! isset( $dec['return'], $dec['return']['last'], $dec['return']['high'], $dec['return']['low'], $dec['return']['avg'] ) )
        return false;
    $aRet = array();
    foreach( $dec['return'] as $k => $v ){
        $k = strtolower( $k );
        if( in_array( $k, array( 'last', 'high', 'low', 'avg' ) ) )
            $aRet[ $k ] = str_replace( '$', '', str_replace( ',', '', $v['display_short'] ) );
    }
    return $aRet;
}

function virtCur_btc_e( $from, $to ){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, 'https://btc-e.com/api/2/'.strtolower($from).'_'.strtolower($to).'/ticker');
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $res = curl_exec($ch);
    if ($res === false){
        echo('Could not get reply: '.curl_error($ch));
        return false;
    }
    $dec = json_decode($res, true);
    if (!$dec){
        echo('Invalid data received, please make sure btc-e connection is working and requested API exists');
        return false;
    }
    if( ! isset( $dec['ticker'], $dec['ticker']['last'], $dec['ticker']['high'], $dec['ticker']['low'], $dec['ticker']['avg'] ) )
        return false;
    $aRet = array();
    foreach( $dec['ticker'] as $k => $v ){
        $k = strtolower( $k );
        if( in_array( $k, array( 'last', 'high', 'low', 'avg' ) ) )
            $aRet[ $k ] = str_replace( '$', '', str_replace( ',', '', $v ) );
    }
    return $aRet;
}
function virtCur_vircurex( $from, $to ){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, 'https://vircurex.com/api/get_info_for_1_currency.json?base='.strtolower($from).'&alt='.strtolower($to) );
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $res = curl_exec($ch);
    if ($res === false){
        echo('Could not get reply: '.curl_error($ch));
        return false;
    }
    $dec = json_decode($res, true);
    if (!$dec){
        echo('Invalid data received, please make sure vircurex connection is working and requested API exists');
        return false;
    }
    if( ! isset( $dec['lowest_ask'], $dec['highest_bid'], $dec['last_trade'] ) )
        return false;
    $aRet = array();
    $aMappings = array(
        'lowest_ask' => 'low',
        'highest_bid' => 'high',
        'last_trade' => 'last',
    );
    foreach( $aMappings as $mapFrom => $mapTo ){
        $aRet[ $mapTo ] = str_replace( '$', '', str_replace( ',', '', $dec[$mapFrom] ) );
    }
    $aRet['avg'] = 0;
    return $aRet;
}
function virtCur_coinbase( $from, $to ){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, 'https://coinbase.com/api/v1/currencies/exchange_rates' );
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $res = curl_exec($ch);
    if ($res === false){
        echo('Could not get reply: '.curl_error($ch));
        return false;
    }
    $dec = json_decode($res, true);
    if (!$dec){
        echo('Invalid data received, please make sure coinbase connection is working and requested API exists');
        return false;
    }
    if( ! isset( $dec["{$from}_to_{$to}"] ) )
        return false;
    return array( 'last' => $dec["{$from}_to_{$to}"] );
}

function virtCur_coinbase_get_currencies(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, 'https://coinbase.com/api/v1/currencies/exchange_rates' );
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $res = curl_exec($ch);
    if ($res === false){
        echo('Could not get reply: '.curl_error($ch));
        return false;
    }
    $dec = json_decode($res, true);
    if ( !$dec ){
        echo('Invalid data received, please make sure coinbase connection is working and requested API exists');
        return false;
    }
    return array_keys( $dec );
}