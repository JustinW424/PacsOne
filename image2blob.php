<?php
#if (!extension_loaded("php_imagick.dll"))
#    dl ("php_imagick.dll");

$file = stristr(getenv("OS"), "Window")? "C:/Temp/test.dcm" : "/tmp/test.dcm";

        $handle = imagick_readimage( $file );
        if ( imagick_iserror( $handle ) )
        {
                $reason      = imagick_failedreason( $handle ) ;
                $description = imagick_faileddescription( $handle ) ;

                print "failed to read $file<BR>\nReason: $reason<BR>\nDescription: $description<BR>\n" ;
		exit ;
        }
		$ext = (imagick_getlistsize($handle) > 1)? ".gif" : ".jpg";
        if ( !imagick_writeimage( $handle, $file . $ext ) )
        {
                $reason      = imagick_failedreason( $handle ) ;
                $description = imagick_faileddescription( $handle ) ;

                print "imagick_writeimage() failed<BR>\nReason: $reason<BR>\nDescription: $description<BR>\n" ;
		exit ;
        }

        $jpg = imagick_readimage( $file . $ext );
        if ( imagick_iserror( $jpg ) )
        {
                $reason      = imagick_failedreason( $jpg ) ;
                $description = imagick_faileddescription( $jpg ) ;

                print "failed to read $file<BR>\nReason: $reason<BR>\nDescription: $description<BR>\n" ;
		exit ;
        }
	if ( !( $image_data = imagick_image2blob( $jpg ) ) )
	{
                $reason      = imagick_failedreason( $jpg ) ;
                $description = imagick_faileddescription( $jpg ) ;

                print "imagick_image2blob() failed<BR>\nReason: $reason<BR>\nDescription: $description<BR>\n" ;
		exit ;
        }

	header( "Content-type: " . imagick_getmimetype( $jpg ) ) ;
	print $image_data ;
?>
