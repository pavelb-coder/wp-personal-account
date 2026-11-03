<?php
/**
 * Команды: объединение нескольких аккаунтов в одну группу.
 *
 * Модель данных:
 * - команда — запись типа wppa_team (post_title = название, post_author = капитан);
 * - участник — мета пользователя _wppa_team_id + _wppa_team_role (owner|member);
 * - приглашение — токен в мета команды, ссылка вида ?wppa_team_join=<токен>.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPPA_Teams
 */
class WPPA_Teams {

	/**
	 * Тип записи команды.
	 */
	const CPT = 'wppa_team';

	/**
	 * Мета: ID команды у пользователя.
	 */
	const META_TEAM = '_wppa_team_id';

	/**
	 * Мета: роль пользователя в команде.
	 */
	const META_ROLE = '_wppa_team_role';

	/**
	 * Мета: токен приглашения.
	 */
	const META_TOKEN = '_wppa_team_invite_token';

	/**
	 * Мета: срок действия токена.
	 */
	const META_TOKEN_EXPIRES = '_wppa_team_invite_expires';

	/**
	 * Мета: дата создания команды.
	 */
	const META_CREATED = '_wppa_team_created';

	/**
	 * Срок жизни приглашения (7 дней).
	 */
	const TOKEN_TTL = 604800;

	/**
	 * Хуки.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'deleted_user', array( __CLASS__, 'on_user_deleted' ) );
	}

	/**
	 * Регистрация скрытого типа записи.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::CPT,
			array(
				'label'          => __( 'Команды', 'wp-personal-account' ),
				'public'         => false,
				'show_ui'        => false,
				'show_in_menu'   => false,
				'supports'       => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'   => true,
			)
		);
	}

	/**
	 * Максимальный размер команды.
	 *
	 * @return int
	 */
	public static function max_members() {
		$max = (int) WPPA_Plugin::setting( 'max_team_members', 10 );
		$max = max( 2, $max );

		/**
		 * Фильтр лимита участников команды.
		 *
		 * @param int $max Лимит.
		 */
		return (int) apply_filters( 'wppa_team_max_members', $max );
	}

	/* ---------------------------------------------------------------------
	 * Чтение
	 * ------------------------------------------------------------------ */

	/**
	 * ID команды пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return int
	 */
	public static function team_id( $user_id ) {
		$team_id = (int) get_user_meta( $user_id, self::META_TEAM, true );

		if ( $team_id && self::CPT === get_post_type( $team_id ) ) {
			return $team_id;
		}

		return 0;
	}

	/**
	 * Роль пользователя в команде.
	 *
	 * @param int $user_id ID пользователя.
	 * @return string owner|member|''
	 */
	public static function role( $user_id ) {
		$role = (string) get_user_meta( $user_id, self::META_ROLE, true );
		return in_array( $role, array( 'owner', 'member' ), true ) ? $role : '';
	}

	/**
	 * Является ли пользователь капитаном своей команды.
	 *
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public static function is_owner( $user_id ) {
		return 'owner' === self::role( $user_id ) && self::team_id( $user_id ) > 0;
	}

	/**
	 * Данные команды.
	 *
	 * @param int $team_id ID команды.
	 * @return array|null
	 */
	public static function get( $team_id ) {
		$post = get_post( $team_id );

		if ( ! $post || self::CPT !== $post->post_type ) {
			return null;
		}

		return array(
			'id'      => (int) $post->ID,
			'name'    => $post->post_title,
			'owner'   => (int) $post->post_author,
			'created' => (int) get_post_meta( $post->ID, self::META_CREATED, true ),
			'token'   => (string) get_post_meta( $post->ID, self::META_TOKEN, true ),
			'expires' => (int) get_post_meta( $post->ID, self::META_TOKEN_EXPIRES, true ),
			'members' => self::members( $post->ID ),
		);
	}

