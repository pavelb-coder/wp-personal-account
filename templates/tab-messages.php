<?php
/** Вкладка личной переписки наставника и кураторов. */
defined( 'ABSPATH' ) || exit;
WPPA_Builder::render_tab( 'messages', array( 'user' => $user, 'tabs' => $tabs ) );
