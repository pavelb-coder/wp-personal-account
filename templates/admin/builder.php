<?php
/**
 * Админ-панель: страница «Конструктор».
 *
 * Доступные переменные: $notice.
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

$wppa_tabs    = WPPA_Template::all_tabs();
$wppa_layout  = WPPA_Builder::layout();
$wppa_blocks  = WPPA_Builder::blocks();
$wppa_indexes = array();
?>
<div class="wrap wppa-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Конструктор личного кабинета', 'wp-personal-account' ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( WPPA_Plugin::account_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Открыть кабинет', 'wp-personal-account' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : 'warning' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<p class="wppa-admin__muted" style="max-width:760px;">
		<?php esc_html_e( 'Соберите каждую вкладку из блоков: включите нужные, переставьте стрелками, переименуйте заголовок или добавьте свой HTML-блок. Порядок блоков на сайте совпадает с порядком в списке.', 'wp-personal-account' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'wppa_layout_group' ); ?>

		<?php foreach ( $wppa_tabs as $wppa_tab => $wppa_label ) : ?>
			<?php
			$wppa_items = isset( $wppa_layout[ $wppa_tab ] ) ? (array) $wppa_layout[ $wppa_tab ] : array();
			$wppa_index = 0;
			?>
			<section class="wppa-admin__panel wppa-builder" data-tab="<?php echo esc_attr( $wppa_tab ); ?>">
				<h2><?php echo esc_html( $wppa_label ); ?></h2>

				<div class="wppa-builder__list" data-wppa-list>
					<?php foreach ( $wppa_items as $wppa_item ) : ?>
						<?php
						$wppa_type  = isset( $wppa_item['type'] ) ? $wppa_item['type'] : 'block';
						$wppa_id    = isset( $wppa_item['id'] ) ? $wppa_item['id'] : '';
						$wppa_name  = 'wppa_layout[' . $wppa_tab . '][' . $wppa_index . ']';
						$wppa_title = isset( $wppa_item['title'] ) ? $wppa_item['title'] : '';
						$wppa_aud   = isset( $wppa_item['audience'] ) ? $wppa_item['audience'] : 'all';
						$wppa_html  = isset( $wppa_item['html'] ) ? $wppa_item['html'] : '';
						$wppa_is_c  = 'custom' === $wppa_type;
						$wppa_lbl   = $wppa_is_c
							? __( 'Свой HTML-блок', 'wp-personal-account' )
							: ( isset( $wppa_blocks[ $wppa_id ]['label'] ) ? $wppa_blocks[ $wppa_id ]['label'] : $wppa_id );
						$wppa_desc  = $wppa_is_c ? '' : ( isset( $wppa_blocks[ $wppa_id ]['desc'] ) ? $wppa_blocks[ $wppa_id ]['desc'] : '' );
						++$wppa_index;
						?>
						<div class="wppa-builder__row" data-wppa-row>
							<span class="wppa-builder__handle" aria-hidden="true">☰</span>

							<div class="wppa-builder__main">
								<div class="wppa-builder__head">
									<label class="wppa-builder__toggle">
										<input type="checkbox" name="<?php echo esc_attr( $wppa_name ); ?>[enabled]" value="1" <?php checked( ! empty( $wppa_item['enabled'] ) ); ?> />
										<strong><?php echo esc_html( $wppa_lbl ); ?></strong>
									</label>

									<?php if ( $wppa_is_c ) : ?>
										<span class="wppa-badge wppa-badge--owner"><?php esc_html_e( 'HTML', 'wp-personal-account' ); ?></span>
									<?php endif; ?>
								</div>

								<?php if ( $wppa_desc ) : ?>
									<p class="wppa-admin__muted wppa-builder__desc"><?php echo esc_html( $wppa_desc ); ?></p>
								<?php endif; ?>

								<div class="wppa-builder__fields">
									<input
										type="text"
										name="<?php echo esc_attr( $wppa_name ); ?>[title]"
										value="<?php echo esc_attr( $wppa_title ); ?>"
										placeholder="<?php esc_attr_e( 'Заголовок блока (необязательно)', 'wp-personal-account' ); ?>"
										class="regular-text"
									/>

									<select name="<?php echo esc_attr( $wppa_name ); ?>[audience]">
										<option value="all" <?php selected( $wppa_aud, 'all' ); ?>><?php esc_html_e( 'Все пользователи', 'wp-personal-account' ); ?></option>
										<option value="admins" <?php selected( $wppa_aud, 'admins' ); ?>><?php esc_html_e( 'Только администраторы', 'wp-personal-account' ); ?></option>
									</select>
								</div>

								<?php if ( $wppa_is_c ) : ?>
									<p class="wppa-builder__html">
										<label for="wppa-html-<?php echo esc_attr( $wppa_tab . '-' . $wppa_index ); ?>" class="screen-reader-text"><?php esc_html_e( 'HTML-содержимое', 'wp-personal-account' ); ?></label>
										<textarea
											id="wppa-html-<?php echo esc_attr( $wppa_tab . '-' . $wppa_index ); ?>"
											name="<?php echo esc_attr( $wppa_name ); ?>[html]"
											rows="5"
											class="large-text code"
											placeholder="<?php esc_attr_e( 'Текст, ссылки, списки — разрешены базовые HTML-теги', 'wp-personal-account' ); ?>"
										><?php echo esc_textarea( $wppa_html ); ?></textarea>
									</p>
								<?php endif; ?>
							</div>

							<input type="hidden" name="<?php echo esc_attr( $wppa_name ); ?>[type]" value="<?php echo esc_attr( $wppa_is_c ? 'custom' : 'block' ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( $wppa_name ); ?>[id]" value="<?php echo esc_attr( $wppa_id ); ?>" />

							<span class="wppa-builder__actions">
								<button type="button" class="button button-small" data-wppa-move="up" aria-label="<?php esc_attr_e( 'Выше', 'wp-personal-account' ); ?>">↑</button>
								<button type="button" class="button button-small" data-wppa-move="down" aria-label="<?php esc_attr_e( 'Ниже', 'wp-personal-account' ); ?>">↓</button>
								<button type="button" class="button button-small" data-wppa-remove><?php esc_html_e( 'Убрать', 'wp-personal-account' ); ?></button>
							</span>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="wppa-builder__add">
					<button
						type="button"
						class="button"
						data-wppa-add-custom
						data-tab="<?php echo esc_attr( $wppa_tab ); ?>"
						data-index="<?php echo esc_attr( (string) $wppa_index ); ?>"
					>
						<?php esc_html_e( '+ HTML-блок', 'wp-personal-account' ); ?>
					</button>
				</p>
			</section>
		<?php endforeach; ?>

		<?php submit_button( __( 'Сохранить раскладку', 'wp-personal-account' ) ); ?>
	</form>

	<section class="wppa-admin__panel">
		<h2><?php esc_html_e( 'Сброс', 'wp-personal-account' ); ?></h2>
		<p class="wppa-admin__muted"><?php esc_html_e( 'Вернуть стандартный набор и порядок блоков во всех вкладках.', 'wp-personal-account' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wppa_tool' ); ?>
			<input type="hidden" name="action" value="wppa_tool" />
			<input type="hidden" name="wppa_tool" value="reset_layout" />
			<button type="submit" class="button" data-wppa-confirm="<?php esc_attr_e( 'Сбросить раскладку к стандартной?', 'wp-personal-account' ); ?>">
				<?php esc_html_e( 'Сбросить к стандартной', 'wp-personal-account' ); ?>
			</button>
		</form>
	</section>
</div>