	/**
	 * Участники команды.
	 *
	 * @param int $team_id ID команды.
	 * @return WP_User[]
	 */
	public static function members( $team_id ) {
		$users = get_users(
			array(
				'meta_key' => self::META_TEAM, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => (int) $team_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 200,
			)
		);

		return is_array( $users ) ? $users : array();
	}

	/**
	 * Количество участников.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function members_count( $team_id ) {
		return count( self::members( $team_id ) );
	}

	/**
	 * Ссылка-приглашение в команду.
	 *
	 * @param int $team_id ID команды.
	 * @return string
	 */
	public static function invite_link( $team_id ) {
		$token = (string) get_post_meta( $team_id, self::META_TOKEN, true );

		if ( '' === $token ) {
			$token = self::refresh_token( $team_id );
		}

		return WPPA_Plugin::account_url(
			array(
				'wppa_tab'       => 'team',
				'wppa_team_join' => $token,
			)
		);
	}

	/**
	 * Есть ли активный токен.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function has_valid_token( $team_id ) {
		$token   = (string) get_post_meta( $team_id, self::META_TOKEN, true );
		$expires = (int) get_post_meta( $team_id, self::META_TOKEN_EXPIRES, true );

		return '' !== $token && $expires > time();
	}

	/**
	 * Команда по токену приглашения.
	 *
	 * @param string $token Токен.
	 * @return int ID команды или 0.
	 */
	public static function find_by_token( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );

		if ( strlen( $token ) < 16 ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => self::META_TOKEN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);

