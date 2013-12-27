<?php

// relevant.log
$date = @date("M-d-y");

@mkdir( 'relevant', 0700 );
rename( "relevant.log", "relevant/$date.log" );
touch( "relevant.log" );

mail( "jason@jason-rush.com", "Lethe - Relevant Log", file_get_contents( "relevant/$date.log") );
