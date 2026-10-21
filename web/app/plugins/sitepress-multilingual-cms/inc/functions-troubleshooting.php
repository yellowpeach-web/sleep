<?php

function icl_reset_wpml( $blog_id = false ) {
	( new WPML\Troubleshooting\ResetService() )->run( $blog_id );
}