		return $found ? (int) $found[0] : 0;
	}

	/* ---------------------------------------------------------------------
	 * Изменение
	 * ------------------------------------------------------------------ */

	/**
	 * Создание команды.
	 *
	 * @param string $name    Название.
	 * @param int    $user_id Капитан.
	 * @return int|WP_Error
	 */
	public static function create( $name, $user_id ) {
		if ( self::team_id( $user_id ) ) {
			return new WP_Error( 'wppa_already_member' );
		}

		$team_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $name,
				'post_author' => (int) $user_id,
				'meta_input'  => array(
					self::META_CREATED => time(),
				),
			),
			true
		);

		if ( is_wp_error( $team_id ) ) {
			return $team_id;
		}

		update_user_meta( $user_id, self::META_TEAM, (int) $team_id );
		update_user_meta( $user_id, self::META_ROLE, 'owner' );

		self::refresh_token( $team_id );

		return (int) $team_id;
	}

	/**
	 * Переименование команды.
	 *
	 * @param int    $team_id ID команды.
	 * @param string $name    Новое название.
	 * @return bool
	 */
	public static function rename( $team_id, $name ) {
		$result = wp_update_post(
			array(
				'ID'         => (int) $team_id,
				'post_title' => $name,
			),
			true
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Перегенерация токена приглашения.
	 *
	 * @param int $team_id ID команды.
	 * @return string Новый токен.
	 */
	public static function refresh_token( $team_id ) {
		$token = wp_generate_password( 32, false, false );

		update_post_meta( $team_id, self::META_TOKEN, $token );
		update_post_meta( $team_id, self::META_TOKEN_EXPIRES, time() + self::TOKEN_TTL );

		return $token;
	}

	/**
	 * Добавление участника.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя.
	 * @return string|WP_Error Код успеха или ошибка.
	 */
	public static function add_member( $team_id, $user_id ) {
		if ( ! $team_id || ! get_post( $team_id ) ) {
			return new WP_Error( 'wppa_team_invalid_token' );
		}

		$current_team = self::team_id( $user_id );

		if ( $current_team === (int) $team_id ) {
			return new WP_Error( 'wppa_already_member' );
		}

		if ( $current_team ) {
			return new WP_Error( 'wppa_team_other' );
		}

		if ( self::members_count( $team_id ) >= self::max_members() ) {
			return new WP_Error( 'wppa_team_full' );
		}

		update_user_meta( $user_id, self::META_TEAM, (int) $team_id );
		update_user_meta( $user_id, self::META_ROLE, 'member' );

		return 'joined';
	}

	/**
	 * Выход из команды. Капитан при выходе распускает команду.
	 *
	 * @param int $user_id ID пользователя.
	 * @return string dissolved|left
	 */
	public static function leave( $user_id ) {
		$team_id = self::team_id( $user_id );

		if ( ! $team_id ) {
			return 'left';
		}

		if ( self::is_owner( $user_id ) ) {
			self::dissolve( $team_id );
			return 'dissolved';
		}

		delete_user_meta( $user_id, self::META_TEAM );
		delete_user_meta( $user_id, self::META_ROLE );

		return 'left';
	}

	/**
	 * Удаление участника капитаном.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public static function remove_member( $team_id, $user_id ) {
		if ( (int) self::team_id( $user_id ) !== (int) $team_id || self::is_owner( $user_id ) ) {
			return false;
		}

		delete_user_meta( $user_id, self::META_TEAM );
		delete_user_meta( $user_id, self::META_ROLE );

		return true;
	}

	/**
	 * Роспуск команды.
	 *
	 * @param int $team_id ID команды.
	 * @return void
	 */
	public static function dissolve( $team_id ) {
		foreach ( self::members( $team_id ) as $member ) {
			delete_user_meta( $member->ID, self::META_TEAM );
			delete_user_meta( $member->ID, self::META_ROLE );
		}

		wp_delete_post( $team_id, true );
	}

	/**
	 * Очистка данных при удалении пользователя.
	 *
	 * @param int $user_id ID пользователя.
	 * @return void
	 */
	public static function on_user_deleted( $user_id ) {
		$team_id = self::team_id( $user_id );

		if ( $team_id && self::is_owner( $user_id ) ) {
			self::dissolve( $team_id );
		}
	}

	/* ---------------------------------------------------------------------
	 * Обработка форм
	 * ------------------------------------------------------------------ */

	/**
	 * Обработчик POST-форм вкладки «Команда».
	 *
	 * @return void
	 */
	public static function handle_request() {
		check_admin_referer( 'wppa_team', 'wppa_nonce' );

		if ( ! is_user_logged_in() ) {
			WPPA_Notices::redirect( 'invalid_nonce' );
		}

		$user_id = get_current_user_id();
		$tab_url = WPPA_Plugin::tab_url( 'team' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверен выше.
		$sub = isset( $_POST['wppa_team_action'] ) ? sanitize_key( wp_unslash( $_POST['wppa_team_action'] ) ) : '';

		switch ( $sub ) {
			case 'create':
				$name = isset( $_POST['team_name'] ) ? sanitize_text_field( wp_unslash( $_POST['team_name'] ) ) : '';

				if ( '' === $name ) {
					WPPA_Notices::redirect( 'team_name_required', $tab_url );
				}

				$result = self::create( mb_substr( $name, 0, 100 ), $user_id );

				if ( is_wp_error( $result ) ) {
					WPPA_Notices::redirect( 'team_already_member', $tab_url );
				}

				WPPA_Notices::redirect( 'team_created', $tab_url );

			case 'rename':
				$team_id = self::team_id( $user_id );
				$name    = isset( $_POST['team_name'] ) ? sanitize_text_field( wp_unslash( $_POST['team_name'] ) ) : '';

				if ( ! $team_id || ! self::is_owner( $user_id ) ) {
					WPPA_Notices::redirect( 'team_owner_only', $tab_url );
				}

				if ( '' === $name ) {
					WPPA_Notices::redirect( 'team_name_required', $tab_url );
				}

				self::rename( $team_id, mb_substr( $name, 0, 100 ) );
				WPPA_Notices::redirect( 'team_renamed', $tab_url );

			case 'refresh_token':
				$team_id = self::team_id( $user_id );

				if ( ! $team_id || ! self::is_owner( $user_id ) ) {
					WPPA_Notices::redirect( 'team_owner_only', $tab_url );
				}

				self::refresh_token( $team_id );
				WPPA_Notices::redirect( 'team_token_refreshed', $tab_url );

			case 'invite_email':
				$team_id = self::team_id( $user_id );
				$email   = isset( $_POST['invite_email'] ) ? sanitize_email( wp_unslash( $_POST['invite_email'] ) ) : '';

				if ( ! $team_id || ! self::is_owner( $user_id ) ) {
					WPPA_Notices::redirect( 'team_owner_only', $tab_url );
				}

				if ( ! is_email( $email ) ) {
					WPPA_Notices::redirect( 'email_invalid', $tab_url );
				}

				$team  = self::get( $team_id );
				$body  = sprintf(
					/* translators: 1: имя пригласившего, 2: название команды, 3: ссылка */
					__( "%1\$s приглашает вас в команду «%2\$s».\n\nПрисоединиться: %3\$s\n\nСсылка действует 7 дней.", 'wp-personal-account' ),
					wp_get_current_user()->display_name,
					$team['name'],
					self::invite_link( $team_id )
				);
				$sent  = wp_mail( $email, __( 'Приглашение в команду', 'wp-personal-account' ), $body );

				WPPA_Notices::redirect( $sent ? 'team_invite_sent' : 'save_failed', $tab_url );

			case 'remove_member':
				$team_id  = self::team_id( $user_id );
				$member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;

				if ( ! $team_id || ! self::is_owner( $user_id ) ) {
					WPPA_Notices::redirect( 'team_owner_only', $tab_url );
				}

				self::remove_member( $team_id, $member_id );
				WPPA_Notices::redirect( 'team_member_removed', $tab_url );

			case 'join_code':
				$raw  = isset( $_POST['team_code'] ) ? sanitize_text_field( wp_unslash( $_POST['team_code'] ) ) : '';
				$code = self::extract_token( $raw );

				if ( '' === $code ) {
					WPPA_Notices::redirect( 'team_invalid_token', $tab_url );
				}

				self::handle_join( $code );
				break;

			case 'leave':
				$outcome = self::leave( $user_id );
				WPPA_Notices::redirect( 'dissolved' === $outcome ? 'team_dissolved' : 'team_left', $tab_url );

			default:
				WPPA_Notices::redirect( '', $tab_url );
		}
	}

	/**
	 * Извлекает токен из ссылки или кода.
	 *
	 * @param string $raw Ссылка или код.
	 * @return string
	 */
	public static function extract_token( $raw ) {
		$raw = trim( (string) $raw );

		if ( false !== strpos( $raw, 'wppa_team_join=' ) ) {
			$query = wp_parse_url( $raw, PHP_URL_QUERY );
			$args  = array();
			if ( $query ) {
				parse_str( $query, $args );
			}
			$raw = isset( $args['wppa_team_join'] ) ? (string) $args['wppa_team_join'] : '';
		}

		return preg_replace( '/[^A-Za-z0-9]/', '', $raw );
	}

	/**
	 * Присоединение по ссылке-приглашению.
	 *
	 * @param string $token Токен из URL.
	 * @return void
	 */
	public static function handle_join( $token ) {
		$tab_url = WPPA_Plugin::tab_url( 'team' );

		if ( ! is_user_logged_in() ) {
			WPPA_Notices::redirect( 'team_login_required', WPPA_Plugin::account_url( array( 'wppa_view' => 'login' ) ) );
		}

		$team_id = self::find_by_token( $token );

		if ( ! $team_id || ! self::has_valid_token( $team_id ) ) {
			WPPA_Notices::redirect( 'team_invalid_token', $tab_url );
		}

		$result = self::add_member( $team_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			WPPA_Notices::redirect( $result->get_error_code(), $tab_url );
		}

		WPPA_Notices::redirect( 'team_joined', $tab_url );
	}
}
