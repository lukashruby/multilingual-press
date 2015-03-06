<?php # -*- coding: utf-8 -*-
add_action( 'inpsyde_mlp_loaded', 'mlp_feature_user_backend_language', 0 );

/**
 * @param Inpsyde_Property_List_Interface $data
 * @return void
 */
function mlp_feature_user_backend_language( Inpsyde_Property_List_Interface $data ) {

	if ( ! is_admin() ) {
		return;
	}
	$user_lang = new Mlp_User_Backend_Language( $data->module_manager );
	$user_lang->setup();

	add_action( 'after_setup_theme', array( $user_lang, 'register_locale_filter' ) );
}