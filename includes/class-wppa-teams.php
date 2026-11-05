<?php
/**
 * Команды: объединение нескольких аккаунтов в одну группу.
 *
 * Модель данных:
 * - команда — запись типа wppa_team (post_title = название, post_author = капитан);
 * - участник — мета пользователя _wppa_team_id + _wppa_team_role (owner|mentor|member);
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
	 * Допустимые роли в команде.
	 */
	const ROLES = array( 'owner', 'mentor', 'member' );

	/**
	 * Мета: пользователь — куратор (статус на уровне сайта, не роль в команде).
	 */
	const META_CURATOR = '_wppa_curator';

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
	 * Мета: индивидуальный лимит участников (0 или пусто — общий).
	 */
	const META_LIMIT = '_wppa_team_limit';

	/**
	 * Мета: индивидуальный минимум участников (0 или пусто — общий).
	 */
	const META_MIN = '_wppa_team_min';

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

	/**
	 * Общий минимум участников команды.
	 *
	 * @return int
	 */
	public static function min_members() {
		$min = (int) WPPA_Plugin::setting( 'min_team_members', 2 );
		$min = max( 1, $min );
		$max = self::max_members();

		if ( $min > $max ) {
			$min = $max;
		}

		/**
		 * Фильтр общего минимума участников.
		 *
		 * @param int $min Минимум.
		 */
		return (int) apply_filters( 'wppa_team_min_members', $min );
	}

	/**
	 * Минимум участников конкретной команды.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function team_min( $team_id ) {
		$min = (int) get_post_meta( (int) $team_id, self::META_MIN, true );

		if ( $min >= 1 ) {
			// Минимум не может превышать лимит команды.
			$min = min( $min, self::team_limit( $team_id ) );

			/**
			 * Фильтр минимума конкретной команды.
			 *
			 * @param int $min     Минимум.
			 * @param int $team_id ID команды.
			 */
			return (int) apply_filters( 'wppa_team_min', $min, (int) $team_id );
		}

		return self::min_members();
	}

	/**
	 * Индивидуальный минимум команды (0 — используется общий).
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function raw_team_min( $team_id ) {
		$min = (int) get_post_meta( (int) $team_id, self::META_MIN, true );
		return $min >= 1 ? $min : 0;
	}

	/**
	 * Установка минимума команды.
	 *
	 * @param int $team_id ID команды.
	 * @param int $min     Новый минимум; 0 — вернуть общий.
	 * @return void
	 */
	public static function set_team_min( $team_id, $min ) {
		$min = (int) $min;

		if ( $min < 1 ) {
			delete_post_meta( (int) $team_id, self::META_MIN );
			return;
		}

		update_post_meta( (int) $team_id, self::META_MIN, min( self::team_limit( $team_id ), $min ) );
	}

	/**
	 * Сколько участников не хватает до минимума.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function missing_members( $team_id ) {
		$missing = self::team_min( $team_id ) - self::members_count( $team_id );
		return max( 0, (int) $missing );
	}

	/**
	 * Команда не добрала минимум участников.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function is_below_min( $team_id ) {
		return self::missing_members( $team_id ) > 0;
	}

	/**
	 * Требуется ли наставник в команде (настройка плагина).
	 *
	 * @return bool
	 */
	public static function require_mentor() {
		/**
		 * Фильтр требования наставника.
		 *
		 * @param bool $required Требуется ли наставник.
		 */
		return (bool) apply_filters( 'wppa_require_mentor', (bool) WPPA_Plugin::setting( 'require_mentor', 1 ) );
	}

	/**
	 * Есть ли в команде наставник.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function has_mentor( $team_id ) {
		return $team_id && self::mentors_count( $team_id ) > 0;
	}

	/**
	 * В команде не хватает наставника при включённом требовании.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function is_missing_mentor( $team_id ) {
		if ( ! self::require_mentor() ) {
			return false;
		}

		return $team_id && ! self::has_mentor( $team_id );
	}

	/**
	 * Команда сформирована: набран минимум участников и (если требуется) есть наставник.
	 *
	 * @param int $team_id ID команды.
	 * @return bool
	 */
	public static function is_complete( $team_id ) {
		if ( ! $team_id || self::is_below_min( $team_id ) ) {
			return false;
		}

		return ! self::is_missing_mentor( $team_id );
	}

	/**
	 * Что мешает считать команду сформированной.
	 *
	 * Ключи: members (сколько не хватает), mentor (нет наставника).
	 *
	 * @param int $team_id ID команды.
	 * @return array
	 */
	public static function gaps( $team_id ) {
		return array(
			'members' => (int) self::missing_members( $team_id ),
			'mentor'  => (bool) self::is_missing_mentor( $team_id ),
		);
	}

	/**
	 * Лимит участников конкретной команды.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function team_limit( $team_id ) {
		$limit = (int) get_post_meta( (int) $team_id, self::META_LIMIT, true );

		if ( $limit >= 2 ) {
			/**
			 * Фильтр лимита конкретной команды.
			 *
			 * @param int $limit   Лимит команды.
			 * @param int $team_id ID команды.
			 */
			return (int) apply_filters( 'wppa_team_limit', min( 100, $limit ), (int) $team_id );
		}

		return self::max_members();
	}

	/**
	 * Индивидуальный лимит команды (0 — если используется общий).
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function raw_team_limit( $team_id ) {
		$limit = (int) get_post_meta( (int) $team_id, self::META_LIMIT, true );
		return $limit >= 2 ? $limit : 0;
	}

	/**
	 * Установка лимита команды.
	 *
	 * @param int $team_id ID команды.
	 * @param int $limit   Новый лимит; 0 — вернуть общий лимит из настроек.
	 * @return void
	 */
	public static function set_team_limit( $team_id, $limit ) {
		$limit = (int) $limit;

		if ( $limit < 2 ) {
			delete_post_meta( (int) $team_id, self::META_LIMIT );
			return;
		}

		update_post_meta( (int) $team_id, self::META_LIMIT, min( 100, $limit ) );
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
		return in_array( $role, self::ROLES, true ) ? $role : '';
	}

	/**
	 * Человекочитаемое название роли.
	 *
	 * @param string $role owner|mentor|member.
	 * @return string
	 */
	public static function role_label( $role ) {
		switch ( $role ) {
			case 'owner':
				return __( 'Капитан', 'wp-personal-account' );
			case 'mentor':
				return __( 'Наставник', 'wp-personal-account' );
			case 'curator':
				return __( 'Куратор', 'wp-personal-account' );
			default:
				return __( 'Участник', 'wp-personal-account' );
		}
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
	 * Является ли пользователь наставником своей команды.
	 *
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public static function is_mentor( $user_id ) {
		return 'mentor' === self::role( $user_id ) && self::team_id( $user_id ) > 0;
	}

	/**
	 * Может ли пользователь управлять составом: капитан или наставник.
	 *
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public static function is_manager( $user_id ) {
		return self::is_owner( $user_id ) || self::is_mentor( $user_id );
	}

	/**
	 * Куратор ли пользователь.
	 *
	 * Куратор — статус на уровне сайта, а не роль в команде: он не занимает место
	 * в лимите, не идёт в зачёт минимума и не получает кнопку перехода.
	 *
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return bool
	 */
	public static function is_curator( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		return '1' === (string) get_user_meta( $user_id, self::META_CURATOR, true );
	}

	/**
	 * Назначить или снять куратора.
	 *
	 * @param int  $user_id ID пользователя.
	 * @param bool $on      true — назначить, false — снять.
	 * @return bool
	 */
	public static function set_curator( $user_id, $on = true ) {
		$user_id = (int) $user_id;

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return false;
		}

		if ( $on ) {
			update_user_meta( $user_id, self::META_CURATOR, '1' );

			return true;
		}

		delete_user_meta( $user_id, self::META_CURATOR );

		return true;
	}

	/**
	 * Все кураторы сайта.
	 *
	 * @return WP_User[]
	 */
	public static function curators() {
		$users = get_users(
			array(
				'meta_key'   => self::META_CURATOR,
				'meta_value' => '1',
			)
		);

		return is_array( $users ) ? $users : array();
	}

	/**
	 * Все команды сайта (сначала новые).
	 *
	 * @param int $limit Сколько вернуть.
	 * @return array Список: id, name, owner, participants, mentors, complete, missing.
	 */
	public static function all_teams( $limit = 200 ) {
		$posts = get_posts(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'numberposts' => max( 1, (int) $limit ),
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$out = array();

		foreach ( (array) $posts as $post ) {
			$owner = get_userdata( (int) $post->post_author );

			$out[] = array(
				'id'           => (int) $post->ID,
				'name'         => (string) $post->post_title,
				'owner'        => $owner ? $owner->display_name : '',
				'owner_id'     => (int) $post->post_author,
				'participants' => (int) self::members_count( (int) $post->ID ),
				'mentors'      => (int) self::mentors_count( (int) $post->ID ),
				'complete'     => (bool) self::is_complete( (int) $post->ID ),
				'missing'      => (int) self::missing_members( (int) $post->ID ),
			);
		}

		return $out;
	}

	/**
	 * Может ли пользователь управлять конкретной командой.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return bool
	 */
	public static function can_manage_team( $team_id, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $team_id || ! $user_id || self::CPT !== get_post_type( $team_id ) ) {
			return false;
		}

		if ( self::is_curator( $user_id ) ) {
			return true;
		}

		return (int) self::team_id( $user_id ) === $team_id && self::is_manager( $user_id );
	}

	/**
	 * Какую команду куратор открыл для управления: ?wppa_team=ID.
	 *
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return int ID команды или 0.
	 */
	public static function viewing_team_id( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id || ! self::is_curator( $user_id ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только чтение номера команды.
		$team_id = isset( $_GET['wppa_team'] ) ? absint( wp_unslash( $_GET['wppa_team'] ) ) : 0;

		if ( ! $team_id || self::CPT !== get_post_type( $team_id ) ) {
			return 0;
		}

		return $team_id;
	}

	/**
	 * Команда, которой касается действие из формы.
	 *
	 * Куратор работает с любой командой (team_id приходит скрытым полем),
	 * остальные — только со своей.
	 *
	 * @param int $user_id ID пользователя (0 — текущий).
	 * @return int
	 */
	public static function request_team_id( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $user_id && self::is_curator( $user_id ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce проверяется в handle_request().
			$posted = isset( $_POST['team_id'] ) ? absint( wp_unslash( $_POST['team_id'] ) ) : 0;

			if ( $posted && self::CPT === get_post_type( $posted ) ) {
				return $posted;
			}

			$viewed = self::viewing_team_id( $user_id );

			if ( $viewed ) {
				return $viewed;
			}
		}

		return (int) self::team_id( $user_id );
	}

	/**
	 * Наставники команды.
	 *
	 * @param int $team_id ID команды.
	 * @return WP_User[]
	 */
	public static function mentors( $team_id ) {
		$mentors = array();

		foreach ( self::members( $team_id ) as $member ) {
			if ( 'mentor' === self::role( $member->ID ) ) {
				$mentors[] = $member;
			}
		}

		return $mentors;
	}

	/**
	 * Смена роли участника команды. Капитана изменить нельзя.
	 *
	 * @param int    $team_id ID команды.
	 * @param int    $user_id ID пользователя.
	 * @param string $role    mentor|member (капитан так не назначается).
	 * @return bool
	 */
	public static function set_role( $team_id, $user_id, $role ) {
		// Роль капитана через этот метод не выдаётся.
		if ( ! in_array( $role, array( 'mentor', 'member' ), true ) ) {
			return false;
		}

		if ( (int) self::team_id( $user_id ) !== (int) $team_id ) {
			return false;
		}

		// Капитан назначается только при создании/передаче команды.
		if ( 'owner' === self::role( $user_id ) ) {
			return false;
		}

		update_user_meta( (int) $user_id, self::META_ROLE, 'mentor' === $role ? 'mentor' : 'member' );

		return true;
	}

	/**
	 * Назначение наставника.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public static function make_mentor( $team_id, $user_id ) {
		return self::set_role( $team_id, $user_id, 'mentor' );
	}

	/**
	 * Снятие роли наставника.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id ID пользователя.
	 * @return bool
	 */
	public static function dismiss_mentor( $team_id, $user_id ) {
		return self::set_role( $team_id, $user_id, 'member' );
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
			'members'         => self::members( $post->ID ),
			'participants'    => self::members_count( $post->ID ),
			'mentors'         => self::mentors( $post->ID ),
			'mentors_count'   => self::mentors_count( $post->ID ),
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
	 * Участники команды без наставников (наставник не считается участником).
	 *
	 * @param int $team_id ID команды.
	 * @return WP_User[]
	 */
	public static function participants( $team_id ) {
		$participants = array();

		foreach ( self::members( $team_id ) as $member ) {
			if ( 'mentor' === self::role( $member->ID ) ) {
				continue;
			}

			$participants[] = $member;
		}

		return $participants;
	}

	/**
	 * Количество участников команды (без наставников): капитан + обычные участники.
	 *
	 * Именно это число сравнивается с минимумом и лимитом команды.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function members_count( $team_id ) {
		return count( self::participants( $team_id ) );
	}

	/**
	 * Количество наставников команды.
	 *
	 * @param int $team_id ID команды.
	 * @return int
	 */
	public static function mentors_count( $team_id ) {
		return count( self::mentors( $team_id ) );
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

		// Наставники не считаются участниками и не занимают место в лимите.
		if ( self::members_count( $team_id ) >= self::team_limit( $team_id ) ) {
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
		// Файлы куратора живут вместе с командой: распустили — удалили.
		if ( class_exists( 'WPPA_Curator_Files' ) ) {
			WPPA_Curator_Files::clear( $team_id );
		}

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
	 * Капитан или наставник именно этой команды.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id Пользователь.
	 * @return bool
	 */
	public static function is_manager_of( $team_id, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		return $team_id && $user_id && (int) self::team_id( $user_id ) === $team_id && self::is_manager( $user_id );
	}

	/**
	 * Капитан именно этой команды.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id Пользователь.
	 * @return bool
	 */
	public static function is_captain_of( $team_id, $user_id = 0 ) {
		$team_id = (int) $team_id;
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		return $team_id && $user_id && (int) self::team_id( $user_id ) === $team_id && 'owner' === self::role( $user_id );
	}

	/**
	 * Адрес вкладки «Команда» с сохранением открытой команды куратора.
	 *
	 * @param int $team_id ID команды.
	 * @param int $user_id Пользователь.
	 * @return string
	 */
	public static function context_url( $team_id, $user_id = 0 ) {
		$url     = WPPA_Plugin::tab_url( 'team' );
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( $team_id && self::is_curator( $user_id ) ) {
			$url = add_query_arg( 'wppa_team', (int) $team_id, $url );
		}

		return $url;
	}

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
				$team_id = self::request_team_id( $user_id );
				$name    = isset( $_POST['team_name'] ) ? sanitize_text_field( wp_unslash( $_POST['team_name'] ) ) : '';
				$back    = self::context_url( $team_id, $user_id );

				// Переименование — только капитан этой команды. Куратору недоступно.
				if ( ! $team_id || ! self::is_captain_of( $team_id, $user_id ) ) {
					WPPA_Notices::redirect( 'team_owner_only', $back );
				}

				if ( '' === $name ) {
					WPPA_Notices::redirect( 'team_name_required', $back );
				}

				self::rename( $team_id, mb_substr( $name, 0, 100 ) );
				WPPA_Notices::redirect( 'team_renamed', $back );

			case 'refresh_token':
				$team_id = self::request_team_id( $user_id );
				$back    = self::context_url( $team_id, $user_id );

				// Ссылку-приглашение обновляют капитан и наставник команды, но не куратор.
				if ( ! $team_id || ! self::is_manager_of( $team_id, $user_id ) ) {
					WPPA_Notices::redirect( 'team_manager_only', $back );
				}

				self::refresh_token( $team_id );
				WPPA_Notices::redirect( 'team_token_refreshed', $back );

			case 'invite_email':
				$team_id = self::request_team_id( $user_id );
				$email   = isset( $_POST['invite_email'] ) ? sanitize_email( wp_unslash( $_POST['invite_email'] ) ) : '';
				$back    = self::context_url( $team_id, $user_id );

				// Приглашать участников может капитан и наставник команды, но не куратор.
				if ( ! $team_id || ! self::is_manager_of( $team_id, $user_id ) ) {
					WPPA_Notices::redirect( 'team_manager_only', $back );
				}

				if ( ! is_email( $email ) ) {
					WPPA_Notices::redirect( 'email_invalid', $back );
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

				WPPA_Notices::redirect( $sent ? 'team_invite_sent' : 'save_failed', $back );

			case 'remove_member':
				$team_id    = self::request_team_id( $user_id );
				$as_curator = self::is_curator( $user_id );
				$member_id  = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;

				if ( ! $team_id || ( ! $as_curator && ! self::is_manager( $user_id ) ) ) {
					WPPA_Notices::redirect( 'team_manager_only', $tab_url );
				}

				// Капитана не исключает никто: ни наставник, ни куратор.
				if ( 'owner' === self::role( $member_id ) ) {
					WPPA_Notices::redirect( 'team_captain_denied', $tab_url );
				}

				// Наставник не может исключать других наставников.
				if ( ! $as_curator && ! self::is_owner( $user_id ) && 'mentor' === self::role( $member_id ) ) {
					WPPA_Notices::redirect( 'team_mentor_denied', $tab_url );
				}

				if ( ! self::remove_member( $team_id, $member_id ) ) {
					WPPA_Notices::redirect( 'team_mentor_invalid', $tab_url );
				}

				WPPA_Notices::redirect( 'team_member_removed', $tab_url );

			case 'make_mentor':
			case 'dismiss_mentor':
				$team_id    = self::request_team_id( $user_id );
				$as_curator = self::is_curator( $user_id );
				$member_id  = isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0;

				// Наставников назначает капитан команды или куратор.
				if ( ! $team_id || ( ! $as_curator && ! self::is_owner( $user_id ) ) ) {
					WPPA_Notices::redirect( 'team_owner_only', $tab_url );
				}

				if ( (int) self::team_id( $member_id ) !== (int) $team_id || 'owner' === self::role( $member_id ) ) {
					WPPA_Notices::redirect( 'team_mentor_invalid', $tab_url );
				}

				if ( 'make_mentor' === $sub ) {
					self::make_mentor( $team_id, $member_id );
					WPPA_Notices::redirect( 'team_mentor_added', $tab_url );
				}

				self::dismiss_mentor( $team_id, $member_id );
				WPPA_Notices::redirect( 'team_mentor_removed', $tab_url );

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
